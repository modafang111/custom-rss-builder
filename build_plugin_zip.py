# -*- coding: utf-8 -*-
"""
WordPress 向けプラグイン ZIP。

- 既定: Level 3 のクライアント用 + 正本サーバー用（dist/）
- --dev-only: 開発用フルツリー 1 本（custom-rss-builder.zip）
"""
from __future__ import annotations

import argparse
import sys
import zipfile
from pathlib import Path

BASE = Path(__file__).resolve().parent
PLUGIN_DIR = BASE / "custom-rss-builder" / "custom-rss-builder"
DEV_ZIP = BASE / "custom-rss-builder.zip"
SKIP_NAMES = {".DS_Store", "Thumbs.db"}


def should_skip(path: Path) -> bool:
    return path.name in SKIP_NAMES or path.name.startswith(".")


def build_dev_zip() -> Path:
    if not PLUGIN_DIR.is_dir():
        raise FileNotFoundError(f"Plugin folder not found: {PLUGIN_DIR}")
    if DEV_ZIP.exists():
        DEV_ZIP.unlink()
    with zipfile.ZipFile(DEV_ZIP, "w", zipfile.ZIP_DEFLATED) as zf:
        for file_path in sorted(PLUGIN_DIR.rglob("*")):
            if file_path.is_dir() or should_skip(file_path):
                continue
            if "__pycache__" in file_path.parts or file_path.suffix == ".pyc":
                continue
            rel = file_path.relative_to(PLUGIN_DIR).as_posix()
            zf.write(file_path, f"{PLUGIN_DIR.name}/{rel}")
    return DEV_ZIP


def main() -> int:
    parser = argparse.ArgumentParser(description="Build Custom RSS Builder ZIP(s)")
    parser.add_argument(
        "--dev-only",
        action="store_true",
        help="Build full dev tree zip only (custom-rss-builder.zip)",
    )
    args = parser.parse_args()

    if args.dev_only:
        out = build_dev_zip()
        print(f"OK: {out}")
        return 0

    from build_plugin_dist import main as build_dist

    code = build_dist()
    if code != 0:
        return code

    out = build_dev_zip()
    print(f"OK (dev full): {out}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
