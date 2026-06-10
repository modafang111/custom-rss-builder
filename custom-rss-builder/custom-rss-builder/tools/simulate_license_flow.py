#!/usr/bin/env python3
"""
ライセンスフロー静的シミュレーション（通し確認用）。

想定フロー（同一 WordPress / 123789.jp）:
  1. 管理画面を開く → 無料ライセンス自動有効化（未設定時）
  2. フィード編集 → スロット {%1%}〜{%3%} のみ UI 表示
  3. 保存 → usable + free なら成功、{%4%}+ は保存時にクリア
  4. Pro 手動発行 → 有効化 → スロット 12、取り込み・cron 可
"""
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FAIL = 0


def ok(msg: str) -> None:
    print(f"  OK  {msg}")


def fail(msg: str) -> None:
    global FAIL
    FAIL += 1
    print(f"  FAIL {msg}")


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def step(title: str) -> None:
    print(f"\n=== {title} ===")


def main() -> int:
    print("CRB license flow simulation (static)\n")

    step("1. 起動・自動無料化")
    main_php = read("custom-rss-builder.php")
    lic = read("includes/functions-license.php")
    if "crb_license_ensure_active" not in main_php:
        fail("plugins_loaded hook for crb_license_ensure_active")
    else:
        ok("plugins_loaded → crb_license_ensure_active")
    if "crb_license_is_authoritative_server()" not in lic.split("function crb_license_ensure_active", 1)[1].split("function crb_license_maybe_ensure_local_free", 1)[0]:
        fail("ensure_active gated by authoritative server")
    else:
        ok("auto free only on authoritative embedded server")

    for needle in (
        "crb_license_setup_free_local",
        "crb_license_is_authoritative_server",
        "CRB_LICENSE_FREE_SLOT_LIMIT', 3",
        "crb_license_apply_slot_limits",
        "crb_license_get_record_slot_count",
    ):
        if needle not in lic:
            fail(f"missing {needle}")
        else:
            ok(needle)

    step("2. 有効化（HTTP 不要・local）")
    client = read("includes/class-license-client.php")
    if "request_local" not in client:
        fail("local license client")
    else:
        ok("activate/check via request_local on same site")

    step("3. 無料プランのゲート")
    if "case 'save':" not in lic or "case 'create_feed':" not in lic:
        fail("save/create_feed gates in crb_license_can")
    else:
        ok("free: save/rss/discover/preview/import/cron allowed; feed count limited")
    if "case 'import_posts':" in lic:
        imp_block = lic.split("case 'import_posts':", 1)[1].split("case 'create_feed':", 1)[0]
        if "return false" in imp_block:
            fail("import_posts should be allowed on free")
        else:
            ok("import_posts allowed on free plan")

    step("4. スロット 3 つ制限")
    if "crb_license_apply_slot_limits" not in read("includes/functions-css.php"):
        fail("sanitize_css applies slot limits")
    else:
        ok("save clamps {%4%}+ on free plan")

    edit = read("admin/views/edit-feed.php")
    if "crb_max_slot_index" not in edit or "CRB_LICENSE_FREE_SLOT_LIMIT" not in edit:
        fail("edit-feed filters slot rows for free")
    else:
        ok("edit UI hides {%4%}+ rows on free")

    js = read("assets/js/admin.js")
    if "crbAdmin.maxSlotCount" not in js:
        fail("admin.js uses maxSlotCount")
    else:
        ok("discover dropdown respects maxSlotCount")

    admin = read("admin/class-admin-page.php")
    if "maxSlotCount" not in admin:
        fail("wp_localize_script maxSlotCount")
    else:
        ok("maxSlotCount passed to JS")

    step("5. Pro へのアップグレード")
    lic = read("includes/functions-license.php")
    if "CRB_LICENSE_PRO_FEED_LIMIT" not in lic:
        fail("missing CRB_LICENSE_PRO_FEED_LIMIT")
    elif ", 10 );" not in lic.split("CRB_LICENSE_PRO_FEED_LIMIT", 1)[1][:30]:
        fail("pro feed limit should be 10")
    else:
        ok("pro feed limit constant = 10")
    pro_gate = lic.split("'pro' === $plan", 1)[1].split("'free' !== $plan", 1)[0]
    if "CRB_LICENSE_PRO_FEED_LIMIT" not in pro_gate or "create_feed" not in pro_gate:
        fail("pro plan should gate create_feed by feed limit")
    elif "case 'save':" not in pro_gate:
        fail("pro plan should gate save when over feed limit")
    else:
        ok("pro plan gates create_feed and save by feed limit")
    ls_lic = read("license-server/admin/views/licenses.php")
    if 'value="pro"' not in ls_lic:
        fail("manual issue Pro option")
    else:
        ok("ライセンス管理 → 手動発行 Pro")
    if 'value="standard"' not in ls_lic:
        fail("manual issue Standard option")
    else:
        ok("ライセンス管理 → 手動発行 Standard")

    step("5b. スタンダードプランのゲート")
    if "CRB_LICENSE_STANDARD_FEED_LIMIT" not in lic:
        fail("missing CRB_LICENSE_STANDARD_FEED_LIMIT")
    elif ", 3 );" not in lic.split("CRB_LICENSE_STANDARD_FEED_LIMIT", 1)[1][:30]:
        fail("standard feed limit should be 3")
    else:
        ok("standard feed limit constant = 3")
    if "CRB_LICENSE_STANDARD_SLOT_LIMIT" not in lic:
        fail("missing CRB_LICENSE_STANDARD_SLOT_LIMIT")
    elif ", 5 );" not in lic.split("CRB_LICENSE_STANDARD_SLOT_LIMIT", 1)[1][:30]:
        fail("standard slot limit should be 5")
    else:
        ok("standard slot limit constant = 5")
    if "'standard' === $plan" not in lic:
        fail("standard plan gate missing in crb_license_can")
    else:
        std_gate = lic.split("'standard' === $plan", 1)[1].split("'free' !== $plan", 1)[0]
        if "CRB_LICENSE_STANDARD_FEED_LIMIT" not in std_gate or "ai_transform" not in std_gate:
            fail("standard should gate feeds and block ai_transform")
        else:
            ok("standard gates create_feed/save and blocks ai_transform")
    if "crb_license_standard_payment_url" not in lic:
        fail("missing crb_license_standard_payment_url()")
    else:
        ok("standard payment URL hook present")

    view = read("admin/views/license-settings.php")
    if "test_run_ensure" not in view or "crb_license_is_authoritative_server" not in read("includes/functions-license.php"):
        fail("license settings UI / authority gate")
    else:
        ok("ライセンス画面: 正本サーバーのみテスト用無料発行")

    step("6. cron 取り込み")
    imp = read("public/class-import-endpoint.php")
    if "cron_import" not in imp:
        fail("import endpoint license check")
    else:
        ok("import endpoint checks cron_import license")
    lic = read("includes/functions-license.php")
    if "case 'cron_import':" in lic:
        block = lic.split("case 'cron_import':", 1)[1].split("case 'create_feed':", 1)[0]
        if "return false" in block:
            fail("cron_import should be allowed on free")
        else:
            ok("cron_import allowed on free plan")
    imp_set = read("admin/views/partials/feed-import-settings.php")
    if "import_schedule_hours" in imp_set and "import_schedule\"" not in imp_set:
        ok("import interval is number input (hours)")
    else:
        fail("feed-import-settings should use import_schedule_hours input")
    if "15_minutes" in imp_set or "30_minutes" in imp_set:
        fail("minute intervals should not appear in import settings UI")
    else:
        ok("import settings UI has no minute intervals")
    if "CRB_LICENSE_FREE_IMPORT_SCHEDULE_MIN_HOURS" not in lic:
        fail("missing CRB_LICENSE_FREE_IMPORT_SCHEDULE_MIN_HOURS")
    elif "24" not in lic.split("CRB_LICENSE_FREE_IMPORT_SCHEDULE_MIN_HOURS", 1)[1][:20]:
        fail("free import min hours should be 24")
    else:
        ok("free plan import min hours = 24")
    if "crb_license_import_schedule_min_hours" not in lic:
        fail("missing crb_license_import_schedule_min_hours()")
    else:
        ok("plan-aware import schedule min helper present")
    sched = read("includes/functions-import-schedule.php")
    if "crb_import_schedule_effective_hours" not in sched:
        fail("missing crb_import_schedule_effective_hours()")
    else:
        ok("effective import schedule hours helper present")
    if "schedule_plan_min" not in imp_set:
        fail("feed-import-settings should use plan-specific schedule min")
    else:
        ok("import settings UI uses plan-specific min")
    if "import_schedule_auto" not in imp_set:
        fail("feed-import-settings missing free plan import_schedule_auto toggle")
    elif "crb-import-schedule-field--free" not in imp_set:
        fail("feed-import-settings missing free plan fixed schedule UI")
    else:
        ok("free plan uses fixed 24h schedule UI")
    if "crb_import_schedule_hours_from_request" not in sched:
        fail("missing crb_import_schedule_hours_from_request()")
    else:
        ok("import schedule hours from request helper present")

    print()
    if FAIL:
        print(f"Simulation failed: {FAIL} issue(s)")
        return 1
    for needle in (
        "crb_license_prepare_request",
        "crb_license_apply_feed_limits",
        "crb_get_effective_slot_count",
    ):
        if needle not in read("includes/functions-license.php") and needle not in read("includes/functions-css.php"):
            fail(f"missing {needle}")
        else:
            ok(needle)

    if "crb_license_update_settings" in read("admin/class-admin-license.php") and "setup_free" in read("admin/class-admin-license.php"):
        body = read("admin/class-admin-license.php")
        if "license_key' => $key" in body and "case 'setup_free':" in body:
            # setup_free must not wipe key via blank POST — handler should not set key before switch on setup_free only
            setup_idx = body.find("case 'setup_free':")
            activate_idx = body.find("case 'activate':")
            if setup_idx > -1 and activate_idx > -1:
                between = body[setup_idx:activate_idx]
                if "crb_license_update_settings" in between:
                    fail("setup_free must not call crb_license_update_settings with POST key before activate")
                else:
                    ok("setup_free does not wipe license key")
    print("Simulation passed. Deploy BUILD 20260601a and test in WP admin.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
