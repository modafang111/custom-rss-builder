#!/usr/bin/env python3
import re, urllib.request
from collections import Counter

URL = "https://www.dlsite.com/maniax/"
html = urllib.request.urlopen(urllib.request.Request(URL, headers={"User-Agent": "CRB"}), timeout=25).read().decode("utf-8", "replace")

# extract repeated class tokens (likely item blocks)
tokens = []
for m in re.finditer(r'class="([^"]+)"', html):
    for t in m.group(1).split():
        if len(t) >= 4:
            tokens.append(t)
counts = Counter(tokens)
print("Top repeated classes:")
for cls, n in counts.most_common(40):
    if 2 <= n <= 200:
        print(f"  .{cls}: {n}")

# li / article patterns near product_id links
links = len(re.findall(r'product_id', html))
print(f"\nproduct_id links in raw HTML: {links}")

# sample ranking item html
m = re.search(r'同人作品人気ランキング.{0,500}', html, re.S)
if m:
    print("\nranking section snippet:", m.group(0)[:400])
