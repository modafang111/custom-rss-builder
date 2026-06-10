#!/usr/bin/env python3
"""Simulate scope preview on arbitrary DLsite review-list HTML."""
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

try:
    from lxml import html as lhtml
except ImportError:
    print("pip install lxml", file=sys.stderr)
    sys.exit(2)

PREVIEW_LIMIT = 3
BASE_URL = "https://www.dlsite.com/"


def norm(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "")).strip()


def resolve_url(url: str) -> str:
    url = url.strip()
    if url.startswith("//"):
        return "https:" + url
    if url.startswith("/"):
        return BASE_URL.rstrip("/") + url
    return url


def product_id_from_href(href: str) -> str:
    m = re.search(r"product_id/([A-Z0-9]+)", href, re.I)
    return m.group(1).upper() if m else ""


def ga4_product_id(ctx) -> str:
    for el in ctx.xpath('.//*[contains(@class,"ga4_event_item")]'):
        pid = (el.get("data-product_id") or "").strip()
        if pid:
            return pid.upper()
    return ""


def is_hidden_el(el) -> bool:
    if el.get("hidden") is not None:
        return True
    style = (el.get("style") or "").replace(" ", "").lower()
    if "display:none" in style:
        return True
    parent = el.getparent()
    if parent is not None and parent != el:
        return is_hidden_el(parent)
    return False


def score_work_link(el) -> int:
    href = norm(el.get("href") or el.get("link") or "")
    text = norm(el.get("title") or el.text_content())
    if not href or href == "#":
        return -9999
    score = 0
    if "/work/" in href and "product_id" in href and "/work/reviewlist/" not in href:
        score += 100
    if len(text) >= 4:
        score += min(40, len(text))
    if re.search(r"/(reviewlist|reviewer|fsr)/", href, re.I):
        score -= 50
    return score


def extract_review_desc(ctx, visible_only: bool) -> tuple[str, str]:
    best = ""
    best_vis = ""
    for p in ctx.xpath(".//p[contains(concat(' ', normalize-space(@class), ' '), ' review_desc ')]"):
        t = norm(p.text_content())
        if not t:
            continue
        if len(t) > len(best):
            best = t
        if not visible_only or not is_hidden_el(p):
            if len(t) > len(best_vis):
                best_vis = t
    note = ""
    if best and not best_vis:
        note = "※本文DOMは display:none（ネタバレ折りたたみ）"
    return best_vis or best, note


def extract_item(ctx) -> dict:
    best_work = None
    best_score = -9999
    for el in ctx.xpath(".//a[@href]"):
        sc = score_work_link(el)
        if sc > best_score:
            best_score = sc
            best_work = el
    title = ""
    link = ""
    pid = ""
    if best_work is not None:
        title = norm(best_work.get("title") or best_work.text_content())
        link = resolve_url(norm(best_work.get("href") or ""))
        pid = product_id_from_href(link)
    if not pid:
        pid = ga4_product_id(ctx)

    review_title = ""
    for a in ctx.xpath(".//div[contains(@class,'reveiw_title')]//a[@href]"):
        t = norm(a.text_content())
        if t and len(t) > len(review_title):
            review_title = t

    body, body_note = extract_review_desc(ctx, visible_only=False)
    body_vis, _ = extract_review_desc(ctx, visible_only=True)

    return {
        "work_title": title[:52] + ("…" if len(title) > 52 else ""),
        "review_title": review_title[:40],
        "product_id": pid,
        "review_body": (body_vis or body)[:70] + ("…" if len(body_vis or body) > 70 else ""),
        "body_note": body_note if body and not body_vis else "",
        "json_ld": bool(ctx.xpath('.//script[@type="application/ld+json"]')),
    }


def run_simulation(html: str, scope: str, item: str) -> dict:
    doc = lhtml.fromstring(f'<div id="crb-root">{html}</div>')
    root = doc.get_element_by_id("crb-root")
    scope_els = root.cssselect(scope) if scope else [root]
    if not scope_els:
        return {"error": f"scope not found: {scope}"}
    scope_el = scope_els[0]

    item_fallback = False
    if item:
        items = scope_el.cssselect(item)
        if not items:
            return {"error": f"item not found: {item}"}
        item_count = len(items)
    else:
        items = [scope_el]
        item_fallback = True
        item_count = 1

    containers = items[:PREVIEW_LIMIT]
    previews = []
    for i, ctx in enumerate(containers):
        row = extract_item(ctx)
        row["index"] = i + 1
        previews.append(row)

    note = f"{scope} / {item or '(空)'}"
    if item_fallback:
        note += " (範囲を1件として試読)"
    else:
        note += f" ({item_count}件)"
    if len(containers) > 1:
        note += f" / 試し読み {len(containers)}件表示（範囲内 {item_count}件）"

    return {
        "context_note": note,
        "item_count": item_count,
        "preview_shown": len(containers),
        "previews": previews,
        "all_product_ids": [ga4_product_id(c) or extract_item(c)["product_id"] for c in items],
    }


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("html_file", type=Path)
    ap.add_argument("--scope", default="#review_list")
    ap.add_argument("--item", default=".review_contents")
    ap.add_argument("--compare-inner", action="store_true")
    args = ap.parse_args()

    html = args.html_file.read_text(encoding="utf-8")
    print(f"File: {args.html_file} ({len(html):,} bytes)\n")

    for label, scope, item in [
        ("おすすめ", args.scope, args.item),
        ("おすすめB", args.scope, ".review_inner > .review_contents"),
    ]:
        if label == "おすすめB" and item == args.item:
            continue
        r = run_simulation(html, scope, item)
        print("=" * 64)
        print(f"【{label}】 {scope} + {item}")
        if "error" in r:
            print(f"  ERROR: {r['error']}")
            continue
        print(f"  {r['context_note']}")
        for p in r["previews"]:
            print(f"\n  — {p['index']}件目  product_id={p['product_id']}")
            print(f"      作品(≈{{%1}}): {p['work_title'] or '(空)'}")
            print(f"      レビュー見出し: {p['review_title'] or '(空)'}")
            b = p["review_body"] or "(空)"
            print(f"      本文(≈{{%8}}): {b}")
            if p.get("body_note"):
                print(f"      {p['body_note']}")

    if args.compare_inner:
        r = run_simulation(html, args.scope, "div.review_contents_inner")
        print("\n" + "=" * 64)
        print("【比較: inner断片】")
        print(f"  {r.get('context_note', r.get('error'))}")
        for p in r.get("previews", [])[:3]:
            print(f"  {p['index']}件目 pid={p['product_id']} body={p['review_body'][:40] or '(空)'}")

    r = run_simulation(html, args.scope, args.item)
    if "error" not in r:
        ids = [x for x in r["all_product_ids"] if x]
        print(f"\n範囲内 .review_contents 全{len(ids)}件の product_id:")
        print("  " + ", ".join(ids))
        dup = [x for x in ids if ids.count(x) > 1]
        if dup:
            print(f"  ※重複: {', '.join(sorted(set(dup)))}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
