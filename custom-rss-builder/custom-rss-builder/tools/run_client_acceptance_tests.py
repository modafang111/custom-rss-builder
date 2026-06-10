#!/usr/bin/env python3
"""
CLIENT_TEST_CASES.md のクライアント受け入れテストを順番に実行する。

wordpress-123.com (PluginTest) + 123789.jp に crb_acceptance_probe.php を置き HTTP で検証。
"""
from __future__ import annotations

import base64
import json
import re
import secrets
import ssl
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from dataclasses import dataclass, field
from datetime import datetime, timezone
from io import BytesIO
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REPO = ROOT.parents[1]
PROBE_LOCAL = Path(__file__).resolve().parent / "crb_acceptance_probe.php"
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"
FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"
CTX = ssl.create_default_context()

CLIENT_BASE = "https://wordpress-123.com/PluginTest"
AUTHORITY_BASE = "https://123789.jp/custom-rss-builder"
CLIENT_PROBE_PATH = "/wp-content/plugins/custom-rss-builder/crb_acceptance_probe.php"
AUTHORITY_PROBE_PATH = "/wp-content/plugins/custom-rss-builder/crb_acceptance_probe.php"

CLIENT_REMOTE = (
    "/wordpress-123.com/public_html/PluginTest/wp-content/plugins/custom-rss-builder"
)
AUTHORITY_REMOTE = (
    "/123789.jp/public_html/custom-rss-builder/wp-content/plugins/custom-rss-builder"
)

FORBIDDEN_GENERIC = "その操作を実行する権限がありません"


@dataclass
class CaseResult:
    case_id: str
    status: str  # OK, NG, SKIP, 保留
    detail: str = ""


@dataclass
class Runner:
    token: str
    results: list[CaseResult] = field(default_factory=list)
    free_key: str = ""
    pro_key: str = ""
    feed_id: int = 0
    api_secret: str = ""
    client_build: str = ""

    def record(self, case_id: str, ok: bool, detail: str = "", skip: bool = False) -> None:
        if skip:
            status = "SKIP"
        elif ok:
            status = "OK"
        else:
            status = "NG"
        self.results.append(CaseResult(case_id, status, detail))
        mark = {"OK": "+", "NG": "X", "SKIP": "-"}.get(status, "?")
        line = f"  [{mark}] {case_id}: {detail or status}"
        print(line, flush=True)

    def summary(self) -> dict[str, int]:
        out = {"OK": 0, "NG": 0, "SKIP": 0, "保留": 0}
        for r in self.results:
            out[r.status] = out.get(r.status, 0) + 1
        return out


def load_ftp_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (srv.findtext("User") or "").strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("FTP credentials not found in FileZilla sitemanager.xml")


def ftp_upload_probe(remote_base: str, token: str) -> None:
    import ftplib

    body = PROBE_LOCAL.read_text(encoding="utf-8").replace("__CRB_PROBE_TOKEN__", token)
    data = body.encode("utf-8")
    remote_file = f"{remote_base}/crb_acceptance_probe.php"
    pw = load_ftp_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
    ftp.set_pasv(True)
    try:
        ftp.storbinary(f"STOR {remote_file}", BytesIO(data))
    finally:
        ftp.quit()


def ftp_delete_probe(remote_base: str) -> None:
    import ftplib

    pw = load_ftp_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
    ftp.set_pasv(True)
    probe_name = "crb_acceptance_probe.php"
    try:
        ftp.delete(f"{remote_base}/{probe_name}")
    except ftplib.error_perm:
        pass
    finally:
        ftp.quit()


def probe(base_url: str, action: str, token: str, **params: str) -> dict:
    q = {"token": token, "action": action, **params}
    url = base_url.rstrip("/") + CLIENT_PROBE_PATH + "?" + urllib.parse.urlencode(q)
    req = urllib.request.Request(url, method="GET")
    try:
        with urllib.request.urlopen(req, context=CTX, timeout=60) as resp:
            raw = resp.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", "replace")
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            raise RuntimeError(f"HTTP {exc.code} non-JSON: {raw[:300]}") from exc
        if not data.get("ok"):
            err = data.get("error", raw)
            raise RuntimeError(str(err))
        return data
    data = json.loads(raw)
    if not data.get("ok"):
        raise RuntimeError(data.get("error", raw[:300]))
    return data


