#!/usr/bin/env python3
"""Phase 2: REST separation mode static checks."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FAIL = 0


def ok(msg: str) -> None:
    print(f"OK   {msg}")


def fail(msg: str) -> None:
    global FAIL
    FAIL += 1
    print(f"FAIL {msg}")


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def main() -> int:
    print("=== verify_remote_license_stack ===\n")

    main_php = read(ROOT / "custom-rss-builder.php")
    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    if not build:
        fail("CRB_BUILD_ID missing")
    else:
        ok(f"BUILD_ID {build.group(1)}")

    lic_fn = read(ROOT / "includes" / "functions-license.php")
    for needle in (
        "crb_license_get_connection_mode",
        "crb_license_uses_remote_api",
        "crb_license_is_embedded_mode",
        "crb_license_get_configured_api_base",
        "crb_license_connection_mode_label",
    ):
        if needle not in lic_fn:
            fail(f"functions-license.php missing {needle}")
        else:
            ok(needle)

    if "if ( ! crb_license_is_embedded_mode() )" not in lic_fn:
        fail("ensure_active should skip auto-free in remote mode")
    else:
        ok("ensure_active respects embedded mode")

    client = read(ROOT / "includes" / "class-license-client.php")
    if "crb_license_uses_remote_api()" not in client:
        fail("license client should branch on crb_license_uses_remote_api()")
    else:
        ok("client uses remote API flag")

    admin = read(ROOT / "admin" / "class-admin-license.php")
    for needle in ("save_connection", "test_connection"):
        if needle not in admin:
            fail(f"admin license missing {needle} handler")
        else:
            ok(f"handler {needle}")

    view = read(ROOT / "admin" / "views" / "license-settings.php")
    for needle in (
        "connection_mode",
        "save_connection",
        "test_connection",
        "REST（分離）",
    ):
        if needle not in view:
            fail(f"license UI missing {needle}")
        else:
            ok(f"UI has {needle}")

    print()
    if FAIL:
        print(f"{FAIL} failed")
        return 1
    print("All remote license stack checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
