# -*- coding: utf-8 -*-
"""正本に LP 強制再生成プローブを置き、HTTP で実行して削除する。"""
from __future__ import annotations

import base64
import ftplib
import json
import secrets
import sys
import urllib.request
import xml.etree.ElementTree as ET
from io import BytesIO
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PROBE_LOCAL = ROOT / "tools" / "crb_force_lp_reinstall.php"
REMOTE_ROOT = (
    "/123789.jp/public_html/custom-rss-builder/wp-content/plugins/custom-rss-builder"
)
REMOTE_PROBE = f"{REMOTE_ROOT}/tools/crb_force_lp_reinstall.php"
PROBE_URL = (
    "https://123789.jp/custom-rss-builder/wp-content/plugins/"
    "custom-rss-builder/tools/crb_force_lp_reinstall.php"
)
FZ = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"


def load_password() -> str:
    tree = ET.parse(FZ)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() != "sv7288.xserver.jp":
            continue
        if (srv.findtext("User") or "").strip() != "ideamart1":
            continue
        enc = srv.find("Pass")
        if enc is not None and enc.text:
            return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("FTP credentials not found")


def ftp_makedirs(ftp: ftplib.FTP, remote_dir: str) -> None:
    parts = [p for p in remote_dir.split("/") if p]
    path = ""
    for part in parts:
        path += "/" + part
        try:
            ftp.mkd(path)
        except ftplib.error_perm:
            pass


def main() -> int:
    key = secrets.token_hex(16)
    probe_body = PROBE_LOCAL.read_text(encoding="utf-8")
    probe_body = probe_body.replace(
        "getenv( 'CRB_PROBE_KEY' )",
        f"'{key}'",
    )

    pw = load_password()
    ftp = ftplib.FTP("sv7288.xserver.jp", "ideamart1", pw, timeout=120)
    ftp_makedirs(ftp, f"{REMOTE_ROOT}/tools")
    ftp.storbinary(f"STOR {REMOTE_PROBE}", BytesIO(probe_body.encode("utf-8")))
    print("Uploaded probe")

    url = f"{PROBE_URL}?key={key}"
    with urllib.request.urlopen(url, timeout=120) as resp:
        raw = resp.read().decode("utf-8", errors="replace")
    print("Probe response:")
    print(raw[:2000])
    try:
        data = json.loads(raw)
        if not data.get("ok"):
            return 1
    except json.JSONDecodeError:
        print("Non-JSON response", file=sys.stderr)
        return 1

    try:
        ftp.delete(REMOTE_PROBE)
        print("Deleted probe")
    finally:
        ftp.quit()
    return 0


if __name__ == "__main__":
    sys.exit(main())
