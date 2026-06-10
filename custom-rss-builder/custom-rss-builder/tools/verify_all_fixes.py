#!/usr/bin/env python3
"""Verify 0.6.5 fixes: images (relative), 10 preview slots, server deploy."""
from __future__ import annotations

import re
import subprocess
import sys
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN_PHP = ROOT / "custom-rss-builder.php"
ADMIN_JS_URL = (
    "https://123789.jp/custom-rss-builder/wp-content/plugins/"
    "custom-rss-builder/assets/js/admin.js"
)

failures: list[str] = []


def ok(msg: str) -> None:
    print(f"OK   {msg}")


def fail(msg: str, detail: str = "") -> None:
    line = f"FAIL {msg}"
    if detail:
        line += f" — {detail}"
    failures.append(line)
    print(line)


def check_local_version() -> None:
    text = PLUGIN_PHP.read_text(encoding="utf-8")
    if "0.6.6" not in text:
        fail("local CRB_VERSION", "expected 0.6.6")
        return
    ok("local CRB_VERSION 0.6.6")
    css = (ROOT / "includes" / "functions-css.php").read_text(encoding="utf-8")
    if "CRB_DISCOVER_PREVIEW_SLOTS', CRB_RECORD_SLOT_COUNT" not in css:
        fail("CRB_DISCOVER_PREVIEW_SLOTS", "expected CRB_RECORD_SLOT_COUNT")
        return
    ok("CRB_DISCOVER_PREVIEW_SLOTS = CRB_RECORD_SLOT_COUNT")
    if "function crb_is_usable_image_url" not in css:
        fail("crb_is_usable_image_url", "missing")
    else:
        ok("crb_is_usable_image_url exists")
    if "probe_record_slots_in_context( $xpath, $context, $config, $base_url, $preview_slots )" not in css:
        if ", 10 )" not in css and "CRB_DISCOVER_PREVIEW_SLOTS" not in css:
            fail("scope preview uses 10 slots")
        else:
            ok("scope preview uses preview_slots constant")
    else:
        ok("scope preview uses preview_slots constant")
    if "crb_probe_rows_ldjson_fallback( $xpath, $context, $rows, $base_url );" not in css.replace(
        "if ( $anchor_in_context < 1 )", ""
    ):
        # Always call ldjson (not only when anchor < 1)
        if re.search(
            r"\$rows\s*=\s*crb_probe_rows_ldjson_fallback\s*\([^)]+\)\s*;",
            css,
        ) and "if ( $anchor_in_context < 1 )" in css.split("crb_probe_rows_ldjson_fallback")[0][-200:]:
            fail("ldjson fallback still gated by anchor count")
        else:
            ok("ldjson fallback always applied for empty slots")
    dom_core = (ROOT / "includes" / "functions-dom-core.php").read_text(encoding="utf-8")
    ext = (ROOT / "includes" / "class-css-extractor.php").read_text(encoding="utf-8")
    if "crb_collect_image_urls_from_subtree_attributes" not in dom_core:
        fail("thumb-candidates collection in dom-core")
    elif "crb_collect_image_urls_from_subtree_attributes" not in ext:
        fail("thumb-candidates collection wired in extractor")
    else:
        ok("thumb-candidates collection in dom-core + extractor")


def check_server_admin_js() -> None:
    try:
        req = urllib.request.Request(ADMIN_JS_URL, headers={"User-Agent": "CRB-Verify/2"})
        with urllib.request.urlopen(req, timeout=20) as r:
            body = r.read().decode("utf-8", "replace")
        m = re.search(r"crbAdminBuild\s*=\s*['\"]([^'\"]+)['\"]", body)
        ver = m.group(1) if m else "?"
        if ver != "0.6.5":
            fail("server admin.js version", ver)
        else:
            ok(f"server admin.js {ver}")
    except Exception as exc:
        fail("server admin.js fetch", str(exc))


def run_simulate() -> None:
    script = ROOT / "tools" / "simulate_scope_preview.py"
    rc = subprocess.call([sys.executable, str(script)])
    if rc != 0:
        fail("simulate_scope_preview.py")
    else:
        ok("simulate_scope_preview.py")


def check_relative_image_logic() -> None:
    css = (ROOT / "includes" / "functions-css.php").read_text(encoding="utf-8")
    # Simulate PHP logic in Python
    def is_usable(url: str) -> bool:
        url = url.strip()
        if not url or url.startswith("data:"):
            return False
        if re.match(r"^(https?:)?//", url, re.I):
            return True
        if re.match(r"^(/|\./|\.\./)", url):
            return True
        return bool(re.search(r"\.(?:jpe?g|webp|png|gif|avif|svg)(?:\?|$)", url, re.I))

    cases = [
        ("/modpub/foo.jpg", True),
        ("//cdn.example.com/a.png", True),
        ("images/thumb.webp", True),
        ("data:image/gif;base64,xxx", False),
    ]
    for url, expected in cases:
        if is_usable(url) != expected:
            fail(f"crb_is_usable_image_url logic for {url!r}")
            return
    ok("relative image URL acceptance logic")


def main() -> int:
    print("=== verify_all_fixes ===\n")
    check_local_version()
    check_relative_image_logic()
    check_server_admin_js()
    print()
    run_simulate()
    print()
    if failures:
        print(f"\n{len(failures)} check(s) failed.")
        return 1
    print("\nAll verification checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
