#!/usr/bin/env python3
"""Simulate fresh WordPress (no crb_license_settings) on PluginTest via probe."""
from __future__ import annotations

import base64
import json
import re
import secrets
import ssl
import sys
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
import zipfile
from io import BytesIO
from pathlib import Path

import ftplib

BASE = Path(__file__).resolve().parent.parent
DIST_ZIP = BASE / "dist" / "custom-rss-builder-client.zip"
PROBE_LOCAL = (
    BASE / "custom-rss-builder" / "custom-rss-builder" / "tools" / "crb_acceptance_probe.php"
)
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"
FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"
CLIENT_REMOTE = (
    "/wordpress-123.com/public_html/PluginTest/wp-content/plugins/custom-rss-builder"
)
CLIENT_BASE = "https://wordpress-123.com/PluginTest"
CTX = ssl.create_default_context()
FAILURES: list[str] = []


def ok(msg: str) -> None:
    print(f"OK   {msg}")


def fail(msg: str) -> None:
    FAILURES.append(msg)
    print(f"FAIL {msg}")


def load_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (
            srv.findtext("User") or ""
        ).strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("FTP credentials not found")


def probe_get(token: str, action: str, **params: str) -> dict:
    q = {"action": action, "token": token, **params}
    url = (
        f"{CLIENT_BASE}/wp-content/plugins/custom-rss-builder/crb_acceptance_probe.php?"
        + urllib.parse.urlencode(q)
    )
    req = urllib.request.Request(url, headers={"User-Agent": "verify-fresh-sim/1"})
    with urllib.request.urlopen(req, context=CTX, timeout=45) as resp:
        return json.loads(resp.read().decode("utf-8"))


def upload_probe(token: str) -> None:
    body = PROBE_LOCAL.read_text(encoding="utf-8").replace("__CRB_PROBE_TOKEN__", token)
    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
    ftp.set_pasv(True)
    remote = f"{CLIENT_REMOTE}/crb_acceptance_probe.php"
    ftp.storbinary(f"STOR {remote}", BytesIO(body.encode("utf-8")))
    ftp.quit()


def verify_zip_logic() -> None:
    print("=== 1. Dist ZIP static checks ===\n")
    if not DIST_ZIP.is_file():
        fail(f"missing {DIST_ZIP}")
        return
    with zipfile.ZipFile(DIST_ZIP) as zf:
        lic = zf.read("custom-rss-builder/includes/functions-license.php").decode("utf-8")
        main = zf.read("custom-rss-builder/custom-rss-builder.php").decode("utf-8")
    if "CRB_PACKAGE_VARIANT', 'client'" not in main:
        fail("client ZIP missing CRB_PACKAGE_VARIANT=client")
    else:
        ok("CRB_PACKAGE_VARIANT=client in ZIP")
    if "function crb_license_get_state" not in lic or "'' === $key" not in lic.split(
        "function crb_license_get_state", 1
    )[1][:1200]:
        fail("get_state missing empty-key guard")
    else:
        ok("get_state returns unusable when license_key empty")
    if "未登録" not in lic:
        fail("plan_label_for_state missing 未登録")
    else:
        ok("plan label 未登録 for empty key on client screen")


def parse_html(html: str) -> dict[str, str]:
    out: dict[str, str] = {}
    m = re.search(r"crb-license-plan-badge[^>]*>([^<]+)", html)
    if m:
        out["plan_badge"] = m.group(1).strip()
    m = re.search(r"このサイトで利用可</th>\s*<td>([^<]+)", html)
    if m:
        out["usable_cell"] = m.group(1).strip()
    m = re.search(r"ビルド ([0-9a-z]+)", html)
    if m:
        out["build"] = m.group(1)
    out["has_key_row"] = "crb-license-key" in html
    return out


def check_state(label: str, data: dict, expect_unregistered: bool) -> None:
    payload = data.get("data") or {}
    state = payload.get("state") or {}
    settings = payload.get("settings") or {}
    plan = state.get("plan")
    usable = state.get("usable")
    key = (state.get("license_key") or settings.get("license_key") or "").strip()
    print(f"\n--- {label} ---")
    print(f"     state plan={plan!r} usable={usable!r} key_present={bool(key)}")
    print(f"     settings plan={settings.get('plan')!r} usable={settings.get('usable')!r}")
    html = (payload.get("html") or "") if "html" in payload else ""
    if not html and label.endswith("html"):
        pass
    if expect_unregistered:
        if usable and plan == "pro":
            fail(f"{label}: expected unregistered but got Pro+usable")
        elif usable and plan == "free":
            fail(f"{label}: fresh install should be unusable, got usable free (auto-issue?)")
        elif not usable and not key:
            ok(f"{label}: unusable, no key (expected fresh state)")
        else:
            fail(f"{label}: unexpected state plan={plan} usable={usable} key={bool(key)}")


def main() -> int:
    print("=== verify_fresh_install_simulation ===\n")
    verify_zip_logic()

    token = secrets.token_urlsafe(24)
    upload_probe(token)
    ok("probe uploaded")

    print("\n=== 2. Simulate empty DB (delete crb_license_settings) ===\n")
    r0 = probe_get(token, "license_simulate_fresh")
    if not r0.get("ok"):
        fail(f"license_simulate_fresh: {r0.get('error', r0)}")
        return 1

    st = probe_get(token, "license_state")
    if not st.get("ok"):
        fail(f"license_state after fresh: {st.get('error')}")
        return 1
    check_state("after delete_option", st, expect_unregistered=True)

    html = probe_get(token, "license_html")
    if not html.get("ok"):
        fail(f"license_html: {html.get('error')}")
    else:
        parsed = parse_html(html["data"]["html"])
        print(f"     UI plan_badge={parsed.get('plan_badge')!r} usable={parsed.get('usable_cell')!r}")
        print(f"     build={parsed.get('build')!r} key_row_in_status={parsed.get('has_key_row')}")
        if parsed.get("plan_badge") == "未登録" and parsed.get("usable_cell") == "いいえ":
            ok("license screen shows 未登録 / いいえ")
        elif parsed.get("plan_badge") == "Pro" and parsed.get("usable_cell") == "はい":
            fail("license screen shows Pro+はい on simulated fresh install")
        else:
            fail(
                f"unexpected UI: plan={parsed.get('plan_badge')} usable={parsed.get('usable_cell')}"
            )

    print("\n=== 3. Simulate stale DB (pro+usable in options, key empty) ===\n")
    r1 = probe_get(token, "license_seed_stale")
    if not r1.get("ok"):
        fail(f"license_seed_stale: {r1.get('error', r1)}")
    else:
        st2 = probe_get(token, "license_state")
        check_state("stale seed via get_state", st2, expect_unregistered=True)
        html2 = probe_get(token, "license_html")
        if html2.get("ok"):
            parsed2 = parse_html(html2["data"]["html"])
            print(
                f"     UI plan_badge={parsed2.get('plan_badge')!r} usable={parsed2.get('usable_cell')!r}"
            )
            if parsed2.get("plan_badge") == "未登録" and parsed2.get("usable_cell") == "いいえ":
                ok("stale pro+usable without key displays 未登録 / いいえ")
            else:
                fail(
                    f"stale options leak to UI: plan={parsed2.get('plan_badge')} "
                    f"usable={parsed2.get('usable_cell')}"
                )

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("Fresh-install simulation passed on PluginTest.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
