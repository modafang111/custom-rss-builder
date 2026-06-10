#!/usr/bin/env python3
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
    try:
        main_php = ftp_read(ftp, f"{REMOTE}/custom-rss-builder.php")
        lic_view = ftp_read(ftp, f"{REMOTE}/admin/views/license-settings.php")
        lic_fn = ftp_read(ftp, f"{REMOTE}/includes/functions-license.php")
    finally:
        ftp.quit()

    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    print("FTP BUILD_ID:", build.group(1) if build else "NOT FOUND")
    print("connection panel:", "save_connection" in lic_view)
    print("sprintf fix fn:", "'pro'   => '{%1%}" in lic_fn)
    print("sprintf fix view:", "{%1%%}" in lic_view)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
