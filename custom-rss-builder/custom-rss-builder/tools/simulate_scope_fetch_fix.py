#!/usr/bin/env python3
"""
Simulate ajax_discover_scope_html / crb_query_css_elements after 20260607a fix.

Mirrors PHP:
  crb_html_prepare_for_dom
  crb_dom_load_html (no mb_convert_encoding)
  crb_query_css_elements (fast #id / .class → XPath fallback)
"""
from __future__ import annotations

import argparse
import re
import sys
import time
from pathlib import Path

try:
    from lxml import etree
    from lxml import html as lhtml
except ImportError:
    print("pip install lxml", file=sys.stderr)
    sys.exit(2)

ROOT = Path(__file__).resolve().parents[1]
FIXTURE_REVIEW = ROOT / "test-fixture" / "dlsite-user-review-list.html"
TIMEOUT_SEC = 30.0
WP_UA = "Custom RSS Builder/0.9.0; https://wordpress-123.com/PluginTest/"


def prepare_for_dom(html: str) -> str:
    patterns = (
        r"(?is)<script\b[^>]*>.*?</script>",
        r"(?is)<style\b[^>]*>.*?</style>",
        r"(?is)<svg\b[^>]*>.*?</svg>",
        r"(?is)<noscript\b[^>]*>.*?</noscript>",
    )
    for pat in patterns:
        html = re.sub(pat, "", html)
    html = re.sub(r"(?s)<!--.*?-->", "", html)
    return html


def css_to_xpath(selector: str) -> str:
    """Mirror PHP crb_css_to_xpath (simple cases)."""
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
                m2 = re.match(
                    r'^\[([a-zA-Z0-9_-]+)(\*=)?=(?:"([^"]*)"|([^\]]+))?\]', rest
                )
                if not m2:
                    raise ValueError(f"bad attr in {part}")
                attr = m2.group(1)
                if not m2.group(2) and m2.group(3) is None and m2.group(4) is None:
                    conds.append(f"@{attr}")
                else:
                    val = m2.group(3) if m2.group(3) is not None else (m2.group(4) or "")
                    if m2.group(2) == "*=":
                        conds.append(f'contains(@{attr}, "{val}")')
                    else:
                        conds.append(f'@{attr}="{val}"')
                rest = rest[len(m2.group(0)) :]
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


def load_crb_root(html: str, *, prepared: bool) -> etree._Element:
    if not prepared:
        html = prepare_for_dom(html)
    wrapped = f'<?xml encoding="utf-8" ?><div id="crb-root">{html}</div>'
    doc = lhtml.fromstring(wrapped.encode("utf-8"))
    root = doc.xpath('//*[@id="crb-root"]')
    if not root:
        raise RuntimeError("crb-root missing")
    return root[0]


def query_css_fast(root: etree._Element, doc: etree._Element, selector: str) -> list | None:
    selector = selector.strip()
    if re.match(r"^#([a-zA-Z][\w-]*)$", selector):
        el = None
        try:
            el = doc.get_element_by_id(selector[1:])
        except KeyError:
            el = None
        if el is not None:
            cur: etree._Element | None = el
            while cur is not None:
                if cur is root:
                    return [el]
                cur = cur.getparent()
        return []

    if re.match(r"^\.([a-zA-Z][\w-]*)$", selector):
        return root.cssselect(selector)

    if re.match(r"^([a-zA-Z][\w-]*)$", selector):
        return root.xpath(f".//{selector}")

    if m := re.match(r"^([a-zA-Z][\w-]*)#([a-zA-Z][\w-]*)$", selector):
        el = None
        try:
            el = doc.get_element_by_id(m.group(2))
        except KeyError:
            el = None
        if el is not None and el.tag.lower() == m.group(1).lower():
            for cur in [el] + list(el.iterancestors()):
                if cur is root:
                    return [el]
        return []

    if m := re.match(r"^([a-zA-Z][\w-]*)\\.([a-zA-Z][\w-]*)$", selector):
        cls = m.group(2)
        out = []
        for el in root.xpath(f".//{m.group(1)}[@class]"):
            classes = " " + " ".join(el.get("class", "").split()) + " "
            if f" {cls} " in classes:
                out.append(el)
        return out

    return None


def query_css_elements(root: etree._Element, selector: str) -> list:
    doc = root.getroottree().getroot()
    fast = query_css_fast(root, doc, selector)
    if fast is not None:
        return fast
    xp = css_to_xpath(selector)
    return root.xpath(xp)


def simulate_scope_fetch(html: str, scope: str, item: str = "") -> dict:
    raw_bytes = len(html.encode("utf-8"))
    t0 = time.perf_counter()
    prepared = prepare_for_dom(html)
    prep_ms = (time.perf_counter() - t0) * 1000

    t1 = time.perf_counter()
    root = load_crb_root(prepared, prepared=True)
    parse_ms = (time.perf_counter() - t1) * 1000

    t2 = time.perf_counter()
    scope_nodes = query_css_elements(root, scope) if scope else [root]
    scope_ms = (time.perf_counter() - t2) * 1000

    total_ms = (time.perf_counter() - t0) * 1000
    ok = len(scope_nodes) > 0

    item_count = 0
    item_ms = 0.0
    sample_title = ""
    if ok and item:
        t3 = time.perf_counter()
        items = query_css_elements(scope_nodes[0], item)
        item_ms = (time.perf_counter() - t3) * 1000
        item_count = len(items)
        if items:
            links = items[0].cssselect('a[href*="product_id"]')
            if links:
                sample_title = (links[0].get("title") or links[0].text_content() or "").strip()[:60]

    scope_html_len = 0
    if ok:
        scope_html_len = len(etree.tostring(scope_nodes[0], encoding="unicode"))

    return {
        "raw_bytes": raw_bytes,
        "prepared_bytes": len(prepared.encode("utf-8")),
        "prep_ms": round(prep_ms, 1),
        "parse_ms": round(parse_ms, 1),
        "scope_ms": round(scope_ms, 1),
        "item_ms": round(item_ms, 1),
        "total_ms": round(total_ms + item_ms, 1),
        "scope_match": len(scope_nodes),
        "item_match": item_count,
        "scope_html_chars": scope_html_len,
        "sample_title": sample_title,
        "ok": ok,
        "timeout": total_ms + item_ms > TIMEOUT_SEC * 1000,
    }


