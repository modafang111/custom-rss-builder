#!/usr/bin/env python3
import re
import ssl
import sys
import urllib.request

url = sys.argv[1] if len(sys.argv) > 1 else "https://otona-column.com/wp-content/plugins/custom-rss-builder/custom-rss-builder.php"
ctx = ssl.create_default_context()
try:
    req = urllib.request.Request(url, headers={"Cache-Control": "no-cache"})
    body = urllib.request.urlopen(req, context=ctx, timeout=20).read().decode("utf-8", "replace")
    m = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", body)
    print("OK build=", m.group(1) if m else "unknown")
except Exception as exc:
    print("ERR", exc)
