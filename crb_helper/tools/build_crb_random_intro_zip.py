# -*- coding: utf-8 -*-
"""CRB Random Intro 配布 ZIP をビルドする。"""
from __future__ import annotations

import re
import zipfile
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
CANDIDATE_DIRS = (
    BASE / "helpers_new" / "crb-random-intro",
    BASE / "reference" / "crb-random-intro",
)
DIST_DIR = BASE / "dist"
ZIP_NAME = "crb-random-intro.zip"
PLUGIN_SLUG = "crb-random-intro"


def find_plugin_dir() -> Path:
    for path in CANDIDATE_DIRS:
        if (path / "crb-random-intro.php").is_file():
            return path
    raise FileNotFoundError(
        "crb-random-intro.php not found. Tried:\n  "
        + "\n  ".join(str(p) for p in CANDIDATE_DIRS)
    )


def read_version(plugin_dir: Path) -> str:
    main = (plugin_dir / "crb-random-intro.php").read_text(encoding="utf-8")
    m = re.search(r"Version:\s*([0-9.]+)", main)
    return m.group(1) if m else "?"


def write_zip(plugin_dir: Path, out_path: Path) -> int:
    out_path.parent.mkdir(parents=True, exist_ok=True)
    count = 0
    with zipfile.ZipFile(out_path, "w", compression=zipfile.ZIP_DEFLATED) as zf:
        for path in sorted(plugin_dir.rglob("*")):
            if not path.is_file():
                continue
            if path.name.startswith(".") or path.name in {".DS_Store", "Thumbs.db"}:
                continue
            rel = path.relative_to(plugin_dir).as_posix()
            zf.write(path, f"{PLUGIN_SLUG}/{rel}")
            count += 1
    return count


def main() -> int:
    plugin_dir = find_plugin_dir()
    version = read_version(plugin_dir)
    out_path = DIST_DIR / ZIP_NAME
    count = write_zip(plugin_dir, out_path)
    print(f"OK  {out_path} ({count} files, version {version})")
    with zipfile.ZipFile(out_path) as zf:
        names = zf.namelist()
        if f"{PLUGIN_SLUG}/crb-random-intro.php" not in names:
            print("FAIL: missing main plugin file", flush=True)
            return 2
    print("All checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
