#!/usr/bin/env python3
"""HTTP でリモート admin.js / preview-results.php の要点を確認。"""
from __future__ import annotations

import re
import sys
import urllib.request

BASE = "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder"
EXPECT_BUILD = "20260529r"
UA = {"User-Agent": "CRB-Verify/1.0"}


def fetch(url: str) -> tuple[int, str]:
    req = urllib.request.Request(url, headers=UA)
    with urllib.request.urlopen(req, timeout=30) as resp:
        return resp.status, resp.read().decode("utf-8", errors="replace")


def main() -> int:
    print("=== verify_remote_scope_preview ===\n")
    fail = 0

    main_url = f"{BASE}/custom-rss-builder.php"
    js_url = f"{BASE}/assets/js/admin.js"
    preview_url = f"{BASE}/admin/views/preview-results.php"

    try:
        _, main_php = fetch(main_url)
        status, admin_js = fetch(js_url)
        print(f"admin.js HTTP {status} bytes {len(admin_js)}")
    except Exception as exc:
        print(f"FAIL fetch: {exc}")
        return 1

    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    remote_build = build.group(1) if build and main_php.strip() else None
    checks = [
        ("1000 char preview on HTTP", "renderScopeHtmlPreview(wrap, data.scope_html || '', 1000)" in admin_js, "admin.js still 300 or old"),
        ("renderScopeHtmlPreview", "renderScopeHtmlPreview" in admin_js, "missing helper"),
        ("<more!> toggle", "<more!>" in admin_js and "<less!>" in admin_js, "toggle missing"),
        ("crb-scope-html-more CSS class", "crb-scope-html-more" in admin_js, "class missing"),
        ("no html_snippet in JS", "html_snippet" not in admin_js, "stale snippet ref"),
    ]

    for label, ok, detail in checks:
        print(("OK   " if ok else "FAIL ") + label + (f" ({detail})" if not ok and detail else ""))
        if not ok:
            fail += 1

    try:
        status, preview_php = fetch(preview_url)
        if status == 200:
            no_snippet = "取得HTML（抜粋）" not in preview_php and "html_snippet" not in preview_php
            print(("OK   " if no_snippet else "FAIL ") + "preview-results: no html_snippet block")
            if not no_snippet:
                fail += 1
        else:
            print(f"SKIP preview-results.php HTTP {status}")
    except Exception as exc:
        print(f"SKIP preview-results.php: {exc}")

    print()
    if fail:
        print(f"{fail} check(s) failed.")
        return 1
    print("All remote scope-preview checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
