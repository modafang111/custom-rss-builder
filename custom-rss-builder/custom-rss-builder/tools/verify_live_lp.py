# -*- coding: utf-8 -*-
"""正本 LP のプラン比較が期待どおりか HTTP で確認。"""
from __future__ import annotations

import re
import sys
import urllib.request

LP_URL = "https://123789.jp/custom-rss-builder/"
PHP_URL = (
    "https://123789.jp/custom-rss-builder/wp-content/plugins/"
    "custom-rss-builder/includes/functions-sales-lp.php"
)


def fetch(url: str) -> str:
    with urllib.request.urlopen(url, timeout=60) as resp:
        return resp.read().decode("utf-8", errors="replace")


def main() -> int:
    php = fetch(PHP_URL)
    ver = re.search(r"CRB_SALES_LP_VERSION',\s*'(\d+)'", php)
    print(f"remote CRB_SALES_LP_VERSION={ver.group(1) if ver else '?'}")
    print(f"remote php has standard card: {'price-card--standard' in php}")

    html = fetch(LP_URL)
    checks = {
        "スタンダード (heading)": "スタンダード" in html,
        "standard price card class": "price-card--standard" in html,
        "WordPress サイト数 row": "WordPress サイト数" in html,
        "old フィード無制限": "フィード無制限" in html,
        "3-col table header": ">スタンダード<" in html or "スタンダード</th>" in html,
    }
    print("\nLive LP checks:")
    ok = True
    for label, passed in checks.items():
        if label.startswith("old"):
            if passed:
                ok = False
        elif not passed:
            ok = False
        if label.startswith("old"):
            good = not passed
        else:
            good = passed
        status = "OK" if good else "NG"
        print(f"  [{status}] {label.encode('ascii', 'backslashreplace').decode()}")

    m = re.search(r"<h2[^>]*>プラン比較</h2>(.{0,4000})", html, re.S)
    if m:
        snippet = re.sub(r"<[^>]+>", " ", m.group(0))
        snippet = re.sub(r"\s+", " ", snippet).strip()
        print("\nPricing snippet:")
        print(snippet[:1200].encode("ascii", "backslashreplace").decode())

    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
