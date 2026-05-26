#!/usr/bin/env python3
"""Simulate element discovery (mirrors PHP logic) for offline testing."""
from __future__ import annotations

import re
import sys
from collections import defaultdict
from pathlib import Path

from cssselect.parser import SelectorSyntaxError
from lxml import html as lhtml

MIN_LINK_SCORE = 15
MIN_BLOCK_REPEAT = 2
MAX_GROUPS = 25

NOISE_TOKENS = frozenset({"clear", "hide", "hidden", "inner", "wrap", "separator"})
NOISE_PREFIXES = ("type_", "icon_", "btn_", "star_", "work_btn", "ga4_")


def css_count(scope, selector: str) -> int:
    try:
        return len(scope.cssselect(selector))
    except SelectorSyntaxError:
        return 0


def class_tokens(el) -> list[str]:
    raw = (el.get("class") or "").strip()
    return [t for t in raw.split() if t]


def node_text(el) -> str:
    return re.sub(r"\s+", " ", el.get_text(strip=True) or "").strip()


def ancestor_has_class(el, token: str) -> bool:
    node = el
    while node is not None:
        if token in class_tokens(node):
            return True
        node = node.getparent()
    return False


def node_text_lxml(el) -> str:
    return re.sub(r"\s+", " ", (el.text_content() or "")).strip()


def score_link(href: str, text: str, title: str, el) -> int:
    score = 0
    if "product_id" in href:
        score += 70
    elif "/work/" in href:
        score += 40
    if ancestor_has_class(el, "work_name"):
        score += 50
    if len(title) >= 8:
        score += 20
    if len(text) >= 12:
        score += 10
    if re.search(r"/(cart|genre|fsr|reviewlist|reviewer|contact|circle/profile|keyword_creater)/", href, re.I):
        score -= 90
    if re.search(r"/(cart|wishlist)/", href, re.I) or "btn_" in href:
        score -= 50
    if re.match(r"^\(\d+\)$", text) or text in (
        "カートに入れる",
        "お気に入りに追加",
        "無料サンプル",
        "報告する",
    ):
        score -= 60
    if ancestor_has_class(el, "search_tag"):
        score -= 40
    return score


def suggest_link_selector(el, href: str) -> str:
    if ancestor_has_class(el, "work_name"):
        if "product_id" in href:
            return 'dt.work_name a[href*="product_id"]'
        return "dt.work_name a"
    if "product_id" in href and ancestor_has_class(el, "work_thumb"):
        return '.work_thumb a[href*="product_id"]'
    if "product_id" in href:
        return 'a[href*="product_id"]'
    return "a"  # simplified


def is_noisy_block_class(token: str, class_counts: dict[str, int]) -> bool:
    for other in class_counts:
        if other == token:
            continue
        if len(token) > len(other) and other in token:
            return True
    if token in NOISE_TOKENS:
        return True
    return any(token.startswith(p) for p in NOISE_PREFIXES)


def discover(html: str, scope_selector: str = "#review_list") -> dict:
    wrapped = f'<div id="crb-root">{html}</div>'
    doc = lhtml.fromstring(wrapped)
    scope = doc
    if scope_selector:
        found = doc.cssselect(scope_selector)
        if not found:
            raise SystemExit(f"Scope not found: {scope_selector}")
        scope = found[0]

    # Links
    link_buckets: dict[str, dict] = {}
    for a in scope.cssselect("a[href]"):
        href = (a.get("href") or "").strip()
        if not href or href == "#" or href.startswith("javascript:"):
            continue
        sel = suggest_link_selector(a, href)
        score = score_link(href, node_text_lxml(a), (a.get("title") or "").strip(), a)
        if score < MIN_LINK_SCORE:
            continue
        b = link_buckets.setdefault(
            sel,
            {"kind": "link", "selector": sel, "priority": score, "samples": [], "recommended": False},
        )
        b["priority"] = max(b["priority"], score)
        if len(b["samples"]) < 2:
            b["samples"].append(
                {
                    "href": href[:120],
                    "text": node_text_lxml(a)[:80],
                    "title": (a.get("title") or "")[:80],
                }
            )

    link_groups = []
    for b in link_buckets.values():
        cnt = css_count(scope, b["selector"])
        if cnt < 1:
            continue
        b["count"] = cnt
        link_groups.append(b)
    if link_groups:
        best = max(link_groups, key=lambda g: g["priority"])
        best["recommended"] = True

    # Blocks
    class_counts: dict[str, int] = defaultdict(int)
    for el in scope.cssselect("[class]"):
        for t in class_tokens(el):
            if len(t) >= 4:
                class_counts[t] += 1

    block_groups = []
    for token, raw in class_counts.items():
        if raw < MIN_BLOCK_REPEAT or is_noisy_block_class(token, class_counts):
            continue
        sel = f".{token}"
        cnt = css_count(scope, sel)
        if cnt < MIN_BLOCK_REPEAT:
            continue
        pri = cnt * 2 + (100 if token == "review_contents" else 0)
        block_groups.append(
            {
                "kind": "block",
                "selector": sel,
                "count": cnt,
                "priority": pri,
                "recommended": False,
                "samples": [],
            }
        )
    if block_groups:
        best = max(block_groups, key=lambda g: g["priority"])
        best["recommended"] = True

    groups = link_groups + block_groups
    groups.sort(key=lambda g: (-g["priority"], -g["count"]))
    return {"scope_label": scope_selector or "page", "groups": groups[:MAX_GROUPS]}


def main() -> None:
    base = Path(__file__).resolve().parent.parent
    path = Path(sys.argv[1]) if len(sys.argv) > 1 else base / "test-fixture/dlsite-review-snippet.html"
    html = path.read_text(encoding="utf-8")
    result = discover(html)
    print(f"Scope: {result['scope_label']}\n")
    for g in result["groups"][:15]:
        rec = " [recommended]" if g.get("recommended") else ""
        print(f"{g['kind']:6} pri={g['priority']:4} cnt={g['count']:3}{rec}")
        print(f"  {g['selector']}")
        if g.get("samples"):
            s = g["samples"][0]
            if "href" in s:
                print(f"  sample: {(s.get('title') or s.get('text') or '')[:60]} | {s['href'][:80]}")
        print()

    links = [g for g in result["groups"] if g["kind"] == "link"]
    blocks = [g for g in result["groups"] if g["kind"] == "block"]
    top_link = links[0]["selector"] if links else None
    top_block = blocks[0]["selector"] if blocks else None
    ok = top_link == 'dt.work_name a[href*="product_id"]' and top_block == ".review_contents"
    print("ASSERT top link:", "OK" if ok else f"FAIL (got {top_link!r})")
    if blocks:
        print(f"ASSERT .review_contents count >= 2: {blocks[0]['count']}")


if __name__ == "__main__":
    main()
