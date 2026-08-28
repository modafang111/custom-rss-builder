#!/usr/bin/env python3
"""
削除→再インストール後のライセンス初期状態を検証する。

1. 配布 ZIP に uninstall.php があるか
2. PluginTest リモートに uninstall.php / BUILD が載っているか
3. プローブで license_state / wp_options 相当を確認
"""
from __future__ import annotations

import base64
import json
import re
import secrets
import ssl
import sys
import urllib.error
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
    BASE
    / "custom-rss-builder"
    / "custom-rss-builder"
    / "tools"
    / "crb_acceptance_probe.php"
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


def warn(msg: str) -> None:
    print(f"WARN {msg}")


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


def ftp_read(ftp: ftplib.FTP, path: str) -> bytes | None:
    buf = BytesIO()
    try:
        ftp.retrbinary(f"RETR {path}", buf.write)
        return buf.getvalue()
    except ftplib.error_perm:
        return None


def probe_get(token: str, action: str, **params: str) -> dict:
    q = {"action": action, "token": token, **params}
    url = f"{CLIENT_BASE}/wp-content/plugins/custom-rss-builder/crb_acceptance_probe.php?{urllib.parse.urlencode(q)}"
    req = urllib.request.Request(url, headers={"User-Agent": "verify-fresh-install/1"})
    with urllib.request.urlopen(req, context=CTX, timeout=45) as resp:
        return json.loads(resp.read().decode("utf-8"))


def verify_zip() -> str:
    print("=== 1. Distribution ZIP ===\n")
    build = "?"
    if not DIST_ZIP.is_file():
        fail(f"missing {DIST_ZIP}")
        return build

    with zipfile.ZipFile(DIST_ZIP) as zf:
        names = zf.namelist()
        if "custom-rss-builder/uninstall.php" not in names:
            fail("client zip missing uninstall.php")
        else:
            body = zf.read("custom-rss-builder/uninstall.php").decode("utf-8")
            if "crb_license_settings" not in body:
                fail("uninstall.php does not delete crb_license_settings")
            else:
                ok("uninstall.php deletes crb_license_settings")

        main = zf.read("custom-rss-builder/custom-rss-builder.php").decode("utf-8")
        m = re.search(r"CRB_BUILD_ID', '([^']+)'", main)
        build = m.group(1) if m else "?"
        ok(f"dist zip BUILD_ID {build}")

        if "custom-rss-builder/license-server/" in "".join(names):
            fail("client zip must not contain license-server/")
        else:
            ok("client zip has no license-server/")
    return build


def verify_remote_files() -> tuple[str, bool]:
    print("\n=== 2. PluginTest FTP (installed files) ===\n")
    build = "?"
    has_uninstall = False
    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
    ftp.set_pasv(True)

    main = ftp_read(ftp, f"{CLIENT_REMOTE}/custom-rss-builder.php")
    if main is None:
        fail("remote custom-rss-builder.php not found")
    else:
        m = re.search(r"CRB_BUILD_ID', '([^']+)'", main.decode("utf-8", errors="replace"))
        build = m.group(1) if m else "?"
        ok(f"remote BUILD_ID {build}")

    uninstall = ftp_read(ftp, f"{CLIENT_REMOTE}/uninstall.php")
    if uninstall is None:
        fail("remote uninstall.php missing (WP delete will not clear license options)")
    else:
        has_uninstall = True
        text = uninstall.decode("utf-8", errors="replace")
        if "crb_license_settings" in text:
            ok("remote uninstall.php present and targets crb_license_settings")
        else:
            fail("remote uninstall.php missing crb_license_settings cleanup")

    ls_bootstrap = ftp_read(ftp, f"{CLIENT_REMOTE}/license-server/bootstrap.php")
    if ls_bootstrap is not None:
        warn("remote still has license-server/ (hybrid tree; not same as download ZIP only)")

    ftp.quit()
    return build, has_uninstall


def verify_license_state(token: str) -> None:
    print("\n=== 3. PluginTest license state (probe) ===\n")
    try:
        data = probe_get(token, "license_state")
    except urllib.error.HTTPError as exc:
        fail(f"probe HTTP {exc.code}")
        return
    except Exception as exc:  # noqa: BLE001
        fail(f"probe failed: {exc}")
        return

    if not data.get("ok"):
        fail(f"probe error: {data.get('error', data)}")
        return

    payload = data.get("data") or {}
    state = payload.get("state") or {}
    settings = payload.get("settings") or {}
    plan = state.get("plan")
    usable = state.get("usable")
    key = (state.get("license_key") or settings.get("license_key") or "").strip()

    print(f"     plan={plan!r} usable={usable!r} key_present={bool(key)}")
    if key:
        print(f"     key_prefix={key[:8]}...")

    if usable and plan == "pro":
        fail(
            "site still Pro+usable: uninstall did not run before reinstall, "
            "or delete happened while old build (no uninstall.php) was installed"
        )
    elif usable and plan == "free":
        warn("site has usable FREE license (auto or manual); not a clean unregistered state")
    elif not usable and not key:
        ok("expected fresh state: unusable and no license key in options")
    elif not usable and key:
        warn("license key stored but unusable (stale/partial settings)")
    else:
        ok(f"state recorded: plan={plan} usable={usable}")


def upload_probe(token: str) -> bool:
    if not PROBE_LOCAL.is_file():
        fail("local crb_acceptance_probe.php missing")
        return False
    body = PROBE_LOCAL.read_text(encoding="utf-8").replace("__CRB_PROBE_TOKEN__", token)
    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
    ftp.set_pasv(True)
    remote = f"{CLIENT_REMOTE}/crb_acceptance_probe.php"
    try:
        ftp.storbinary(f"STOR {remote}", BytesIO(body.encode("utf-8")))
        ok("probe uploaded for license_state check")
        ftp.quit()
        return True
    except ftplib.error_perm as exc:
        fail(f"probe upload failed: {exc}")
        ftp.quit()
        return False


def main() -> int:
    print("=== verify_fresh_install_license ===\n")
    dist_build = verify_zip()
    remote_build, has_uninstall = verify_remote_files()

    if dist_build != "?" and remote_build != "?" and dist_build != remote_build:
        warn(
            f"PluginTest FTP ({remote_build}) differs from dist zip ({dist_build}); "
            "download-page install may differ from FTP deploy"
        )

    token = secrets.token_urlsafe(24)
    if upload_probe(token):
        verify_license_state(token)

    print("\n=== 4. Delete order (manual check) ===\n")
    print(
        "uninstall.php runs only when deleting the INSTALLED plugin.\n"
        "If you deleted an OLD build (without uninstall.php) first, then installed "
        f"{dist_build}, crb_license_settings would still be in wp_options."
    )
    print(
        "Correct reset: install build WITH uninstall.php → deactivate → delete → "
        "install again → activate."
    )

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("Verification finished (see WARN for non-fatal notes).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
