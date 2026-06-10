#!/usr/bin/env python3
"""Simulate post content template rendering (mirrors PHP Content_Template logic)."""
from __future__ import annotations

import re
import sys
from pathlib import Path

BASE = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(BASE.parent.parent))  # plugins root optional

# Minimal PHP-like helpers
def esc_html(s: str) -> str:
    return (
        s.replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
        .replace('"', "&quot;")
    )


def esc_url(s: str) -> str:
    s = s.strip()
    if not s or re.match(r"^https?://", s, re.I):
        return s
    return s


def strip_all_tags(s: str) -> str:
    return re.sub(r"<[^>]*>", "", s)


def build_replacements(row: dict, link_idx: int = 1, schema_html: set | None = None) -> dict:
    schema_html = schema_html or set()
    out = {}
    for index, value in row.items():
        if not isinstance(index, int):
            continue
        value = str(value).strip()
        is_link = index == link_idx
        if index in schema_html and value:
            safe = value  # wp_kses_post skipped in sim
        elif is_link or re.match(r"^https?://", value, re.I) or value.startswith("//"):
            safe = esc_url(value)
        else:
            safe = esc_html(value)
        n = index + 1
        out[f"{{%{{n}}}}"] = safe
        out[f"%{n}"] = safe
        out[f"{{{{{index}}}}}"] = safe
    return out


def render(template: str, row: dict, schema_html: set | None = None) -> str:
    repl = build_replacements(row, schema_html=schema_html)
    keys = sorted(repl, key=len, reverse=True)
    out = template
    for k in keys:
        out = out.replace(k, repl[k])
    return out


def main() -> int:
    fixture = BASE / "test-fixture" / "review-list-sample.html"
    html = fixture.read_text(encoding="utf-8")
    # Fake extracted row (from prior test output)
    row = {
        0: "作品タイトル第一号",
        1: "https://example.com/work/=/product_id/AAA001.html",
        2: "カテゴリ名",
        3: "https://cdn.example.com/modpub/AAA001_img_main.jpg",
        4: "",
        5: "<p>これは<strong>レビュー本文</strong>です。</p>",
    }
    templates = [
        '<a href="{%2}">{%1}</a>',
        "<p>{%6}</p>",
        '<img src="{%4}" alt="{%1}">',
        "{%6}",
        '<div>{%6}</div><p><a href="{%2}">元記事</a></p>',
    ]
    for tpl in templates:
        plain = render(tpl, row, schema_html={5})
        escaped = render(tpl, row, schema_html=set())  # wrong schema (esc_html on html)
        print("--- template:", tpl[:60])
        print("  with html slot:", plain[:120], "...")
        print("  strip empty?", not strip_all_tags(plain).strip())
        print("  wrong schema:", escaped[:120], "...")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
