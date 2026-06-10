#!/usr/bin/env python3
"""Constructor / bootstrap consistency checks (no WordPress required)."""
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
    print("=== verify_plugin_bootstrap ===\n")

    main_php = read("custom-rss-builder.php")
    bootstrap = read("includes/class-custom-rss-builder.php")
    rss = read("public/class-rss-endpoint.php")
    importer = read("includes/class-post-importer.php")
    fetcher = read("includes/class-html-fetcher.php")

    if "class-html-parser.php" in main_php:
        fail("main still requires class-html-parser.php")
    else:
        ok("no html-parser in bootstrap")

    if re.search(r"function __construct\([^)]*\$html_parser", rss):
        fail("RSS_Endpoint still requires html_parser")
    elif "function __construct( $feed_manager, $html_fetcher, $rss_generator )" in rss:
        ok("RSS_Endpoint 3-arg constructor")
    else:
        fail("RSS_Endpoint constructor signature unexpected")

    if re.search(r"function __construct\([^)]*\$html_parser", importer):
        fail("Post_Importer still requires html_parser")
    elif "function __construct( $feed_manager, $html_fetcher, $item_builder" in importer:
        ok("Post_Importer constructor matches bootstrap")
    else:
        fail("Post_Importer constructor signature unexpected")

    if "new Custom_RSS_Builder_RSS_Endpoint(" in bootstrap and bootstrap.count("$this->rss_generator") >= 1:
        ok("bootstrap wires RSS_Endpoint with rss_generator")
    else:
        fail("bootstrap RSS_Endpoint wiring")

    if "new Custom_RSS_Builder_Content_Template()" in bootstrap:
        ok("bootstrap shares Content_Template with Post_Importer")
    else:
        fail("bootstrap Content_Template wiring")

    if "set_transient" in fetcher or "get_transient" in fetcher:
        fail("HTML fetcher still uses transients")
    else:
        ok("HTML fetcher has no transient cache")

    css_bundle = read("includes/functions-css.php")
    for mod in (
        "functions-css-xpath.php",
        "functions-dom-core.php",
        "functions-css-slots.php",
        "functions-css-feed-config.php",
        "functions-css-discover-preview.php",
    ):
        css_bundle += read(f"includes/{mod}")

    if "crb_parse_client_extract_rows" in css_bundle:
        fail("dead crb_parse_client_extract_rows still present")
    else:
        ok("client extract cache removed")

    if "crb_extract_slot_value" in css_bundle:
        ok("unified crb_extract_slot_value present")
    else:
        fail("crb_extract_slot_value missing")

    m = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    if m:
        ok(f"CRB_BUILD_ID {m.group(1)}")
    else:
        fail("CRB_BUILD_ID missing")

    edit_feed = read("admin/views/edit-feed.php")
    import_partial = read("admin/views/partials/feed-import-settings.php")
    feed_mgr = read("includes/class-feed-manager.php")
    admin_views = edit_feed + import_partial

    if "crb_preset_review_import_template" in admin_views:
        fail("admin views must not reference crb_preset_review_import_template")
    else:
        ok("admin views do not call bundled import preset")

    if re.search(
        r'id="crb-import-content-template"[^>]*placeholder\s*=',
        import_partial,
        re.I,
    ):
        fail("import content textarea must not use placeholder preset")
    else:
        ok("import content textarea has no placeholder preset")

    if "crb_import_content_template_for_ui" not in edit_feed:
        fail("edit-feed must normalize stored content_template for UI")
    else:
        ok("edit-feed uses crb_import_content_template_for_ui")

    if "crb_sanitize_import_content_template" not in feed_mgr:
        fail("feed-manager must strip bundled preset on save")
    else:
        ok("feed-manager strips bundled preset on save")

    if "'content_template'    => ''" not in feed_mgr:
        fail("default_import_settings content_template must be empty")
    else:
        ok("feed default content_template is empty")

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("All bootstrap checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
