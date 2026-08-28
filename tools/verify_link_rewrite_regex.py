# -*- coding: utf-8 -*-
"""Verify link-rewrite auto-detect + DUGA pattern (local logic mirror)."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "custom-rss-builder" / "custom-rss-builder"
LR = (ROOT / "includes" / "functions-link-rewrite.php").read_text(encoding="utf-8")


def ok(msg: str) -> None:
    print("OK ", msg)


def fail(msg: str) -> None:
    raise SystemExit("FAIL " + msg)


def main() -> None:
    if "crb_link_rewrite_source_looks_like_regex" not in LR:
        fail("auto-detect helper missing")
    ok("auto-detect helper present")

    if "crb_link_rewrite_strip_regex_delimiters_for_display" not in LR:
        fail("display strip helper missing")
    ok("display strip helper present")

    # Mirror of the intended PHP transform.
    pattern = re.compile(
        r"^https?://(?:www\.)?duga\.jp(/ppv/[a-z0-9][a-z0-9\-]*-\d+)/?",
        re.I,
    )
    repl = r"https://click.duga.jp\1/24697-03"
    cases = {
        "https://duga.jp/ppv/videointer-0109/": "https://click.duga.jp/ppv/videointer-0109/24697-03",
        "https://duga.jp/ppv/peters-2714/": "https://click.duga.jp/ppv/peters-2714/24697-03",
        "https://duga.jp/ppv/bostoncrab-0053/": "https://click.duga.jp/ppv/bostoncrab-0053/24697-03",
    }
    for src, want in cases.items():
        got = pattern.sub(repl, src, count=1)
        if got != want:
            fail(f"{src} -> {got} (want {want})")
    ok("DUGA transform cases")

    print("All link-rewrite checks passed.")


if __name__ == "__main__":
    main()
