#!/usr/bin/env python3
"""License stack static checks."""
from __future__ import annotations

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
    print("=== verify_license_stack ===\n")

    crb = read(ROOT / "custom-rss-builder.php")
    if "20260531a" not in crb:
        fail("BUILD_ID should be 20260531a")
    else:
        ok("BUILD_ID 20260531a")

    lic_fn = read(ROOT / "includes" / "functions-license.php")
    for needle in (
        "CRB_LICENSE_FREE_SLOT_LIMIT",
        "crb_license_maybe_ensure_local_free",
        "crb_license_apply_slot_limits",
    ):
        if needle not in lic_fn:
            fail(f"functions-license.php missing {needle}")
        else:
            ok(needle)

    for needle in (
        "crb_license_is_local_server",
        "crb_license_setup_free_local",
        "crb_license_can",
    ):
        if needle not in lic_fn:
            fail(f"functions-license.php missing {needle}")
        else:
            ok(needle)

    client = read(ROOT / "includes" / "class-license-client.php")
    if "request_local" not in client:
        fail("license client missing request_local")
    else:
        ok("local license path (no HTTP on same site)")

    if "rest_route" not in client:
        fail("remote client missing rest_route fallback")
    else:
        ok("remote rest_route fallback")

    ls_fn = read(ROOT / "license-server" / "includes" / "functions-license-server.php")
    if "crb_ls_get_manager" not in ls_fn:
        fail("missing crb_ls_get_manager")
    else:
        ok("crb_ls_get_manager")

    view = read(ROOT / "admin" / "views" / "license-settings.php")
    if "setup_free" not in view:
        fail("license UI missing setup_free button")
    else:
        ok("one-click free setup UI")

    admin = read(ROOT / "admin" / "class-admin-license.php")
    if "setup_free" not in admin:
        fail("admin license missing setup_free handler")
    else:
        ok("setup_free handler")

    print()
    if FAIL:
        print(f"{FAIL} failed")
        return 1
    print("All license stack checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