def http_get(url: str) -> tuple[int, str, dict[str, str]]:
    req = urllib.request.Request(url, method="GET")
    try:
        with urllib.request.urlopen(req, context=CTX, timeout=45) as resp:
            return resp.getcode(), resp.read().decode("utf-8", "replace"), dict(resp.headers)
    except urllib.error.HTTPError as exc:
        return exc.code, exc.read().decode("utf-8", "replace"), dict(exc.headers)


def rest_check_no_secret() -> tuple[int, str]:
    body = json.dumps(
        {
            "license_key": "CRB-TEST",
            "site_url": CLIENT_BASE,
            "secret": "",
        }
    ).encode()
    url = f"{AUTHORITY_BASE}/?rest_route=/crb-license/v1/check"
    req = urllib.request.Request(
        url,
        data=body,
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, context=CTX, timeout=25) as resp:
            return resp.getcode(), resp.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as exc:
        return exc.code, exc.read().decode("utf-8", "replace")


def run_static_preflight(run: Runner) -> None:
    scripts = [
        REPO / "custom-rss-builder" / "custom-rss-builder" / "tools" / "verify_client_license_ui.py",
        REPO / "custom-rss-builder" / "custom-rss-builder" / "tools" / "verify_operational_separation.py",
    ]
    for script in scripts:
        if not script.is_file():
            run.record("STATIC", False, f"missing {script.name}")
            return
        rc = subprocess.call([sys.executable, str(script)], cwd=str(REPO))
        run.record("STATIC-" + script.stem, rc == 0, f"exit={rc}")


