#!/usr/bin/env python3
import urllib.request

url = "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder/admin/class-admin-page.php"
req = urllib.request.Request(url, headers={"User-Agent": "CRB/1.0"})
body = urllib.request.urlopen(req, timeout=20).read().decode("utf-8", "replace")
print("len", len(body))
for needle in ("render_admin_page_header", "render_settings_page", "CRB_VERSION", "time()"):
    print(needle, needle in body)
if "render_settings_page" in body:
    i = body.find("render_settings_page")
    print(body[i : i + 400])
