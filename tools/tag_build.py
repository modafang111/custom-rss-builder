#!/usr/bin/env python3
"""現在の CRB_BUILD_ID に Git タグ build-<BUILD_ID> を付ける。"""
from __future__ import annotations

import argparse
import subprocess
import sys
from pathlib import Path

TOOLS_DIR = Path(__file__).resolve().parent
if str(TOOLS_DIR) not in sys.path:
    sys.path.insert(0, str(TOOLS_DIR))

from versioning import (  # noqa: E402
    git_root,
    git_status_porcelain,
    git_tag_exists,
    read_plugin_constants,
    tag_name,
)


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Create annotated git tag build-<CRB_BUILD_ID> at HEAD."
    )
    parser.add_argument(
        "--build-id",
        help="Override BUILD_ID (default: read from custom-rss-builder.php)",
    )
    parser.add_argument(
        "--allow-dirty",
        action="store_true",
        help="Allow tagging when the working tree has uncommitted changes",
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Move an existing tag to current HEAD",
    )
    args = parser.parse_args()

    root = git_root()
    if root is None:
        print("Git repository not found.", file=sys.stderr)
        return 1

    const = read_plugin_constants()
    build_id = (args.build_id or const["build_id"]).strip()
    if not build_id:
        print("BUILD_ID is empty.", file=sys.stderr)
        return 1

    name = tag_name(build_id)
    dirty = git_status_porcelain().strip()
    if dirty and not args.allow_dirty:
        print(
            "Working tree has uncommitted changes. Commit first, or pass --allow-dirty.",
            file=sys.stderr,
        )
        print("Uncommitted files (first 20 lines):", file=sys.stderr)
        for line in dirty.splitlines()[:20]:
            print(f"  {line}", file=sys.stderr)
        return 2

    if git_tag_exists(name) and not args.force:
        print(f"Tag already exists: {name} (use --force to move it to HEAD)")
        return 3

    message = (
        f"Custom RSS Builder {const['version']} build {build_id}\n\n"
        "Tagged for rollback via git checkout or deploy_client_from_archive.py"
    )
    cmd = ["git", "tag", "-a", name, "-m", message]
    if args.force:
        cmd.insert(2, "-f")
    subprocess.check_call(cmd, cwd=root)

    print(f"Tagged: {name} @ HEAD")
    if dirty:
        print("WARNING: tag points at HEAD but working tree still has uncommitted changes.")
    print(f"Rollback source: git checkout {name} -- custom-rss-builder/")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