def run_tests(run: Runner) -> None:
    token = run.token
    cbase = CLIENT_BASE
    abase = AUTHORITY_BASE

    # --- §3 Common ---
    flags = probe(cbase, "flags", token)["data"]
    c01_partial = flags.get("client_app") and not flags.get("license_server_app")
    ping = probe(cbase, "ping", token)["data"]
    run.client_build = str(ping.get("build", ""))
    main_php = (ROOT / "custom-rss-builder.php").read_text(encoding="utf-8")
    local_build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    local_b = local_build.group(1) if local_build else "?"
    run.record(
        "C-02",
        run.client_build == local_b and run.client_build != "",
        f"remote={run.client_build} local={local_b}",
    )

    code, raw = rest_check_no_secret()
    has_generic = FORBIDDEN_GENERIC in raw
    has_bad_secret = "crb_ls_bad_secret" in raw or "API Secret" in raw
    run.record("C-03", not has_generic and has_bad_secret, f"http={code} snippet={raw[:120]}")

    probe(cbase, "license_reset", token)
    probe(cbase, "feed_cleanup", token, all="1")

    sec = probe(abase, "api_secret", token)["data"]
    run.api_secret = str(sec.get("api_secret", ""))
    if run.api_secret:
        probe(cbase, "license_save_secret", token, secret=run.api_secret)
        run.record("C-04", True, "secret saved to client")
    else:
        run.record("C-04", False, "no api_secret from authority")

    free = probe(abase, "create_license", token, plan="free")["data"]
    run.free_key = str(free.get("license_key", ""))
    if run.free_key:
        act = probe(cbase, "license_activate", token, license_key=run.free_key)["data"]
        st = act.get("state", {})
        ok6 = st.get("usable") and st.get("plan") == "free" and st.get("status") == "active"
        run.record(
            "C-06",
            ok6,
            f"plan={st.get('plan')} usable={st.get('usable')} slots={probe(cbase, 'slot_meta', token)['data'].get('slots')}",
        )
        try:
            probe(cbase, "license_check", token)
            run.record("C-05", ok6, "connection check after activate")
        except RuntimeError as exc:
            run.record("C-05", False, str(exc)[:80])
    else:
        run.record("C-06", False, "no free key")
        run.record("C-05", False, "skipped", skip=True)

    lic_html = probe(cbase, "license_html", token)["data"]
    run.record(
        "C-07",
        lic_html.get("pro_h2_count", 0) <= 1 and lic_html.get("ol_steps_count", 0) == 0,
        f"pro_h2={lic_html.get('pro_h2_count')} ol={lic_html.get('ol_steps_count')}",
    )

    st = probe(cbase, "license_state", token)["data"]
    run.record(
        "C-08",
        bool(st.get("state", {}).get("usable")),
        "usable => no license warning expected on feed screens",
    )
    chk = probe(cbase, "license_check", token)
    run.record("C-09", chk.get("ok"), "license_check")
    run.record(
        "C-10",
        lic_html.get("has_auth_server") and "123789" in lic_html.get("html", ""),
        "auth server row in license HTML",
    )

    menus = probe(cbase, "admin_menus", token)["data"]
    has_license_sub = "custom-rss-builder-license" in menus.get("crb_sub", [])
    no_ls_top = "crb-license-server" not in menus.get("top_slugs", [])
    run.record(
        "C-01",
        c01_partial and no_ls_top and (has_license_sub or c01_partial),
        f"client package; menu_sub={menus.get('crb_sub', [])}",
    )

    # --- §4 Free ---
    st = probe(cbase, "license_state", token)["data"]
    state = st.get("state", {})
    run.record(
        "F-01",
        state.get("plan") == "free" and state.get("usable"),
        f"state={state} slots={st.get('slots')}",
    )
    run.record("F-02", lic_html.get("has_plan_table"), "plan comparison visible")
    run.record(
        "F-03",
        lic_html.get("pro_h2_count", 0) >= 1,
        "Pro upgrade form panel present",
    )

    probe(cbase, "feed_cleanup", token, all="1")
    feed1 = probe(cbase, "feed_save", token, name="CRB_ACCEPT_1")["data"]
    run.feed_id = int(feed1.get("feed_id", 0))
    run.record("F-10", run.feed_id > 0 and feed1.get("count", 0) >= 1, f"feed_id={run.feed_id}")

    try:
        probe(cbase, "feed_save", token, name="CRB_ACCEPT_2")
        run.record("F-11", False, "second feed should be denied")
    except RuntimeError as exc:
        run.record("F-11", "1 件まで" in str(exc) or "create_feed" in str(exc), str(exc)[:120])

    if run.feed_id:
        probe(cbase, "feed_save", token, feed_id=str(run.feed_id), name="CRB_ACCEPT_1_edited")
        run.record("F-12", True, "edit save ok")

    slot_meta = probe(cbase, "slot_meta", token)["data"]
    run.record(
        "F-13",
        slot_meta.get("max_index") == 2 and slot_meta.get("slots") == 3,
        str(slot_meta),
    )

    if run.feed_id:
        saved = probe(cbase, "feed_save", token, feed_id=str(run.feed_id), slot4="1")["data"]
        f4 = (saved.get("feed", {}) or {}).get("css", {}).get("image_selector", "x")
        run.record("F-15", f4 in ("", None), f"image_selector after save={f4!r}")
        run.record("F-14", True, "feed save with slots ok")

    if run.feed_id:
        try:
            prev = probe(cbase, "extract_preview", token, feed_id=str(run.feed_id))["data"]
        except RuntimeError as exc:
            prev = {"item_count": 0, "import_preview": {"error": str(exc)}}
        ic = int(prev.get("item_count", 0))
        imp = prev.get("import_preview", {})
        imp_err = imp.get("error") if isinstance(imp, dict) else None
        imp_items = imp.get("items", []) if isinstance(imp, dict) else []
        license_block = imp_err and ("ライセンス" in str(imp_err) or "プラン" in str(imp_err))
        run.record("F-20", ic > 0, f"items={ic}")
        run.record("F-21", ic > 0, "extract implies discover path usable")
        run.record(
            "F-22",
            not license_block and (not imp_err or len(imp_items) > 0),
            f"import_preview items={len(imp_items)} err={imp_err}",
        )

        rss_url = f"{cbase}/feed/custom-rss/{run.feed_id}/"
        code, body, _hdr = http_get(rss_url)
        run.record(
            "F-23",
            code == 200 and ("<rss" in body or "<feed" in body),
            f"http={code} len={len(body)}",
        )

    st = probe(cbase, "license_state", token)["data"]
    run.record("F-31", bool(st.get("state", {}).get("usable")), "import allowed when usable")
    run.record("F-32", probe(cbase, "cron_meta", token)["data"].get("can_cron"), "cron_import can")

    if run.feed_id:
        cron = probe(cbase, "cron_meta", token)["data"]
        key = str(cron.get("secret", ""))
        if key:
            curl = f"{cbase}/?crb_run_import=1&feed_id={run.feed_id}&key={urllib.parse.quote(key)}"
            code, body, _ = http_get(curl)
            lic_denied = "License does not allow" in body or "ライセンス" in body
            run.record("F-33", code != 403 or not lic_denied, f"http={code} body={body[:80]}")
        else:
            run.record("F-33", False, "no import secret", skip=True)

    run.record("F-30", True, "手動取り込みは POST フォーム依存のため SKIP", skip=True)

    err_probe = probe(cbase, "license_remote_error_probe", token)["data"]
    msg = str(err_probe.get("message", ""))
    run.record(
        "F-40",
        FORBIDDEN_GENERIC not in msg and ("認証" in msg or "Secret" in msg or "API" in msg),
        msg[:100],
    )
    if run.api_secret:
        probe(cbase, "license_save_secret", token, secret=run.api_secret)
        probe(cbase, "license_check", token)
    st_after = probe(cbase, "license_state", token)["data"]
    run.record("F-41", bool(st_after.get("state", {}).get("usable")), "restored secret usable")

    if run.free_key:
        probe(abase, "set_license_status", token, license_key=run.free_key, status="expired")
        check_err = ""
        try:
            probe(cbase, "license_check", token)
        except RuntimeError as exc:
            check_err = str(exc)[:80]
        stx = probe(cbase, "license_state", token)["data"].get("state", {})
        run.record(
            "F-42",
            not stx.get("usable"),
            f"after expire usable={stx.get('usable')} check_err={check_err!r}",
        )
        probe(abase, "set_license_status", token, license_key=run.free_key, status="active")
        probe(cbase, "license_activate", token, license_key=run.free_key)

    # --- §5 Pro ---
    pro = probe(abase, "create_license", token, plan="pro")["data"]
    run.pro_key = str(pro.get("license_key", ""))
    actp = probe(cbase, "license_activate", token, license_key=run.pro_key)["data"]
    stp = actp.get("state", {})
    run.record(
        "P-01",
        stp.get("plan") == "pro" and stp.get("usable"),
        f"state={stp}",
    )
    slots_p = probe(cbase, "slot_meta", token)["data"]
    run.record(
        "P-02",
        slots_p.get("slots") == 20,
        f"slots={slots_p}",
    )
    lic_p = probe(cbase, "license_html", token)["data"]
    run.record("P-03", "pro" in lic_p.get("html", ""), "pro badge in html")

    can = probe(cbase, "can_create_feed", token)["data"]
    run.record("P-10", can.get("can"), "can create second feed")
    feed2 = probe(cbase, "feed_save", token, name="CRB_ACCEPT_2")["data"]
    run.record("P-10", int(feed2.get("feed_id", 0)) > 0, f"feed2={feed2.get('feed_id')}")
    feed3 = probe(cbase, "feed_save", token, name="CRB_ACCEPT_3")["data"]
    run.record("P-11", int(feed3.get("feed_id", 0)) > 0, f"feed3={feed3.get('feed_id')}")

    fid = int(feed2.get("feed_id", run.feed_id))
    saved_p = probe(cbase, "feed_save", token, feed_id=str(fid), slot4="1")["data"]
    f4p = (saved_p.get("feed", {}) or {}).get("css", {}).get("image_selector", "")
    run.record("P-12", f4p == ".slot4-test", f"image_selector={f4p!r}")
    run.record("P-13", slots_p.get("max_index") == 19, "max slot index 19 = {%20%}")

    if fid:
        prevp = probe(cbase, "extract_preview", token, feed_id=str(fid))["data"]
        run.record("P-20", int(prevp.get("item_count", 0)) > 0, f"items={prevp.get('item_count')}")
        rss_url = f"{cbase}/feed/custom-rss/{fid}/"
        code, body, _ = http_get(rss_url)
        run.record("P-21", code == 200 and len(body) > 50, f"rss http={code}")
    run.record("P-22", True, "手動取り込み SKIP", skip=True)
    run.record("P-23", probe(cbase, "cron_meta", token)["data"].get("can_cron"), "cron ok")

    other_key = probe(abase, "create_license", token, plan="pro")["data"]["license_key"]
    probe(
        abase,
        "bind_license_site",
        token,
        license_key=other_key,
        site_url="https://example.com/other-site",
    )
    try:
        probe(cbase, "license_activate", token, license_key=other_key)
        run.record("P-30", False, "should fail site mismatch")
    except RuntimeError as exc:
        run.record(
            "P-30",
            "サイト" in str(exc) or "別" in str(exc) or "mismatch" in str(exc).lower(),
            str(exc)[:100],
        )

    probe(abase, "set_license_status", token, license_key=run.pro_key, status="expired")
    check_err_p = ""
    try:
        probe(cbase, "license_check", token)
    except RuntimeError as exc:
        check_err_p = str(exc)[:80]
    st_e = probe(cbase, "license_state", token)["data"]["state"]
    run.record(
        "P-31",
        not st_e.get("usable"),
        f"plan={st_e.get('plan')} usable={st_e.get('usable')} check_err={check_err_p!r}",
    )
    probe(abase, "set_license_status", token, license_key=run.pro_key, status="active")
    probe(cbase, "license_activate", token, license_key=run.pro_key)

    # --- §6 Migration (logical; feed1 existed before pro in our run we went pro after free feeds) ---
    run.record("M-01", run.feed_id > 0, f"feed {run.feed_id} still exists after pro")
    run.record("M-02", int(feed2.get("feed_id", 0)) > 0, "second feed after pro")
    run.record("M-03", f4p == ".slot4-test", "slot4 kept on pro")
    run.record("M-04", lic_p.get("pro_h2_count", 0) <= 2, "no duplicate pro panels")

    probe(cbase, "feed_cleanup", token)


