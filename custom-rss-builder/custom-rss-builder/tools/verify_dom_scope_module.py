#!/usr/bin/env python3
"""Phase 2: functions-dom-scope.php モジュール分離の静的検証。"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
INCLUDES = ROOT / "includes"


def read_rel(*parts: str) -> str:
    return (ROOT.joinpath(*parts)).read_text(encoding="utf-8")


def main() -> int:
    print("=== verify_dom_scope_module ===\n")
    fail = 0

    def ok(msg: str) -> None:
        print(f"OK   {msg}")

    def fail_msg(msg: str) -> None:
        nonlocal fail
        print(f"FAIL {msg}")
        fail += 1

    scope = read_rel("includes", "functions-dom-scope.php")
    css = read_rel("includes", "functions-css.php")
    main = read_rel("custom-rss-builder.php")

    required_in_scope = (
        "function crb_scope_dom_load",
        "function crb_scope_query_elements",
        "function crb_scope_resolve_element",
        "function crb_scope_explain_miss",
        "function crb_scope_miss_message",
        "function crb_scope_suggestions_for_url",
        "function crb_scope_suggestions(",
    )
    for fn in required_in_scope:
        if fn not in scope:
            fail_msg(f"missing in functions-dom-scope.php: {fn}")
        else:
            ok(f"{fn} in functions-dom-scope.php")

    if "function crb_scope_miss_message" in css:
        fail_msg("crb_scope_miss_message still in functions-css.php")
    else:
        ok("crb_scope_miss_message removed from functions-css.php")

    if "function crb_scope_suggestions" in css:
        fail_msg("crb_scope_suggestions still in functions-css.php")
    else:
        ok("crb_scope_suggestions removed from functions-css.php")

    if "getElementsByClassName" in scope:
        fail_msg("getElementsByClassName in functions-dom-scope.php")
    else:
        ok("no getElementsByClassName in scope module")

    if "dlsite.com" in scope.lower() or "dlsite" in scope.lower():
        fail_msg("functions-dom-scope.php must not mention DLsite")
    else:
        ok("no DLsite-specific hints in scope module")

    bad = [p.name for p in INCLUDES.glob("*.php") if "getElementsByClassName" in p.read_text(encoding="utf-8")]
    if bad:
        fail_msg(f"getElementsByClassName in: {', '.join(bad)}")
    else:
        ok("no getElementsByClassName in includes/*.php")

    if "functions-dom-scope.php" not in main:
        fail_msg("custom-rss-builder.php does not require functions-dom-scope.php")
    else:
        ok("functions-dom-scope.php required from bootstrap")

    m = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main)
    if m:
        ok(f"CRB_BUILD_ID {m.group(1)}")

    disc = read_rel("includes", "class-element-discovery.php")
    if "crb_scope_miss_message( $html, $page_url, $scope_selector, $xpath, $root )" not in disc:
        fail_msg("element-discovery should pass xpath/root to crb_scope_miss_message")
    else:
        ok("element-discovery uses crb_scope_miss_message with xpath/root")

    cand = read_rel("includes", "functions-extract-candidates.php")
    if "crb_scope_miss_message( $html, $base_url, $scope_selector, $xpath, $root )" not in cand:
        fail_msg("extract-candidates should pass xpath/root to crb_scope_miss_message")
    else:
        ok("extract-candidates uses crb_scope_miss_message with xpath/root")

    admin = read_rel("admin", "class-admin-page.php")
    if "crb_scope_dom_load" not in admin:
        fail_msg("admin ajax_discover_scope_html should use crb_scope_dom_load")
    else:
        ok("admin scope ajax uses crb_scope_dom_load")

    print(f"\n{'FAIL' if fail else 'PASS'} ({fail} failures)")
    return 1 if fail else 0


if __name__ == "__main__":
    raise SystemExit(main())
