#!/usr/bin/env python3
"""Run verify_extract_flow.php when PHP is available; else basic xpath checks."""
from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP_SCRIPT = ROOT / "tools" / "verify_extract_flow.php"


def run_php() -> int:
    for exe in ("php", "php8.3", "php8.2", "php8.1"):
        try:
            proc = subprocess.run(
                [exe, str(PHP_SCRIPT)],
                cwd=str(ROOT),
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
                timeout=60,
            )
        except FileNotFoundError:
            continue
        out = (proc.stdout or "") + (proc.stderr or "")
        print(out, end="")
        return proc.returncode
    return -1


def css_sources_text() -> str:
    """Loader + Phase 4 split modules (functions-css.php is no longer monolithic)."""
    parts: list[str] = []
    includes = ROOT / "includes"
    loader = includes / "functions-css.php"
    parts.append(loader.read_text(encoding="utf-8"))
    for name in (
        "functions-css-xpath.php",
        "functions-dom-core.php",
        "functions-css-slots.php",
        "functions-css-feed-config.php",
        "functions-css-discover-preview.php",
    ):
        path = includes / name
        if path.is_file():
            parts.append(path.read_text(encoding="utf-8"))
    return "\n".join(parts)


def basic_checks() -> int:
    css = css_sources_text()
    ext = (ROOT / "includes" / "class-css-extractor.php").read_text(encoding="utf-8")
    fail = 0

    def ok(msg: str) -> None:
        print(f"OK   {msg}")

    def fail_msg(msg: str) -> None:
        nonlocal fail
        print(f"FAIL {msg}")
        fail += 1

    if "function crb_is_usable_image_url" not in css:
        fail_msg("crb_is_usable_image_url missing")
    else:
        ok("crb_is_usable_image_url in functions-css.php")

    if "preceding-sibling" not in css or ":nth-child" not in css:
        fail_msg("nth-child xpath parser missing in functions-css.php")
    else:
        ok("nth-child parser present")

    if "a-zA-Z0-9-]*" not in css:
        fail_msg("hyphenated tag name in xpath parser missing")
    else:
        ok("hyphenated tag name in xpath parser")

    if "crb_dom_load_html" not in ext:
        fail_msg("extract uses crb_dom_load_html")
    else:
        ok("extract uses crb_dom_load_html")

    if "collect_image_urls_from_element" not in ext:
        fail_msg("collect_image_urls_from_element missing")
    else:
        ok("collect_image_urls_from_element present")

    if "function crb_extract_slot_value" not in css:
        fail_msg("crb_extract_slot_value missing (③⑤ unified extract)")
    else:
        ok("crb_extract_slot_value present")

    if "function crb_collect_item_containers" not in css:
        fail_msg("crb_collect_item_containers missing")
    else:
        ok("crb_collect_item_containers present")

    if "function crb_xpath_query" not in css:
        fail_msg("crb_xpath_query helper missing")
    else:
        ok("crb_xpath_query helper present")

    if "preg_match( '/^\\*:(\\w[\\w-]*)$/u', $selector, $m )" not in css:
        fail_msg("*:tag normalization missing in functions-css.php")
    else:
        ok("*:tag normalization in crb_css_to_xpath / sanitize")

    disc = (ROOT / "includes" / "class-element-discovery.php").read_text(encoding="utf-8")
    if "false === $nodes" in disc:
        fail_msg("class-element-discovery still uses unsafe false === $nodes")
    else:
        ok("element-discovery uses safe xpath checks")

    if "crb_xpath_query" not in disc:
        fail_msg("class-element-discovery missing crb_xpath_query")
    else:
        ok("element-discovery uses crb_xpath_query")

    cand = (ROOT / "includes" / "functions-extract-candidates.php").read_text(encoding="utf-8")
    if "crb_extract_slot_value" not in cand:
        fail_msg("candidates must use crb_extract_slot_value")
    else:
        ok("candidates use unified extract")

    js = (ROOT / "assets" / "js" / "admin.js").read_text(encoding="utf-8")
    if "scrollIntoView" in js:
        fail_msg("admin.js should not use scrollIntoView")
    else:
        ok("admin.js has no auto scroll")

    if "crb_admin_feed" not in js:
        fail_msg("ajax feed actions missing")
    else:
        ok("ajax feed actions present")

    main = (ROOT / "custom-rss-builder.php").read_text(encoding="utf-8")
    m = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main)
    if not m:
        fail_msg("CRB_BUILD_ID missing")
    else:
        ok(f"CRB_BUILD_ID {m.group(1)}")

    if "colCount" not in js or "crb-discover-match-count" not in js:
        fail_msg("admin.js missing match_count column UI")
    else:
        ok("admin.js has match_count column")

    if "crb_extract_candidates_attach_match_counts" not in cand:
        fail_msg("candidates PHP missing match_count attach")
    else:
        ok("candidates PHP attaches match_count")

    dom_scope = (ROOT / "includes" / "functions-dom-scope.php").read_text(encoding="utf-8")
    if "function crb_scope_miss_message" not in dom_scope:
        fail_msg("crb_scope_miss_message missing from functions-dom-scope.php")
    else:
        ok("crb_scope_miss_message in functions-dom-scope.php")

    if "function crb_scope_miss_message" in css:
        fail_msg("crb_scope_miss_message must not remain in functions-css.php")
    else:
        ok("scope miss message not in functions-css.php")

    for php in (ROOT / "includes").glob("*.php"):
        if "getElementsByClassName" in php.read_text(encoding="utf-8"):
            fail_msg(f"getElementsByClassName forbidden: {php.name}")
            break
    else:
        ok("no getElementsByClassName in includes/*.php")

    return fail


def main() -> int:
    print("=== verify_extract_flow ===\n")
    rc = run_php()
    if rc >= 0:
        return rc
    print("(PHP not found - running static checks only)\n")
    return 1 if basic_checks() else 0


if __name__ == "__main__":
    raise SystemExit(main())
