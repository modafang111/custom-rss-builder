#!/usr/bin/env python3
"""PluginTest リモートの Standard 決済 URL を確認。"""
from __future__ import annotations

import ftplib
import json
import re
from io import BytesIO
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
REMOTE = "/wordpress-123.com/public_html/PluginTest/wp-content/plugins/custom-rss-builder"


def ftp_read(ftp: ftplib.FTP, path: str) -> str:
    buf = BytesIO()
    ftp.retrbinary(f"RETR {path}", buf.write)
    return buf.getvalue().decode("utf-8", errors="replace")


def main() -> int:
    cfg = json.loads((BASE / "deploy.local.json").read_text(encoding="utf-8"))
    ftp = ftplib.FTP()
    ftp.connect(cfg["host"], cfg.get("port", 21), timeout=60)
    ftp.login(cfg["user"], cfg["password"])
    ftp.set_pasv(True)

    main_php = ftp_read(ftp, f"{REMOTE}/custom-rss-builder.php")
    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    print(f"BUILD_ID: {build.group(1) if build else '?'}")

    lic = ftp_read(ftp, f"{REMOTE}/includes/functions-license.php")
    print(f"code=17 in functions-license.php: {'code=17' in lic}")
    m = re.search(
        r"apply_filters\s*\(\s*'crb_standard_payment_url'\s*,\s*'([^']*)'",
        lic,
    )
    print(f"standard default URL: {m.group(1) if m else 'NOT FOUND'}")

    view = ftp_read(ftp, f"{REMOTE}/admin/views/license-settings.php")
    print(f"show_client_free_upgrade_ctas logic present: {'show_client_free_upgrade_ctas' in view}")
    print(f"スタンダードを申し込む in view: {'スタンダードを申し込む' in view}")

    ftp.quit()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
