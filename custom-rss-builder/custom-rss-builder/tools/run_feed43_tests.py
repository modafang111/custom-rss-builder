#!/usr/bin/env python3
"""Mirror of tests/run-feed43-tests.php when PHP is unavailable."""
from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent


def run_php() -> bool:
    php = None
    for cmd in ("php", "php.exe"):
        try:
            r = subprocess.run([cmd, "-v"], capture_output=True, timeout=5)
            if r.returncode == 0:
                php = cmd
                break
        except OSError:
            continue
    if not php:
        return False
    r = subprocess.run([php, str(BASE / "tests" / "run-feed43-tests.php")], cwd=str(BASE))
    return r.returncode == 0


def main() -> int:
    if run_php():
        return 0
    print("PHP not found — run: php tests/run-feed43-tests.php")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
