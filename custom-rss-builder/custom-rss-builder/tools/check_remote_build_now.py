#!/usr/bin/env python3
import re
import ssl
import urllib.request

BASE = "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder"
CTX = ssl.create_default_context()


def get(path: str) -> str:
    req = urllib.request.Request(BASE + path, headers={"Cache-Control": "no-cache"})
    with urllib.request.urlopen(req, context=CTX, timeout=25) as r:
        return r.read().decode("utf-8", "replace")


main = get("/custom-rss-builder.php")
lic_view = get("/admin/views/license-settings.php")
lic_fn = get("/includes/functions-license.php")

build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main)
print("remote BUILD_ID:", build.group(1) if build else "NOT FOUND")
print("license UI connection panel:", "save_connection" in lic_view)
print("sprintf fix in functions-license:", "'pro'   => '{%1%}" in lic_fn or "{%1%%}" in lic_fn)
