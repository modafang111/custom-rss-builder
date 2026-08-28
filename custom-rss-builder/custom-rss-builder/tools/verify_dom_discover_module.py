#!/usr/bin/env python3
"""Phase 3: functions-dom-discover.php モジュール分離の静的検証。"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read_rel(*parts: str) -> str:
    return ROOT.joinpath(*parts).read_text(encoding="utf-8")


def main() -> int:
    print("=== verify_dom_discover_module ===\n")
    fail = 0

    def ok(msg: str) -> None:
        print(f"OK   {msg}")

    def fail_msg(msg: str) -> None:
        nonlocal fail
        print(f"FAIL {msg}")
        fail += 1

    discover = read_rel("includes", "functions-dom-discover.php")
    main_php = read_rel("custom-rss-builder.php")
    admin = read_rel("admin", "class-admin-page.php")
    disc_cls = read_rel("includes", "class-element-discovery.php")
    ext = read_rel("includes", "class-css-extractor.php")

    for fn in (
        "function crb_discover_prepare_ajax",
        "function crb_discover_dom_load",
        "function crb_discover_resolve_scope",
        "function crb_discover_class_scan_limit",
        "function crb_discover_dom_load_for_flow",
        "CRB_DISCOVER_CLASS_SCAN_LIMIT",
    ):
        if fn not in discover:
            fail_msg(f"missing in functions-dom-discover.php: {fn}")
        else:
            ok(f"{fn} in functions-dom-discover.php")

    if "functions-dom-discover.php" not in main_php:
        fail_msg("bootstrap must require functions-dom-discover.php")
    else:
        ok("functions-dom-discover.php required from bootstrap")

    if "new Custom_RSS_Builder_Element_Discovery( true )" not in admin:
        fail_msg("ajax_discover_elements must use lightweight Element_Discovery")
    else:
        ok("ajax uses Element_Discovery(true)")

    if "crb_build_discover_scope_preview(" not in admin or "true," not in admin:
        # Called with use_discover_dom=true (may pass form probe config as 7th arg).
        fail_msg("scope preview must pass use_discover_dom=true in ajax")
    elif "crb_css_config_from_discover_request" not in admin:
        fail_msg("ajax should build form probe config for scope preview")
    else:
        ok("ajax scope preview uses discover dom + form probe")

    if "crb_build_extract_candidates( $html, $scope, $item_sel, $url, true )" not in admin:
        fail_msg("extract candidates must pass use_discover_dom=true in ajax")
    else:
        ok("ajax extract candidates uses discover dom")

    if "crb_discover_prepare_ajax" not in admin:
        fail_msg("ajax discover should call crb_discover_prepare_ajax")
    else:
        ok("ajax calls crb_discover_prepare_ajax")

    if "private $lightweight_dom" not in disc_cls:
        fail_msg("Element_Discovery missing lightweight_dom flag")
    else:
        ok("Element_Discovery has lightweight_dom")

    if "crb_discover_dom_load" not in disc_cls:
        fail_msg("Element_Discovery load_dom must use crb_discover_dom_load")
    else:
        ok("Element_Discovery uses crb_discover_dom_load")

    if "crb_discover_class_scan_limit" not in disc_cls:
        fail_msg("collect_block_groups must use class scan limit in lightweight mode")
    else:
        ok("block groups scan limit in lightweight mode")

    if "crb_discover_dom_load" in ext:
        fail_msg("css-extractor must not use discover dom")
    else:
        ok("css-extractor unchanged (crb_dom_load_html only)")

    m = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    if m:
        ok(f"CRB_BUILD_ID {m.group(1)}")

    print(f"\n{'FAIL' if fail else 'PASS'} ({fail} failures)")
    return 1 if fail else 0


if __name__ == "__main__":
    raise SystemExit(main())
