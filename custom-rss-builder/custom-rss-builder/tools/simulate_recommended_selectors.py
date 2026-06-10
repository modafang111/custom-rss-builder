#!/usr/bin/env python3
"""Simulate ③ scope preview with recommended vs typical wrong selectors (no PHP)."""
from __future__ import annotations

import re
import sys
from pathlib import Path

try:
    from lxml import html as lhtml
except ImportError:
    print("pip install lxml", file=sys.stderr)
    sys.exit(2)

BASE = Path(__file__).resolve().parents[1]
FIXTURE = BASE / "test-fixture" / "review-list-sample.html"
PREVIEW_LIMIT = 3
BASE_URL = "https://example.com/"


def resolve_url(url: str) -> str:
    url = url.strip()
    if url.startswith("//"):
        return "https:" + url
    if url.startswith("/"):
        return BASE_URL.rstrip("/") + url
    return url


def norm(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "")).strip()


def product_id_from_href(href: str) -> str:
    m = re.search(r"product_id/([A-Z0-9]+)", href, re.I)
    return m.group(1).upper() if m else ""


def score_work_link(el) -> int:
    href = norm(el.get("href") or el.get("link") or "")
    text = norm(el.get("title") or el.text_content())
    if not href or href == "#":
        return -9999
    score = 0
    if "/work/" in href and "product_id" in href:
        score += 100
    if len(text) >= 4:
        score += min(40, len(text))
    if re.search(r"/(reviewlist|reviewer|fsr)/", href, re.I):
        score -= 50
    return score


def extract_item_summary(ctx) -> dict:
    best_work = None
    best_score = -9999
    for el in ctx.xpath(".//a[@href] | .//*[@link]"):
        sc = score_work_link(el)
        if sc > best_score:
            best_score = sc
            best_work = el
    title = ""
    link = ""
    pid = ""
    if best_work is not None:
        title = norm(best_work.get("title") or best_work.text_content())
        link = resolve_url(norm(best_work.get("href") or best_work.get("link") or ""))
        pid = product_id_from_href(link)

    review_body = ""
    for p in ctx.xpath(".//p[contains(@class,'review_desc')] | .//p"):
        t = norm(p.text_content())
        if "review_desc" in " ".join(p.get("class") or "").split() or len(t) >= 20:
            if len(t) > len(review_body):
                review_body = t

    fragment = norm(ctx.get("class") or "")
    return {
        "title": title[:48],
        "product_id": pid,
        "link": link[:72],
        "review_snip": (review_body[:56] + "…") if len(review_body) > 56 else review_body,
        "block_class": fragment[:80],
    }


def simulate(scope: str, item: str) -> dict:
    raw = FIXTURE.read_text(encoding="utf-8")
    doc = lhtml.fromstring(f'<div id="crb-root">{raw}</div>')
    root = doc.get_element_by_id("crb-root")

    scope_els = root.cssselect(scope) if scope else [root]
    if not scope_els:
        return {"error": f"範囲が見つかりません: {scope}"}
    scope_el = scope_els[0]

    item_fallback = False
    if item:
        items = scope_el.cssselect(item)
        if not items:
            items = [scope_el]
            item_fallback = True
            item_count = 0
        else:
            item_count = len(items)
    else:
        items = [scope_el]
        item_fallback = True
        item_count = 1

    containers = items[:PREVIEW_LIMIT] if not item_fallback else [scope_el]
    preview_shown = len(containers)

    note = scope or "ページ全体"
    if item:
        note += f" / {item}"
        if item_fallback:
            note += " (範囲内に1件ブロックなし→範囲を1件として試読)"
        else:
            note += f" ({item_count}件)"
    else:
        note += " / 範囲を1件として試読"

    if preview_shown > 1 and not item_fallback:
        note += f" / 試し読み {preview_shown}件表示（範囲内 {item_count}件）"

    previews = []
    for i, ctx in enumerate(containers):
        s = extract_item_summary(ctx)
        s["index"] = i + 1
        previews.append(s)

    return {
        "scope": scope,
        "item": item,
        "context_note": note,
        "item_count": item_count if not item_fallback else (1 if item else 1),
        "preview_shown": preview_shown,
        "item_fallback": item_fallback,
        "previews": previews,
    }


def print_case(label: str, scope: str, item: str) -> None:
    r = simulate(scope, item)
    print(f"\n{'=' * 60}")
    print(f"【{label}】")
    print(f"  範囲: {scope or '(空)'}")
    print(f"  1件ブロック: {item or '(空)'}")
    if "error" in r:
        print(f"  ERROR: {r['error']}")
        return
    print(f"  context_note: {r['context_note']}")
    print(f"  → 試し読み表: {r['preview_shown']} 枚 / 範囲内マッチ: {r.get('item_count', '?')} 件")
    for p in r["previews"]:
        print(f"    — {p['index']}件目 [{p['block_class']}]")
        print(f"        作品タイトル(≈{{%1}}): {p['title'] or '(空)'}")
        print(f"        product_id(≈{{%2}}判別): {p['product_id'] or '(空)'}")
        print(f"        レビュー本文(≈{{%8}}): {p['review_snip'] or '(空)'}")


def main() -> int:
    print("HTML: test-fixture/review-list-sample.html（レビュー2件入り）")
    print_case(
        "おすすめ A",
        "#review_list",
        ".review_contents",
    )
    print_case(
        "おすすめ B",
        "#review_list",
        ".review_inner > .review_contents",
    )
    print_case(
        "誤り例: inner 断片",
        "#review_list",
        "div.review_contents_inner",
    )
    print_case(
        "誤り例: review_inner を1件に",
        "#review_list",
        ".review_inner",
    )
    print_case(
        "誤り例: ②空",
        "#review_list",
        "",
    )

    good = simulate("#review_list", ".review_contents")
    bad = simulate("#review_list", "div.review_contents_inner")
    ok = (
        good["preview_shown"] >= 2
        and good["previews"][0]["product_id"] == "AAA001"
        and good["previews"][1]["product_id"] == "AAA002"
        and good["previews"][0]["product_id"] != good["previews"][1]["product_id"]
        and bad["preview_shown"] >= 3
        and bad["previews"][0]["product_id"] == bad["previews"][1]["product_id"]
    )
    print(f"\n{'=' * 60}")
    if ok:
        print("判定: おすすめ指定では2件別商品・誤指定では同一商品IDが続く（想定どおり）")
        return 0
    print("判定: 期待と異なる — フィクスチャまたはロジックを確認")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
