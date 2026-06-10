#!/usr/bin/env python3
"""Feed pack import feature checks (no WordPress required)."""
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
    print("=== verify_feed_pack_import ===\n")

    main_php = read("custom-rss-builder.php")
    pack_php = read("includes/functions-feed-pack.php")
    admin_php = read("admin/class-admin-page.php")
    edit_feed = read("admin/views/edit-feed.php")
    admin_js = read("assets/js/admin.js")

    for fn in (
        "crb_parse_feed_pack_json",
        "crb_validate_feed_pack",
        "crb_feed_pack_to_form_fields",
    ):
        if f"function {fn}" not in pack_php:
            fail(f"{fn} missing")
        else:
            ok(f"{fn} present")

    if "crb-import-feed-pack" not in edit_feed:
        fail("import button missing from edit-feed.php")
    elif edit_feed.count("設定をインポート") > 1:
        fail("multiple import buttons in edit-feed.php")
    elif "crb-import-feed-pack-file" not in edit_feed:
        fail("hidden file input missing from edit-feed.php")
    else:
        ok("import UI: single button + hidden file input")

    if "applyFeedPackToForm" not in admin_js:
        fail("applyFeedPackToForm missing from admin.js")
    elif "import_pack" not in admin_js:
        fail("import_pack AJAX action missing from admin.js")
    else:
        ok("import applies sanitized fields via AJAX in admin.js")

    if "case 'import_pack':" not in admin_php or "ajax_handle_import_pack" not in admin_php:
        fail("admin ajax import_pack handler missing")
    else:
        ok("admin ajax import_pack handler wired")

    if "save_feed" in pack_php or "delete_feed" in pack_php or "update_option" in pack_php:
        fail("feed-pack import module must not write to DB")
    else:
        ok("feed-pack import module does not write to DB")

    import_section = admin_php.split("ajax_handle_import_pack", 1)[-1][:1200]
    db_reset_needles = ("license_reset", "feed_cleanup", "delete_option", "delete_feed")
    for needle in db_reset_needles:
        if needle in import_section:
            fail(f"import path must not reference DB reset {needle!r}")
            break
    else:
        ok("import path has no DB reset hooks")

    if "'import_pack'" in admin_js and "AJAX_FEED_ACTIONS" in admin_js:
        m = re.search(r"AJAX_FEED_ACTIONS\s*=\s*\[([^\]]+)\]", admin_js)
        if m and ("'import_pack'" in m.group(1) or '"import_pack"' in m.group(1)):
            fail("import_pack must not hook into form submit AJAX_FEED_ACTIONS")
        else:
            ok("import uses dedicated button handler (not form submit)")
    else:
        ok("import JS handler present")

    if "crb_export_feed_pack" not in pack_php:
        fail("export helper missing for symmetry check")
    elif "crb_sanitize_css_config" not in pack_php:
        fail("import must sanitize css via crb_sanitize_css_config")
    else:
        ok("import reuses export/sanitize helpers")

    m_build = re.search(r"define\s*\(\s*'CRB_BUILD_ID'\s*,\s*'([^']+)'\s*\)", main_php)
    if not m_build:
        fail("CRB_BUILD_ID missing")
    else:
        ok(f"CRB_BUILD_ID {m_build.group(1)}")

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("All feed pack import checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
