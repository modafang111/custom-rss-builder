#!/usr/bin/env python3
"""Version management tooling checks (no network)."""
from __future__ import annotations

import sys
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
FAILURES: list[str] = []


def ok(msg: str) -> None:
    print(f"OK   {msg}")


def fail(msg: str) -> None:
    FAILURES.append(msg)
    print(f"FAIL {msg}")


def read(rel: str) -> str:
    return (BASE / rel).read_text(encoding="utf-8")


def main() -> int:
    print("=== verify_versioning_setup ===\n")

    for rel in (
        "tools/versioning.py",
        "tools/record_build_snapshot.py",
        "tools/tag_build.py",
        "tools/deploy_client_from_archive.py",
        "dist/archives/README.md",
    ):
        if not (BASE / rel).is_file():
            fail(f"missing {rel}")
        else:
            ok(f"present {rel}")

    packaging = read("custom-rss-builder/PACKAGING.md")
    for needle in ("バージョン管理", "record_build_snapshot", "deploy_client_from_archive", "tag_build"):
        if needle not in packaging:
            fail(f"PACKAGING.md missing {needle!r}")
            break
    else:
        ok("PACKAGING.md documents versioning workflow")

    deploy = read("tools/deploy_client_plugin_ftp.py")
    if "archive snapshot" not in deploy:
        fail("deploy_client_plugin_ftp.py does not archive after deploy")
    else:
        ok("deploy archives snapshot after success")

    gitignore = read(".gitignore")
    if "manifest.jsonl" not in gitignore:
        fail(".gitignore should exclude dist/archives/manifest.jsonl")
    else:
        ok(".gitignore excludes local manifest")

    sys.path.insert(0, str(BASE / "tools"))
    try:
        from versioning import read_plugin_constants, tag_name  # noqa: WPS433

        const = read_plugin_constants()
        ok(f"current CRB_VERSION {const['version']}")
        ok(f"current CRB_BUILD_ID {const['build_id']}")
        ok(f"tag name {tag_name(const['build_id'])}")
    except Exception as exc:  # noqa: BLE001
        fail(f"versioning module import failed: {exc}")

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed.")
        return 1
    print("All versioning setup checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
