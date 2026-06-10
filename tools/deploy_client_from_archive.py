#!/usr/bin/env python3
"""dist/archives/ の client ZIP から PluginTest へロールバックデプロイ。"""
from __future__ import annotations

import argparse
import ftplib
import re
import shutil
import sys
import tempfile
import time
import zipfile
from io import BytesIO
from pathlib import Path

TOOLS_DIR = Path(__file__).resolve().parent
if str(TOOLS_DIR) not in sys.path:
    sys.path.insert(0, str(TOOLS_DIR))

from deploy_client_plugin_ftp import (  # noqa: E402
    CLIENT_REMOTE,
    FTP_HOST,
    FTP_USER,
    load_password,
    prune_remote_client_extras,
    touch_remote_files,
    upload_tree,
    verify_remote,
)
from versioning import client_archive_path, read_plugin_constants, tag_name  # noqa: E402

PLUGIN_SLUG = "custom-rss-builder"


def extract_client_zip(zip_path: Path, dest_dir: Path) -> Path:
    stage_dir = dest_dir / PLUGIN_SLUG
    stage_dir.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(zip_path) as zf:
        prefix = f"{PLUGIN_SLUG}/"
        for name in zf.namelist():
            if not name.startswith(prefix) or name.endswith("/"):
                continue
            rel = name[len(prefix) :]
            out = stage_dir / rel
            out.parent.mkdir(parents=True, exist_ok=True)
            out.write_bytes(zf.read(name))
    return stage_dir


def read_build_id_from_zip(zip_path: Path) -> str:
    with zipfile.ZipFile(zip_path) as zf:
        body = zf.read(f"{PLUGIN_SLUG}/custom-rss-builder.php").decode(
            "utf-8", errors="replace"
        )
    m = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", body)
    if not m:
        raise RuntimeError(f"CRB_BUILD_ID not found in {zip_path}")
    return m.group(1)


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Deploy archived client ZIP to PluginTest (rollback)."
    )
    parser.add_argument(
        "--build-id",
        help="BUILD_ID to restore (looks up dist/archives/custom-rss-builder-client-<id>.zip)",
    )
    parser.add_argument(
        "--zip",
        type=Path,
        help="Explicit archive ZIP path",
    )
    args = parser.parse_args()

    if args.zip:
        zip_path = args.zip.resolve()
    elif args.build_id:
        zip_path = client_archive_path(args.build_id.strip())
    else:
        const = read_plugin_constants()
        print(
            "Specify --build-id or --zip. Current BUILD_ID in source:",
            const["build_id"],
            file=sys.stderr,
        )
        return 1

    if not zip_path.is_file():
        print(f"Archive not found: {zip_path}", file=sys.stderr)
        print(
            f"Create it with: python tools/record_build_snapshot.py --build-id {args.build_id or '<id>'}",
            file=sys.stderr,
        )
        return 1

    expected_build = read_build_id_from_zip(zip_path)
    print(f"Archive: {zip_path}")
    print(f"Archive BUILD_ID: {expected_build}")
    print(f"Git tag (if any): {tag_name(expected_build)}")
    print(f"Deploy to: {CLIENT_REMOTE}")

    stage_root = Path(tempfile.mkdtemp(prefix="crb-client-rollback-"))
    try:
        stage_dir = extract_client_zip(zip_path, stage_root)
        pw = load_password()
        ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
        ftp.set_pasv(True)
        try:
            ftp.cwd(CLIENT_REMOTE)
        except ftplib.error_perm:
            from deploy_client_plugin_ftp import ftp_makedirs

            ftp_makedirs(ftp, CLIENT_REMOTE)
            ftp.cwd(CLIENT_REMOTE)

        prune_remote_client_extras(ftp)
        n = upload_tree(ftp, stage_dir, CLIENT_REMOTE)
        touch_remote_files(ftp, CLIENT_REMOTE, stage_dir)
        print(f"Uploaded {n} files")
        print("\n=== verify ===")
        if not verify_remote(ftp, expected_build):
            ftp.quit()
            return 2
        ftp.quit()
    finally:
        shutil.rmtree(stage_root, ignore_errors=True)

    print("\nRollback deploy OK.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
