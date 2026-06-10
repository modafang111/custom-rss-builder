#!/usr/bin/env python3
import re
import sys
import urllib.request

URL = (
    "https://123789.jp/custom-rss-builder/wp-content/plugins/"
    "custom-rss-builder/assets/js/admin.js"
)
EXPECTED = "0.8.0"
req = urllib.request.Request(URL, headers={"User-Agent": "CRB-Verify/1.0"})
with urllib.request.urlopen(req, timeout=20) as r:
    body = r.read().decode("utf-8", "replace")
    print("HTTP", r.status, "bytes", len(body))

m = re.search(r"crbAdminBuild\s*=\s*['\"]([^'\"]+)['\"]", body)
ver = m.group(1) if m else "NOT FOUND"
print("crbAdminBuild", ver)

checks = [
    ("version", ver == EXPECTED, f"expected {EXPECTED}"),
    ("crb-parsed-rows-url", "crb-parsed-rows-url" in body, "preview extract fields"),
    ("no extraction_mode", "extraction_mode" not in body, "HTML mode removed"),
    ("no togglePanels", "togglePanels" not in body, "HTML mode removed"),
]
failed = 0
for name, ok, detail in checks:
    if ok:
        print(f"OK   {name}")
    else:
        print(f"FAIL {name} — {detail}")
        failed += 1

sys.exit(1 if failed else 0)
