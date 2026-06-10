#!/usr/bin/env python3
"""Static integration checks for CSS-only extract + post preview flow."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FAILURES: list[str] = []


def ok(msg: str) -> None:
    print(f"OK   {msg}")


def fail(msg: str, detail: str = "") -> None:
    line = f"FAIL {msg}"
    if detail:
        line += f" - {detail}"
    FAILURES.append(line)
    print(line)


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def css_bundle() -> str:
    parts = [read("includes/functions-css.php")]
    for name in (
        "functions-css-xpath.php",
        "functions-dom-core.php",
        "functions-css-slots.php",
        "functions-css-feed-config.php",
        "functions-css-discover-preview.php",
    ):
        parts.append(read(f"includes/{name}"))
    return "\n".join(parts)


def main() -> int:
    print("=== debug_integrated_flow ===\n")

    main_php = read("custom-rss-builder.php")
    m = re.search(r"define\s*\(\s*'CRB_VERSION'\s*,\s*'([^']+)'", main_php)
    ver = m.group(1) if m else "?"
    ok(f"CRB_VERSION {ver}")

    if "function crb_html_cache_enabled" in read("includes/functions-html-cache.php"):
        ok("HTML cache stub (always fresh fetch)")
    else:
        fail("functions-html-cache.php", "missing crb_html_cache_enabled stub")

    if "CRB_HTML_CACHE_ENABLED" in main_php:
        fail("HTML cache constant", "CRB_HTML_CACHE_ENABLED should be removed")
    else:
        ok("no CRB_HTML_CACHE_ENABLED constant")

    css = css_bundle()
    admin = read("admin/class-admin-page.php")
    js = read("assets/js/admin.js")
    fetcher = read("includes/class-html-fetcher.php")
    edit_feed = read("admin/views/edit-feed.php")

    for fn in (
        "crb_dom_load_html",
        "crb_dom_parse_root",
        "crb_hash_feed_css_config",
        "crb_extract_slot_value",
        "crb_html_cache_enabled",
        "crb_html_fetch_resolve_options",
    ):
        path = "includes/functions-html-cache.php" if fn.startswith("crb_html") else "includes/functions-css*.php"
        body = read(path) if fn.startswith("crb_html") else css
        if f"function {fn}" in body:
            ok(f"{path}: {fn}")
        else:
            fail(f"{path}: {fn}", "missing")

    if "class-html-parser.php" in main_php or (ROOT / "includes/class-html-parser.php").exists():
        fail("html parser removed", "still referenced or file exists")
    else:
        ok("HTML parser mode removed")

    if "crb_hash_feed_css_config( $effective_css )" in admin or "build_preview_data_from_post" in admin:
        ok("preview: uses current form config (no stale discover cache)")
    else:
        fail("preview: form config", "expected build_preview_data_from_post or hash check")

    if "crb_admin_feed" in admin and "ajax_handle_preview" in admin:
        ok("ajax: save/preview without full reload")
    else:
        fail("ajax admin feed", "missing crb_admin_feed handlers")

    if "refreshExtractPreview" in js and "scheduleExtractPreviewRefresh" in js:
        ok("admin.js: slot change refreshes extract preview")
    else:
        fail("admin.js: live preview refresh", "missing scheduleExtractPreviewRefresh")

    if js.count("applyScopePreviewToStep4(") > 0:
        fail("admin.js: legacy applyScopePreviewToStep4", "should be removed or unused")
    else:
        ok("admin.js: no legacy applyScopePreviewToStep4")

    if "crb-apply-scope-to-step4" in edit_feed:
        fail("edit-feed: legacy step4 apply button", "removed in slot-dropdown flow")
    else:
        ok("edit-feed: no legacy step4 apply button")

    if "function crb_is_usable_image_url" not in css:
        fail("css modules: crb_is_usable_image_url")
    else:
        ok("css modules: crb_is_usable_image_url")

    if "collect_image_urls_from_element" not in read("includes/class-css-extractor.php"):
        fail("extractor: popover/thumb image collection")
    else:
        ok("extractor: container image collection")

    if "crb-dom-root" in css:
        fail("css modules: dom id", "crb-dom-root should be crb-root")
    else:
        ok("css modules: unified crb-root wrapper")

    if "updateDiscoverButtonLabels" in js:
        ok("admin.js: scope-aware discover button labels")
    else:
        fail("admin.js: discover labels", "missing updateDiscoverButtonLabels")

    if "extraction_mode" in js or "togglePanels" in js:
        fail("admin.js: old HTML mode UI", "extraction_mode or togglePanels still present")
    else:
        ok("admin.js: no HTML extraction mode UI")

    if "RSSフィード用の割り当て" in read("admin/views/edit-feed.php"):
        fail("edit-feed: RSS mapping UI", "should be removed")
    else:
        ok("edit-feed: RSS mapping UI removed")

    if "crb_default_rss_mapping()" in read("includes/class-item-builder.php"):
        ok("item-builder: fixed RSS slot mapping")
    else:
        fail("item-builder: default mapping", "missing crb_default_rss_mapping()")

    if "match_count" in read("includes/functions-extract-candidates.php") and "crb-discover-match-count" in js:
        ok("③ candidates: match_count column wired")
    else:
        fail("match_count column", "missing in PHP or admin.js")

    if "crb_extract_slot_value" in read("includes/functions-extract-candidates.php"):
        ok("candidates use unified crb_extract_slot_value")
    else:
        fail("candidates extract", "expected crb_extract_slot_value")

    if "new Custom_RSS_Builder_RSS_Endpoint(" in read("includes/class-custom-rss-builder.php"):
        ok("bootstrap: RSS_Endpoint wired without html_parser")
    else:
        fail("bootstrap RSS wiring", "missing or wrong")

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("All integration checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
