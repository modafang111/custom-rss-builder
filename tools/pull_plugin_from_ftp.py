#!/usr/bin/env python3
"""FTP 上の custom-rss-builder プラグインをローカルへ同期（ダウンロード）。"""
from __future__ import annotations

import argparse
import base64
import ftplib
import re
import sys
import xml.etree.ElementTree as ET
from io import BytesIO
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
LOCAL_DIR = BASE / "custom-rss-builder" / "custom-rss-builder"
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"

FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"

TARGETS = {
    "authority": {
        "label": "123789.jp (正本サーバー)",
        "remote": (
            "/123789.jp/public_html/custom-rss-builder/wp-content/plugins/custom-rss-builder"
        ),
    },
    "plugintest": {
        "label": "wordpress-123.com (PluginTest)",
        "remote": (
            "/wordpress-123.com/public_html/PluginTest/wp-content/plugins/custom-rss-builder"
        ),
    },
}

SKIP_NAMES = {".DS_Store", "Thumbs.db", "Thumbs.db:encryptable"}


def load_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (
            srv.findtext("User") or ""
        ).strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", "replace")
    raise RuntimeError("FTP credentials not found in FileZilla sitemanager.xml")


def extract_build_id(text: str) -> str | None:
    m = re.search(r"CRB_BUILD_ID['\"]\s*,\s*['\"]([^'\"]+)['\"]", text)
    return m.group(1) if m else None


def ftp_read_bytes(ftp: ftplib.FTP, remote_path: str) -> bytes:
    buf = BytesIO()
    ftp.retrbinary(f"RETR {remote_path}", buf.write)
    return buf.getvalue()


def ftp_list_recursive(ftp: ftplib.FTP, remote_dir: str) -> list[str]:
    """Return remote file paths (not directories) under remote_dir."""
    files: list[str] = []
    try:
        entries = list(ftp.mlsd(remote_dir))
    except ftplib.error_perm:
        try:
            names = ftp.nlst(remote_dir)
        except ftplib.error_perm:
            return files
        for name in names:
            base = name.rsplit("/", 1)[-1]
            if base in (".", ".."):
                continue
            child = name if name.startswith("/") else f"{remote_dir}/{base}"
            if child == remote_dir:
                continue
            try:
                ftp.size(child)
                files.append(child)
            except ftplib.error_perm:
                files.extend(ftp_list_recursive(ftp, child))
        return files

    for name, facts in entries:
        if name in (".", ".."):
            continue
        child = f"{remote_dir}/{name}".replace("//", "/")
        if facts.get("type") == "dir":
            files.extend(ftp_list_recursive(ftp, child))
        elif facts.get("type") == "file":
            files.append(child)
    return files


def download_tree(
    ftp: ftplib.FTP, remote_root: str, local_root: Path, *, dry_run: bool
) -> tuple[int, int]:
    remote_root = remote_root.rstrip("/")
    files = ftp_list_recursive(ftp, remote_root)
    downloaded = 0
    skipped = 0

    for remote_path in sorted(files):
        rel = remote_path[len(remote_root) :].lstrip("/")
        base = Path(rel).name
        if base in SKIP_NAMES or base.startswith("."):
            skipped += 1
            continue

        local_path = local_root / rel.replace("/", "\\")
        if dry_run:
            print(f"  WOULD GET {rel}")
            downloaded += 1
            continue

        local_path.parent.mkdir(parents=True, exist_ok=True)
        data = ftp_read_bytes(ftp, remote_path)
        local_path.write_bytes(data)
        print(f"  OK {rel}")
        downloaded += 1

    return downloaded, skipped


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--target",
        choices=sorted(TARGETS),
        default="authority",
        help="FTP 同期元 (default: authority = 123789.jp)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="ダウンロードせず対象ファイルのみ表示",
    )
    parser.add_argument(
        "--remote-subpath",
        default="",
        help="リモート内の特定サブディレクトリのみ同期 (例: license-server)",
    )
    args = parser.parse_args()

    target = TARGETS[args.target]
    remote_root = target["remote"].rstrip("/")
    if args.remote_subpath:
        remote_root = f"{remote_root}/{args.remote_subpath.strip('/')}"
    local_root = LOCAL_DIR
    if args.remote_subpath:
        local_root = local_root / args.remote_subpath.strip("/").replace("/", "\\")

    if not local_root.is_dir():
        if args.remote_subpath and not args.dry_run:
            local_root.mkdir(parents=True, exist_ok=True)
        elif not local_root.is_dir():
            print(f"Missing local dir: {local_root}", file=sys.stderr)
            return 1

    local_main = LOCAL_DIR / "custom-rss-builder.php"
    local_build = None
    if local_main.is_file():
        local_build = extract_build_id(local_main.read_text(encoding="utf-8", errors="replace"))

    print(f"Target: {target['label']}")
    print(f"Remote: {remote_root}")
    print(f"Local:  {local_root}")
    print(f"Local BUILD_ID: {local_build or 'NOT FOUND'}")

    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
    ftp.set_pasv(True)
    try:
        if not args.remote_subpath:
            main_remote = f"{target['remote'].rstrip('/')}/custom-rss-builder.php"
            remote_body = ftp_read_bytes(ftp, main_remote).decode("utf-8", errors="replace")
            remote_build = extract_build_id(remote_body)
            print(f"Remote BUILD_ID: {remote_build or 'NOT FOUND'}")

            if remote_build and local_build and remote_build == local_build and not args.dry_run:
                print("\nBUILD_ID は既に一致しています。続行してファイル全体を上書き同期します。")
        else:
            print(f"Remote subpath only: {args.remote_subpath}")

        print("\n=== download ===")
        n, skipped = download_tree(ftp, remote_root, local_root, dry_run=args.dry_run)
        print(f"\nDownloaded: {n} files (skipped {skipped})")

        if not args.dry_run and not args.remote_subpath and local_main.is_file():
            new_build = extract_build_id(
                local_main.read_text(encoding="utf-8", errors="replace")
            )
            print(f"Local BUILD_ID after sync: {new_build or 'NOT FOUND'}")
    finally:
        ftp.quit()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
