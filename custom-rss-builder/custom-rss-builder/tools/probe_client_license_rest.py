#!/usr/bin/env python3
"""Probe 123789.jp license REST (no WordPress login). Usage: probe_client_license_rest.py [secret]"""
from __future__ import annotations

import json
import ssl
import sys
import urllib.error
import urllib.request

API_BASE = "https://123789.jp/custom-rss-builder"
ROUTE = "crb-license/v1/check"
CTX = ssl.create_default_context()


def post(url: str, body: dict, secret: str | None) -> tuple[int, dict | str]:
    payload = json.dumps(body).encode("utf-8")
    headers = {"Content-Type": "application/json"}
    if secret:
        headers["X-CRB-License-Secret"] = secret
    req = urllib.request.Request(url, data=payload, headers=headers, method="POST")
    try:
        with urllib.request.urlopen(req, context=CTX, timeout=25) as resp:
            raw = resp.read().decode("utf-8", "replace")
            code = resp.getcode()
    except urllib.error.HTTPError as exc:
        code = exc.code
        raw = exc.read().decode("utf-8", "replace")
    try:
        data = json.loads(raw)
    except json.JSONDecodeError:
        return code, raw[:500]
    return code, data


def main() -> int:
    secret = sys.argv[1] if len(sys.argv) > 1 else ""
    body = {
        "license_key": "CRB-TEST-PROBE",
        "site_url": "https://wordpress-123.com/PluginTest",
        "secret": secret,
    }
    urls = [
        f"{API_BASE}/?rest_route=/{ROUTE}",
        f"{API_BASE}/wp-json/{ROUTE}",
    ]
    print("probe_client_license_rest")
    print("secret:", "set" if secret else "(empty)")
    fail = 0
    for url in urls:
        code, data = post(url, body, secret or None)
        print(f"\nURL {url}")
        print("HTTP", code)
        print("body", data)
        if isinstance(data, dict):
            msg = str(data.get("message", ""))
            if not secret and "その操作を実行する権限がありません" in msg:
                print("FAIL: still generic WP forbidden (server not updated?)")
                fail += 1
            elif not secret and "API Secret" in msg:
                print("OK: explicit secret error (server updated)")
            elif secret and code == 200:
                print("OK: authenticated (key may still be invalid)")
            elif secret and code < 500:
                print("OK: secret accepted by API")
    return fail


if __name__ == "__main__":
    raise SystemExit(main())
