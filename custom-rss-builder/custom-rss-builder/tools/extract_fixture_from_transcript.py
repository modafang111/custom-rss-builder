#!/usr/bin/env python3
import json
from pathlib import Path

transcript = Path(
    r"C:\Users\アイデアマート\.cursor\projects\c-ai-spec-builder\agent-transcripts"
    r"\b78a3911-f5b6-492c-8fb4-264dca0f1376\b78a3911-f5b6-492c-8fb4-264dca0f1376.jsonl"
)
out = Path(__file__).resolve().parent.parent / "test-fixture" / "dlsite-review-snippet.html"

html = ""
for line in transcript.read_text(encoding="utf-8").splitlines():
    if "review_list" not in line or '"role":"user"' not in line:
        continue
    try:
        row = json.loads(line)
    except json.JSONDecodeError:
        continue
    if row.get("role") != "user":
        continue
    for part in row.get("message", {}).get("content", []):
        if part.get("type") != "text":
            continue
        text = part.get("text", "")
        if '<div class="review_list"' in text:
            start = text.find('<div class="review_list"')
            html = text[start:]
            break
    if html:
        break

if not html:
    raise SystemExit("review_list HTML not found in transcript")

# User pasted full page chunk; keep through last ld+json script if present.
marker = "</script>\n        <div class=\"review_contents\">"
last = html.rfind('</script>')
if last > 0:
    # Include content through final review block's closing tags when possible.
    tail = html[last:]
    if "RJ01630014" in html:
        end = html.rfind("</script>", 0, last)
        if end > 0:
            html = html[: last + len("</script>")]

out.write_text(html, encoding="utf-8")
print(f"wrote {len(html)} chars -> {out}")
print("review_contents:", html.count('class="review_contents"'))
