#!/usr/bin/env python3
"""Client-facing PHP/JS must not embed site-specific brand URLs or selector defaults."""
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FAILURES: list[str] = []

# Substrings that must not appear in user-facing client code.
FORBIDDEN = (
    "dlsite.com",
    "dlaf.jp",
    "dslite_moda",
    "DLsite",
    "maniax/work",
    "maniax/",
    "work_img_popover",
    "work_thumb",
    "work_btn",
    "thumb-candidate",
    "review_desc",
    "reveiw_title",
    "reveiw_author",
    "review_contents_inner",
)

CLIENT_DIRS = (
    ROOT / "admin",
    ROOT / "assets",
    ROOT / "includes",
)
CLIENT_FILES = (
    ROOT / "custom-rss-builder.php",
)


def ok(msg: str) -> None:
    print(f"OK   {msg}")


def fail(msg: str) -> None:
    FAILURES.append(msg)
    print(f"FAIL {msg}")


def iter_client_files() -> list[Path]:
    out: list[Path] = []
    for base in CLIENT_DIRS:
        if not base.is_dir():
            continue
        for path in sorted(base.rglob("*")):
            if not path.is_file():
                continue
            if path.suffix.lower() not in {".php", ".js", ".css"}:
                continue
            out.append(path)
    for path in CLIENT_FILES:
        if path.is_file():
            out.append(path)
    return out


def main() -> int:
    print("=== verify_no_adult_brand_in_client ===\n")
    checked = 0
    for path in iter_client_files():
        text = path.read_text(encoding="utf-8")
        rel = path.relative_to(ROOT).as_posix()
        checked += 1
        lower = text.lower()
        for needle in FORBIDDEN:
            if needle.lower() in lower:
                fail(f"{rel} contains {needle!r}")
    ok(f"scanned {checked} client-facing files")
    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("No forbidden brand/site-specific strings in client-facing code.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
