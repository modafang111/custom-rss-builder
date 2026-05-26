# -*- coding: utf-8 -*-
"""FTP で custom-rss-builder プラグインをアップロードする（検証用）。"""
from __future__ import annotations

import base64
import ftplib
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

BASE = Path(__file__).resolve().parent
PLUGIN_DIR = BASE / "custom-rss-builder" / "custom-rss-builder"
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"

# 123789.jp 検証サイト想定（自分のサイト > エックスサーバー）
FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"
REMOTE_CANDIDATES = [
    "/123789.jp/public_html/custom-rss-builder/wp-content/plugins/custom-rss-builder",
    "/123789.jp/public_html/wp-content/plugins/custom-rss-builder",
    "/custom-rss-builder/wp-content/plugins/custom-rss-builder",
]

SKIP_NAMES = {".DS_Store", "Thumbs.db"}


def should_skip(path: Path) -> bool:
    return path.name in SKIP_NAMES or path.name.startswith(".")


def decode_fz_pass(b64: str) -> str:
    return base64.b64decode(b64).decode("utf-8", errors="replace")


def load_credentials() -> tuple[str, str, str, int]:
    """FileZilla または deploy.local.json から接続情報を取得。"""
    local_cfg = BASE / "deploy.local.json"
    if local_cfg.is_file():
        import json

        data = json.loads(local_cfg.read_text(encoding="utf-8"))
        return (
            str(data["host"]),
            str(data["user"]),
            str(data["password"]),
            int(data.get("port", 21)),
        )

    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        host = (srv.findtext("Host") or "").strip()
        user = (srv.findtext("User") or "").strip()
        if host == FTP_HOST and user == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return FTP_HOST, FTP_USER, decode_fz_pass(enc.text.strip()), 21
    raise RuntimeError(f"FileZilla に {FTP_HOST} / {FTP_USER} が見つかりません")


def ftp_makedirs(ftp: ftplib.FTP, remote_dir: str) -> None:
    parts = [p for p in remote_dir.split("/") if p]
    path = ""
    for part in parts:
        path += "/" + part
        try:
            ftp.mkd(path)
        except ftplib.error_perm:
            pass


def upload_tree(ftp: ftplib.FTP, local: Path, remote: str) -> int:
    count = 0
    for item in sorted(local.rglob("*")):
        if item.is_dir():
            continue
        rel = item.relative_to(local).as_posix()
        remote_path = f"{remote}/{rel}".replace("//", "/")
        remote_parent = "/".join(remote_path.split("/")[:-1])
        if remote_parent:
            ftp_makedirs(ftp, remote_parent)
        with item.open("rb") as f:
            ftp.storbinary(f"STOR {remote_path}", f)
        count += 1
        print(f"  OK {rel}")
    return count


def remote_has_main_php(ftp: ftplib.FTP) -> bool:
    try:
        names = ftp.nlst()
    except ftplib.error_perm:
        return False
    return "custom-rss-builder.php" in names


def find_plugin_remote(ftp: ftplib.FTP) -> str | None:
    for cand in REMOTE_CANDIDATES:
        try:
            ftp.cwd(cand)
            if remote_has_main_php(ftp):
                return cand
        except ftplib.error_perm:
            continue

    try:
        ftp.cwd("/")
        for name in ftp.nlst():
            if "123789" in name:
                base = f"/{name}/public_html"
                for sub in (
                    "custom-rss-builder/wp-content/plugins/custom-rss-builder",
                    "wp-content/plugins/custom-rss-builder",
                ):
                    path = f"{base}/{sub}"
                    try:
                        ftp.cwd(path)
                        if remote_has_main_php(ftp):
                            return path
                    except ftplib.error_perm:
                        continue
    except ftplib.error_perm:
        pass
    return None


def touch_remote_files(ftp: ftplib.FTP, remote: str, local: Path) -> None:
    import time

    ts = time.strftime("%Y%m%d%H%M%S", time.gmtime())
    for file_path in local.rglob("*"):
        if file_path.is_dir() or should_skip(file_path):
            continue
        rel = file_path.relative_to(local).as_posix()
        remote_path = f"{remote}/{rel}".replace("//", "/")
        try:
            ftp.sendcmd(f"MDTM {ts} {remote_path}")
        except ftplib.error_perm:
            pass


def verify_http() -> bool:
    import urllib.request

    url = (
        "https://123789.jp/custom-rss-builder/wp-content/plugins/"
        "custom-rss-builder/assets/js/admin.js"
    )
    try:
        with urllib.request.urlopen(url, timeout=30) as resp:
            body = resp.read().decode("utf-8", errors="replace")
            cache = resp.headers.get("Cache-Control", "")
    except Exception as exc:
        print(f"HTTP verify failed: {exc}", file=sys.stderr)
        return False

    ok = (
        "applySuggestedSlots" in body
        and "recordFieldLabels" not in body
        and "crb-slot-lines" in body
        and "renderSlotRulesTable" in body
        and "0.5.9-image-slot4" in body
    )
    print(f"HTTP verify: ok={ok} Cache-Control={cache}")
    return ok


def main() -> int:
    if not PLUGIN_DIR.is_dir():
        print(f"Missing plugin dir: {PLUGIN_DIR}", file=sys.stderr)
        return 1

    host, user, password, port = load_credentials()
    ftp = ftplib.FTP(timeout=120)
    ftp.connect(host, port)
    ftp.login(user, password)
    ftp.set_pasv(True)

    remote = find_plugin_remote(ftp)
    if not remote:
        print("Plugin remote path not found. Tried:", REMOTE_CANDIDATES, file=sys.stderr)
        ftp.quit()
        return 2

    print(f"Deploy to: {remote}")
    ftp.cwd(remote)
    n = upload_tree(ftp, PLUGIN_DIR, remote)
    touch_remote_files(ftp, remote, PLUGIN_DIR)
    ftp.quit()
    print(f"Done: {n} files")
    if not verify_http():
        print("WARNING: HTTP verification failed", file=sys.stderr)
        return 3
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
