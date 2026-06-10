#!/usr/bin/env python3
import re
import urllib.request

URL = "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder/custom-rss-builder.php"
req = urllib.request.Request(URL, headers={"User-Agent": "CRB-Verify/1.0"})
with urllib.request.urlopen(req, timeout=20) as r:
    body = r.read().decode("utf-8", "replace")
    print("HTTP", r.status)
    v = re.search(r"define\s*\(\s*'CRB_VERSION'\s*,\s*'([^']+)'", body)
    b = re.search(r"define\s*\(\s*'CRB_BUILD_ID'\s*,\s*'([^']+)'", body)
    print("CRB_VERSION", v.group(1) if v else "NOT FOUND")
    print("CRB_BUILD_ID", b.group(1) if b else "NOT FOUND")
    has_infer = "functions-discover-infer.php" in body
    print("requires discover-infer", has_infer)
