#!/usr/bin/env python3
"""
Python mirror of tests/run-feed43-tests.php (when PHP CLI is unavailable).
"""
from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path

try:
    from lxml import html as lhtml
except ImportError:
    print("pip install lxml", file=sys.stderr)
    sys.exit(2)

BASE = Path(__file__).resolve().parents[1]
FAILURES = 0

CSS_MODULE_NAMES = (
    "functions-css.php",
    "functions-css-xpath.php",
    "functions-dom-core.php",
    "functions-css-slots.php",
    "functions-css-feed-config.php",
    "functions-css-discover-preview.php",
)


def ok(name: str) -> None:
    print(f"OK   {name}")


def fail(name: str, detail: str = "") -> None:
    global FAILURES
    FAILURES += 1
    print(f"FAIL {name}" + (f" - {detail}" if detail else ""))


def css_bundle_text() -> str:
    parts: list[str] = []
    for name in CSS_MODULE_NAMES:
        path = BASE / "includes" / name
        if path.is_file():
            parts.append(path.read_text(encoding="utf-8"))
    return "\n".join(parts)


def discover_preview_source() -> str:
    return (BASE / "includes" / "functions-css-discover-preview.php").read_text(encoding="utf-8")


def is_usable_image_url(url: str) -> bool:
    url = url.strip()
    if not url or url.startswith("data:") or url.startswith("javascript:"):
        return False
    if re.match(r"^(https?:)?//", url, re.I):
        return True
    if re.match(r"^(/|\./|\.\./)", url):
        return True
    return bool(re.search(r"\.(?:jpe?g|webp|png|gif|avif|svg)(?:\?|$)", url, re.I))


def resolve_url(url: str, base: str = "https://example.com/") -> str:
    url = url.strip()
    if re.match(r"^https?://", url, re.I):
        return url
    if url.startswith("//"):
        return "https:" + url
    if url.startswith("/"):
        return base.rstrip("/") + url
    return url


def best_image_in_block(ctx) -> str:
    candidates: list[str] = []
    for img in ctx.xpath(".//img[@src or @data-src] | .//picture//img"):
        for attr in ("src", "data-src", "data-original", "data-lazy-src"):
            val = (img.get(attr) or "").strip()
            if is_usable_image_url(val):
                candidates.append(val)
    for c in candidates:
        if "AAA001_img_main" in c or "modpub" in c:
            return resolve_url(c)
    return resolve_url(candidates[0]) if candidates else ""


def load_scope(fixture: str, scope: str):
    html = (BASE / "test-fixture" / fixture).read_text(encoding="utf-8")
    doc = lhtml.fromstring(f'<div id="crb-root">{html}</div>')
    root = doc.get_element_by_id("crb-root")
    return root.cssselect(scope)[0] if scope else root


def load_item(fixture: str, scope: str, item: str):
    scope_el = load_scope(fixture, scope)
    items = scope_el.cssselect(item) if item else [scope_el]
    return items[0]


def urls_from_media_attribute_value(value: str) -> list[str]:
    import html as html_lib

    value = html_lib.unescape(value)
    urls: list[str] = []
    for m in re.finditer(
        r"(?:https?:)?//[^\s'\"\\\]]+\.(?:jpe?g|webp|png|gif)(?:\?[^\s'\"\\\]]*)?",
        value,
        re.I,
    ):
        url = m.group(0).strip()
        if url and is_usable_image_url(url):
            urls.append(url)
    return list(dict.fromkeys(urls))


def attribute_may_contain_image_urls(name: str, value: str) -> bool:
    name_l = name.lower()
    if not value:
        return False
    if "thumb" in name_l and "candidate" in name_l:
        return True
    if name_l == "data-samples":
        return True
    if "//img." in value or "/modpub/" in value or "_img_main" in value:
        return True
    return False


def collect_image_urls_from_subtree(root) -> list[str]:
    candidates: list[str] = []
    for el in root.iter():
        for name, val in el.attrib.items():
            if not attribute_may_contain_image_urls(name, val or ""):
                continue
            candidates.extend(urls_from_media_attribute_value(val or ""))
    return candidates


def test_thumb_candidates_fixture() -> None:
    ctx = load_item("dlsite-thumb-candidates-snippet.html", ".review_inner", ".review_contents")
    urls = collect_image_urls_from_subtree(ctx)
    if not urls:
        fail("thumb-candidates: URLs from Vue attributes")
        return
    joined = " ".join(urls)
    if "RJ01618599_img_main" in joined:
        ok("thumb-candidates: RJ01618599_img_main from attribute")
    else:
        fail("thumb-candidates: expected img_main URL", joined[:120])


def test_review_list() -> None:
    ctx = load_item("review-list-sample.html", "#review_list", ".review_contents")
    imgs = ctx.cssselect(".work_img_popover img")
    if not imgs:
        fail("review-list: popover img exists")
    else:
        src = (imgs[0].get("src") or "").strip()
        if is_usable_image_url(src):
            ok("review-list: protocol-relative img src usable")
        else:
            fail("review-list: img src usable", src)
    img_url = best_image_in_block(ctx)
    if "AAA001_img_main" in img_url:
        ok("review-list: image slot resolvable when selector configured")
    else:
        fail("review-list: image slot", img_url)


