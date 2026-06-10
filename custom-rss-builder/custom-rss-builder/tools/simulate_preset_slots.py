#!/usr/bin/env python3
"""Simulate DLsite preset slot extraction ({%1}–{%8}) against HTML fixtures."""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

from lxml import html as lhtml

PRESET = {
    "scope_selector": "#review_list",
    "item_selector": ".review_inner > .review_contents",
    "link_selector": 'dt.work_name a[href*="product_id"]',
    "title_mode": "attr",
    "title_attr": "title",
    "image_selector": ".review_work .work_img_popover img",
    "author_selector": "dd.maker_name span.author a",
    "review_title_selector": '.reveiw_title a[href*="reviewlist"]',
    "summary_selector": "dd.work_text",
    "category_selector": ".review_work .work_category a",
    "review_body_selector": ".review_main p.review_desc",
}

BASE_URL = "https://www.dlsite.com/maniax/"


def norm(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "")).strip()


def resolve_url(url: str) -> str:
    url = url.strip()
    if url.startswith("//"):
        return "https:" + url
    if url.startswith("/"):
        return BASE_URL.rstrip("/") + url
    return url


def usable_image(url: str) -> bool:
    url = url.strip()
    if not url or url.startswith("data:"):
        return False
    return bool(re.match(r"^(https?:)?//", url, re.I))


def first_srcset_url(srcset: str) -> str:
    srcset = (srcset or "").strip()
    if not srcset:
        return ""
    part = srcset.split(",")[0].strip()
    return part.split()[0] if part else ""


def read_image(scope) -> str:
    candidates: list[tuple[int, str]] = []

    def add(url: str, score: int) -> None:
        if usable_image(url):
            candidates.append((score, resolve_url(url)))

    for img in scope.cssselect(".work_img_popover img, .review_work .work_img_popover img"):
        add(img.get("src") or "", 10)
    for src in scope.cssselect("picture source[srcset], .thumb-container picture source[srcset]"):
        add(first_srcset_url(src.get("srcset") or ""), 15)
    for img in scope.cssselect("picture img, .thumb-container img"):
        add(img.get("src") or "", 12)

    if not candidates:
        return ""
    candidates.sort(key=lambda x: (-x[0], -len(x[1])))
    return candidates[0][1]


def read_text_all(scope, selector: str, pick_longest: bool = False) -> str:
    try:
        nodes = scope.cssselect(selector)
    except Exception:
        return ""
    texts = [norm(n.text_content()) for n in nodes]
    texts = [t for t in texts if t]
    if not texts:
        return ""
    return max(texts, key=len) if pick_longest else texts[0]


def read_ld_json_review_body(scope) -> str:
    for script in scope.cssselect('script[type="application/ld+json"]'):
        raw = (script.text or "").strip()
        if not raw:
            continue
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            continue
        body = data.get("review", {}).get("reviewBody") if isinstance(data.get("review"), dict) else ""
        if not body and "reviewBody" in data:
            body = data.get("reviewBody")
        body = norm(str(body or ""))
        if body:
            return body
    return ""


def extract_record(ctx, cfg: dict) -> dict:
    link_el = None
    try:
        links = ctx.cssselect(cfg["link_selector"])
        link_el = links[0] if links else None
    except Exception:
        pass

    title = ""
    link = ""
    if link_el is not None:
        link = resolve_url(link_el.get("href") or "")
        if cfg.get("title_mode") == "attr":
            title = norm(link_el.get("title") or "") or norm(link_el.text_content())
        else:
            title = norm(link_el.text_content())

    body = read_text_all(ctx, cfg["review_body_selector"], pick_longest=True)
    if not body:
        body = read_ld_json_review_body(ctx)

    return {
        "{%1}": title,
        "{%2}": link,
        "{%3}": read_text_all(ctx, cfg["summary_selector"]),
        "{%4}": read_image(ctx),
        "{%5}": read_text_all(ctx, cfg["category_selector"]),
        "{%6}": read_text_all(ctx, cfg["review_title_selector"]),
        "{%7}": read_text_all(ctx, cfg["author_selector"]),
        "{%8}": body,
    }


def run(html: str, cfg: dict) -> dict:
    doc = lhtml.fromstring(f'<div id="crb-root">{html}</div>')
    root = doc.get_element_by_id("crb-root")

    scope_els = root.cssselect(cfg["scope_selector"]) if cfg.get("scope_selector") else [root]
    if not scope_els:
        return {"error": f"scope not found: {cfg.get('scope_selector')}"}
    scope_el = scope_els[0]

    items = scope_el.cssselect(cfg["item_selector"]) if cfg.get("item_selector") else [scope_el]
    if not items:
        return {"error": f"item not found: {cfg.get('item_selector')}"}

    records = []
    for i, item in enumerate(items[:3]):
        row = extract_record(item, cfg)
        row["_index"] = i + 1
        records.append(row)

    return {"item_count": len(items), "records": records}


def print_report(label: str, result: dict) -> None:
    print("=" * 70)
    print(label)
    if "error" in result:
        print("  ERROR:", result["error"])
        return
    print(f"  items in scope: {result['item_count']}")
    for rec in result["records"]:
        print(f"\n  — {rec['_index']}件目")
        for slot in ("{%1}", "{%2}", "{%3}", "{%4}", "{%5}", "{%6}", "{%7}", "{%8}"):
            val = rec.get(slot, "")
            mark = "OK" if val else "EMPTY"
            preview = val[:72] + ("…" if len(val) > 72 else "")
            print(f"    {slot} [{mark}]: {preview or '(空)'}")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("html_file", type=Path)
    ap.add_argument("--fragment-only", action="store_true", help="HTML is .review_inner only (no #review_list)")
    args = ap.parse_args()

    html = args.html_file.read_text(encoding="utf-8")
    cfg = dict(PRESET)

    print_report("【プリセット】 scope=#review_list", run(html, cfg))

    if args.fragment_only or "review_list" not in html:
        cfg2 = dict(PRESET)
        cfg2["scope_selector"] = ".review_inner"
        print_report("【代替】 scope=.review_inner（フラグメント用）", run(html, cfg2))

    cfg3 = dict(PRESET)
    cfg3["item_selector"] = ".review_contents"
    print_report("【比較】 item=.review_contents（子孫すべて）", run(html, cfg3))

    bad = dict(PRESET)
    bad["item_selector"] = "div.review_contents_inner"
    print_report("【誤設定】 item=review_contents_inner", run(html, bad))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
