#!/usr/bin/env python3
"""Download Monitor script dependency compat shim."""
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def main() -> int:
    print("=== verify_dlm_compat ===\n")
    main_php = (ROOT / "custom-rss-builder.php").read_text(encoding="utf-8")
    compat = (ROOT / "includes" / "functions-third-party-compat.php").read_text(encoding="utf-8")
    fail = 0

    if "functions-third-party-compat.php" not in main_php:
        print("FAIL bootstrap missing functions-third-party-compat.php")
        fail += 1
    else:
        print("OK   bootstrap loads compat")

    if "dlm-reports-app" not in compat or "admin_enqueue_scripts" not in compat:
        print("FAIL compat missing dlm-reports-app stub")
        fail += 1
    else:
        print("OK   dlm-reports-app stub registered early")

    print(f"\n{'FAIL' if fail else 'PASS'} ({fail} failures)")
    return 1 if fail else 0


if __name__ == "__main__":
    raise SystemExit(main())
