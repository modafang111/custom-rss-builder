#!/usr/bin/env python3
"""FTP でリモートの feed-import-settings.php / BUILD を検証。"""
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


def ftp_read(ftp: ftplib.FTP, path: str) -> str:
    buf = BytesIO()
    ftp.retrbinary(f"RETR {path}", buf.write)
    return buf.getvalue().decode("utf-8", errors="replace")


def main() -> int:
    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=30)
    fail = 0
    try:
        main_php = ftp_read(ftp, f"{REMOTE}/custom-rss-builder.php")
        partial = ftp_read(ftp, f"{REMOTE}/admin/views/partials/feed-import-settings.php")
        sanitize = ftp_read(ftp, f"{REMOTE}/includes/functions-sanitize.php")
    finally:
        ftp.quit()

    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    print("REMOTE BUILD_ID:", build.group(1) if build else "NOT FOUND")

    if re.search(r'crb-import-content-template"[^>]*placeholder\s*=', partial, re.I):
        print("FAIL: remote textarea still has placeholder")
        fail += 1
    else:
        print("OK: no placeholder on content textarea")

    if "crb_preset_review_import_template" in partial:
        print("FAIL: remote partial still calls crb_preset_review_import_template")
        fail += 1
    else:
        print("OK: partial does not call preset function")

    if "crb_import_content_template_for_ui" not in sanitize:
        print("FAIL: remote functions-sanitize missing UI guard")
        fail += 1
    else:
        print("OK: sanitize has crb_import_content_template_for_ui")

    if "<p>{%1}</p>" in partial:
        print("FAIL: remote partial embeds DLsite HTML preset")
        fail += 1
    else:
        print("OK: partial has no embedded DLsite HTML block")

    return 1 if fail else 0


if __name__ == "__main__":
    raise SystemExit(main())
