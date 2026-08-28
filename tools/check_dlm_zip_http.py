#!/usr/bin/env python3
"""HTTP check: DLM client ZIP and download page."""
from __future__ import annotations

import io
import re
import ssl
import sys
import urllib.request
import zipfile

URLS = (
    "https://123789.jp/custom-rss-builder/wp-content/uploads/dlm_uploads/2026/06/custom-rss-builder-client.zip",
    "https://123789.jp/custom-rss-builder/download/695/",
)


def main() -> int:
    ctx = ssl.create_default_context()
    failed = 0
    for url in URLS:
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "CRB-DLM-Check/1.0"})
            with urllib.request.urlopen(req, context=ctx, timeout=30) as resp:
                data = resp.read()
            print(f"OK   {url}")
            print(f"     HTTP {resp.status}  {len(data)} bytes")
            if data[:2] == b"PK":
                with zipfile.ZipFile(io.BytesIO(data)) as zf:
                    main_php = zf.read("custom-rss-builder/custom-rss-builder.php").decode(
                        "utf-8", errors="replace"
                    )
                m = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
                print(f"     BUILD_ID {m.group(1) if m else '?'}")
        except Exception as exc:
            print(f"FAIL {url}")
            print(f"     {exc}")
            failed += 1
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
