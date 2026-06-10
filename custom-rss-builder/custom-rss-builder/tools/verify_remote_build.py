#!/usr/bin/env python3
import re
import ssl
import urllib.request

BASE = "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder"
CTX = ssl.create_default_context()

def get(path: str) -> str:
    with urllib.request.urlopen(BASE + path, context=CTX, timeout=25) as r:
        return r.read().decode("utf-8", "replace")

main = get("/custom-rss-builder.php")
lic = get("/includes/functions-license.php")
build = re.search(r"CRB_BUILD_ID', '([^']+)'", main)
print("BUILD_ID", build.group(1) if build else "NOT FOUND")
print("prepare_request", "crb_license_prepare_request" in lic)
print("FREE_SLOT_3", "CRB_LICENSE_FREE_SLOT_LIMIT', 3" in lic)
print("OK" if build and build.group(1) == "20260531a" else "FAIL")
