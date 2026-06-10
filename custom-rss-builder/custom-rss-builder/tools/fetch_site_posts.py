#!/usr/bin/env python3
import re
import urllib.request

url = "https://123789.jp/custom-rss-builder/"
req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
with urllib.request.urlopen(req, timeout=25) as r:
    b = r.read().decode("utf-8", "replace")
print("len", len(b), "status", r.status)
for m in re.finditer(r'href="([^"]+)"', b):
    h = m.group(1)
    if "custom-rss-builder" in h and ("?p=" in h or re.search(r"/\d{4}/", h)):
        print("post?", h)
