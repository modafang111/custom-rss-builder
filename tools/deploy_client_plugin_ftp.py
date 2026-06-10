#!/usr/bin/env python3
"""wordpress-123.com (PluginTest) へ client ZIP 相当の内容を FTP デプロイ。"""
from __future__ import annotations

import base64
import ftplib
import importlib.util
import re
import shutil
import sys
import tempfile
import time
import xml.etree.ElementTree as ET
from io import BytesIO
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"

FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"
CLIENT_REMOTE = (
    "/wordpress-123.com/public_html/PluginTest/wp-content/plugins/custom-rss-builder"
)

SKIP_NAMES = {".DS_Store", "Thumbs.db"}

# 旧フルツリーデプロイで残りうるパス（client ZIP には含めない）
PRUNE_REMOTE_PATHS = (
    "license-server",
    "tools",
    "test-fixture",
    "tests",
    "includes/class-custom-rss-builder-authority.php",
    "crb_acceptance_probe.php",
)


def load_build_module():
    spec = importlib.util.spec_from_file_location(
        "build_plugin_dist", BASE / "build_plugin_dist.py"
    )
    if spec is None or spec.loader is None:
        raise RuntimeError("build_plugin_dist.py not found")
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def should_skip(path: Path) -> bool:
    return path.name in SKIP_NAMES or path.name.startswith(".")


def load_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (
            srv.findtext("User") or ""
        ).strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("FTP credentials not found")


def ftp_makedirs(ftp: ftplib.FTP, remote_dir: str) -> None:
    parts = [p for p in remote_dir.split("/") if p]
    path = ""
    for part in parts:
        path += "/" + part
        try:
            ftp.mkd(path)
        except ftplib.error_perm:
            pass


def ftp_delete_recursive(ftp: ftplib.FTP, remote_path: str) -> None:
    """リモートのファイルまたはディレクトリを再帰削除。"""
    try:
        ftp.delete(remote_path)
        print(f"  DEL {remote_path}")
        return
    except ftplib.error_perm:
        pass

    try:
        names = ftp.nlst(remote_path)
    except (ftplib.error_perm, ftplib.error_temp):
        return

    for name in names:
        base = name.rsplit("/", 1)[-1]
        if base in (".", ".."):
            continue
        child = name if "/" in name else f"{remote_path}/{base}"
        if child == remote_path:
            continue
        ftp_delete_recursive(ftp, child)

    try:
        ftp.rmd(remote_path)
        print(f"  RMD {remote_path}")
    except ftplib.error_perm:
        pass


def prune_remote_client_extras(ftp: ftplib.FTP) -> None:
    print("=== prune remote (non-client paths) ===")
    for rel in PRUNE_REMOTE_PATHS:
        remote_path = f"{CLIENT_REMOTE}/{rel}".replace("//", "/")
        ftp_delete_recursive(ftp, remote_path)


def upload_tree(ftp: ftplib.FTP, local: Path, remote: str) -> int:
    count = 0
    for item in sorted(local.rglob("*")):
        if item.is_dir() or should_skip(item):
            continue
        rel = item.relative_to(local).as_posix()
        remote_path = f"{remote}/{rel}".replace("//", "/")
        remote_parent = "/".join(remote_path.split("/")[:-1])
        if remote_parent:
            ftp_makedirs(ftp, remote_parent)
        with item.open("rb") as f:
            ftp.storbinary(f"STOR {remote_path}", f)
        count += 1
        print(f"  OK {rel}")
    return count


def touch_remote_files(ftp: ftplib.FTP, remote: str, local: Path) -> None:
    ts = time.strftime("%Y%m%d%H%M%S", time.gmtime())
    for file_path in local.rglob("*"):
        if file_path.is_dir() or should_skip(file_path):
            continue
        rel = file_path.relative_to(local).as_posix()
        remote_path = f"{remote}/{rel}".replace("//", "/")
        try:
            ftp.sendcmd(f"MDTM {ts} {remote_path}")
        except ftplib.error_perm:
            pass


def ftp_read(ftp: ftplib.FTP, path: str) -> str:
    buf = BytesIO()
    ftp.retrbinary(f"RETR {path}", buf.write)
    return buf.getvalue().decode("utf-8", errors="replace")


def ftp_exists(ftp: ftplib.FTP, path: str) -> bool:
    try:
        ftp.size(path)
        return True
    except ftplib.error_perm:
        pass
    try:
        ftp.cwd(path)
        ftp.cwd("/")
        return True
    except ftplib.error_perm:
        return False


