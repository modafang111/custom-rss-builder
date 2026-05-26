# Simulate discovery on dlsite review snippet
import re
from html.parser import HTMLParser
from collections import defaultdict

# minimal: use regex-based extraction for quick test (not full DOM)
HTML_PATH = r"c:\ai_spec_builder\plugins\custom-rss-builder\custom-rss-builder\test-fixture\dlsite-review-snippet.html"

def load():
    try:
        with open(HTML_PATH, "r", encoding="utf-8") as f:
            return f.read()
    except FileNotFoundError:
        return None

# Write snippet from user message - first part only if file missing
SNIPPET_START = '<div class="review_list" id="review_list">'

def score_link(href, text, title, parent_hint):
    s = 0
    h = href.lower()
    if not href or href == "#" or h.startswith("javascript:"):
        return -100
    if "product_id" in h or "/work/=" in h:
        s += 50
    if "work_name" in parent_hint:
        s += 40
    if title and len(title) > 5:
        s += 20
    if len(text) > 10:
        s += 10
    if "/cart/" in h or "/genre/" in h or "/reviewlist/" in h or "/reviewer/" in h or "/fsr/" in h:
        s -= 30
    if text.strip() in ("(1)", "(2)", "(32)") or len(text) < 4:
        s -= 20
  if "btn_" in parent_hint or "cart" in h:
        s -= 25
    return s

# crude: find <a href="..."> with context
def find_links(html):
    results = []
    for m in re.finditer(r'<a\b([^>]*)>([\s\S]*?)</a>', html, re.I):
        attrs, inner = m.group(1), m.group(2)
        hm = re.search(r'href\s*=\s*["\']([^"\']*)["\']', attrs, re.I)
        tm = re.search(r'title\s*=\s*["\']([^"\']*)["\']', attrs, re.I)
        if not hm:
            continue
        href = hm.group(1)
        text = re.sub(r'<[^>]+>', '', inner)
        text = re.sub(r'\s+', ' ', text).strip()
        title = tm.group(1) if tm else ""
        # context before <a
        start = max(0, m.start() - 200)
        ctx = html[start:m.start()]
        parent = "work_name" if "work_name" in ctx else ("thumb" if "thumb" in ctx else "other")
        sc = score_link(href, text, title, parent)
        results.append((sc, href[:60], text[:40], title[:40], parent))
    return sorted(results, reverse=True)[:15]

html = load()
if html is None:
    print("fixture missing - create from user paste")
else:
    # scope: only inside review_list
    m = re.search(r'<div class="review_list" id="review_list">([\s\S]*)</div>\s*</div>\s*$', html)
    scoped = m.group(0) if m else html
    print("=== top links by score (regex approx) ===")
    for row in find_links(scoped):
        print(row)
