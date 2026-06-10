#!/usr/bin/env python3
"""Feed pack manual fixed-page checks (no WordPress required)."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FAILURES: list[str] = []


def ok(msg: str) -> None:
    print(f"OK   {msg}")


def fail(msg: str) -> None:
    FAILURES.append(msg)
    print(f"FAIL {msg}")


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def main() -> int:
    print("=== verify_feed_pack_manual ===\n")

    main_php = read("custom-rss-builder.php")
    manual_php = read("includes/functions-feed-pack-manual.php")
    demo_php = read("includes/functions-demo-samples.php")
    install_php = read("includes/functions-install-manual.php")
    ls_settings = read("license-server/admin/views/settings.php")

    if "functions-feed-pack-manual.php" not in main_php:
        fail("bootstrap must require functions-feed-pack-manual.php")
    else:
        ok("bootstrap requires feed-pack manual module")

    for fn in (
        "crb_feed_pack_manual_page_url",
        "crb_feed_pack_manual_build_page_content",
        "crb_feed_pack_manual_install",
        "crb_feed_pack_manual_maybe_install",
    ):
        if f"function {fn}" not in manual_php:
            fail(f"{fn} missing")
        else:
            ok(f"{fn} present")

    if "crb-feed-pack-manual" not in manual_php:
        fail("manual page slug missing")
    else:
        ok("manual page slug defined")

    for needle in ("crb-fpack-export", "crb-fpack-import", "pack_version"):
        if needle not in manual_php:
            fail(f"manual content missing {needle!r}")
            break
    else:
        ok("manual covers export/import and pack_version")

    if "crb_feed_pack_manual_install" not in demo_php:
        fail("demo samples install must refresh feed-pack manual")
    else:
        ok("demo samples install hooks feed-pack manual")

    if "crb_feed_pack_manual_page_url" not in install_php:
        fail("install manual should link to feed-pack manual")
    else:
        ok("install manual links feed-pack manual")

    if "crb_feed_pack_manual_page_url" not in ls_settings:
        fail("license-server settings missing feed-pack manual link")
    else:
        ok("license-server settings link present")

    m_build = re.search(r"define\s*\(\s*'CRB_BUILD_ID'\s*,\s*'([^']+)'\s*\)", main_php)
    if not m_build:
        fail("CRB_BUILD_ID missing")
    else:
        ok(f"CRB_BUILD_ID {m_build.group(1)}")

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("All feed pack manual checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
