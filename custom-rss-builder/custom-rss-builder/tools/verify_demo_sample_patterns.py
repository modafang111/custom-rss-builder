#!/usr/bin/env python3
"""Verify demo sample selectors work scope-relative (like plugin extraction)."""
from __future__ import annotations

from pathlib import Path

from lxml import html

ROOT = Path(__file__).resolve().parent.parent
SAMPLES = ROOT / "samples"

PATTERNS = [
    {
        "id": "simple-div",
        "file": "01-simple-div.html",
        "scope": "",
        "item": ".crb-sample-news-item",
        "link": "a",
        "title": "a",
        "min_items": 3,
    },
    {
        "id": "list-wrapper",
        "file": "02-list-wrapper.html",
        "scope": ".crb-sample-list",
        "item": ".crb-sample-item",
        "link": ".crb-sample-title a",
        "title": ".crb-sample-title a",
        "min_items": 2,
    },
    {
        "id": "ul-li",
        "file": "03-ul-li.html",
        "scope": "ul.crb-sample-ul",
        "item": "li.crb-sample-li",
        "link": "a.crb-sample-link",
        "title": "a.crb-sample-link",
        "min_items": 3,
    },
    {
        "id": "nested-feed",
        "file": "04-nested-feed.html",
        "scope": ".crb-sample-feed",
        "item": ".crb-sample-feed_list_item",
        "link": "a.crb-sample-feed_link",
        "title": "a.crb-sample-feed_link",
        "min_items": 2,
    },
    {
        "id": "table-rows",
        "file": "05-table-rows.html",
        "scope": "table.crb-sample-table",
        "item": "tbody tr",
        "link": "a",
        "title": "a",
        "min_items": 2,
    },
]


def load_root(filename: str) -> html.HtmlElement:
    body = (SAMPLES / filename).read_text(encoding="utf-8")
    return html.fromstring(f'<div id="crb-root">{body}</div>')


def run_pattern(p: dict) -> list[str]:
    errors: list[str] = []
    root = load_root(p["file"])
    scope = root
    if p["scope"]:
        found = root.cssselect(p["scope"])
        if not found:
            return [f"{p['id']}: scope miss {p['scope']!r}"]
        scope = found[0]

    items = scope.cssselect(p["item"])
    if len(items) < p["min_items"]:
        errors.append(f"{p['id']}: item {p['item']!r} got {len(items)} (need {p['min_items']})")

    for idx, item in enumerate(items[: p["min_items"]]):
        if not item.cssselect(p["link"]):
            errors.append(f"{p['id']}: row {idx + 1} link miss {p['link']!r}")
        if not item.cssselect(p["title"]):
            errors.append(f"{p['id']}: row {idx + 1} title miss {p['title']!r}")

    return errors


def main() -> int:
    fail = 0
    for p in PATTERNS:
        errs = run_pattern(p)
        if errs:
            fail += len(errs)
            for e in errs:
                print("FAIL", e)
        else:
            print("OK  ", p["id"])
    return 1 if fail else 0


if __name__ == "__main__":
    raise SystemExit(main())
