#!/usr/bin/env python3
"""Verify remote license fix deployment."""
from __future__ import annotations

import re
import ssl
import urllib.request

BASE = "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder"
CTX = ssl.create_default_context()


def fetch(path: str) -> str:
    with urllib.request.urlopen(BASE + path, context=CTX, timeout=25) as resp:
        return resp.read().decode("utf-8", "replace")


def main() -> int:
    main_php = fetch("/custom-rss-builder.php")
    build = re.search(r"CRB_BUILD_ID', '([^']+)'", main_php)
    print("BUILD_ID", build.group(1) if build else "NOT FOUND")

    client = fetch("/includes/class-license-client.php")
    print("request_local", "request_local" in client)

    view = fetch("/admin/views/license-settings.php")
    print("setup_free UI", "setup_free" in view)

    fn = fetch("/includes/functions-license.php")
    print("setup_free_local fn", "crb_license_setup_free_local" in fn)

    ok = build and build.group(1) == "20260530e" and "request_local" in client and "setup_free" in view
    print("RESULT", "OK" if ok else "FAIL")
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
