#!/usr/bin/env python3
"""Static checks for client license UI (no WordPress runtime)."""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LICENSE_VIEW = ROOT / "admin" / "views" / "license-settings.php"
KEY_FORM = ROOT / "admin" / "views" / "partials" / "license-key-form.php"
ADMIN_LICENSE = ROOT / "admin" / "class-admin-license.php"
FUNCTIONS = ROOT / "includes" / "functions-license.php"
MAIN = ROOT / "custom-rss-builder.php"

errors: list[str] = []


def fail(msg: str) -> None:
    errors.append(msg)


def ok(msg: str) -> None:
    print(f"OK: {msg}")


def read(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def main() -> int:
    lv = read(LICENSE_VIEW)
    kf = read(KEY_FORM)
    al = read(ADMIN_LICENSE)
    fl = read(FUNCTIONS)
    client = read(ROOT / "includes" / "class-license-client.php")
    rest = read(ROOT / "license-server" / "includes" / "class-rest-api.php")
    main_php = read(MAIN)

    # max_slots before slot_range_label
    ms = lv.find("$max_slots")
    sr = lv.find("$slot_range_label")
    if ms < 0 or sr < 0 or ms > sr:
        fail("license-settings.php: $max_slots must be defined before $slot_range_label")
    else:
        ok("max_slots ordering")

    # Client: Pro CTA panel when free + usable
    if "$show_client_pro_cta" in lv and "Pro を申し込む" in lv:
        ok("client pro payment CTA on license screen")
    else:
        fail("client should show Pro payment CTA when free")

    # Server dev: ol panel only on non-client
    if re.search(
        r"\$show_pro_upgrade_panel && ! \$is_client_screen",
        lv,
    ):
        ok("pro upgrade ol panel server-only")
    else:
        fail("server pro upgrade ol should exclude client screen")

    # Pro upgrade hidden field + activate guard
    if 'name="crb_pro_upgrade"' in kf:
        ok("crb_pro_upgrade hidden field")
    else:
        fail("license-key-form.php missing crb_pro_upgrade hidden input")

    if "crb_pro_upgrade" in al and "is_pro_upgrade" in al:
        ok("activate respects crb_pro_upgrade")
    else:
        fail("class-admin-license.php missing Pro upgrade activate guard")

    if "$body['secret']" in client or "body['secret']" in client:
        ok("remote client sends secret in JSON body")
    else:
        fail("class-license-client.php should send secret in POST body")

    if "'permission_callback' => '__return_true'" in rest and "require_secret" in rest:
        ok("REST secret verified inside callback")
    else:
        fail("license REST should verify secret in callback not permission_callback")

    if "crb_license_map_remote_http_message" in fl and "crb_license_is_rest_permission_denied_message" in fl:
        ok("REST forbidden message mapping")
    else:
        fail("functions-license.php missing REST error mapping")

    if re.search(
        r"function crb_license_get_state\(\)\s*\{[\s\S]{0,1200}?crb_license_sync_if_stale",
        fl,
    ):
        ok("get_state syncs stale license from server")
    else:
        fail("crb_license_get_state should call crb_license_sync_if_stale")

    # Status label helper
    if "function crb_license_status_label_for_display" in fl:
        ok("status_label_for_display helper")
    else:
        fail("functions-license.php missing crb_license_status_label_for_display")

    if "crb_license_status_label_for_display( $state )" in lv:
        ok("license view uses status_label_for_display")
    else:
        fail("license-settings.php should use crb_license_status_label_for_display")

    # Stale usable repair on get_state
    if re.search(
        r"function crb_license_get_state\(\)\s*\{[\s\S]{0,1200}?crb_license_repair_stale_unusable",
        fl,
    ):
        ok("get_state calls repair_stale_unusable")
    else:
        fail("crb_license_get_state should call crb_license_repair_stale_unusable")

    # maybe_refresh skips license page
    if "custom-rss-builder-license" in fl and "function crb_license_maybe_refresh" in fl:
        block = fl[fl.find("function crb_license_maybe_refresh") : fl.find("function crb_license_", fl.find("function crb_license_maybe_refresh") + 1)]
        if "custom-rss-builder-license" in block:
            ok("maybe_refresh skips license settings page")
        else:
            fail("crb_license_maybe_refresh should skip license page")
    else:
        fail("maybe_refresh license page guard missing")

    # Client-facing view: avoid jargon in key strings (sample)
    bad_client_strings = ["REST（分離）", "組み込み（同一DB）", "API Secret"]
    for needle in bad_client_strings:
        if needle in kf:
            fail(f"license-key-form.php contains ops string: {needle}")

    # Plan comparison table when crb_license_plan_comparison_rows() returns rows.
    if "$show_plan_comparison" in lv and "! empty( $comparison_rows )" in lv:
        ok("plan comparison when comparison_rows available")
    else:
        fail("show_plan_comparison wiring missing")

    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    if build:
        ok(f"CRB_BUILD_ID {build.group(1)}")
    else:
        fail("CRB_BUILD_ID missing")

    if errors:
        print("\nFAILED:")
        for e in errors:
            print(f"  - {e}")
        return 1

    print("\nAll client license UI checks passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
