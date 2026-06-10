#!/usr/bin/env python3
"""Verify UI trim: removed panels/options must not reappear in client code."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FAILURES: list[str] = []

FORBIDDEN_IN_CLIENT = (
    "html_hrefs",
    "middle_infix",
    "ai_transform_apply_preview",
    "ai_transform_apply_rss",
    "ai_transform_apply_import",
    "import_append_source",
    "元記事を読む",
    "配信・自動実行",
    "上級者向け: 正規表現",
    "続きの前に挿入",
    "HTML スロット内の href も変換",
    "crb-feed-meta-mount",
    "meta_html",
    "feed-meta-panel.php",
    "crb_apply_link_rewrite_to_html_hrefs",
    "crb_link_rewrite_is_valid_pattern",
)

CLIENT_DIRS = (ROOT / "admin", ROOT / "assets", ROOT / "includes")
CLIENT_MAIN = ROOT / "custom-rss-builder.php"


def ok(msg: str) -> None:
    print(f"OK   {msg}")


def fail(msg: str) -> None:
    FAILURES.append(msg)
    print(f"FAIL {msg}")


def iter_client_files() -> list[Path]:
    out: list[Path] = []
    for base in CLIENT_DIRS:
        if not base.is_dir():
            continue
        for path in sorted(base.rglob("*")):
            if path.is_file() and path.suffix.lower() in {".php", ".js", ".css"}:
                out.append(path)
    if CLIENT_MAIN.is_file():
        out.append(CLIENT_MAIN)
    return out


def check_ai_always_preview_import() -> None:
    path = ROOT / "includes" / "functions-ai-transform.php"
    text = path.read_text(encoding="utf-8")
    if "case 'rss':" not in text or "return false;" not in text.split("case 'rss':")[1][:80]:
        fail("AI transform: rss context must return false")
    else:
        ok("AI transform skips RSS")
    if "case 'import':" not in text or "case 'preview':" not in text:
        fail("AI transform: preview/import contexts missing")
    else:
        ok("AI transform applies on preview and import")


def check_link_rewrite_prefix_only() -> None:
    path = ROOT / "includes" / "functions-link-rewrite.php"
    text = path.read_text(encoding="utf-8")
    if "'regex'" in text or "preg_replace(" in text:
        fail("link rewrite still has regex path")
    else:
        ok("link rewrite is prefix-only")
    if "html_hrefs" in text:
        fail("link rewrite still references html_hrefs")
    else:
        ok("link rewrite has no html_hrefs")


def check_append_source_disabled() -> None:
    fm = (ROOT / "includes" / "class-feed-manager.php").read_text(encoding="utf-8")
    if "'append_source'" not in fm or "'append_source'       => false" not in fm.replace("\r\n", "\n"):
        fail("feed-manager must force append_source false on save")
    else:
        ok("append_source forced false on save")
    pi = (ROOT / "includes" / "class-post-importer.php").read_text(encoding="utf-8")
    if "元記事を読む" in pi or "append_source" in pi:
        fail("post-importer must not append source link")
    else:
        ok("post-importer does not append source link")


def main() -> int:
    print("=== verify_trim_ui ===\n")
    checked = 0
    for path in iter_client_files():
        text = path.read_text(encoding="utf-8")
        rel = path.relative_to(ROOT).as_posix()
        checked += 1
        lower = text.lower()
        for needle in FORBIDDEN_IN_CLIENT:
            if needle.lower() in lower:
                fail(f"{rel} contains {needle!r}")
    ok(f"scanned {checked} client-facing files for forbidden strings")

    if (ROOT / "admin" / "views" / "partials" / "feed-meta-panel.php").is_file():
        fail("feed-meta-panel.php must be removed")
    else:
        ok("feed-meta-panel.php absent")

    check_ai_always_preview_import()
    check_link_rewrite_prefix_only()
    check_append_source_disabled()

    m = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", (ROOT / "custom-rss-builder.php").read_text(encoding="utf-8"))
    if m:
        ok(f"CRB_BUILD_ID {m.group(1)}")

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("All trim UI checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
