#!/usr/bin/env python3
"""運用分離 A: wp-config に CRB 定数を追加し、両サイトへプラグインをデプロイ。"""
from __future__ import annotations

import base64
import ftplib
import re
import subprocess
import sys
import xml.etree.ElementTree as ET
from io import BytesIO
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
TOOLS = BASE / "tools"
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"
FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"

AUTHORITY_WPCONFIG = "/123789.jp/public_html/custom-rss-builder/wp-config.php"
CLIENT_WPCONFIG = "/wordpress-123.com/public_html/PluginTest/wp-config.php"

AUTHORITY_SNIPPET = """
/** Custom RSS Builder: license authority site (operational separation). */
define( 'CRB_LICENSE_AUTHORITY', true );
"""

CLIENT_SNIPPET = """
/** Custom RSS Builder: client test site → license server on 123789.jp */
define( 'CRB_LICENSE_API_BASE', 'https://123789.jp/custom-rss-builder' );
"""


def load_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (srv.findtext("User") or "").strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("FTP credentials not found")


def ftp_read(ftp: ftplib.FTP, path: str) -> str:
    buf = BytesIO()
    ftp.retrbinary(f"RETR {path}", buf.write)
    return buf.getvalue().decode("utf-8", errors="replace")


def ftp_write(ftp: ftplib.FTP, path: str, content: str) -> None:
    data = content.encode("utf-8")
    ftp.storbinary(f"STOR {path}", BytesIO(data))


def patch_wp_config(content: str, marker: str, snippet: str) -> tuple[str, bool]:
    if marker in content:
        return content, False
    needle = "/* That's all, stop editing!"
    if needle not in content:
        needle = "/* That's all, stop editing! Happy publishing. */"
    if needle not in content:
        return content, False
    updated = content.replace(needle, snippet + "\n" + needle, 1)
    return updated, True


def main() -> int:
    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
    ftp.set_pasv(True)
    try:
        auth_cfg = ftp_read(ftp, AUTHORITY_WPCONFIG)
        new_auth, changed_auth = patch_wp_config(auth_cfg, "CRB_LICENSE_AUTHORITY", AUTHORITY_SNIPPET)
        if changed_auth:
            ftp_write(ftp, AUTHORITY_WPCONFIG, new_auth)
            print("OK   123789 wp-config: CRB_LICENSE_AUTHORITY added")
        else:
            print("OK   123789 wp-config: CRB_LICENSE_AUTHORITY already set")

        client_cfg = ftp_read(ftp, CLIENT_WPCONFIG)
        new_client, changed_client = patch_wp_config(client_cfg, "CRB_LICENSE_API_BASE", CLIENT_SNIPPET)
        if changed_client:
            ftp_write(ftp, CLIENT_WPCONFIG, new_client)
            print("OK   client wp-config: CRB_LICENSE_API_BASE added")
        else:
            print("OK   client wp-config: CRB_LICENSE_API_BASE already set")
    finally:
        ftp.quit()

    print("\n=== deploy plugins ===")
    for script in (BASE / "deploy_plugin_ftp.py", BASE / "tools" / "deploy_client_plugin_ftp.py"):
        rc = subprocess.call([sys.executable, str(script)], cwd=str(BASE))
        if rc != 0:
            print(f"FAIL deploy {script.name} exit={rc}", file=sys.stderr)
            return rc

    print("\nOperational separation setup complete.")
    print("Next manual steps:")
    print("  123789: Custom RSS Builder → ライセンス → 「正本サーバー設定を適用」")
    print("  client: Custom RSS Builder → ライセンス → Secret 入力 → 「REST 接続のひな形を適用」→ キー有効化")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
