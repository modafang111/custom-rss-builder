#!/usr/bin/env python3
"""現在の CRB_BUILD_ID で client（必要なら authority）ZIP を dist/archives/ に保存。"""
from __future__ import annotations

import argparse
import shutil
import sys
from pathlib import Path

TOOLS_DIR = Path(__file__).resolve().parent
if str(TOOLS_DIR) not in sys.path:
    sys.path.insert(0, str(TOOLS_DIR))

from versioning import (  # noqa: E402
    append_manifest,
    authority_archive_path,
    client_archive_path,
    ensure_archives_dir,
    load_build_module,
    read_plugin_constants,
    utc_now_iso,
)


def archive_variant(
    variant: str,
    build_id: str,
    *,
    force: bool = False,
) -> tuple[Path, int]:
    bpd = load_build_module()
    dest = (
        client_archive_path(build_id)
        if variant == "client"
        else authority_archive_path(build_id)
    )
    ensure_archives_dir()
    if dest.exists() and not force:
        raise FileExistsError(f"Archive already exists: {dest} (use --force to overwrite)")

    staging = dest.with_suffix(".zip.tmp")
    if staging.exists():
        staging.unlink()

    count, _ = bpd.write_zip(variant, staging)
    errors = bpd.verify_zip(staging, variant)
    if errors:
        staging.unlink(missing_ok=True)
        raise RuntimeError("; ".join(errors))

    shutil.move(str(staging), str(dest))
    return dest, count


def main() -> int:
    parser = argparse.ArgumentParser(description="Archive dist ZIPs by CRB_BUILD_ID.")
    parser.add_argument(
        "--build-id",
        help="Override BUILD_ID (default: read from custom-rss-builder.php)",
    )
    parser.add_argument(
        "--authority",
        action="store_true",
        help="Also archive license-server ZIP",
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="Overwrite existing archive files",
    )
    args = parser.parse_args()

    const = read_plugin_constants()
    build_id = (args.build_id or const["build_id"]).strip()
    if not build_id:
        print("BUILD_ID is empty.", file=sys.stderr)
        return 1

    try:
        client_zip, client_count = archive_variant("client", build_id, force=args.force)
    except FileExistsError as exc:
        print(str(exc))
        return 2
    except SystemExit as exc:
        print(f"Client archive failed: {exc}", file=sys.stderr)
        return 1
    except Exception as exc:  # noqa: BLE001
        print(f"Client archive failed: {exc}", file=sys.stderr)
        return 1

    print(f"OK client archive: {client_zip} ({client_count} entries)")
    append_manifest(
        {
            "build_id": build_id,
            "version": const["version"],
            "variant": "client",
            "path": client_zip.name,
            "recorded_at": utc_now_iso(),
        }
    )

    if args.authority:
        try:
            auth_zip, auth_count = archive_variant("authority", build_id, force=args.force)
        except Exception as exc:  # noqa: BLE001
            print(f"Authority archive failed: {exc}", file=sys.stderr)
            return 1
        print(f"OK authority archive: {auth_zip} ({auth_count} entries)")
        append_manifest(
            {
                "build_id": build_id,
                "version": const["version"],
                "variant": "authority",
                "path": auth_zip.name,
                "recorded_at": utc_now_iso(),
            }
        )

    print("Manifest: dist/archives/manifest.jsonl")
    return 0


def record_client_snapshot(
    build_id: str | None = None,
    *,
    force: bool = False,
) -> Path | None:
    """client ZIP を archives に保存。既存があれば None。"""
    const = read_plugin_constants()
    bid = (build_id or const["build_id"]).strip()
    if not bid:
        raise ValueError("BUILD_ID is empty")
    try:
        dest, _count = archive_variant("client", bid, force=force)
    except FileExistsError:
        return None
    append_manifest(
        {
            "build_id": bid,
            "version": const["version"],
            "variant": "client",
            "path": dest.name,
            "recorded_at": utc_now_iso(),
        }
    )
    return dest


if __name__ == "__main__":
    raise SystemExit(main())
