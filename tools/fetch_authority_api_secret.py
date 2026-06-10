#!/usr/bin/env python3
"""123789.jp 正本から API Secret を取得し、client ZIP ビルド用 JSON に保存する。"""
from __future__ import annotations

import base64
import ftplib
import json
import secrets
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from io import BytesIO
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
OUT_PATH = Path(__file__).resolve().parent / "crb_client_build_secrets.local.json"
PROBE_LOCAL = (
    BASE
    / "custom-rss-builder"
    / "custom-rss-builder"
    / "tools"
    / "crb_acceptance_probe.php"
)
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"
FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"
AUTHORITY_BASE = "https://123789.jp/custom-rss-builder"
AUTHORITY_REMOTE = (
    "/123789.jp/public_html/custom-rss-builder/wp-content/plugins/custom-rss-builder"
)
PROBE_PATH = "/wp-content/plugins/custom-rss-builder/crb_acceptance_probe.php"
DEFAULT_API_BASE = "https://123789.jp/custom-rss-builder"
CTX = ssl.create_default_context()


def load_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (
            srv.findtext("User") or ""
        ).strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", "replace")
    raise RuntimeError("FTP credentials not found")


def ftp_upload_probe(token: str) -> None:
    body = PROBE_LOCAL.read_text(encoding="utf-8").replace("__CRB_PROBE_TOKEN__", token)
    remote_file = f"{AUTHORITY_REMOTE}/crb_acceptance_probe.php"
    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
    ftp.set_pasv(True)
    try:
        ftp.storbinary(f"STOR {remote_file}", BytesIO(body.encode("utf-8")))
    finally:
        ftp.quit()


def ftp_delete_probe() -> None:
    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=120)
    ftp.set_pasv(True)
    try:
        ftp.delete(f"{AUTHORITY_REMOTE}/crb_acceptance_probe.php")
    except ftplib.error_perm:
        pass
    finally:
        ftp.quit()


def probe_api_secret(token: str) -> str:
    q = urllib.parse.urlencode({"token": token, "action": "api_secret"})
    url = AUTHORITY_BASE.rstrip("/") + PROBE_PATH + "?" + q
    req = urllib.request.Request(url, method="GET")
    try:
        with urllib.request.urlopen(req, context=CTX, timeout=60) as resp:
            raw = resp.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", "replace")
        raise RuntimeError(f"HTTP {exc.code}: {raw[:300]}") from exc
    data = json.loads(raw)
    if not data.get("ok"):
        raise RuntimeError(data.get("error", raw[:300]))
    secret = str((data.get("data") or {}).get("api_secret", "")).strip()
    if not secret:
        raise RuntimeError("api_secret empty in probe response")
    return secret


def main() -> int:
    if not PROBE_LOCAL.is_file():
        print(f"Missing probe: {PROBE_LOCAL}", file=sys.stderr)
        return 1

    token = secrets.token_urlsafe(24)
    print("Uploading acceptance probe to authority...")
    ftp_upload_probe(token)
    try:
        print("Fetching API Secret...")
        secret = probe_api_secret(token)
    finally:
        print("Removing probe from authority...")
        ftp_delete_probe()

    payload = {
        "api_base": DEFAULT_API_BASE,
        "api_secret": secret,
    }
    OUT_PATH.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    print(f"Saved: {OUT_PATH}")
    print(f"Secret length: {len(secret)} chars (not printed)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
