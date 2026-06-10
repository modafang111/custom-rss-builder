#!/usr/bin/env python3
import re, urllib.request
URL = "https://www.dlsite.com/maniax/"
html = urllib.request.urlopen(urllib.request.Request(URL, headers={"User-Agent": "CRB"}), timeout=25).read().decode("utf-8", "replace")
for pat in ["new_worklist", "news-item", "news_item", "ranking", "work_block", "n_worklist"]:
    idx = html.find(pat)
    print(pat, "found at", idx if idx >= 0 else "NO")
    if idx >= 0:
        print("  context:", repr(html[max(0,idx-40):idx+80])[:120])

# ranking list structure
for m in re.finditer(r'<(section|div|ul|ol)[^>]*class="([^"]*rank[^"]*)"', html[:80000]):
    print("rank block:", m.group(1), m.group(2)[:60])