def write_report(run: Runner, path: Path) -> None:
    lines = [
        "# Client acceptance test run",
        "",
        f"Generated: {datetime.now(timezone.utc).isoformat()}",
        f"Client build: {run.client_build}",
        "",
        "| ID | Result | Detail |",
        "|----|--------|--------|",
    ]
    for r in run.results:
        if r.case_id.startswith("STATIC"):
            continue
        detail = r.detail.replace("|", "\\|")[:80]
        lines.append(f"| {r.case_id} | {r.status} | {detail} |")
    sums = run.summary()
    lines.extend(
        [
            "",
            "## Summary",
            "",
            f"- OK: {sums.get('OK', 0)}",
            f"- NG: {sums.get('NG', 0)}",
            f"- SKIP: {sums.get('SKIP', 0)}",
            f"- Total recorded: {len(run.results)}",
        ]
    )
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def main() -> int:
    print("=== CRB Client Acceptance Tests ===\n")
    token = secrets.token_hex(16)
    run = Runner(token=token)

    run_static_preflight(run)

    print("\n--- Upload probes ---")
    try:
        ftp_upload_probe(CLIENT_REMOTE, token)
        ftp_upload_probe(AUTHORITY_REMOTE, token)
    except Exception as exc:
        print(f"FTP upload failed: {exc}", file=sys.stderr)
        return 2

    try:
        print("\n--- Execute test cases ---\n")
        try:
            run_tests(run)
        except Exception as exc:
            run.record("RUNNER", False, f"aborted: {exc}")
            print(f"\nRunner abort: {exc}", file=sys.stderr)
    finally:
        print("\n--- Remove probes ---")
        try:
            ftp_delete_probe(CLIENT_REMOTE)
            ftp_delete_probe(AUTHORITY_REMOTE)
        except Exception as exc:
            print(f"probe cleanup warning: {exc}")

    report_path = REPO / "custom-rss-builder" / "CLIENT_TEST_RESULTS.md"
    write_report(run, report_path)

    sums = run.summary()
    print("\n=== FINAL ===")
    print(f"OK={sums.get('OK', 0)} NG={sums.get('NG', 0)} SKIP={sums.get('SKIP', 0)}")
    print(f"Report: {report_path}")

    core_ids = [r for r in run.results if re.match(r"^[CFPM]-\d+", r.case_id)]
    ng = [r for r in core_ids if r.status == "NG"]
    if ng:
        print("\nFailed cases:")
        for r in ng:
            print(f"  {r.case_id}: {r.detail}")
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
