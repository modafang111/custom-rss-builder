#!/usr/bin/env python3
"""Verify prefix link rewrite (mirrors PHP crb_apply_one_link_rewrite_rule)."""
from __future__ import annotations

SOURCE = "https://example.com/list/item/"
TARGET = "https://aff.example.net/track/"

CASES = [
    (
        "https://example.com/list/item/PRODUCT-001.html",
        "https://aff.example.net/track/PRODUCT-001.html",
    ),
    (
        "https://example.com/list/item/PRODUCT-002.html",
        "https://aff.example.net/track/PRODUCT-002.html",
    ),
    (
        "https://news.yahoo.co.jp/articles/abc123",
        "https://news.yahoo.co.jp/articles/abc123",
    ),
]


def rewrite_prefix(url: str) -> str:
    if not url.lower().startswith(SOURCE.lower()):
        return url
    tail = url[len(SOURCE) :]
    return TARGET + tail


def main() -> int:
    fail = 0
    for src, expect in CASES:
        got = rewrite_prefix(src)
        if got != expect:
            fail += 1
            print("FAIL", src, "got", got, "expected", expect)
        else:
            print("OK  ", src[:60])
    return 1 if fail else 0


if __name__ == "__main__":
    raise SystemExit(main())
