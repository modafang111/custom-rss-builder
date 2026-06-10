#!/usr/bin/env python3
"""Compare OLD (full HTML parse) vs NEW (prepare + fast path) timing."""
import re
import time
from pathlib import Path

from lxml import html as lhtml

ROOT = Path(__file__).resolve().parents[1]
sys_path = ROOT / "tools" / "simulate_scope_fetch_fix.py"
# import helpers
import importlib.util
spec = importlib.util.spec_from_file_location("sim", sys_path)
sim = importlib.util.module_from_spec(spec)
spec.loader.exec_module(sim)

html = sim.synthetic_heavy_html()
scope = "#new_worklist"

# OLD: parse without strip
t0 = time.perf_counter()
root_old = sim.load_crb_root(html, prepared=False)
old_parse = (time.perf_counter() - t0) * 1000
t1 = time.perf_counter()
xp = sim.css_to_xpath(scope)
old_nodes = root_old.xpath(xp)
old_scope = (time.perf_counter() - t1) * 1000

# NEW
r = sim.simulate_scope_fetch(html, scope, ".n_worklist_item")

print("=== OLD vs NEW (5MB synthetic page) ===")
print(f"OLD parse+scope: {old_parse:.0f}ms + {old_scope:.0f}ms = {old_parse + old_scope:.0f}ms")
print(f"NEW total:       {r['total_ms']:.0f}ms")
print(f"NEW scope match: {r['scope_match']} (OLD xpath: {len(old_nodes)})")
print(f"Under 30s limit: OLD={'YES' if old_parse+old_scope < 30000 else 'NO'} NEW={'YES' if r['total_ms'] < 30000 else 'NO'}")
