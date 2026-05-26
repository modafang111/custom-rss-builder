#!/usr/bin/env python3
"""Verify slot order matches DLsite review_list HTML."""
from pathlib import Path

from lxml import html

ROOT = Path(__file__).resolve().parent.parent
FIXTURE = ROOT / "test-fixture/dlsite-review-snippet.html"

# {%1}..{%8} slot keys in plugin order
SLOTS = (
    "title",
    "link",
    "summary",
    "image_url",
    "category",
    "review_title",
    "review_body",
    "author",
)

doc = html.fromstring('<div id="crb-root">' + FIXTURE.read_text(encoding="utf-8") + "</div>")
scope = doc.cssselect("#review_list")[0]
blocks = scope.cssselect(".review_contents")
assert len(blocks) >= 3, f"expected >=3 blocks, got {len(blocks)}"


def pick_image(b):
    for sel in (
        ".review_work .work_thumb .thumb-container img",
        ".review_work .work_thumb picture source[srcset]",
        ".review_work .work_thumb picture img",
        ".work_img_popover img",
    ):
        nodes = b.cssselect(sel)
        for node in nodes:
            if node.tag == "source":
                srcset = node.get("srcset", "")
                url = srcset.split(",")[0].strip().split()[0] if srcset else ""
            else:
                url = node.get("src", "")
            if url and not url.startswith("data:"):
                return url
    return ""


def extract_block(b):
    link_el = b.cssselect('dt.work_name a[href*="product_id"]')[0]
    return {
        "title": (link_el.get("title") or link_el.text_content()).strip(),
        "link": link_el.get("href", "").strip(),
        "summary": b.cssselect("dd.work_text")[0].text_content().strip(),
        "image_url": pick_image(b),
        "category": b.cssselect(".review_work .work_category a")[0].text_content().strip(),
        "review_title": b.cssselect('.reveiw_title a[href*="reviewlist"]')[0].text_content().strip(),
        "review_body": html.tostring(
            b.cssselect(".review_main p.review_desc")[0], encoding="unicode", method="html"
        ),
        "author": b.cssselect("dd.maker_name span.author a")[0].text_content().strip(),
    }


for i, b in enumerate(blocks[:3]):
    rec = extract_block(b)
    for key in SLOTS:
        assert rec.get(key), f"block {i+1} missing {key}"
    print(
        f"OK {i+1}: slot1(title)={rec['title'][:30]}... "
        f"| slot2(link) | slot3(summary) | slot4(image)={rec['image_url'][:50]}"
    )

print("All slot checks passed (3 blocks).")
