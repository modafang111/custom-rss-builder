# -*- coding: utf-8 -*-
"""WordPress 向けに forward-slash パスでプラグイン ZIP を作成する。"""
from __future__ import annotations

import time
import zipfile
from pathlib import Path

BASE = Path(__file__).resolve().parent
# ソースは custom-rss-builder/custom-rss-builder/ にある（ZIP 内は custom-rss-builder/ 直下）
PLUGIN_DIR = BASE / "custom-rss-builder" / "custom-rss-builder"
OUTPUT_ZIP = BASE / "custom-rss-builder.zip"

SKIP_NAMES = {".DS_Store", "Thumbs.db"}

# ZIP フォーマットは 1980 年より前のタイムスタンプを表現できない。
# チェックアウト直後のファイルが epoch(1970) mtime を持つ環境でも
# ビルドが失敗しないよう、下限を 1980-01-01 にクランプする。
ZIP_MIN_DATE_TIME = (1980, 1, 1, 0, 0, 0)


def should_skip(path: Path) -> bool:
    return path.name in SKIP_NAMES or path.name.startswith(".")


def zip_date_time(path: Path) -> tuple[int, int, int, int, int, int]:
    parts = time.localtime(path.stat().st_mtime)[:6]
    if parts[0] < 1980:
        return ZIP_MIN_DATE_TIME
    return parts


def build_zip() -> Path:
    if not PLUGIN_DIR.is_dir():
        raise FileNotFoundError(f"Plugin folder not found: {PLUGIN_DIR}")
    if not (PLUGIN_DIR / "custom-rss-builder.php").is_file():
        raise FileNotFoundError("Main plugin file missing: custom-rss-builder/custom-rss-builder.php")

    if OUTPUT_ZIP.exists():
        OUTPUT_ZIP.unlink()

    with zipfile.ZipFile(OUTPUT_ZIP, "w", zipfile.ZIP_DEFLATED) as zf:
        for file_path in sorted(PLUGIN_DIR.rglob("*")):
            if file_path.is_dir() or should_skip(file_path):
                continue
            rel = file_path.relative_to(PLUGIN_DIR).as_posix()
            arcname = f"{PLUGIN_DIR.name}/{rel}"
            info = zipfile.ZipInfo(arcname, date_time=zip_date_time(file_path))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = (0o644 & 0xFFFF) << 16
            zf.writestr(info, file_path.read_bytes())

    return OUTPUT_ZIP


if __name__ == "__main__":
    out = build_zip()
    with zipfile.ZipFile(out) as zf:
        names = zf.namelist()[:5]
    print(f"OK: {out}")
    print("Sample entries:")
    for name in names:
        print(f"  {name}")