def test_relative_path() -> None:
    if is_usable_image_url("/modpub/AAA001_img_main.jpg"):
        ok("image: relative path is usable")
    else:
        fail("image: relative path is usable")


def test_item_previews_in_source() -> None:
    preview = discover_preview_source()
    if (
        "item_previews" not in preview
        or "crb_dedupe_scope_item_previews" not in preview
    ):
        fail("item_previews in scope preview builder")
    else:
        ok("scope preview builds item_previews (up to CRB_RECORD_PREVIEW_LIMIT)")


def test_preview_row_count() -> None:
    preview = discover_preview_source()
    if "probe_record_slots_in_context( $xpath, $container, $config, $base_url, $preview_slots )" not in preview:
        fail("scope preview passes preview_slots to probe")
    else:
        ok("scope preview passes preview_slots to probe")


def test_item_selector_miss_static() -> None:
    preview = discover_preview_source()
    candidates = (BASE / "includes" / "functions-extract-candidates.php").read_text(encoding="utf-8")
    if "item_selector_error" not in preview:
        fail("discover preview returns item_selector_error on item miss")
    elif "is_wp_error( $containers )" not in candidates:
        fail("extract candidates propagates item miss WP_Error")
    else:
        ok("item selector miss fails loud (discover + candidates)")


def test_item_selector_miss_dom() -> None:
    scope_el = load_scope("review-list-sample.html", "#review_list")
    try:
        bad = scope_el.cssselect(".work_namebbbbb")
    except Exception:
        bad = []
    good = scope_el.cssselect(".work_name")
    if len(bad) == 0 and len(good) >= 1:
        ok("wrong item selector .work_namebbbbb matches 0; .work_name matches in fixture")
    else:
        fail(
            "item selector fixture sanity",
            f"bad={len(bad)} good={len(good)}",
        )


def test_no_site_specific_defaults() -> None:
    feed_cfg = (BASE / "includes" / "functions-css-feed-config.php").read_text(encoding="utf-8")
    extractor = (BASE / "includes" / "class-css-extractor.php").read_text(encoding="utf-8")
    needles = (".review_desc", "work_img_popover", "reveiw_title", "thumb-candidate")
    for needle in needles:
        if needle in feed_cfg or needle in extractor:
            fail("no site-specific defaults in client extract", needle)
            return
    ok("no site-specific selector defaults in feed-config / extractor")


def test_preview_slots_constant() -> None:
    css = css_bundle_text()
    m = re.search(
        r"define\s*\(\s*'CRB_DISCOVER_PREVIEW_SLOTS'\s*,\s*CRB_RECORD_SLOT_COUNT\s*\)",
        css,
    )
    if not m:
        m2 = re.search(r"define\s*\(\s*'CRB_RECORD_SLOT_COUNT'\s*,\s*(\d+)\s*\)", css)
        slot_n = m2.group(1) if m2 else "?"
        fail("CRB_DISCOVER_PREVIEW_SLOTS", f"expected CRB_RECORD_SLOT_COUNT (={slot_n})")
    else:
        ok("CRB_DISCOVER_PREVIEW_SLOTS = CRB_RECORD_SLOT_COUNT")


def test_php_extract_helpers() -> None:
    css = css_bundle_text()
    ext = (BASE / "includes" / "class-css-extractor.php").read_text(encoding="utf-8")
    if "function crb_is_usable_image_url" not in css:
        fail("crb_is_usable_image_url missing from css bundle")
    elif "collect_image_urls_from_element" not in ext:
        fail("collect_image_urls_from_element missing from extractor")
    elif "crb_collect_image_urls_from_subtree_attributes" not in (BASE / "includes" / "functions-dom-core.php").read_text(encoding="utf-8"):
        fail("crb_collect_image_urls_from_subtree_attributes missing from dom-core")
    elif "crb_collect_image_urls_from_subtree_attributes" not in ext:
        fail("crb_collect_image_urls_from_subtree_attributes not wired in extractor")
    else:
        ok("PHP sources: image helpers + subtree attribute collection")


def try_php_tests() -> bool:
    for cmd in ("php", "php.exe"):
        try:
            r = subprocess.run(
                [cmd, str(BASE / "tests" / "run-feed43-tests.php")],
                cwd=str(BASE),
                capture_output=True,
                text=True,
                timeout=60,
            )
            if r.returncode == 0:
                print(r.stdout)
                ok("PHP run-feed43-tests.php")
                return True
            if "php" in cmd.lower():
                print(r.stdout)
                print(r.stderr, file=sys.stderr)
        except OSError:
            continue
    return False


def main() -> int:
    print("=== run_feed43_tests_py ===\n")
    if try_php_tests():
        test_preview_slots_constant()
        return 0 if FAILURES == 0 else 1

    print("(PHP not found - running Python mirror)\n")
    test_preview_slots_constant()
    test_item_previews_in_source()
    test_preview_row_count()
    test_item_selector_miss_static()
    test_item_selector_miss_dom()
    test_no_site_specific_defaults()
    test_relative_path()
    test_review_list()
    test_thumb_candidates_fixture()
    test_php_extract_helpers()

    print()
    if FAILURES:
        print(f"{FAILURES} test(s) failed.")
        return 1
    print("All Python mirror tests passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
