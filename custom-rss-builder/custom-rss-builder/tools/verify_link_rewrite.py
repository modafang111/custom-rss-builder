#!/usr/bin/env python3
"""Verify prefix + regex link rewrite (mirrors PHP helpers)."""
from __future__ import annotations

import re


def rewrite_prefix(url: str, source: str, target: str) -> str:
    if not url.lower().startswith(source.lower()):
        return url
    return target + url[len(source) :]


def ensure_delimiters(pattern: str) -> str:
    if pattern[:1] in "/#~%" and pattern.rfind(pattern[:1]) > 0:
        return pattern
    return "#" + pattern.replace("#", r"\#") + "#iu"


def rewrite_regex(url: str, pattern: str, replacement: str) -> str:
    delimited = ensure_delimiters(pattern)
    # Python uses different flags; approximate with IGNORECASE + UNICODE
    body = delimited
    flags = 0
    if body.startswith("#") and body.endswith("#iu"):
        body = body[1:-3].replace(r"\#", "#")
        flags = re.IGNORECASE | re.UNICODE
    elif body.startswith("#") and body.endswith("#i"):
        body = body[1:-2].replace(r"\#", "#")
        flags = re.IGNORECASE
    compiled = re.compile(body, flags)
    # PHP-style $1 → Python \1
    py_repl = re.sub(r"\$(\d+)", r"\\g<\1>", replacement)
    out, n = compiled.subn(py_repl, url, count=1)
    return out if n else url


CASES = [
    (
        "prefix",
        "https://example.com/list/item/PRODUCT-001.html",
        "https://example.com/list/item/",
        "https://aff.example.net/track/",
        "https://aff.example.net/track/PRODUCT-001.html",
    ),
    (
        "prefix-miss",
        "https://news.yahoo.co.jp/articles/abc123",
        "https://example.com/list/item/",
        "https://aff.example.net/track/",
        "https://news.yahoo.co.jp/articles/abc123",
    ),
    (
        "regex",
        "https://www.example.com/maniax/work/=/product_id/RJ012345.html",
        r"https://www\.example\.com/.*/product_id/(RJ[0-9]+)\.html",
        r"https://aff.example.net/track/$1",
        "https://aff.example.net/track/RJ012345",
    ),
]


def main() -> int:
    fail = 0
    for kind, src, pattern, repl, expect in CASES:
        if kind.startswith("prefix"):
            got = rewrite_prefix(src, pattern, repl)
        else:
            got = rewrite_regex(src, pattern, repl)
        if got != expect:
            fail += 1
            print("FAIL", kind, "got", got, "expected", expect)
        else:
            print("OK  ", kind, src[:50])
    return 1 if fail else 0


if __name__ == "__main__":
    raise SystemExit(main())
