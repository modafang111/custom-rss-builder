#!/usr/bin/env python3
"""Custom RSS Builder — BUILD_ID / Git tag / archive パスの共通ヘルパー。"""
from __future__ import annotations

import importlib.util
import json
import re
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
PLUGIN_MAIN = BASE / "custom-rss-builder" / "custom-rss-builder" / "custom-rss-builder.php"
ARCHIVES_DIR = BASE / "dist" / "archives"
MANIFEST_PATH = ARCHIVES_DIR / "manifest.jsonl"
PLUGIN_SLUG = "custom-rss-builder"


def load_build_module():
    spec = importlib.util.spec_from_file_location(
        "build_plugin_dist", BASE / "build_plugin_dist.py"
    )
    if spec is None or spec.loader is None:
        raise RuntimeError("build_plugin_dist.py not found")
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def read_plugin_constants() -> dict[str, str]:
    if not PLUGIN_MAIN.is_file():
        raise FileNotFoundError(f"Missing plugin main: {PLUGIN_MAIN}")
    text = PLUGIN_MAIN.read_text(encoding="utf-8")
    version = re.search(r"define\s*\(\s*'CRB_VERSION'\s*,\s*'([^']+)'\s*\)", text)
    build = re.search(r"define\s*\(\s*'CRB_BUILD_ID'\s*,\s*'([^']+)'\s*\)", text)
    if not version or not build:
        raise RuntimeError("CRB_VERSION or CRB_BUILD_ID not found in custom-rss-builder.php")
    return {
        "version": version.group(1),
        "build_id": build.group(1),
    }


def tag_name(build_id: str) -> str:
    return f"build-{build_id}"


def client_archive_path(build_id: str) -> Path:
    return ARCHIVES_DIR / f"{PLUGIN_SLUG}-client-{build_id}.zip"


def authority_archive_path(build_id: str) -> Path:
    return ARCHIVES_DIR / f"{PLUGIN_SLUG}-license-server-{build_id}.zip"


def ensure_archives_dir() -> Path:
    ARCHIVES_DIR.mkdir(parents=True, exist_ok=True)
    return ARCHIVES_DIR


def append_manifest(record: dict[str, str]) -> None:
    ensure_archives_dir()
    line = json.dumps(record, ensure_ascii=False)
    with MANIFEST_PATH.open("a", encoding="utf-8") as fh:
        fh.write(line + "\n")


def git_root() -> Path | None:
    try:
        out = subprocess.check_output(
            ["git", "rev-parse", "--show-toplevel"],
            cwd=BASE,
            stderr=subprocess.DEVNULL,
            text=True,
        ).strip()
    except (subprocess.CalledProcessError, FileNotFoundError):
        return None
    return Path(out) if out else None


def git_status_porcelain() -> str:
    root = git_root()
    if root is None:
        return ""
    try:
        return subprocess.check_output(
            ["git", "status", "--porcelain"],
            cwd=root,
            stderr=subprocess.DEVNULL,
            text=True,
        )
    except (subprocess.CalledProcessError, FileNotFoundError):
        return ""


def git_tag_exists(name: str) -> bool:
    root = git_root()
    if root is None:
        return False
    try:
        subprocess.check_call(
            ["git", "rev-parse", "--verify", f"refs/tags/{name}"],
            cwd=root,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
        return True
    except (subprocess.CalledProcessError, FileNotFoundError):
        return False


def utc_now_iso() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


def eprint(*args: object) -> None:
    print(*args, file=sys.stderr)
