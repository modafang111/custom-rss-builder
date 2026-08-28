#!/usr/bin/env python3
"""123789.jp Download Monitor の client ZIP を dist 最新版で差し替える。"""
from __future__ import annotations

import base64
import ftplib
import re
import sys
import time
import xml.etree.ElementTree as ET
import zipfile
from io import BytesIO
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
DIST_ZIP = BASE / "dist" / "custom-rss-builder-client.zip"
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"
FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"

REMOTE_CANDIDATES = (
    "/123789.jp/public_html/custom-rss-builder/wp-content/uploads/dlm_uploads/2026/06/custom-rss-builder-client.zip",
    "/123789.jp/public_html/custom-rss-builder/wp-content/uploads/dlm_uploads/custom-rss-builder-client.zip",
)


def load_credentials() -> tuple[str, str, str, int]:
    import json

    local_cfg = BASE / "deploy.local.json"
    if local_cfg.is_file():
        data = json.loads(local_cfg.read_text(encoding="utf-8-sig"))
        return (
            str(data.get("host") or FTP_HOST),
            str(data.get("user") or FTP_USER),
            str(data["password"]),
            int(data.get("port", 21)),
        )

    if not FZ_PATH.is_file():
        raise RuntimeError("FTP credentials not found: deploy.local.json or FileZilla sitemanager.xml")
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (
            srv.findtext("User") or ""
        ).strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return (
                    FTP_HOST,
                    FTP_USER,
                    base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace"),
                    21,
                )
    raise RuntimeError("FTP credentials not found")


def zip_build_id(data: bytes) -> str:
    with zipfile.ZipFile(BytesIO(data)) as z:
        main = z.read("custom-rss-builder/custom-rss-builder.php").decode("utf-8")
    m = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main)
    return m.group(1) if m else "?"


def ftp_read(ftp: ftplib.FTP, path: str) -> bytes | None:
    buf = BytesIO()
    try:
        ftp.retrbinary(f"RETR {path}", buf.write)
        return buf.getvalue()
    except ftplib.error_perm:
        return None


def ftp_upload(ftp: ftplib.FTP, path: str, data: bytes) -> None:
    ftp.storbinary(f"STOR {path}", BytesIO(data))
    ts = time.strftime("%Y%m%d%H%M%S", time.gmtime())
    try:
        ftp.sendcmd(f"MDTM {ts} {path}")
    except ftplib.error_perm:
        pass


def main() -> int:
    if not DIST_ZIP.is_file():
        print(f"Missing: {DIST_ZIP}", file=sys.stderr)
        print("Run: python build_plugin_dist.py", file=sys.stderr)
        return 1

    local = DIST_ZIP.read_bytes()
    local_build = zip_build_id(local)
    print(f"Local ZIP: {DIST_ZIP}")
    print(f"Local size: {len(local)} bytes")
    print(f"Local BUILD_ID: {local_build}")

    host, user, pw, port = load_credentials()
    ftp = ftplib.FTP(host, user, pw, timeout=180)
    ftp.set_pasv(True)

    remote_paths: list[str] = []
    for cand in REMOTE_CANDIDATES:
        data = ftp_read(ftp, cand)
        if data is not None:
            remote_paths.append(cand)
            print(f"\nFound remote: {cand}")
            print(f"Remote size: {len(data)} bytes")
            print(f"Remote BUILD_ID: {zip_build_id(data)}")

    if not remote_paths:
        print("FAIL: remote custom-rss-builder-client.zip not found", file=sys.stderr)
        ftp.quit()
        return 2

    needs_upload = False
    for cand in remote_paths:
        data = ftp_read(ftp, cand)
        if data != local:
            needs_upload = True
            break

    if not needs_upload:
        print("\nRemote already matches local ZIP - no upload needed.")
        ftp.quit()
        return 0

    for remote_path in remote_paths:
        print(f"\nUploading to {remote_path} ...")
        ftp_upload(ftp, remote_path, local)
        remote_after = ftp_read(ftp, remote_path)
        if remote_after != local:
            print(f"FAIL: remote file mismatch after upload: {remote_path}", file=sys.stderr)
            ftp.quit()
            return 3
        print(f"OK: {remote_path}")

    ftp.quit()

    print(f"\nRemote BUILD_ID now: {local_build}")
    print("\nDownload page (refresh cache if needed):")
    print("https://123789.jp/custom-rss-builder/download/695/")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
