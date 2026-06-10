#!/usr/bin/env python3
import re
import sys
import urllib.request

URLS = sys.argv[1:] or [
    "https://wordpress-123.com/PluginTest/wp-content/plugins/custom-rss-builder/custom-rss-builder.php",
    "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder/custom-rss-builder.php",
]

ADMIN_JS = [
    "https://wordpress-123.com/PluginTest/wp-content/plugins/custom-rss-builder/assets/js/admin.js",
    "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder/assets/js/admin.js",
]


def fetch(url: str) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": "CRB-Check/1.0"})
    with urllib.request.urlopen(req, timeout=20) as resp:
        return resp.read().decode("utf-8", errors="replace")


def check_main(url: str) -> None:
    try:
        body = fetch(url)
        build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", body)
        print(f"{url}")
        print(f"  HTTP OK  bytes={len(body)}  BUILD={build.group(1) if build else 'NOT FOUND (direct PHP may be blocked)'}")
    except Exception as exc:
        print(f"{url}")
        print(f"  ERROR {exc}")


def check_js(url: str) -> None:
    try:
        body = fetch(url)
        print(f"{url}")
        print(f"  bytes={len(body)}")
        dlsite = "scope_selector: '#review_list'" in body
        print(f"  touches import-content-template: {'import-content-template' in body}")
        print(f"  has DLsite preset block: {dlsite}")
    except Exception as exc:
        print(f"{url}")
        print(f"  ERROR {exc}")


if __name__ == "__main__":
    print("=== main.php ===")
    for u in URLS:
        check_main(u)
    print("\n=== admin.js ===")
    for u in ADMIN_JS:
        check_js(u)
