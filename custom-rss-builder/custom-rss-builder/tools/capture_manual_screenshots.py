#!/usr/bin/env python3
"""公開ページ＋管理画面モックの操作マニュアル用スクリーンショットを生成する。"""
from __future__ import annotations

from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "assets" / "images" / "manual"
LP = ROOT / "assets" / "images" / "lp"


def capture_public() -> None:
    shots = [
        (
            "01-lp-free-license.png",
            "https://123789.jp/custom-rss-builder/#crb-lp-register",
            ["#crb-lp-register", ".crb-sales-lp__register", "form"],
            False,
        ),
        (
            "02-practice-samples.png",
            "https://123789.jp/custom-rss-builder/crb-practice-samples/",
            ["article", "main", ".site-content"],
            False,
        ),
        (
            "03-sample-simple-div.png",
            "https://123789.jp/custom-rss-builder/crb-practice-samples/crb-sample-simple-div/",
            ["article", "main", ".site-content"],
            True,
        ),
        (
            "07-feed-pack-manual.png",
            "https://123789.jp/custom-rss-builder/crb-practice-samples/crb-feed-pack-manual/",
            ["article", "main", ".site-content"],
            False,
        ),
    ]
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={"width": 1280, "height": 900},
            locale="ja-JP",
            device_scale_factor=1.25,
        )
        page = context.new_page()
        for name, url, selectors, full in shots:
            print("public", name)
            page.goto(url, wait_until="networkidle", timeout=120000)
            page.wait_for_timeout(1000)
            path = OUT / name
            if full:
                page.screenshot(path=str(path), full_page=True)
            else:
                saved = False
                for sel in selectors:
                    loc = page.locator(sel).first
                    if loc.count() == 0:
                        continue
                    try:
                        loc.scroll_into_view_if_needed(timeout=3000)
                        page.wait_for_timeout(300)
                        loc.screenshot(path=str(path))
                        saved = True
                        break
                    except Exception:
                        continue
                if not saved:
                    page.screenshot(path=str(path), full_page=False)
            print(" saved", path.name, path.stat().st_size)
        browser.close()


MOCK_UPLOAD = """<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><title>プラグインのアップロード</title>
<style>
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f0f1;color:#1d2327}
#wpadminbar{height:32px;background:#1d2327;color:#fff;display:flex;align-items:center;padding:0 12px;font-size:13px}
#adminmenu{width:160px;background:#1d2327;color:#fff;min-height:100vh;float:left;padding:12px 0;box-sizing:border-box}
#adminmenu div{padding:8px 12px;opacity:.85;font-size:13px}
#adminmenu .current{background:#2271b1;opacity:1}
#wpcontent{margin-left:160px;padding:24px 28px}
h1{font-size:23px;font-weight:400;margin:0 0 16px}
.card{background:#fff;border:1px solid #c3c4c7;box-shadow:0 1px 1px rgba(0,0,0,.04);padding:20px 22px;max-width:720px}
.button-primary{background:#2271b1;border:1px solid #2271b1;color:#fff;padding:6px 14px;border-radius:3px;font-size:13px}
.button{background:#f6f7f7;border:1px solid #c3c4c7;padding:6px 12px;border-radius:3px;font-size:13px;margin-right:8px}
.note{margin-top:14px;color:#50575e;font-size:13px;line-height:1.5}
.arrow{display:inline-block;background:#d63638;color:#fff;font-size:12px;padding:2px 8px;border-radius:999px;margin-left:8px;vertical-align:middle}
.callout{margin:12px 0;padding:10px 12px;border-left:4px solid #2271b1;background:#f0f6fc;font-size:13px}
</style></head><body>
<div id="wpadminbar">WordPress 管理画面</div>
<div id="adminmenu">
  <div>ダッシュボード</div>
  <div class="current">プラグイン</div>
  <div>　新規追加</div>
  <div>Custom RSS Builder</div>
</div>
<div id="wpcontent">
  <h1>プラグインをアップロード <span class="arrow">ここ</span></h1>
  <div class="card">
    <p>プラグインの .zip ファイルがある場合、ここでアップロードしてインストールできます。</p>
    <div class="callout">ファイルを選択 → <strong>custom-rss-builder-client.zip</strong></div>
    <p>
      <button class="button">ファイルを選択</button>
      <button class="button-primary">今すぐインストール</button>
    </p>
    <p class="note">インストール完了後、「プラグインを有効化」を押します。左メニューに「Custom RSS Builder」が追加されます。</p>
  </div>
</div>
</body></html>
"""

