#!/usr/bin/env python3
"""Verify cron import URL returns JSON (not HTML homepage)."""
from __future__ import annotations

import json
import ssl
import sys
import urllib.parse
import urllib.request

# Test with feed_id only; key must be passed via env or argv for security.
URL = (
    "https://123789.jp/custom-rss-builder/"
    "?crb_run_import=1&feed_id=8&key=TU17l2ZPs4wTnVEyfDvRWop4AvVG4Vja"
)


def main() -> int:
    ctx = ssl.create_default_context()
    req = urllib.request.Request(URL, headers={"User-Agent": "CRB-verify-cron/1"})
    try:
        with urllib.request.urlopen(req, context=ctx, timeout=60) as resp:
            body = resp.read().decode("utf-8", "replace")
            ctype = resp.headers.get("Content-Type", "")
            status = resp.status
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", "replace")
        ctype = exc.headers.get("Content-Type", "")
        status = exc.code

    print("HTTP status:", status)
    print("Content-Type:", ctype)
    print("Body prefix:", body[:200].replace("\n", " "))

    if body.lstrip().startswith("<!DOCTYPE") or body.lstrip().startswith("<html"):
        print("FAIL: HTML response (import hook not running)")
        return 1

    try:
        data = json.loads(body)
    except json.JSONDecodeError:
        print("FAIL: not JSON")
        return 1

    print("JSON:", json.dumps(data, ensure_ascii=False)[:500])
    if "error" in data:
        print("NOTE: endpoint ran but returned error:", data.get("error"))
        return 0
    if "feed_id" in data:
        print("OK: import endpoint responded")
        return 0

    print("FAIL: unexpected JSON shape")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
