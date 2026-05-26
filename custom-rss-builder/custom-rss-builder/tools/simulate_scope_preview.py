#!/usr/bin/env python3
"""Offline scope-preview simulation (item fallback + {%1}-{%8} rows)."""
from __future__ import annotations

import re
import subprocess
import sys
from pathlib import Path

from cssselect import GenericTranslator
from lxml import html as lhtml

BASE = Path(__file__).resolve().parent.parent
FIXTURE = BASE / "test-fixture" / "dlsite-review-snippet.html"
BASE_URL = "https://www.dlsite.com/maniax/"


def css_first(scope, selector: str):
    try:
        nodes = scope.cssselect(selector)
    except Exception:
        return None
    return nodes[0] if nodes else None


def text_of(el) -> str:
    return re.sub(r"\s+", " ", (el.text_content() or "")).strip()


def resolve_url(href: str) -> str:
    href = (href or "").strip()
    if href.startswith("//"):
        return "https:" + href
    if href.startswith("/"):
        return BASE_URL.rstrip("/") + href
    return href


def read_title(anchor, mode="attr"):
    if anchor is None:
        return ""
    if mode == "text":
        return text_of(anchor)
    t = anchor.get("title") or ""
    return t.strip() or text_of(anchor)


def read_image(scope):
    for sel in (
        ".work_img_popover img",
        "picture img",
        ".thumb-container img",
        "img",
    ):
        img = css_first(scope, sel)
        if img is None:
            continue
        src = img.get("src") or ""
        if src and not src.startswith("data:"):
            return resolve_url(src)
    return ""


def suggest_link(groups):
    best = ("", -1)
    for g in groups:
        role = g.get("role", "")
        kind = g.get("kind", "")
        if role in ("product_link", "link") or kind == "link":
            pri = int(g.get("priority", 0))
            sel = (g.get("selector") or "").strip()
            if sel and pri > best[1]:
                best = (sel, pri)
    return best[0]


def suggest_by_role(groups, role_names, default_sel=""):
    best = ("", -1)
    for g in groups:
        if g.get("role") in role_names or g.get("kind") in role_names:
            pri = int(g.get("priority", 0))
            sel = (g.get("selector") or "").strip()
            if sel and pri > best[1]:
                best = (sel, pri)
    return best[0] or default_sel


def minimal_discover_groups(scope_el):
    """Rough group list from first scope (link / text / image)."""
    groups = []
    for a in scope_el.cssselect("a[href*='product_id']"):
        href = a.get("href") or ""
        if "product_id" not in href:
            continue
        groups.append(
            {
                "role": "product_link",
                "kind": "link",
                "priority": 80,
                "selector": "dt.work_name a[href*='product_id']",
                "extract_mode": "href",
            }
        )
        break
    if css_first(scope_el, "dd.work_text"):
        groups.append(
            {
                "role": "text",
                "kind": "text",
                "priority": 40,
                "selector": "dd.work_text",
                "extract_mode": "text",
            }
        )
    if css_first(scope_el, "dd.maker_name a"):
        groups.append(
            {
                "role": "author",
                "kind": "link",
                "priority": 35,
                "selector": "dd.maker_name a",
                "extract_mode": "text",
            }
        )
    if css_first(scope_el, ".work_img_popover img, picture img"):
        groups.append(
            {
                "role": "eyecatch",
                "kind": "image",
                "priority": 30,
                "selector": ".work_img_popover img",
                "extract_mode": "src",
            }
        )
    return groups


def build_preview(html: str, scope_sel: str, item_sel: str) -> tuple[str, list[dict]]:
    doc = lhtml.fromstring(html)
    scope = css_first(doc, scope_sel)
    if scope is None:
        return f"scope miss: {scope_sel}", []

    context = scope
    item_fallback = False
    item_count = 1
    if item_sel:
        item = css_first(scope, item_sel)
        if item is not None:
            context = item
            item_count = len(scope.cssselect(item_sel))
        else:
            item_fallback = True
            context = scope
            item_count = 1

    groups = minimal_discover_groups(context)
    link_sel = suggest_link(groups) or "dt.work_name a[href*='product_id']"
    summary_sel = suggest_by_role(groups, ("text",), "dd.work_text")
    author_sel = suggest_by_role(groups, ("author",), "dd.maker_name a")
    image_sel = suggest_by_role(groups, ("eyecatch", "image"), ".work_img_popover img")

    anchor = css_first(context, link_sel) if link_sel else None
    slots = [
        ("{%1}", link_sel or "-", "attr (title)", read_title(anchor)),
        ("{%2}", link_sel or "-", "href", resolve_url(anchor.get("href", "")) if anchor is not None else ""),
        ("{%3}", summary_sel or "-", "text", text_of(css_first(context, summary_sel)) if summary_sel else ""),
        ("{%4}", image_sel or "-", "src", read_image(context)),
        ("{%5}", "-", "text", ""),
        ("{%6}", "-", "text", ""),
        ("{%7}", "-", "html", ""),
        ("{%8}", author_sel or "-", "text", text_of(css_first(context, author_sel)) if author_sel else ""),
    ]

    note = scope_sel
    if item_sel:
        if item_fallback:
            note += f" / {item_sel} (範囲内に1件ブロックなし→範囲を1件として試読)"
        else:
            note += f" / {item_sel} ({item_count}件)"
    else:
        note += " / 範囲を1件として試読"

    rows = [
        {"token": t, "selector": s, "mode_label": m, "value": v}
        for t, s, m, v in slots
    ]
    return note, rows


def run_php() -> bool:
    php = shutil_which("php")
    script = BASE / "tools" / "simulate_scope_preview.php"
    if not php or not script.is_file():
        return False
    proc = subprocess.run([php, str(script)], capture_output=True, text=True, cwd=str(BASE))
    print(proc.stdout)
    if proc.stderr:
        print(proc.stderr, file=sys.stderr)
    return proc.returncode == 0


def shutil_which(cmd: str):
    import shutil

    return shutil.which(cmd)


def main() -> int:
    html = FIXTURE.read_text(encoding="utf-8", errors="replace")
    cases = [
        ("work_1col_table + .review_contents", "table.work_1col_table", ".review_contents"),
        ("work_1col_table + empty item", "table.work_1col_table", ""),
        ("#review_list + .review_contents", "#review_list", ".review_contents"),
    ]
    print("=== Python simulation (0.5.16 logic) ===\n")
    for label, scope, item in cases:
        print(f"--- {label} ---")
        note, rows = build_preview(html, scope, item)
        print("Note:", note)
        for r in rows:
            val = r["value"] or "(empty)"
            if len(val) > 72:
                val = val[:69] + "..."
            print(f"{r['token']:6} {r['selector'][:28]:28} {r['mode_label']:18} {val}")
        print()

    if run_php():
        print("=== PHP simulation OK ===")
    else:
        print("(PHP not available — Python results above are authoritative for this machine)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