def fetch_url(url: str, user_agent: str) -> str:
    import urllib.request

    req = urllib.request.Request(
        url,
        headers={"User-Agent": user_agent, "Accept-Language": "ja,en;q=0.8"},
    )
    with urllib.request.urlopen(req, timeout=25) as resp:
        return resp.read().decode("utf-8", errors="replace")


def detect_scopes(html: str) -> list[str]:
    found: list[str] = []
    for needle in ("new_worklist", "review_list", "content", "main"):
        if f'id="{needle}"' in html or f"id='{needle}'" in html:
            found.append(f"#{needle}")
    return found


def print_case(label: str, html: str, scope: str, item: str = "") -> bool:
    print(f"\n{'=' * 72}")
    print(f"CASE: {label}")
    print(f"  scope={scope!r}  item={item!r}")
    r = simulate_scope_fetch(html, scope, item)
    if not r["ok"]:
        alts = detect_scopes(html)
        if alts:
            print(f"  scope miss - detected in HTML: {', '.join(alts)}")
            for alt in alts[:2]:
                alt_r = simulate_scope_fetch(html, alt, item)
                if alt_r["ok"]:
                    print(f"  retry {alt}: {alt_r['scope_match']} match, total {alt_r['total_ms']}ms OK")
                    r = alt_r
                    scope = alt
                    break
    print(f"  HTML: {r['raw_bytes']:,} bytes -> prepared {r['prepared_bytes']:,} bytes "
          f"(-{100 - r['prepared_bytes'] * 100 // max(r['raw_bytes'], 1)}%)")
    print(f"  Timing: prep {r['prep_ms']}ms | parse {r['parse_ms']}ms | "
          f"scope {r['scope_ms']}ms | item {r['item_ms']}ms | TOTAL {r['total_ms']}ms")
    print(f"  Scope matches: {r['scope_match']} | Item matches: {r['item_match']} | "
          f"scope HTML chars: {r['scope_html_chars']:,}")
    if r["sample_title"]:
        print(f"  Sample link title: {r['sample_title']}")
    if r["timeout"]:
        print(f"  FAIL: would exceed {TIMEOUT_SEC}s PHP limit")
        return False
    if not r["ok"]:
        print("  FAIL: scope not found")
        return False
    print("  OK")
    return True


def synthetic_heavy_html() -> str:
    """Page with huge script blocks (typical commercial site)."""
    script = "<script>var x=" + ("[1,2,3]," * 8000) + ";</script>"
    body = '<div id="new_worklist"><dl class="n_worklist_item">' + script
    for i in range(30):
        body += (
            f'<dt class="work_name"><a href="/work/=/product_id/RJ{i:08d}.html" '
            f'title="作品{i}">作品{i}</a></dt><dd class="work_text">説明{i}</dd>'
        )
    body += "</dl></div>"
    return "<html><head>" + (script * 40) + "</head><body>" + body + script * 40 + "</body></html>"


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--url", action="append", help="Live URL to fetch")
    ap.add_argument("--scope", default="#new_worklist")
    ap.add_argument("--item", default=".n_worklist_item")
    args = ap.parse_args()

    cases: list[tuple[str, str, str, str]] = []

    cases.append(("Synthetic heavy (scripts stripped)", synthetic_heavy_html(), "#new_worklist", ".n_worklist_item"))

    if FIXTURE_REVIEW.is_file():
        html = FIXTURE_REVIEW.read_text(encoding="utf-8", errors="replace")
        cases.append(("Fixture review list", html, "#review_list", ".review_contents"))

    live_urls = args.url or [
        "https://www.dlsite.com/maniax/",
        "https://www.dlsite.com/maniax/new/=/type/work",
        "https://www.dlsite.com/maniax/reviewlist/=/reviewer/REV0000012345.html",
    ]

    for url in live_urls:
        try:
            print(f"\nFetching {url} ...")
            body = fetch_url(url, WP_UA)
            print(f"  fetched {len(body.encode('utf-8')):,} bytes")
            cases.append((f"Live {url}", body, args.scope, args.item))
        except Exception as exc:
            print(f"  SKIP fetch failed: {exc}")

    ok_count = 0
    fail_count = 0
    for label, html, scope, item in cases:
        if print_case(label, html, scope, item):
            ok_count += 1
        else:
            fail_count += 1

    print(f"\n{'=' * 72}")
    print(f"SUMMARY: {ok_count} OK / {fail_count} FAIL (PHP limit {TIMEOUT_SEC}s)")
    print("Build fix: crb_html_prepare_for_dom + crb_query_css_elements (20260607a)")
    return 1 if fail_count else 0


if __name__ == "__main__":
    raise SystemExit(main())
