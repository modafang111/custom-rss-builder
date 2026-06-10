#!/usr/bin/env python3
"""Phase 4: functions-css.php 分割モジュールの静的検証。"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
INCLUDES = ROOT / "includes"

CSS_MODULES = (
    "functions-css-xpath.php",
    "functions-dom-core.php",
    "functions-css-slots.php",
    "functions-css-feed-config.php",
    "functions-css-discover-preview.php",
)

REQUIRED_BY_MODULE: dict[str, tuple[str, ...]] = {
    "functions-css-xpath.php": ("function crb_css_to_xpath", "function crb_css_segment_to_xpath"),
    "functions-dom-core.php": (
        "function crb_dom_load_html",
        "function crb_xpath_query",
        "function crb_collect_item_containers",
    ),
    "functions-css-slots.php": (
        "function crb_get_effective_slot_count",
        "function crb_extract_slot_value",
        "function crb_preset_review_import_template",
    ),
    "functions-css-feed-config.php": (
        "function crb_extract_items_from_html",
        "function crb_get_feed_css_config",
        "function crb_sanitize_css_config",
        "function crb_resolve_scope_element",
    ),
    "functions-css-discover-preview.php": (
        "function crb_build_discover_scope_preview",
        "function crb_dedupe_scope_item_previews",
    ),
}

LOADER_ONLY_IN_CSS = (
    "CRB_RECORD_PREVIEW_LIMIT",
    "CRB_RECORD_SLOT_COUNT",
    "CRB_DISCOVER_PREVIEW_SLOTS",
    "functions-css-xpath.php",
    "functions-css-slots.php",
)

MUST_NOT_BE_IN_CSS = (
    "function crb_css_to_xpath",
    "function crb_dom_load_html",
    "function crb_extract_slot_value",
    "function crb_get_feed_css_config",
    "function crb_build_discover_scope_preview",
    "function crb_scope_miss_message",
)


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def css_bundle() -> str:
    parts = [read("includes/functions-css.php")]
    for name in CSS_MODULES:
        parts.append(read(f"includes/{name}"))
    return "\n".join(parts)


def main() -> int:
    print("=== verify_css_module_split ===\n")
    fail = 0

    def ok(msg: str) -> None:
        print(f"OK   {msg}")

    def fail_msg(msg: str) -> None:
        nonlocal fail
        print(f"FAIL {msg}")
        fail += 1

    loader = read("includes/functions-css.php")
    main = read("custom-rss-builder.php")

    for name in CSS_MODULES:
        path = INCLUDES / name
        if not path.is_file():
            fail_msg(f"missing module: {name}")
            continue
        ok(f"module exists: {name}")

    for name, needles in REQUIRED_BY_MODULE.items():
        text = read(f"includes/{name}")
        for needle in needles:
            if needle not in text:
                fail_msg(f"{needle} missing from {name}")
            else:
                ok(f"{needle} in {name}")

    for needle in LOADER_ONLY_IN_CSS:
        if needle not in loader:
            fail_msg(f"loader missing: {needle}")
        else:
            ok(f"loader has {needle}")

    for needle in MUST_NOT_BE_IN_CSS:
        if needle in loader and needle.startswith("function "):
            fail_msg(f"implementation still in loader: {needle}")
        elif needle in loader:
            fail_msg(f"forbidden in loader: {needle}")
        else:
            ok(f"loader does not define {needle}")

    fn_re = re.compile(r"^function (crb_\w+)", re.M)
    seen: dict[str, str] = {}
    for name in CSS_MODULES:
        for fn in fn_re.findall(read(f"includes/{name}")):
            if fn in seen:
                fail_msg(f"duplicate function {fn} in {name} and {seen[fn]}")
            else:
                seen[fn] = name
    if seen:
        ok(f"{len(seen)} unique crb_* functions across split modules")

    bundle = css_bundle()
    legacy_checks = (
        ("preceding-sibling", "nth-child xpath parser"),
        ("a-zA-Z0-9-]*", "hyphenated tag in xpath"),
        ("preg_match( '/^\\*:(\\w[\\w-]*)$/u', $selector, $m )", "*:tag normalization"),
        ("function crb_is_usable_image_url", "crb_is_usable_image_url"),
    )
    for needle, label in legacy_checks:
        if needle not in bundle:
            fail_msg(f"{label} missing from css bundle")
        else:
            ok(f"{label} in css bundle")

    if "functions-css.php" not in main:
        fail_msg("bootstrap must require functions-css.php")
    else:
        ok("bootstrap requires functions-css.php loader")

    m = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main)
    if m:
        ok(f"CRB_BUILD_ID {m.group(1)}")

    slot_m = re.search(
        r"define\s*\(\s*'CRB_RECORD_SLOT_COUNT'\s*,\s*(\d+)\s*\)",
        loader,
    )
    if slot_m and slot_m.group(1) == "20":
        ok("CRB_RECORD_SLOT_COUNT = 20 (Pro max)")
    else:
        fail_msg(f"CRB_RECORD_SLOT_COUNT expected 20, got {slot_m.group(1) if slot_m else '?'}")

    print(f"\n{'FAIL' if fail else 'PASS'} ({fail} failures)")
    return 1 if fail else 0


if __name__ == "__main__":
    raise SystemExit(main())
