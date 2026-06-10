#!/usr/bin/env python3
import re
import sys
import urllib.request

URL = (
    "https://123789.jp/custom-rss-builder/wp-content/plugins/"
    "custom-rss-builder/assets/js/admin.js"
)
req = urllib.request.Request(URL, headers={"User-Agent": "CRB-Check/1.0"})
body = urllib.request.urlopen(req, timeout=20).read().decode("utf-8", "replace")
build = re.search(r"crbAdminBuild\s*=\s*'([^']+)'", body)
rd_start = body.find("function renderDiscoverResults")
rd_end = body.find("function discoverElements", rd_start)
rd = body[rd_start:rd_end] if rd_start >= 0 and rd_end > rd_start else ""
checks = [
    ("crbAdminBuild 0.8.0", (build.group(1) if build else "") == "0.8.0"),
    ("no auto apply in renderDiscoverResults", "applyScopePreviewToStep4(lastDiscoverPayload)" not in rd),
    ("no step3 apply button", "④スロットに反映" not in body and "crb-discover-apply-bar" not in body),
    ("step4 button handler", body.count("applyScopePreviewToStep4(lastDiscoverPayload)") == 1),
]
failed = 0
for name, ok in checks:
    print("OK  " if ok else "FAIL", name)
    if not ok:
        failed += 1
sys.exit(1 if failed else 0)
