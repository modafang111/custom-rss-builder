#!/usr/bin/env python3
"""PluginTest から LP 用スクリーンショットを取得し assets/images/lp/ に保存する。

新規フィード（未入力）状態の画面を撮影する。モザイクは使わない。
"""
from __future__ import annotations

import base64
import ftplib
import json
import secrets
import ssl
import sys
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


def probe_url(token: str, action: str, **params: str) -> str:
    q = {"token": token, "action": action, **params}
    return (
        CLIENT_BASE
        + "/wp-content/plugins/custom-rss-builder/"
        + PROBE_REMOTE_NAME
        + "?"
        + urllib.parse.urlencode(q)
    )


def http_json(url: str) -> dict:
    req = urllib.request.Request(url, headers={"Cache-Control": "no-cache"})
    with urllib.request.urlopen(req, context=CTX, timeout=60) as resp:
        return json.loads(resp.read().decode("utf-8", errors="replace"))


def capture(token: str) -> None:
    probe_base = CLIENT_BASE + "/wp-content/plugins/custom-rss-builder/" + PROBE_REMOTE_NAME

    front_payload = http_json(probe_url(token, "prepare_lp_front"))
    if not front_payload.get("ok"):
        raise RuntimeError("prepare_lp_front failed: " + str(front_payload))
    front_post_url = str(front_payload.get("url") or CLIENT_BASE + "/")
    print("Sample post:", front_post_url)

    login_url = probe_url(token, "login_edit", feed_id="new")
    edit_url = CLIENT_BASE + "/wp-admin/admin.php?page=custom-rss-builder&action=edit"
    rss_demo_url = probe_url(token, "lp_rss_demo")

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

        for selector in (
            "#crb-step-4",
            "#crb-step-3",
            ".crb-workflow-step--slots",
            ".crb-panel--css",
        ):
            loc = page.locator(selector).first
            if loc.count() > 0:
                loc.scroll_into_view_if_needed()
                page.wait_for_timeout(500)
                break
        page.screenshot(path=str(OUT_DIR / "lp-hero-feed-edit.png"), full_page=False)

        step1 = page.locator("#crb-step-1").first
        if step1.count() > 0:
            step1.scroll_into_view_if_needed()
            page.wait_for_timeout(500)

        from PIL import Image

        shots: list[Image.Image] = []
        if step1.count() > 0:
            step1_png = OUT_DIR / "_tmp-step1.png"
            step1.screenshot(path=str(step1_png))
            shots.append(Image.open(step1_png).convert("RGB"))

        panel = page.locator("#crb-preview-panel").first
        if panel.count() > 0:
            panel_png = OUT_DIR / "_tmp-preview.png"
            panel.screenshot(path=str(panel_png))
            shots.append(Image.open(panel_png).convert("RGB"))

        if shots:
            width = max(im.size[0] for im in shots)
            total_h = sum(im.size[1] for im in shots)
            combined = Image.new("RGB", (width, total_h), (255, 255, 255))
            y = 0
            for im in shots:
                combined.paste(im, (0, y))
                y += im.size[1]
            combined.save(OUT_DIR / "lp-shot-preview.png", format="PNG", optimize=True)
            for tmp in (OUT_DIR / "_tmp-step1.png", OUT_DIR / "_tmp-preview.png"):
                if tmp.is_file():
                    tmp.unlink()
            print(f"  lp-shot-preview.png {width}x{total_h}")
        else:
            page.screenshot(path=str(OUT_DIR / "lp-shot-preview.png"), full_page=False)

        rss_page = context.new_page()
        rss_page.goto(rss_demo_url, wait_until="domcontentloaded", timeout=120000)
        rss_page.wait_for_timeout(1500)
        rss_page.screenshot(path=str(OUT_DIR / "lp-shot-rss.png"), full_page=False)

        front_page = context.new_page()
        front_page.goto(front_post_url, wait_until="networkidle", timeout=120000)
        front_page.wait_for_timeout(1500)
        article = front_page.locator("article.post, .entry-content, main article").first
        if article.count() > 0:
            article.screenshot(path=str(OUT_DIR / "lp-shot-imported.png"))
        else:
            front_page.screenshot(path=str(OUT_DIR / "lp-shot-imported.png"), full_page=False)

        browser.close()


def main() -> None:
    token = secrets.token_urlsafe(24)
    print("Uploading probe...")
    ftp_upload_probe(token)
    try:
        capture(token)
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
