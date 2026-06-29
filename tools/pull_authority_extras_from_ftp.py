#!/usr/bin/env python3
"""正本サーバーにのみある個別ファイルを FTP から取得（PluginTest 同期後用）。"""
from __future__ import annotations

import base64
import ftplib
import sys
import xml.etree.ElementTree as ET
from io import BytesIO
from pathlib import Path

LOCAL = Path(__file__).resolve().parent.parent / "custom-rss-builder" / "custom-rss-builder"
REMOTE_BASE = (
    "/123789.jp/public_html/custom-rss-builder/wp-content/plugins/custom-rss-builder"
)
FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"

REL_PATHS = [
    "README.md",
    "uninstall.php",
    "assets/css/sales-lp.css",
    "includes/class-custom-rss-builder-authority.php",
    "includes/class-html-parser.php",
    "includes/functions-sales-lp.php",
]


def load_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (
            srv.findtext("User") or ""
        ).strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", "replace")
    raise RuntimeError("FTP credentials not found")


def main() -> int:
    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
    ftp.set_pasv(True)
    ok = 0
    miss = 0
    try:
        for rel in REL_PATHS:
            remote = f"{REMOTE_BASE}/{rel}".replace("//", "/")
            local = LOCAL / rel.replace("/", "\\")
            buf = BytesIO()
            try:
                ftp.retrbinary(f"RETR {remote}", buf.write)
            except ftplib.error_perm as exc:
                print(f"MISS {rel}: {exc}")
                miss += 1
                continue
            local.parent.mkdir(parents=True, exist_ok=True)
            local.write_bytes(buf.getvalue())
            print(f"OK   {rel}")
            ok += 1
    finally:
        ftp.quit()
    print(f"\nFetched {ok} files ({miss} missing)")
    return 0 if miss == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
