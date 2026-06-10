#!/usr/bin/env python3
"""Level 2 構造分離の静的チェック（正本=ライセンスのみ / クライアント=フィード+REST）。"""
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FAILURES: list[str] = []


def ok(msg: str) -> None:
    print(f"OK   {msg}")


def fail(msg: str) -> None:
    FAILURES.append(msg)
    print(f"FAIL {msg}")


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def main() -> int:
    print("=== verify_operational_separation ===\n")
    lic = read("includes/functions-license.php")
    bootstrap = read("custom-rss-builder.php")
    admin_page = read("admin/class-admin-page.php")
    ls_admin = read("license-server/admin/class-admin.php")
    license_view = read("admin/views/license-settings.php")
    settings_view = read("admin/views/settings-page.php")

    for fn in (
        "crb_package_variant",
        "crb_is_client_app_enabled",
        "crb_is_license_server_app_enabled",
        "crb_license_is_authoritative_server",
    ):
        if f"function {fn}" not in lic:
            fail(f"missing {fn}")
        else:
            ok(fn)

    if not (ROOT / "includes/class-custom-rss-builder-authority.php").is_file():
        fail("class-custom-rss-builder-authority.php missing")
    else:
        ok("authority bootstrap class")

    if "if ( crb_is_client_app_enabled() )" not in bootstrap:
        fail("custom-rss-builder.php must conditionally load client app")
    else:
        ok("conditional client requires")

    if "class-custom-rss-builder-authority.php" not in bootstrap:
        fail("authority class must be loaded when client disabled")
    else:
        ok("authority require path")

    if "operations-banner.php" in admin_page:
        fail("admin header must not include operations banner")
    else:
        ok("no operations banner in admin header")

    if "crb-panel--operations" in license_view:
        fail("license-settings must not include operations panel")
    else:
        ok("no operations panel in license-settings")

    if "ops_role" in settings_view or "authority" in settings_view:
        fail("settings-page must not branch on authority role")
    else:
        ok("settings-page is client-only UI")

    if "crb_is_client_app_enabled() ) && ! crb_is_client_app_enabled()" not in ls_admin.replace("\n", " "):
        if "! crb_is_client_app_enabled()" not in ls_admin or "add_menu_page" not in ls_admin:
            fail("license server admin must use top-level menu when client disabled")
        else:
            ok("license server top-level menu on authority")
    else:
        ok("license server top-level menu on authority")

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("All structural separation checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
