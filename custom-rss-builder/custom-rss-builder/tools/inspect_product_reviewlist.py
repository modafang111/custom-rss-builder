#!/usr/bin/env python3
import urllib.request
from lxml import html as lhtml

url = "https://www.dlsite.com/maniax/work/reviewlist/=/product_id/RJ01482137.html"
req = urllib.request.Request(url, headers={"User-Agent": "Custom RSS Builder/0.9.0"})
body = urllib.request.urlopen(req, timeout=25).read().decode("utf-8", "replace")
root = lhtml.fromstring(('<div id="crb-root">' + body + "</div>").encode("utf-8"))
root = root.xpath('//*[@id="crb-root"]')[0]

candidates = [
    "#review_list",
    ".review_list",
    ".review_inner",
    "table.work_1col_table",
    ".review_list_box",
    "#review_list_box",
    ".review_contents",
    "div.review_contents",
]
for sel in candidates:
    try:
        from simulate_scope_discover import css_to_xpath, query_scope

        n = len(query_scope(root, sel))
    except Exception as e:
        n = f"ERR {e}"
    print(f"{sel:30} {n}")
