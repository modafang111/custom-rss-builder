#!/usr/bin/env python3
import re
import urllib.request

FILES = (
    "custom-rss-builder.php",
    "admin/class-admin-page.php",
    "includes/functions-sanitize.php",
)

BASE = "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder/"

for name in FILES:
    url = BASE + name
    req = urllib.request.Request(url, headers={"User-Agent": "CRB-Check/1.0"})
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            body = r.read().decode("utf-8", "replace")
    except Exception as exc:
        print(name, "ERROR", exc)
        continue
    print("===", name, "HTTP", r.status, "===")
    if name == "custom-rss-builder.php":
        v = re.search(r"define\s*\(\s*'CRB_VERSION'\s*,\s*'([^']+)'", body)
        print("CRB_VERSION", v.group(1) if v else "NOT FOUND")
    if name == "admin/class-admin-page.php":
        for needle in (
            "render_admin_page_header",
            "crb-version-badge",
            "render_admin_page_header()",
        ):
            print(needle, "=>", needle in body)
    if name == "includes/functions-sanitize.php":
        print("crb_get_plugin_version_info =>", "crb_get_plugin_version_info" in body)
