#!/usr/bin/env python3
"""クライアント (wordpress-123 PluginTest) の無料クレジットデプロイ確認。"""
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
from io import BytesIO
from pathlib import Path

EXPECTED_BUILD = "20260606n"
CLIENT_BASE = "https://wordpress-123.com/PluginTest"
CLIENT_REMOTE = (
    "/wordpress-123.com/public_html/PluginTest/wp-content/plugins/custom-rss-builder"
)
PROBE_LOCAL = Path(__file__).resolve().parent / "crb_acceptance_probe.php"
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"
FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"
CTX = ssl.create_default_context()


def load_ftp_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (srv.findtext("User") or "").strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("FTP credentials not found")


def ftp_read(path: str) -> str:
    import ftplib

    pw = load_ftp_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=60)
    try:
        buf = BytesIO()
        ftp.retrbinary(f"RETR {path}", buf.write)
        return buf.getvalue().decode("utf-8", errors="replace")
    finally:
        ftp.quit()


def probe(action: str, token: str, **params: str) -> dict:
    q = {"action": action, "token": token, **params}
    url = CLIENT_BASE + "/wp-content/plugins/custom-rss-builder/crb_acceptance_probe.php?" + urllib.parse.urlencode(q)
    req = urllib.request.Request(url, headers={"User-Agent": "CRB-Verify/1.0"})
    with urllib.request.urlopen(req, context=CTX, timeout=30) as resp:
        payload = json.loads(resp.read().decode("utf-8"))
    if not payload.get("ok"):
        raise RuntimeError(payload.get("error") or payload)
    return payload.get("data") or {}


def upload_probe(token: str) -> None:
    import ftplib

    body = PROBE_LOCAL.read_text(encoding="utf-8").replace("__CRB_PROBE_TOKEN__", token)
    remote_file = f"{CLIENT_REMOTE}/crb_acceptance_probe.php"
    pw = load_ftp_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=60)
    try:
        ftp.storbinary(f"STOR {remote_file}", BytesIO(body.encode("utf-8")))
    finally:
        ftp.quit()


def delete_probe() -> None:
    import ftplib

    pw = load_ftp_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=60)
    try:
        ftp.delete(f"{CLIENT_REMOTE}/crb_acceptance_probe.php")
    except Exception:
        pass
    finally:
        ftp.quit()


def main() -> int:
    ok = True

    main_php = ftp_read(f"{CLIENT_REMOTE}/custom-rss-builder.php")
    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    build_id = build.group(1) if build else ""
    print(f"FTP BUILD_ID: {build_id}")
    if build_id != EXPECTED_BUILD:
        print(f"FAIL: expected {EXPECTED_BUILD}")
        ok = False
    else:
        print("OK: BUILD_ID")

    credit_php = ftp_read(f"{CLIENT_REMOTE}/includes/functions-free-credit.php")
    for needle in (
        "crb_license_requires_free_credit",
        "crb_license_append_free_credit",
        "123789.jp/custom-rss-builder/",
        "wp_footer",
    ):
        hit = needle in credit_php
        print(f"{'OK' if hit else 'FAIL'}: functions-free-credit.php contains {needle!r}")
        ok = ok and hit

    rss_gen = ftp_read(f"{CLIENT_REMOTE}/includes/class-rss-generator.php")
    hit = "append_description_element" in rss_gen
    print(f"{'OK' if hit else 'FAIL'}: class-rss-generator.php append_description_element")
    ok = ok and hit

    token = secrets.token_hex(16)
    try:
        upload_probe(token)
        ping = probe("ping", token)
        print(f"Probe ping build={ping.get('build')}")
        if ping.get("build") != EXPECTED_BUILD:
            print(f"FAIL: probe build expected {EXPECTED_BUILD}")
            ok = False
        else:
            print("OK: probe runtime BUILD")

        flags = probe("flags", token)
        print(
            "Probe flags:",
            f"client_app={flags.get('client_app')}",
            f"authority={flags.get('authority')}",
        )

        lic = probe("license_state", token)
        st = lic.get("state") if isinstance(lic.get("state"), dict) else {}
        print(
            "License state:",
            f"plan={st.get('plan')}",
            f"usable={st.get('usable')}",
        )
        requires = st.get("usable") and st.get("plan") == "free"
        if requires:
            print("NOTE: free+usable - credit should render on front/RSS/import")
        else:
            print("NOTE: not free+usable - credit injection disabled until free license active")
    finally:
        delete_probe()

    print("\nOVERALL:", "PASS" if ok else "FAIL")
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
