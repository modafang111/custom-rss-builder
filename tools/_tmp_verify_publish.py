#!/usr/bin/env python3
import re
import ssl
import urllib.request
import zipfile
from io import BytesIO

ctx = ssl.create_default_context()
zip_url = (
    "https://123789.jp/custom-rss-builder/wp-content/uploads/dlm_uploads/"
    "2026/06/custom-rss-builder-client.zip"
)
main_url = (
    "https://123789.jp/custom-rss-builder/wp-content/plugins/"
    "custom-rss-builder/custom-rss-builder.php"
)

for label, url, is_zip in (
    ("dlm_zip", zip_url, True),
    ("authority_main", main_url, False),
):
    req = urllib.request.Request(url, headers={"User-Agent": "crb-publish-verify/1.0"})
    with urllib.request.urlopen(req, context=ctx, timeout=120) as resp:
        data = resp.read()
    print(label, "status", resp.status, "bytes", len(data))
    if is_zip:
        z = zipfile.ZipFile(BytesIO(data))
        main = z.read("custom-rss-builder/custom-rss-builder.php").decode("utf-8")
        sched = z.read("custom-rss-builder/includes/functions-import-schedule.php").decode(
            "utf-8"
        )
    else:
        main = data.decode("utf-8", "replace")
        sched = ""
    build = re.search(r"CRB_BUILD_ID',\s*'([^']+)'", main)
    print("  BUILD_ID", build.group(1) if build else "NOT FOUND")
    if sched:
        fixed = "・]+/u" not in sched and "/[,、|\\/]+/u" in sched
        print("  tag_split_fix", fixed)
