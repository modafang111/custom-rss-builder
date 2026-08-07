#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Pull live PluginTest CRB-related plugins via FTP (deploy.local.json)."""
from __future__ import annotations

import ftplib
import json
import re
from pathlib import Path

_CFG_CANDIDATES = (
    Path(__file__).resolve().parents[1] / "env" / "deploy.local.json",
    Path(__file__).resolve().parents[2] / "deploy.local.json",
    Path(r"D:\dev\custom-rss-builder\deploy.local.json"),
    Path(r"D:\dev\ai_spec_builder\plugins\deploy.local.json"),
    Path(r"E:\ai_spec_builder\plugins\deploy.local.json"),
)
CFG_PATH = next((p for p in _CFG_CANDIDATES if p.is_file()), _CFG_CANDIDATES[0])
OUT_ROOT = Path(__file__).resolve().parents[1] / "site_snapshot" / "plugins"
SKIP = {".DS_Store", "Thumbs.db", "Thumbs.db:encryptable"}
TARGETS = {
    "custom-rss-builder": "/wordpress-123.com/public_html/PluginTest/wp-content/plugins/custom-rss-builder",
    "crb-id-split": "/wordpress-123.com/public_html/PluginTest/wp-content/plugins/crb-id-split",
    "crb-title-redirect": "/wordpress-123.com/public_html/PluginTest/wp-content/plugins/crb-title-redirect",
}


def list_files(ftp: ftplib.FTP, remote_dir: str) -> list[str]:
    files: list[str] = []
    try:
        entries = list(ftp.mlsd(remote_dir))
        for name, facts in entries:
            if name in (".", "..") or name in SKIP:
                continue
            child = f"{remote_dir.rstrip('/')}/{name}"
            if facts.get("type") == "dir":
                files.extend(list_files(ftp, child))
            else:
                files.append(child)
        return files
    except ftplib.error_perm:
        pass

    try:
        names = ftp.nlst(remote_dir)
    except ftplib.error_perm as exc:
        print("list fail", remote_dir, exc)
        return files

    for name in names:
        base = name.rsplit("/", 1)[-1]
        if base in (".", "..") or base in SKIP:
            continue
        child = name if name.startswith("/") else f"{remote_dir.rstrip('/')}/{base}"
        try:
            ftp.size(child)
            files.append(child)
        except Exception:
            files.extend(list_files(ftp, child))
    return files


def main() -> int:
    cfg = json.loads(CFG_PATH.read_text(encoding="utf-8"))
    ftp = ftplib.FTP()
    ftp.connect(cfg["host"], int(cfg.get("port", 21)), timeout=60)
    ftp.login(cfg["user"], cfg["password"])
    ftp.encoding = "utf-8"
    print("FTP OK", cfg["host"])

    OUT_ROOT.mkdir(parents=True, exist_ok=True)
    for slug, remote in TARGETS.items():
        local_base = OUT_ROOT / slug
        local_base.mkdir(parents=True, exist_ok=True)
        files = list_files(ftp, remote)
        print(f"{slug}: {len(files)} files")
        for rpath in files:
            rel = rpath[len(remote) :].lstrip("/")
            lpath = local_base / rel
            lpath.parent.mkdir(parents=True, exist_ok=True)
            with open(lpath, "wb") as fh:
                ftp.retrbinary(f"RETR {rpath}", fh.write)

        for main_name in (
            "custom-rss-builder.php",
            "crb-id-split.php",
            "crb-title-redirect.php",
        ):
            main = local_base / main_name
            if not main.is_file():
                continue
            text = main.read_text(encoding="utf-8", errors="replace")
            for line in text.splitlines()[:20]:
                if "Version:" in line or "Plugin Name:" in line:
                    print(" ", line.strip())
            m = re.search(r"Version:\s*(\S+)", text)
            if m:
                print("  parsed_version", m.group(1))

    ftp.quit()
    print("DONE ->", OUT_ROOT)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
