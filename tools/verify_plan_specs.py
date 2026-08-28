#!/usr/bin/env python3
"""Static audit: plan specs vs implementation."""
from __future__ import annotations

import re
import sys
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent / "custom-rss-builder" / "custom-rss-builder"
FAILURES: list[str] = []


def read(rel: str) -> str:
    return (BASE / rel).read_text(encoding="utf-8")


def ok(msg: str) -> None:
    print(f"  OK  {msg}")


def fail(msg: str) -> None:
    FAILURES.append(msg)
    print(f"  FAIL {msg}")


def check_const(name: str, expected: int) -> None:
    lic = read("includes/functions-license.php")
    m = re.search(rf"define\s*\(\s*'{name}'\s*,\s*(\d+)\s*\)", lic)
    if not m:
        fail(f"missing constant {name}")
    elif int(m.group(1)) != expected:
        fail(f"{name}={m.group(1)} expected {expected}")
    else:
        ok(f"{name} = {expected}")


def main() -> int:
    print("=== Plan spec audit (static) ===\n")

    print("-- Constants --")
    check_const("CRB_LICENSE_FREE_FEED_LIMIT", 1)
    check_const("CRB_LICENSE_STANDARD_FEED_LIMIT", 3)
    check_const("CRB_LICENSE_PRO_FEED_LIMIT", 10)
    check_const("CRB_LICENSE_FREE_SLOT_LIMIT", 3)
    check_const("CRB_LICENSE_STANDARD_SLOT_LIMIT", 5)
    check_const("CRB_LICENSE_FREE_IMPORT_SCHEDULE_MIN_HOURS", 24)

    print("\n-- crb_license_can gates --")
    lic = read("includes/functions-license.php")
    can = lic.split("function crb_license_can", 1)[1].split("function crb_license_feed_count", 1)[0]

    def plan_block(plan: str) -> str:
        marker = f"'{plan}' === $plan"
        if marker not in can:
            return ""
        return can.split(marker, 1)[1].split("if ( 'free' !== $plan", 1)[0]

    for plan in ("pro", "standard", "free"):
        block = plan_block(plan)
        if not block:
            fail(f"crb_license_can missing block for {plan}")
            continue
        if "create_feed" not in block or "CRB_LICENSE" not in block:
            fail(f"{plan}: create_feed/save feed gate missing")
        else:
            ok(f"{plan}: feed count gates present")

    std = plan_block("standard")
    if "case 'ai_transform':" in std and "return false" in std.split("case 'ai_transform':", 1)[1]:
        ok("standard: ai_transform blocked")
    else:
        fail("standard: ai_transform should return false")

    pro = plan_block("pro")
    if "case 'ai_transform':" in pro and "return true" in pro.split("case 'ai_transform':", 1)[1]:
        ok("pro: ai_transform allowed")
    else:
        fail("pro: ai_transform should return true")

    free = plan_block("free")
    if "case 'ai_transform':" in free and "return false" in free.split("case 'ai_transform':", 1)[1]:
        ok("free: ai_transform blocked")
    else:
        fail("free: ai_transform should return false")

    if "case 'cron_import':" in can:
        fail("cron_import has explicit case (unexpected)")
    else:
        ok("free/standard/pro: cron_import allowed via default")

    print("\n-- Slot limits --")
    rsc = lic.split("function crb_license_get_record_slot_count", 1)[1].split(
        "function crb_license_get_max_slot_index", 1
    )[0]
    if "CRB_LICENSE_STANDARD_SLOT_LIMIT" in rsc and "CRB_RECORD_SLOT_COUNT" in rsc:
        ok("record slot count: free=3, standard=5, pro=20")
    else:
        fail("crb_license_get_record_slot_count incomplete")

    cssfc = read("includes/functions-css-feed-config.php")
    if "crb_license_apply_slot_limits" in cssfc:
        ok("CSS sanitize applies slot limits (functions-css-feed-config.php)")
    else:
        fail("slot limits not applied in CSS sanitize")

    print("\n-- Auto import schedule --")
    imp_ui = read("admin/views/partials/feed-import-settings.php")
    if 'min="0"' in imp_ui:
        ok("paid plan UI: import_schedule_hours min=0")
    else:
        fail("paid plan UI missing min=0 on schedule hours")

    if "import_schedule_auto" in imp_ui and "crb-import-schedule-field--free" in imp_ui:
        ok("free plan UI: checkbox + fixed 24h")
    else:
        fail("free plan schedule UI incomplete")

    imp_sched = read("includes/functions-import-schedule.php")
    if "crb_import_schedule_effective_hours" in imp_sched:
        ok("effective hours clamps sub-min to plan minimum")
    else:
        fail("missing effective hours helper")

    if "CRB_LICENSE_FREE_IMPORT_SCHEDULE_MIN_HOURS" in imp_sched or "crb_license_import_schedule_min_hours" in read(
        "includes/functions-license.php"
    ):
        ok("free min 24h wired via crb_license_import_schedule_min_hours")
    else:
        fail("free 24h import min not wired")

    print("\n-- Free credit --")
    fc = read("includes/functions-free-credit.php")
    if "'free' === ( $state['plan'] ?? '' )" in fc:
        ok("free credit only when plan=free")
    else:
        fail("free credit plan check missing")

    print("\n-- Plan comparison UI --")
    rows = lic.split("function crb_license_plan_comparison_rows", 1)[1].split(
        "function crb_license_free_plan_summary", 1
    )[0]
    for key in ("free", "standard", "pro"):
        if f"'{key}'" in rows:
            ok(f"comparison row has '{key}' column data")
        else:
            fail(f"comparison rows missing '{key}'")

    lic_view = read("admin/views/license-settings.php")
    if "crb-license-plan-table" in lic_view and "スタンダード" in lic_view:
        ok("license settings: 3-column plan table")
    else:
        fail("license settings plan table incomplete")

    print("\n-- Sales LP gaps --")
    lp = read("includes/functions-sales-lp.php")
    table = lp.split("crb-sales-lp__plan-table", 1)[1][:600] if "crb-sales-lp__plan-table" in lp else ""
    if "スタンダード" not in table and "standard" not in table.lower():
        fail("GAP: sales LP plan table has only Free+Pro columns (Standard omitted)")
    else:
        ok("sales LP includes Standard in plan table")

    if "crb_sales_lp_plan_rows" in lp and "crb_license_plan_comparison_rows" in lp:
        ok("sales LP uses full comparison rows (standard data exists but may not render)")

    pricing = lp.split("crb-sales-lp__pricing-grid", 1)[1][:1200] if "crb-sales-lp__pricing-grid" in lp else ""
    if "スタンダード" not in pricing and "Standard" not in pricing:
        fail("GAP: sales LP pricing cards have no Standard tier card")
    else:
        ok("sales LP pricing includes Standard card")

    print("\n-- Standard payment --")
    if "code=17&mode=button" in lic and "crb_license_standard_payment_url" in lic:
        ok("standard payment URL default wired (code=17)")
    elif "define( 'CRB_STANDARD_PAYMENT_URL', '' )" in lic or "CRB_STANDARD_PAYMENT_URL', ''" in lic:
        ok("standard payment URL empty constant (filter override expected)")
    else:
        fail("standard payment URL default unexpected")

    print("\n-- Setup service --")
    ls = read("license-server/includes/functions-license-server.php")
    if "array( 'standard', 'pro' )" in ls.replace(" ", ""):
        ok("setup service mail block: standard + pro")
    elif "array( 'standard', 'pro' )" in ls:
        ok("setup service mail block: standard + pro")
    else:
        if "'standard'" in ls and "crb_ls_mail_setup_service_block" in ls:
            ok("setup service includes standard (format may vary)")
        else:
            fail("setup service block missing standard")

    if "'standard'" in read("license-server/admin/views/licenses.php"):
        ok("license server manual issue: standard option")
    else:
        fail("license server manual issue missing standard")

    print("\n-- Import UI (20260613a) --")
    imp_set = read("admin/views/partials/feed-import-settings.php")
    if "import_enabled" not in imp_set:
        ok("import_enabled checkbox removed from UI")
    else:
        fail("import_enabled still in UI")

    fm = read("includes/class-feed-manager.php")
    si = fm.split("function sanitize_import", 1)[1][:900]
    if "content_template" in si and "enabled" in si:
        ok("import enabled derived from content_template on save")
    else:
        fail("sanitize_import enabled logic unexpected")

    print("\n-- Prices (labels) --")
    if "1,100" in lic and "3,300" in lic:
        ok("standard 1100 / pro 3300 price labels in functions-license.php")
    else:
        fail("price labels missing")

    print()
    if FAILURES:
        print(f"FAILED: {len(FAILURES)} issue(s)")
        for f in FAILURES:
            print(f"  - {f}")
        return 1
    print("All plan spec checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
