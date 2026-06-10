#!/usr/bin/env python3
"""Simulate scope fetch for user case: dlsite maniax / #new_worklist / .news-item"""
import importlib.util
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("sim", ROOT / "tools" / "simulate_scope_fetch_fix.py")
sim = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sim)

import urllib.request

URL = "https://www.dlsite.com/maniax/"
SCOPE = "#new_worklist"
ITEM = ".news-item"

html = urllib.request.urlopen(
    urllib.request.Request(URL, headers={"User-Agent": "Custom RSS Builder/0.9.0"}),
    timeout=25,
).read().decode("utf-8", "replace")

print("=== User case simulation ===")
print(f"URL: {URL}")
print(f"Scope: {SCOPE}  Item: {ITEM}")
print()

for label, sel in [("scope raw #new_worklist", SCOPE), ("item raw .news-item", ITEM)]:
    hit = sim.prepare_for_dom  # noqa - use raw check
    s = sel.lstrip("#.")
    if sel.startswith("#"):
        ok = f'id="{s}"' in html
        print(f"{label}: in raw HTML = {ok}")
    elif sel.startswith("."):
        import re
        ok = bool(re.search(rf'class="[^"]*\b{re.escape(s)}\b', html))
        print(f"{label}: in raw HTML = {ok}")

print()
r = sim.simulate_scope_fetch(html, SCOPE, ITEM)
print(f"Scope fetch result: {'OK' if r['ok'] else 'FAIL'}")
print(f"  total {r['total_ms']}ms  scope={r['scope_match']}  item={r['item_match']}")

print()
print("--- Working selectors on this URL ---")
for scope, item in [
    ("#main", ".recommend_work_item"),
    ("#main", ".genre_ranking_item"),
]:
    r2 = sim.simulate_scope_fetch(html, scope, item)
    print(f"  {scope} + {item}: scope={r2['scope_match']} item={r2['item_match']} ({r2['total_ms']}ms)")

sys.exit(0 if r["ok"] else 1)
