#!/usr/bin/env python3
"""Run local verification suite."""
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
scripts = [
    ROOT / "tools" / "verify_css_module_split.py",
    ROOT / "tools" / "verify_dom_scope_module.py",
    ROOT / "tools" / "verify_dom_discover_module.py",
    ROOT / "tools" / "verify_extract_flow.py",
    ROOT / "tools" / "verify_no_adult_brand_in_client.py",
    ROOT / "tools" / "verify_trim_ui.py",
    ROOT / "tools" / "verify_feed_pack_export.py",
    ROOT / "tools" / "verify_feed_pack_import.py",
    ROOT / "tools" / "run_feed43_tests_py.py",
    ROOT / "tools" / "verify_plugin_bootstrap.py",
    ROOT / "tools" / "debug_integrated_flow.py",
]

failed = 0
for s in scripts:
    print(f"\n=== {s.name} ===")
    rc = subprocess.call([sys.executable, str(s)])
    if rc != 0:
        failed += 1

print(f"\n{'ALL OK' if failed == 0 else f'{failed} script(s) failed'}")
sys.exit(1 if failed else 0)
