#!/usr/bin/env python3
import urllib.request
from lxml import html as lhtml

URL = "https://www.dlsite.com/maniax/"
raw = urllib.request.urlopen(urllib.request.Request(URL, headers={"User-Agent": "CRB"}), timeout=25).read()
doc = lhtml.fromstring(raw)
root = doc.get_element_by_id("main")
print("main found:", root is not None)
if root is not None:
    for sel in [".genre_ranking_item", ".recommend_work_item", "li", ".rank_number", ".work_name"]:
        els = root.cssselect(sel)
        print(f"  {sel}: {len(els)}")
    # first ranking item path
    rn = root.cssselect(".rank_number")
    if rn:
        el = rn[0]
        for _ in range(5):
            p = el.getparent()
            if p is None:
                break
            print("ancestor:", p.tag, p.get("class", "")[:50])
            el = p
