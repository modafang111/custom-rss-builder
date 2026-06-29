#!/usr/bin/env python3
"""PluginTest から LP 用スクリーンショットを取得し assets/images/lp/ に保存する。"""
from __future__ import annotations

import base64
import ftplib
import json
import re
import secrets
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from io import BytesIO
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "assets" / "images" / "lp"
PROBE_LOCAL = Path(__file__).resolve().parent / "crb_lp_screenshot_probe.php"
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"
FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"
CLIENT_BASE = "https://wordpress-123.com/PluginTest"
CLIENT_REMOTE = (
    "/wordpress-123.com/public_html/PluginTest/wp-content/plugins/custom-rss-builder"
)
PROBE_REMOTE_NAME = "crb_lp_screenshot_probe.php"
CTX = ssl.create_default_context()


def load_ftp_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (
            srv.findtext("User") or ""
        ).strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("FTP password not found")


def ftp_connect() -> ftplib.FTP:
    return ftplib.FTP(FTP_HOST, FTP_USER, load_ftp_password(), timeout=120)


def ftp_upload_probe(token: str) -> None:
    body = PROBE_LOCAL.read_text(encoding="utf-8").replace("__CRB_LP_PROBE_TOKEN__", token)
    bio = BytesIO(body.encode("utf-8"))
    ftp = ftp_connect()
    try:
        ftp.storbinary("STOR " + CLIENT_REMOTE + "/" + PROBE_REMOTE_NAME, bio)
    finally:
        ftp.quit()


def ftp_delete_probe() -> None:
    ftp = ftp_connect()
    try:
        ftp.delete(CLIENT_REMOTE + "/" + PROBE_REMOTE_NAME)
    except ftplib.error_perm:
        pass
    finally:
        ftp.quit()


def http_json(url: str) -> dict:
    req = urllib.request.Request(url, headers={"Cache-Control": "no-cache"})
    with urllib.request.urlopen(req, context=CTX, timeout=60) as resp:
        return json.loads(resp.read().decode("utf-8", errors="replace"))


def capture(token: str) -> int:
    probe_base = CLIENT_BASE + "/wp-content/plugins/custom-rss-builder/" + PROBE_REMOTE_NAME
    feeds_url = probe_base + "?" + urllib.parse.urlencode(
        {"token": token, "action": "list_feeds"}
    )
    payload = http_json(feeds_url)
    if not payload.get("ok"):
        raise RuntimeError("list_feeds failed: " + str(payload))
    feeds = payload.get("feeds") or []
    if not feeds:
        raise RuntimeError("no feeds on PluginTest")
    feed_id = int(feeds[0]["id"])
    print("Using feed:", feed_id, feeds[0].get("name", ""))

    login_url = probe_base + "?" + urllib.parse.urlencode(
        {"token": token, "action": "login_edit", "feed_id": feed_id}
    )
    edit_url = (
        CLIENT_BASE
        + "/wp-admin/admin.php?page=custom-rss-builder&action=edit&feed_id="
        + str(feed_id)
    )
    rss_url = CLIENT_BASE + "/feed/custom-rss/" + str(feed_id) + "/"
    front_url = CLIENT_BASE + "/"

    OUT_DIR.mkdir(parents=True, exist_ok=True)

    from playwright.sync_api import sync_playwright

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={"width": 1440, "height": 900},
            locale="ja-JP",
        )
        page = context.new_page()

        page.goto(login_url, wait_until="networkidle", timeout=120000)
        if "wp-admin" not in page.url:
            page.goto(edit_url, wait_until="networkidle", timeout=120000)
        page.wait_for_timeout(2000)

        # Hero: scroll to slot / scope workflow area.
        for selector in (
            "#crb-step-3",
            "#crb-step-4",
            ".crb-workflow-step--slots",
            ".crb-panel--css",
        ):
            loc = page.locator(selector).first
            if loc.count() > 0:
                loc.scroll_into_view_if_needed()
                page.wait_for_timeout(500)
                break
        page.screenshot(path=str(OUT_DIR / "lp-hero-feed-edit.png"), full_page=False)

        preview_btn = page.locator(
            "button[name='crb_action'][value='preview'], "
            "input[name='crb_action'][value='preview']"
        ).first
        if preview_btn.count() > 0:
            preview_btn.click()
            page.wait_for_load_state("networkidle", timeout=180000)
            page.wait_for_timeout(2500)
            panel = page.locator("#crb-preview-panel, #crb-preview-extract-body").first
            if panel.count() > 0:
                panel.scroll_into_view_if_needed()
                page.wait_for_timeout(800)
        page.screenshot(path=str(OUT_DIR / "lp-shot-preview.png"), full_page=True)

        rss_page = context.new_page()
        rss_page.goto(rss_url, wait_until="domcontentloaded", timeout=120000)
        rss_page.wait_for_timeout(1500)
        rss_page.screenshot(path=str(OUT_DIR / "lp-shot-rss.png"), full_page=False)

        front_page = context.new_page()
        front_page.goto(front_url, wait_until="networkidle", timeout=120000)
        front_page.wait_for_timeout(1500)
        front_page.screenshot(path=str(OUT_DIR / "lp-shot-imported.png"), full_page=False)

        browser.close()

    return feed_id


def main() -> None:
    token = secrets.token_urlsafe(24)
    print("Uploading probe...")
    ftp_upload_probe(token)
    try:
        feed_id = capture(token)
        print("Captured feed_id", feed_id)
        print("Saved images to", OUT_DIR)
    finally:
        print("Removing probe...")
        ftp_delete_probe()


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        print("ERROR:", exc, file=sys.stderr)
        sys.exit(1)
