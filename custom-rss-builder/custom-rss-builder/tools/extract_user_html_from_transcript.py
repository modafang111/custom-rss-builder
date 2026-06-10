#!/usr/bin/env python3
import json
import re
import sys
from pathlib import Path

TRANSCRIPT = Path(
    r"C:\Users\アイデアマート\.cursor\projects\c-ai-spec-builder-plugins"
    r"\agent-transcripts\bc5d4ea0-c10e-48fd-853c-ff02e419094c"
    r"\bc5d4ea0-c10e-48fd-853c-ff02e419094c.jsonl"
)
OUT = Path(__file__).resolve().parents[1] / "test-fixture" / "dlsite-user-review-list.html"


def message_text(obj: dict) -> str:
    if not isinstance(obj, dict):
        return ""
    if isinstance(obj.get("message"), dict):
        return message_text(obj["message"])
    content = obj.get("content")
    if isinstance(content, str):
        return content
    if isinstance(content, list):
        parts = []
        for part in content:
            if isinstance(part, dict) and part.get("type") == "text":
                parts.append(part.get("text", ""))
        return "".join(parts)
    return ""


def main() -> int:
    html = None
    for line in TRANSCRIPT.open(encoding="utf-8"):
        try:
            obj = json.loads(line)
        except json.JSONDecodeError:
            continue
        if obj.get("role") != "user":
            continue
        text = message_text(obj)
        if "コレで試してください" not in text and "RJ01600467" not in text:
            continue
        m = re.search(r'(<div class="review_list".*)', text, re.DOTALL)
        if m:
            html = m.group(1).strip()
            # trim after closing review_list if trailing assistant text leaked in
            end = html.rfind("</div>")
            if end != -1:
                # keep last two closing divs (review_inner + review_list)
                pass
            if "コレで試してください" in text:
                break
    if not html:
        print("HTML not found", file=sys.stderr)
        return 1
    OUT.write_text(html, encoding="utf-8")
    print(f"Wrote {len(html):,} bytes -> {OUT}")
    print("review_contents:", html.count('class="review_contents"'))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
