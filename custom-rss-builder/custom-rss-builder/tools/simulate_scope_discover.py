#!/usr/bin/env python3
"""Simulate crb_discover_scope_html (#review_list) on fetched or fixture HTML."""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

try:
    from lxml import html as lhtml
    from lxml import etree
except ImportError:
    print("pip install lxml", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[1]
FIXTURE = ROOT / "test-fixture" / "dlsite-user-review-list.html"
WP_UA = "Custom RSS Builder/0.9.0; https://123789.jp/custom-rss-builder/"


def css_to_xpath(selector: str) -> str:
    """Mirror PHP crb_css_to_xpath for simple selectors (#id, .class, tag, descendant)."""
    selector = selector.strip()
    if re.match(r"^\*:(\w[\w-]*)$", selector):
        selector = re.sub(r"^\*:(\w[\w-]*)$", r"\1", selector)
    normalized = re.sub(r"\s*>\s*", " > ", selector)
    parts = re.split(r"\s+", normalized.strip())
    segments: list[str] = []
    rels: list[str] = []
    rel = "desc"
    for part in parts:
        if part == ">":
            rel = "child"
            continue
        tag = "*"
        conds: list[str] = []
        rest = part
        m = re.match(r"^([a-zA-Z][a-zA-Z0-9-]*)", rest)
        if m:
            tag, rest = m.group(1), rest[len(m.group(1)) :]
        while rest:
            if rest.startswith("."):
                m2 = re.match(r"^\.([a-zA-Z0-9_-]+)", rest)
                if not m2:
                    raise ValueError(f"bad class in {part}")
                conds.append(
                    f'contains(concat(" ", normalize-space(@class), " "), " {m2.group(1)} ")'
                )
                rest = rest[len(m2.group(0)) :]
            elif rest.startswith("#"):
                m2 = re.match(r"^#([a-zA-Z0-9_-]+)", rest)
                if not m2:
                    raise ValueError(f"bad id in {part}")
                conds.append(f'@id="{m2.group(1)}"')
                rest = rest[len(m2.group(0)) :]
            elif rest.startswith("["):
                raise ValueError(f"attr selector not implemented in sim: {part}")
            else:
                raise ValueError(f"unsupported segment: {part}")
        seg = tag
        if conds:
            seg += "[" + " and ".join(conds) + "]"
        segments.append(seg)
        rels.append(rel)
        rel = "desc"
    xp = "."
    for seg, r in zip(segments, rels):
        xp += "/" if r == "child" else "//"
        xp += seg
    return xp


def load_crb_root(html: str) -> etree._Element:
    wrapped = f'<?xml encoding="utf-8" ?><div id="crb-root">{html}</div>'
    doc = lhtml.fromstring(wrapped.encode("utf-8"))
    root = doc.xpath('//*[@id="crb-root"]')
    if not root:
        raise RuntimeError("crb-root missing")
    return root[0]


def query_scope(root, scope: str) -> list:
    return root.xpath(css_to_xpath(scope))


def analyze_html(html: str, scope: str, label: str) -> int:
    print(f"\n=== {label} ===")
    print(f"HTML bytes: {len(html.encode('utf-8'))}")
    for needle in (
        'id="review_list"',
        "id='review_list'",
        'class="review_list"',
        "review_list_box",
        "work_1col_table",
    ):
        print(f"  contains {needle!r}: {needle in html}")

    root = load_crb_root(html)
    xp = css_to_xpath(scope)
    print(f"  XPath: {xp}")
    nodes = query_scope(root, scope)
    print(f"  Match count ({scope} strict): {len(nodes)}")
    if nodes:
        el = nodes[0]
        snippet = etree.tostring(el, encoding="unicode")[:200]
        print(f"  First: tag={el.tag} id={el.get('id')} class={el.get('class')}")
        print(f"  Snippet: {snippet}...")
    for alt in (".review_list", ".review_inner", "table.work_1col_table"):
        try:
            print(f"  Alt {alt}: {len(root.xpath(css_to_xpath(alt)))}")
        except Exception as exc:
            print(f"  Alt {alt}: ERR {exc}")
    return len(nodes)


def fetch_url(url: str, user_agent: str) -> str:
    import urllib.request

    req = urllib.request.Request(
        url,
        headers={
            "User-Agent": user_agent,
            "Accept-Language": "ja,en;q=0.8",
        },
    )
    with urllib.request.urlopen(req, timeout=25) as resp:
        status = resp.status
        body = resp.read().decode("utf-8", errors="replace")
    print(f"  HTTP {status}")
    return body


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--url", action="append", help="Fetch live HTML (repeatable)")
    ap.add_argument("--fixture", default=str(FIXTURE))
    ap.add_argument("--scope", default="#review_list")
    ap.add_argument("--wp-ua", action="store_true", help="Use WordPress plugin User-Agent")
    args = ap.parse_args()

    fail = 0
    fixture = Path(args.fixture)
    if fixture.is_file():
        if analyze_html(fixture.read_text(encoding="utf-8", errors="replace"), args.scope, f"fixture {fixture.name}") == 0:
            fail += 1
    else:
        print(f"SKIP fixture missing: {fixture}")

    ua = WP_UA if args.wp_ua else "Mozilla/5.0 (compatible; CustomRSSBuilder/1.0)"
    for url in args.url or []:
        try:
            body = fetch_url(url, ua)
            n = analyze_html(body, args.scope, f"live [{ua[:40]}…] {url}")
            if n == 0:
                fail += 1
                print("  => FAIL (same as ajax_discover_scope_html error)")
        except Exception as exc:
            print(f"\nFAIL fetch {url}: {exc}")
            fail += 1

    if not args.url:
        print("\nTip: --url https://www.dlsite.com/maniax/reviewlist/=/reviewer/REV....html")
        print("     --url https://www.dlsite.com/maniax/work/reviewlist/=/product_id/RJ....html")

    return 1 if fail else 0


if __name__ == "__main__":
    raise SystemExit(main())
