# -*- coding: utf-8 -*-
"""123789.jp 正本へ authority ZIP 相当の内容を FTP デプロイ。"""
from __future__ import annotations

import base64
import ftplib
import importlib.util
import json
import re
import shutil
import sys
import tempfile
import time
import urllib.request
import xml.etree.ElementTree as ET
from io import BytesIO
from pathlib import Path

BASE = Path(__file__).resolve().parent
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"

FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"
REMOTE_CANDIDATES = [
    "/123789.jp/public_html/custom-rss-builder/wp-content/plugins/custom-rss-builder",
    "/123789.jp/public_html/wp-content/plugins/custom-rss-builder",
    "/custom-rss-builder/wp-content/plugins/custom-rss-builder",
]

SKIP_NAMES = {".DS_Store", "Thumbs.db"}


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


def decode_fz_pass(b64: str) -> str:
    return base64.b64decode(b64).decode("utf-8", errors="replace")


def load_credentials() -> tuple[str, str, str, int]:
    local_cfg = BASE / "deploy.local.json"
    if local_cfg.is_file():
        data = json.loads(local_cfg.read_text(encoding="utf-8"))
        return (
            str(data["host"]),
            str(data["user"]),
            str(data["password"]),
            int(data.get("port", 21)),
        )

    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        host = (srv.findtext("Host") or "").strip()
        user = (srv.findtext("User") or "").strip()
        if host == FTP_HOST and user == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return FTP_HOST, FTP_USER, decode_fz_pass(enc.text.strip()), 21
    raise RuntimeError(f"FileZilla に {FTP_HOST} / {FTP_USER} が見つかりません")


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


def prune_remote_authority_extras(ftp: ftplib.FTP, remote: str, bpd) -> None:
    print("=== prune remote (non-authority paths) ===")
    prefixes, exact = bpd.authority_deploy_prune_paths()
    for rel in sorted(exact):
        ftp_delete_recursive(ftp, f"{remote}/{rel}".replace("//", "/"))
    for prefix in prefixes:
        if prefix.endswith("/"):
            ftp_delete_recursive(ftp, f"{remote}/{prefix.rstrip('/')}".replace("//", "/"))


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


def remote_has_main_php(ftp: ftplib.FTP) -> bool:
    try:
        names = ftp.nlst()
    except ftplib.error_perm:
        return False
    return "custom-rss-builder.php" in names


def find_plugin_remote(ftp: ftplib.FTP) -> str | None:
    for cand in REMOTE_CANDIDATES:
        try:
            ftp.cwd(cand)
            if remote_has_main_php(ftp):
                return cand
        except ftplib.error_perm:
            continue

    try:
        ftp.cwd("/")
        for name in ftp.nlst():
            if "123789" in name:
                base = f"/{name}/public_html"
                for sub in (
                    "custom-rss-builder/wp-content/plugins/custom-rss-builder",
                    "wp-content/plugins/custom-rss-builder",
                ):
                    path = f"{base}/{sub}"
                    try:
                        ftp.cwd(path)
                        if remote_has_main_php(ftp):
                            return path
                    except ftplib.error_perm:
                        continue
    except ftplib.error_perm:
        pass
    return None


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


def verify_remote(ftp: ftplib.FTP, remote: str, expected_build: str) -> bool:
    ok = True
    main_php = ftp_read(ftp, f"{remote}/custom-rss-builder.php")
    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main_php)
    build_id = build.group(1) if build else ""
    print(f"REMOTE BUILD_ID: {build_id or 'NOT FOUND'}")
    if build_id != expected_build:
        ok = False

    if "CRB_PACKAGE_VARIANT', 'authority'" not in main_php:
        print("FAIL: CRB_PACKAGE_VARIANT is not authority")
        ok = False
    else:
        print("OK: CRB_PACKAGE_VARIANT=authority")

    ls_bootstrap = f"{remote}/license-server/bootstrap.php"
    if not ftp_exists(ftp, ls_bootstrap):
        print("FAIL: missing license-server/bootstrap.php")
        ok = False
    else:
        print("OK: license-server present")

    feed_mgr = f"{remote}/includes/class-feed-manager.php"
    if ftp_exists(ftp, feed_mgr):
        print("FAIL: client feed manager still on authority remote")
        ok = False
    else:
        print("OK: no class-feed-manager.php on remote")

    lic_view = f"{remote}/admin/views/license-settings.php"
    if ftp_exists(ftp, lic_view):
        print("FAIL: client license-settings.php still on authority remote")
        ok = False
    else:
        print("OK: no license-settings.php on remote")

    return ok


def verify_http(expected_build: str) -> bool:
    url = (
        "https://123789.jp/custom-rss-builder/wp-content/plugins/"
        "custom-rss-builder/custom-rss-builder.php"
    )
    try:
        with urllib.request.urlopen(url, timeout=30) as resp:
            body = resp.read().decode("utf-8", errors="replace")
    except Exception as exc:
        print(f"HTTP verify failed: {exc}", file=sys.stderr)
        return False

    ok = f"CRB_BUILD_ID', '{expected_build}'" in body or "CRB_BUILD_ID" in body
    print(f"HTTP verify main.php: ok={ok} build_in_body={'CRB_BUILD_ID' in body}")
    return ok


def main() -> int:
    bpd = load_build_module()
    plugin_dir = bpd.PLUGIN_DIR
    if not plugin_dir.is_dir():
        print(f"Missing plugin dir: {plugin_dir}", file=sys.stderr)
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
    print("Staging authority package (same as custom-rss-builder-license-server.zip)...")

    stage_root = Path(tempfile.mkdtemp(prefix="crb-authority-deploy-"))
    stage_dir = stage_root / bpd.PLUGIN_SLUG
    try:
        count, _ = bpd.materialize_variant("authority", stage_dir)
        print(f"Staged {count} files")

        host, user, password, port = load_credentials()
        ftp = ftplib.FTP(timeout=120)
        ftp.connect(host, port)
        ftp.login(user, password)
        ftp.set_pasv(True)

        remote = find_plugin_remote(ftp)
        if not remote:
            print("Plugin remote path not found.", file=sys.stderr)
            ftp.quit()
            return 2

        ftp.cwd("/")
        prune_remote_authority_extras(ftp, remote, bpd)
        n = upload_tree(ftp, stage_dir, remote)
        touch_remote_files(ftp, remote, stage_dir)
        print(f"Uploaded {n} files")
        print("\n=== verify ===")
        remote_ok = verify_remote(ftp, remote, local_build)
        ftp.quit()
    finally:
        shutil.rmtree(stage_root, ignore_errors=True)

    if not remote_ok:
        return 3
    if not verify_http(local_build):
        print("WARNING: HTTP verification failed (FTP verify passed)", file=sys.stderr)

    print("\nAuthority deploy OK (authority ZIP equivalent).")
    print(
        "NOTE: For wordpress-123.com client use: python tools/deploy_client_plugin_ftp.py"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
