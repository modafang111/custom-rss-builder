#!/usr/bin/env python3
import re
import ssl
import urllib.request

CTX = ssl.create_default_context()
LP = "https://123789.jp/custom-rss-builder/"


def fetch(url: str) -> bytes:
    req = urllib.request.Request(url, headers={"Cache-Control": "no-cache"})
    with urllib.request.urlopen(req, context=CTX, timeout=30) as resp:
        return resp.read()


main = fetch(
    "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder/custom-rss-builder.php"
).decode("utf-8", "replace")
m = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main)
print("BUILD", m.group(1) if m else "?")

html = fetch(LP).decode("utf-8", "replace")
imgs = sorted(set(re.findall(r"lp-[a-z-]+\.png", html)))
print("LP images in HTML:", imgs)
print("has crb-sales-lp__img:", "crb-sales-lp__img" in html)
print("placeholder left:", "ここへヒーロー" in html)

hero = fetch(
    "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder/assets/images/lp/lp-hero-feed-edit.png"
)
print("hero png bytes:", len(hero))