def verify_remote(ftp: ftplib.FTP, expected_build: str) -> bool:
    ok = True
    main_php = ftp_read(ftp, f"{CLIENT_REMOTE}/custom-rss-builder.php")
    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    build_id = build.group(1) if build else ""
    print(f"REMOTE BUILD_ID: {build_id or 'NOT FOUND'}")
    if build_id != expected_build:
        ok = False

    if "CRB_PACKAGE_VARIANT', 'client'" not in main_php:
        print("FAIL: CRB_PACKAGE_VARIANT is not client")
        ok = False
    else:
        print("OK: CRB_PACKAGE_VARIANT=client")

    ls_bootstrap = f"{CLIENT_REMOTE}/license-server/bootstrap.php"
    if ftp_exists(ftp, ls_bootstrap):
        print("FAIL: license-server still on remote")
        ok = False
    else:
        print("OK: no license-server on remote")

    partial = ftp_read(ftp, f"{CLIENT_REMOTE}/admin/views/partials/feed-import-settings.php")
    if re.search(r'crb-import-content-template"[^>]*placeholder\s*=', partial, re.I):
        print("FAIL: placeholder still on content textarea")
        ok = False
    else:
        print("OK: no placeholder")

    if "crb-build-stamp" not in partial:
        print("FAIL: missing build stamp in UI")
        ok = False
    else:
        print("OK: build stamp present")

    sanitize = ftp_read(ftp, f"{CLIENT_REMOTE}/includes/functions-sanitize.php")
    if "crb_import_content_template_for_ui" not in sanitize:
        print("FAIL: missing sanitize guard")
        ok = False
    else:
        print("OK: sanitize guard present")

    css = ftp_read(ftp, f"{CLIENT_REMOTE}/includes/functions-css.php")
    slot = re.search(
        r"define\s*\(\s*'CRB_RECORD_SLOT_COUNT'\s*,\s*(\d+)\s*\)",
        css,
    )
    slot_n = slot.group(1) if slot else "?"
    print(f"REMOTE CRB_RECORD_SLOT_COUNT: {slot_n}")
    if slot_n != "20":
        print("FAIL: expected CRB_RECORD_SLOT_COUNT=20")
        ok = False
    else:
        print("OK: pro slot count default 20")

    return ok


def main() -> int:
    bpd = load_build_module()
    plugin_dir = bpd.PLUGIN_DIR
    if not plugin_dir.is_dir():
        print(f"Missing: {plugin_dir}", file=sys.stderr)
        return 1

    main_local = plugin_dir / bpd.MAIN_FILE
    local_build = "?"
    if main_local.is_file():
        m = re.search(
            r"CRB_BUILD_ID',\s*'([^']+)'",
            main_local.read_text(encoding="utf-8"),
        )
        if m:
            local_build = m.group(1)

    print(f"Local BUILD_ID: {local_build}")
    print(f"Deploy to: {CLIENT_REMOTE}")
    print("Staging client package (same as custom-rss-builder-client.zip)...")

    stage_root = Path(tempfile.mkdtemp(prefix="crb-client-deploy-"))
    stage_dir = stage_root / bpd.PLUGIN_SLUG
    try:
        count, rels = bpd.materialize_variant("client", stage_dir)
        print(f"Staged {count} files")

        pw = load_password()
        ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
        ftp.set_pasv(True)
        try:
            ftp.cwd(CLIENT_REMOTE)
        except ftplib.error_perm:
            ftp_makedirs(ftp, CLIENT_REMOTE)
            ftp.cwd(CLIENT_REMOTE)

        prune_remote_client_extras(ftp)
        n = upload_tree(ftp, stage_dir, CLIENT_REMOTE)
        touch_remote_files(ftp, CLIENT_REMOTE, stage_dir)
        print(f"Uploaded {n} files")
        print("\n=== verify ===")
        if not verify_remote(ftp, local_build):
            ftp.quit()
            return 2
        ftp.quit()
    finally:
        shutil.rmtree(stage_root, ignore_errors=True)

    print("\n=== archive snapshot ===")
    if local_build and local_build != "?":
        try:
            sys.path.insert(0, str(BASE / "tools"))
            from record_build_snapshot import record_client_snapshot  # noqa: WPS433

            archive_path = record_client_snapshot(local_build, force=False)
            if archive_path:
                print(f"Archived: {archive_path}")
            else:
                print(f"Archive already exists for BUILD_ID {local_build} (skipped)")
        except Exception as exc:  # noqa: BLE001
            print(f"WARNING: archive snapshot failed: {exc}", file=sys.stderr)
    else:
        print("WARNING: BUILD_ID unknown; archive skipped", file=sys.stderr)

    print("\nClient deploy OK (client ZIP equivalent).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
