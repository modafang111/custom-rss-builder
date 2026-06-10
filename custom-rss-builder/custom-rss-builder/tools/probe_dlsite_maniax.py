#!/usr/bin/env python3
"""Probe DLsite maniax HTML for scope selectors."""
import re
import urllib.request

URL = "https://www.dlsite.com/maniax/"
html = urllib.request.urlopen(
    urllib.request.Request(URL, headers={"User-Agent": "Custom RSS Builder/0.9.0"}),
    timeout=25,
).read().decode("utf-8", "replace")

print("bytes:", len(html.encode()))
for needle in (
    "new_worklist",
    "news-item",
    "n_worklist_item",
    'id="main"',
    "worklist",
):
    print(f"  {needle!r}: {needle in html}")

ids = re.findall(r'id="([a-zA-Z0-9_-]{3,50})"', html)
from collections import Counter

for pat in ("new", "work", "list", "news", "rank"):
    hits = sorted({i for i in ids if pat in i.lower()})
    if hits:
        print(f"ids with {pat}: {hits[:15]}")

classes = re.findall(r'class="([^"]{0,120})"', html)
news = sorted({c for c in classes if "news" in c.lower() or "worklist" in c.lower() or "work_list" in c.lower()})
print("classes (news/worklist):", news[:20])
