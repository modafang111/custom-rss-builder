#!/usr/bin/env python3
"""FTP でリモート BUILD_ID と admin.js の要点を確認。"""
from __future__ import annotations

import base64
import ftplib
import re
import sys
import xml.etree.ElementTree as ET
from io import BytesIO
from pathlib import Path

FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"
REMOTE = "/123789.jp/public_html/custom-rss-builder/wp-content/plugins/custom-rss-builder"
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"


def load_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (srv.findtext("User") or "").strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("FTP credentials not found")


def ftp_read(ftp: ftplib.FTP, remote_path: str) -> str:
    buf = BytesIO()
    ftp.retrbinary(f"RETR {remote_path}", buf.write)
    return buf.getvalue().decode("utf-8", errors="replace")


def main() -> int:
    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=30)
    try:
        main_php = ftp_read(ftp, f"{REMOTE}/custom-rss-builder.php")
        admin_js = ftp_read(ftp, f"{REMOTE}/assets/js/admin.js")
        cand_php = ftp_read(ftp, f"{REMOTE}/includes/functions-extract-candidates.php")
    finally:
        ftp.quit()

    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    local_build = "20260529r"
    remote_build = build.group(1) if build else None
    print("CRB_BUILD_ID", remote_build or "NOT FOUND", f"(expect {local_build})")
    checks = [
        ("BUILD_ID matches", remote_build == local_build),
        ("colCount in admin.js", "colCount" in admin_js and "crb-discover-match-count" in admin_js),
        ("match_count in candidates PHP", "match_count" in cand_php and "crb_extract_candidates_attach_match_counts" in cand_php),
        ("scope preview 1000 chars", "renderScopeHtmlPreview" in admin_js and "1000" in admin_js),
        ("scope more toggle", "crb-scope-html-more" in admin_js and "<more!>" in admin_js),
        ("no scrollIntoView", "scrollIntoView" not in admin_js),
        ("crb_admin_feed ajax", "crb_admin_feed" in admin_js),
    ]
    fail = 0
    for label, ok in checks:
        print(("OK   " if ok else "FAIL ") + label)
        if not ok:
            fail += 1
    return 1 if (not build or fail) else 0


if __name__ == "__main__":
    raise SystemExit(main())
