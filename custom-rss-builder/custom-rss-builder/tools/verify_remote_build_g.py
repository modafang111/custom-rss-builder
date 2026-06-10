#!/usr/bin/env python3
import re
import urllib.request

BASES = (
    "https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder/",
    "https://123789.jp/wp-content/plugins/custom-rss-builder/",
)


def fetch(url: str) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": "CRB-Verify/1.0"})
    with urllib.request.urlopen(req, timeout=20) as r:
        return r.read().decode("utf-8", "replace")


def main() -> None:
    for base in BASES:
        print("===", base, "===")
        try:
            main_php = fetch(base + "custom-rss-builder.php")
            m = re.search(r"define\s*\(\s*'CRB_BUILD_ID'\s*,\s*'([^']+)'", main_php)
            print("CRB_BUILD_ID", m.group(1) if m else "NOT FOUND")
            admin = fetch(base + "admin/class-admin-page.php")
            print("render_admin_page_header", "render_admin_page_header" in admin)
            js = fetch(base + "assets/js/admin.js")
            print("applyScopePreviewToStep4", "applyScopePreviewToStep4" in js)
            print("cfg.version", "cfg.version" in js)
        except Exception as exc:
            print("ERROR", exc)


if __name__ == "__main__":
    main()
