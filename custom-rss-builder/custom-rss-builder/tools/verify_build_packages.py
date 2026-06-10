#!/usr/bin/env python3
"""Level 3 配布 ZIP の静的検証（build_plugin_dist.py 実行後）。"""
from __future__ import annotations

import sys
import zipfile
from pathlib import Path

PLUGIN_ROOT = Path(__file__).resolve().parents[1]
DIST = Path(__file__).resolve().parents[3] / "dist"
CLIENT_ZIP = DIST / "custom-rss-builder-client.zip"
SERVER_ZIP = DIST / "custom-rss-builder-license-server.zip"
SLUG = "custom-rss-builder"
MAIN = f"{SLUG}/custom-rss-builder.php"
FAILURES: list[str] = []


def ok(msg: str) -> None:
    print(f"OK   {msg}")


def fail(msg: str) -> None:
    FAILURES.append(msg)
    print(f"FAIL {msg}")


def names_in(path: Path) -> list[str]:
    with zipfile.ZipFile(path) as zf:
        return zf.namelist()


def main() -> int:
    print("=== verify_build_packages ===\n")
    lic = (PLUGIN_ROOT / "includes/functions-license.php").read_text(encoding="utf-8")
    if "function crb_package_variant" not in lic:
        fail("crb_package_variant missing in source")
    else:
        ok("source has crb_package_variant")

    for label, zpath, variant in (
        ("client", CLIENT_ZIP, "client"),
        ("server", SERVER_ZIP, "authority"),
    ):
        if not zpath.is_file():
            fail(f"{label} zip missing: {zpath}")
            continue
        ok(f"{label} zip exists")

        names = names_in(zpath)
        body = zipfile.ZipFile(zpath).read(MAIN).decode("utf-8")
        if f"CRB_PACKAGE_VARIANT', '{variant}'" not in body:
            fail(f"{label}: package variant not {variant}")
        else:
            ok(f"{label}: CRB_PACKAGE_VARIANT={variant}")

        if variant == "client":
            if "CRB_LICENSE_API_SECRET" not in body:
                fail(f"{label}: missing baked CRB_LICENSE_API_SECRET")
            else:
                ok(f"{label}: CRB_LICENSE_API_SECRET baked in")

        if any("/license-server/" in n for n in names):
            if variant == "client":
                fail(f"{label}: must not contain license-server/")
            else:
                ok(f"{label}: has license-server/")
        elif variant == "authority":
            fail(f"{label}: license-server/ missing")

        feed_hits = [n for n in names if "class-feed-manager.php" in n]
        if variant == "client":
            if not feed_hits:
                fail(f"{label}: missing feed manager")
            else:
                ok(f"{label}: includes feed PHP")
        elif feed_hits:
            fail(f"{label}: must not contain feed manager")

    print()
    if FAILURES:
        print(f"{len(FAILURES)} check(s) failed. Run: python build_plugin_dist.py")
        return 1
    print("All package checks passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
