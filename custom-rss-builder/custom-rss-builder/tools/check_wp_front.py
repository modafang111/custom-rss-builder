#!/usr/bin/env python3
"""Check whether WordPress front URLs return an empty body (white page symptom)."""
from __future__ import annotations

import sys
import urllib.request

URLS = [
    "https://123789.jp/custom-rss-builder/",
    "https://123789.jp/custom-rss-builder/?p=1",
    "https://123789.jp/custom-rss-builder/wp-login.php",
]


def check(url: str) -> tuple[int, int]:
    req = urllib.request.Request(url, headers={"User-Agent": "CRB-FrontCheck/1.0"})
    with urllib.request.urlopen(req, timeout=25) as resp:
        body = resp.read()
        return int(resp.status), len(body)


def main() -> int:
    failed = 0
    for url in URLS:
        try:
            status, size = check(url)
            label = "EMPTY (white page risk)" if size == 0 and "wp-login" not in url else "OK"
            if size == 0 and "wp-login" not in url:
                failed += 1
            print(f"{label:28} HTTP {status}  {size:6} bytes  {url}")
        except Exception as exc:
            print(f"ERROR                        {url} — {exc}")
            failed += 1
    if failed:
        print(
            "\nFront pages return 0 bytes: theme or PHP fatal on public templates, not post import alone.",
            file=sys.stderr,
        )
        print("Fix: switch to a default theme, enable WP_DEBUG_LOG, check Xserver error log.", file=sys.stderr)
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