MOCK_LICENSE = """<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><title>ライセンス有効化</title>
<style>
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f0f1;color:#1d2327}
#wpadminbar{height:32px;background:#1d2327;color:#fff;display:flex;align-items:center;padding:0 12px;font-size:13px}
#adminmenu{width:180px;background:#1d2327;color:#fff;min-height:100vh;float:left;padding:12px 0;box-sizing:border-box}
#adminmenu div{padding:8px 12px;opacity:.85;font-size:13px}
#adminmenu .current{background:#2271b1;opacity:1}
#wpcontent{margin-left:180px;padding:24px 28px}
h1{font-size:23px;font-weight:400;margin:0 0 16px}
.panel{background:#fff;border:1px solid #c3c4c7;padding:18px 20px;max-width:760px;margin-bottom:16px}
.badge{display:inline-block;background:#00a32a;color:#fff;font-size:12px;padding:2px 8px;border-radius:3px}
.row{margin:10px 0;font-size:14px}
label{display:block;font-weight:600;margin-bottom:6px}
input{width:100%;max-width:480px;padding:8px 10px;border:1px solid #8c8f94;border-radius:3px}
.button-primary{background:#2271b1;border:1px solid #2271b1;color:#fff;padding:8px 16px;border-radius:3px;margin-top:10px}
.arrow{display:inline-block;background:#d63638;color:#fff;font-size:12px;padding:2px 8px;border-radius:999px;margin-left:8px}
.muted{color:#50575e;font-size:13px}
</style></head><body>
<div id="wpadminbar">WordPress 管理画面</div>
<div id="adminmenu">
  <div>ダッシュボード</div>
  <div>Custom RSS Builder</div>
  <div class="current">　ライセンス</div>
</div>
<div id="wpcontent">
  <h1>Custom RSS Builder — ライセンス</h1>
  <div class="panel">
    <h2 style="margin:0 0 10px;font-size:16px">現在の状態</h2>
    <div class="row">プラン: <strong>無料</strong></div>
    <div class="row">このサイトで利用可: <span class="badge">はい</span></div>
  </div>
  <div class="panel">
    <h2 style="margin:0 0 10px;font-size:16px">ライセンスキーを有効化 <span class="arrow">入力</span></h2>
    <label for="key">ライセンスキー</label>
    <input id="key" value="CRB-FREE-XXXXXXXX" />
    <div><button class="button-primary">有効化</button></div>
    <p class="muted">無料登録メールに届いたキーを貼り付けます。有効化後、上部の「利用可：はい」を確認してください。</p>
  </div>
</div>
</body></html>
"""

MOCK_FEED_LIST = """<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><title>フィード一覧</title>
<style>
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f0f1;color:#1d2327}
#wpadminbar{height:32px;background:#1d2327;color:#fff;display:flex;align-items:center;padding:0 12px;font-size:13px}
#adminmenu{width:180px;background:#1d2327;color:#fff;min-height:100vh;float:left;padding:12px 0;box-sizing:border-box}
#adminmenu div{padding:8px 12px;opacity:.85;font-size:13px}
#adminmenu .current{background:#2271b1;opacity:1}
#wpcontent{margin-left:180px;padding:24px 28px}
h1{font-size:23px;font-weight:400;margin:0 0 16px}
.button-primary{background:#2271b1;border:1px solid #2271b1;color:#fff;padding:6px 12px;border-radius:3px;text-decoration:none;font-size:13px}
table{width:100%;max-width:900px;border-collapse:collapse;background:#fff;border:1px solid #c3c4c7}
th,td{border-bottom:1px solid #dcdcde;padding:10px 12px;text-align:left;font-size:13px}
th{background:#f6f7f7}
code{background:#f0f0f1;padding:2px 6px;border-radius:3px}
.arrow{display:inline-block;background:#d63638;color:#fff;font-size:12px;padding:2px 8px;border-radius:999px;margin-left:8px}
</style></head><body>
<div id="wpadminbar">WordPress 管理画面</div>
<div id="adminmenu">
  <div>ダッシュボード</div>
  <div class="current">Custom RSS Builder</div>
  <div>　新規フィード作成</div>
  <div>　ライセンス</div>
</div>
<div id="wpcontent">
  <h1>フィード一覧 <a class="button-primary" href="#">新規フィード作成</a> <span class="arrow">最初はここ</span></h1>
  <table>
    <thead><tr><th>名前</th><th>対象 URL</th><th>RSS URL</th><th>操作</th></tr></thead>
    <tbody>
      <tr>
        <td>練習パターン1</td>
        <td>…/crb-sample-simple-div/</td>
        <td><code>?crb_feed=1</code></td>
        <td>編集 / プレビュー / 取り込み</td>
      </tr>
    </tbody>
  </table>
</div>
</body></html>
"""


def capture_mocks() -> None:
    mocks = [
        ("05-wp-plugin-upload.png", MOCK_UPLOAD),
        ("06-wp-license-activate.png", MOCK_LICENSE),
        ("08-wp-feed-list.png", MOCK_FEED_LIST),
    ]
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={"width": 1280, "height": 800},
            locale="ja-JP",
            device_scale_factor=1.25,
        )
        page = context.new_page()
        for name, html in mocks:
            print("mock", name)
            page.set_content(html, wait_until="domcontentloaded")
            page.wait_for_timeout(200)
            path = OUT / name
            page.screenshot(path=str(path), full_page=False)
            print(" saved", path.name, path.stat().st_size)
        browser.close()


def copy_lp_shots() -> None:
    mapping = {
        "09-feed-edit.png": "lp-hero-feed-edit.png",
        "10-preview.png": "lp-shot-preview.png",
        "11-rss.png": "lp-shot-rss.png",
        "12-imported-post.png": "lp-shot-imported.png",
    }
    for dest, src in mapping.items():
        src_path = LP / src
        if not src_path.is_file():
            print("missing LP", src)
            continue
        data = src_path.read_bytes()
        (OUT / dest).write_bytes(data)
        print("copied", dest, len(data))


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    copy_lp_shots()
    capture_mocks()
    capture_public()
    print("OUT=", OUT)
    for f in sorted(OUT.glob("*.png")):
        print(f.name, f.stat().st_size)


if __name__ == "__main__":
    main()
