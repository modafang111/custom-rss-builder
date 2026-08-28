#!/usr/bin/env python3
"""Probe DLM download page for http:// links and redirect chains."""
from __future__ import annotations

import re
import ssl
import urllib.error
import urllib.request
from urllib.parse import urljoin

PAGE = "https://123789.jp/custom-rss-builder/download/695/"


def fetch(url: str, method: str = "GET") -> tuple[int, dict[str, str], str, str]:
    req = urllib.request.Request(
        url,
        method=method,
        headers={"User-Agent": "CRB-DLM-Probe/1.0"},
    )
    ctx = ssl.create_default_context()
    try:
        with urllib.request.urlopen(req, context=ctx, timeout=30) as resp:
            headers = {k.lower(): v for k, v in resp.headers.items()}
            body = resp.read(200_000).decode("utf-8", errors="replace")
            return resp.status, headers, resp.geturl(), body
    except urllib.error.HTTPError as e:
        headers = {k.lower(): v for k, v in e.headers.items()}
        body = e.read(50_000).decode("utf-8", errors="replace")
        return e.code, headers, e.geturl(), body


def trace(url: str, max_hops: int = 8) -> list[tuple[str, int, str]]:
    chain: list[tuple[str, int, str]] = []
    current = url
    for _ in range(max_hops):
        req = urllib.request.Request(
            current,
            method="HEAD",
            headers={"User-Agent": "CRB-DLM-Probe/1.0"},
        )
        ctx = ssl.create_default_context()
        try:
            with urllib.request.urlopen(req, context=ctx, timeout=20) as resp:
                chain.append((current, resp.status, resp.geturl()))
                loc = resp.headers.get("Location")
                if not loc or resp.status not in (301, 302, 303, 307, 308):
                    break
                current = urljoin(current, loc)
        except urllib.error.HTTPError as e:
            chain.append((current, e.code, e.geturl()))
            loc = e.headers.get("Location")
            if not loc or e.code not in (301, 302, 303, 307, 308):
                break
            current = urljoin(current, loc)
        except Exception as exc:  # noqa: BLE001
            chain.append((current, -1, str(exc)))
            break
    return chain


def main() -> int:
    print("=== probe_dlm_download ===\n")
    status, headers, final, body = fetch(PAGE)
    print(f"PAGE {PAGE}")
    print(f"  status={status} final={final}")
    print(f"  content-type={headers.get('content-type', '?')}")

    http_links = sorted(set(re.findall(r'https?://[^\s"\'<>]+', body)))
    bad = [u for u in http_links if u.startswith("http://")]
    print(f"\nLinks in HTML: {len(http_links)} (http:// = {len(bad)})")
    for u in bad[:20]:
        print(f"  HTTP  {u}")
    for u in [u for u in http_links if u.startswith("https://")][:15]:
        if "download" in u.lower() or "dlm" in u.lower() or ".zip" in u.lower():
            print(f"  HTTPS {u}")

    candidates = [
        u
        for u in http_links
        if "download" in u.lower() or "dlm" in u.lower() or u.endswith(".zip")
    ]
    print("\nRedirect traces (HEAD):")
    for u in candidates[:6]:
        print(f"\n  {u}")
        for hop_url, hop_status, hop_final in trace(u):
            scheme = "http" if hop_url.startswith("http://") else "https"
            print(f"    {hop_status} {scheme} {hop_url}")
            if hop_final != hop_url:
                print(f"         -> {hop_final}")

  # Common DLM endpoint patterns
    for pattern in (
        "https://123789.jp/custom-rss-builder/?download_id=695",
        "https://123789.jp/custom-rss-builder/download/695/?tmstv=1",
    ):
        print(f"\n  {pattern}")
        for hop_url, hop_status, hop_final in trace(pattern):
            print(f"    {hop_status} {hop_url}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
