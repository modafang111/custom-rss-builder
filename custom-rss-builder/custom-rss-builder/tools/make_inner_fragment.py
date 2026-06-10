#!/usr/bin/env python3
from pathlib import Path

src = Path(__file__).resolve().parents[1] / "test-fixture" / "dlsite-user-review-list.html"
dst = Path(__file__).resolve().parents[1] / "test-fixture" / "dlsite-review-inner-fragment.html"
html = src.read_text(encoding="utf-8")
marker = '<div class="review_inner">'
idx = html.find(marker)
dst.write_text(html[idx:] if idx >= 0 else html, encoding="utf-8")
print(dst, len(dst.read_text(encoding="utf-8")))
