#!/usr/bin/env python3
"""Feed pack export feature checks (no WordPress required)."""
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
    print("=== verify_feed_pack_export ===\n")

    main_php = read("custom-rss-builder.php")
    pack_php = read("includes/functions-feed-pack.php")
    admin_php = read("admin/class-admin-page.php")
    edit_feed = read("admin/views/edit-feed.php")
    admin_js = read("assets/js/admin.js")

    if "functions-feed-pack.php" not in main_php:
        fail("bootstrap requires functions-feed-pack.php")
    else:
        ok("bootstrap requires functions-feed-pack.php")

    if "function crb_export_feed_pack" not in pack_php:
        fail("crb_export_feed_pack missing")
    else:
        ok("crb_export_feed_pack present")

    if "function crb_feed_pack_to_json" not in pack_php:
        fail("crb_feed_pack_to_json missing")
    else:
        ok("crb_feed_pack_to_json present")

    if "pack_version" not in pack_php:
        fail("pack_version missing from feed-pack module")
    else:
        ok("pack_version defined")

    # export must not embed environment-specific keys in pack builder
    forbidden_in_pack = ("feed_id", "last_imported", "last_import_run", "category_id", "author_id")
    for key in forbidden_in_pack:
        if f"'{key}'" in pack_php or f'"{key}"' in pack_php:
            fail(f"feed-pack module references forbidden key {key!r}")
            break
    else:
        ok("feed-pack module avoids environment-specific keys")

    if "case 'export':" not in admin_php or "ajax_handle_export" not in admin_php:
        fail("admin ajax export handler missing")
    else:
        ok("admin ajax export handler wired")

    if "crb-export-feed-pack" not in edit_feed:
        fail("export button missing from edit-feed.php")
    elif "crb-copy-feed-pack" in edit_feed or "crb-download-feed-pack" in edit_feed:
        fail("legacy copy/download export buttons still in edit-feed.php")
    elif "crb-feed-pack-json" in edit_feed:
        fail("export JSON textarea still in edit-feed.php")
    elif "crb-feed-pack" not in edit_feed:
        fail("feed pack panel missing from edit-feed.php")
    else:
        ok("export UI: single button only in edit-feed.php")

    if "downloadFeedPackFile" not in admin_js:
        fail("downloadFeedPackFile missing from admin.js")
    elif "crb-copy-feed-pack" in admin_js or "copyFeedPackText" in admin_js:
        fail("legacy copy export handlers still in admin.js")
    else:
        ok("export triggers immediate file download in admin.js")

    db_reset_needles = ("license_reset", "feed_cleanup", "delete_option")
    for needle in db_reset_needles:
        if needle in pack_php or (needle in admin_php and "ajax_handle_export" in admin_php):
            section = admin_php.split("ajax_handle_export", 1)[-1][:800] if needle in admin_php else ""
            if needle in pack_php or needle in section:
                fail(f"export path must not reference DB reset {needle!r}")
                break
    else:
        ok("export path has no DB reset hooks")

    if "'export'" in admin_js and "AJAX_FEED_ACTIONS" in admin_js:
        m = re.search(r"AJAX_FEED_ACTIONS\s*=\s*\[([^\]]+)\]", admin_js)
        if m and ("'export'" in m.group(1) or '"export"' in m.group(1)):
            fail("export must not hook into form submit AJAX_FEED_ACTIONS")
        else:
            ok("export uses dedicated button handler (not form submit)")
    else:
        ok("export JS handler present")

    if "crb_action', 'export'" not in admin_js and "crb_action\", \"export\"" not in admin_js:
        if "export" not in admin_js:
            fail("export action missing from admin.js")
        else:
            ok("export fetch action in admin.js")
    else:
        ok("export fetch action in admin.js")

    m_build = re.search(r"define\s*\(\s*'CRB_BUILD_ID'\s*,\s*'([^']+)'\s*\)", main_php)
    if not m_build:
        fail("CRB_BUILD_ID missing")
    else:
        ok(f"CRB_BUILD_ID {m_build.group(1)}")

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("All feed pack export checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
