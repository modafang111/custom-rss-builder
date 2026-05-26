# -*- coding: utf-8 -*-
"""WordPress 向けに forward-slash パスでプラグイン ZIP を作成する。"""
from __future__ import annotations

import zipfile
from pathlib import Path

BASE = Path(__file__).resolve().parent
# ソースは custom-rss-builder/custom-rss-builder/ にある（ZIP 内は custom-rss-builder/ 直下）
PLUGIN_DIR = BASE / "custom-rss-builder" / "custom-rss-builder"
OUTPUT_ZIP = BASE / "custom-rss-builder.zip"

SKIP_NAMES = {".DS_Store", "Thumbs.db"}


def should_skip(path: Path) -> bool:
    return path.name in SKIP_NAMES or path.name.startswith(".")


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
            zf.write(file_path, arcname)

    return OUTPUT_ZIP


if __name__ == "__main__":
    out = build_zip()
    with zipfile.ZipFile(out) as zf:
        names = zf.namelist()[:5]
    print(f"OK: {out}")
    print("Sample entries:")
    for name in names:
        print(f"  {name}")
