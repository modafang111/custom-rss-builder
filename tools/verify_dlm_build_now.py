"""Verify DLM client ZIP BUILD_ID on FTP and download page reachability."""
from __future__ import annotations

import base64
import ftplib
import io
import re
import ssl
import urllib.request
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path

FZ = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"
HOST = "sv7288.xserver.jp"
USER = "ideamart1"
ZIP = "/123789.jp/public_html/custom-rss-builder/wp-content/uploads/dlm_uploads/2026/06/custom-rss-builder-client.zip"
PAGE = "https://123789.jp/custom-rss-builder/download/695/"


def password() -> str:
    tree = ET.parse(FZ)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == HOST and (srv.findtext("User") or "").strip() == USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("FTP credentials not found")


def main() -> int:
    ftp = ftplib.FTP(HOST, USER, password(), timeout=120)
    ftp.set_pasv(True)
    buf = io.BytesIO()
    ftp.retrbinary(f"RETR {ZIP}", buf.write)
    ftp.quit()
    data = buf.getvalue()
    with zipfile.ZipFile(io.BytesIO(data)) as zf:
        main_php = zf.read("custom-rss-builder/custom-rss-builder.php").decode("utf-8")
        settings = zf.read("custom-rss-builder/admin/views/settings-page.php").decode("utf-8")
    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    version = re.search(r"Version:\s*([^\n]+)", main_php)
    print("ZIP bytes:", len(data))
    print("BUILD_ID:", build.group(1) if build else "?")
    print("Version:", version.group(1).strip() if version else "?")
    print("slot preset button present:", "全フィードにスロット設定を適用" in settings)

    ctx = ssl.create_default_context()
    req = urllib.request.Request(PAGE, headers={"User-Agent": "Mozilla/5.0"})
    try:
        with urllib.request.urlopen(req, context=ctx, timeout=30) as resp:
            body = resp.read(4000).decode("utf-8", errors="replace")
            print("PAGE status:", resp.status)
            print("PAGE has password form:", ("パスワード" in body) or ("password" in body.lower()))
    except urllib.error.HTTPError as e:
        body = e.read(2000).decode("utf-8", errors="replace")
        print("PAGE status:", e.code)
        print("PAGE snippet:", body[:200].replace("\n", " "))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
