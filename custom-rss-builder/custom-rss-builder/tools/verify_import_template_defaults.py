#!/usr/bin/env python3
"""
投稿本文テンプレート (content_template) がクライアント配布向けに
空で始まることを静的に検証する。
"""
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
    print("=== verify_import_template_defaults ===\n")

    admin_dir = ROOT / "admin"
    admin_php = "\n".join(
        p.read_text(encoding="utf-8")
        for p in sorted(admin_dir.rglob("*.php"))
    )

    preset_snippet = "<p>{%1}</p>"

    if "crb_preset_review_import_template" in admin_php:
        fail("admin PHP must not reference crb_preset_review_import_template")
    else:
        ok("admin views do not call bundled import preset")

    if preset_snippet in admin_php:
        fail("admin PHP must not embed DLsite import HTML preset")
    else:
        ok("admin PHP has no hardcoded import HTML block")

    import_partial = read("admin/views/partials/feed-import-settings.php")
    if re.search(r'crb-import-content-template"[^>]*placeholder\s*=', import_partial, re.I):
        fail("content textarea must not have placeholder attribute")
    else:
        ok("content textarea has no placeholder")

    edit_feed = read("admin/views/edit-feed.php")
    if "crb_import_content_template_for_ui" not in edit_feed:
        fail("edit-feed must use crb_import_content_template_for_ui for stored value")
    else:
        ok("edit-feed normalizes stored content_template for UI")

    sanitize = read("includes/functions-sanitize.php")
    for fn in (
        "crb_is_bundled_import_content_preset",
        "crb_import_content_template_for_ui",
        "crb_sanitize_import_content_template",
    ):
        if f"function {fn}" not in sanitize:
            fail(f"missing {fn}")
        else:
            ok(fn)

    feed_mgr = read("includes/class-feed-manager.php")
    if "crb_sanitize_import_content_template" not in feed_mgr:
        fail("feed-manager save must use crb_sanitize_import_content_template")
    else:
        ok("feed save strips bundled preset")

    js = read("assets/js/admin.js")
    if "import_content_template" in js or "import-content-template" in js:
        fail("admin.js must not touch import content template field")
    else:
        ok("admin.js does not set content template")

    main_php = read("custom-rss-builder.php")
    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    if build:
        ok(f"CRB_BUILD_ID {build.group(1)}")
    else:
        fail("CRB_BUILD_ID missing")

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("All import template default checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
