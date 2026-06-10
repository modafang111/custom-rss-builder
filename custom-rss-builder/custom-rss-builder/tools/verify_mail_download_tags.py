#!/usr/bin/env python3
"""ライセンスメールのダウンロード URL タグ（設定連動）の静的検証。"""
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def main() -> int:
    print("=== verify_mail_download_tags ===\n")
    fail = 0

    def ok(msg: str) -> None:
        print(f"OK   {msg}")

    def fail_msg(msg: str) -> None:
        nonlocal fail
        print(f"FAIL {msg}")
        fail += 1

    ls = read("license-server/includes/functions-license-server.php")
    admin = read("license-server/admin/class-admin.php")
    view = read("license-server/admin/views/settings.php")

    for fn in (
        "function crb_ls_mail_install_manual_block",
        "function crb_ls_mail_activation_block",
        "function crb_ls_mail_default_body_template",
        "function crb_ls_apply_recommended_mail_defaults",
    ):
        if fn not in ls:
            fail_msg(f"missing {fn}")
        else:
            ok(fn)

    if "{install_manual_block}" not in ls or "{activation_block}" not in ls:
        fail_msg("default mail template missing install/activation blocks")
    else:
        ok("default template includes install_manual_block and activation_block")

    for fn in (
        "function crb_ls_mail_download_url",
        "function crb_ls_mail_download_password",
        "function crb_ls_mail_download_block",
        "function crb_ls_mail_tag_map",
        "function crb_ls_mail_apply_tags",
    ):
        if fn not in ls:
            fail_msg(f"missing {fn}")
        else:
            ok(fn)

    if "{download_block}" not in ls:
        fail_msg("default mail template missing {download_block}")
    else:
        ok("default template includes {download_block}")

    for key in (
        "mail_download_url",
        "mail_download_password",
        "mail_download_heading",
    ):
        if key not in admin or key not in view:
            fail_msg(f"settings UI/save missing {key}")
        else:
            ok(f"settings field {key}")

    if "mail_body_preview" not in view or "crb_ls_mail_body" not in view:
        fail_msg("settings page missing mail preview")
    else:
        ok("mail preview on settings page")

    print(f"\n{'FAIL' if fail else 'PASS'} ({fail} failures)")
    return 1 if fail else 0


if __name__ == "__main__":
    raise SystemExit(main())
