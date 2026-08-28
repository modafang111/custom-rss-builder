#!/usr/bin/env python3
"""最初のフィード作成〜RSS/取り込みの操作マニュアル用スクショ追加。"""
from __future__ import annotations

from pathlib import Path

from playwright.sync_api import sync_playwright

OUT = Path(__file__).resolve().parents[1] / "assets" / "images" / "manual"

MOCK_NEW_FEED = """<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><title>新規フィード</title>
<style>
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f0f1;color:#1d2327}
#wpadminbar{height:32px;background:#1d2327;color:#fff;display:flex;align-items:center;padding:0 12px;font-size:13px}
#adminmenu{width:180px;background:#1d2327;color:#fff;min-height:100vh;float:left;padding:12px 0;box-sizing:border-box}
#adminmenu div{padding:8px 12px;opacity:.85;font-size:13px}
#adminmenu .current{background:#2271b1;opacity:1}
#wpcontent{margin-left:180px;padding:20px 24px}
h1{font-size:22px;font-weight:400;margin:0 0 14px}
.panel{background:#fff;border:1px solid #c3c4c7;padding:16px 18px;max-width:820px;margin-bottom:14px}
label{display:block;font-weight:600;margin:10px 0 6px;font-size:13px}
input,textarea{width:100%;max-width:640px;padding:8px 10px;border:1px solid #8c8f94;border-radius:3px;font-size:13px;box-sizing:border-box}
.hint{color:#50575e;font-size:12px;margin:4px 0 0}
.arrow{display:inline-block;background:#d63638;color:#fff;font-size:11px;padding:2px 7px;border-radius:999px;margin-left:6px}
.step{display:inline-block;background:#2271b1;color:#fff;font-size:11px;padding:2px 8px;border-radius:3px;margin-right:6px}
code{background:#f0f0f1;padding:1px 5px;border-radius:3px}
</style></head><body>
<div id="wpadminbar">WordPress 管理画面</div>
<div id="adminmenu">
  <div>Custom RSS Builder</div>
  <div class="current">　新規フィード作成</div>
</div>
<div id="wpcontent">
  <h1>新規フィード作成</h1>
  <div class="panel">
    <div><span class="step">①</span><strong>基本情報</strong></div>
    <label>フィード名 <span class="arrow">任意の名前</span></label>
    <input value="練習パターン1" />
    <label>対象 URL <span class="arrow">一覧ページの URL</span></label>
    <input value="https://123789.jp/custom-rss-builder/crb-practice-samples/crb-sample-simple-div/" />
    <p class="hint">記事が並んでいる一覧ページの URL を貼ります（個別記事ページではありません）。</p>
  </div>
</div>
</body></html>
"""

MOCK_SELECTORS = """<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><title>セレクタ設定</title>
<style>
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f0f1;color:#1d2327}
#wpcontent{padding:20px 24px}
.panel{background:#fff;border:1px solid #c3c4c7;padding:16px 18px;max-width:860px}
h2{font-size:16px;margin:0 0 12px}
label{display:block;font-weight:600;margin:12px 0 6px;font-size:13px}
input{width:100%;max-width:520px;padding:8px 10px;border:1px solid #8c8f94;border-radius:3px;font-size:13px;box-sizing:border-box}
.grid{display:grid;grid-template-columns:140px 1fr 140px;gap:8px;align-items:center;margin:8px 0;font-size:13px}
.grid input{max-width:100%}
.arrow{display:inline-block;background:#d63638;color:#fff;font-size:11px;padding:2px 7px;border-radius:999px;margin-left:6px}
.hint{color:#50575e;font-size:12px;line-height:1.5;margin:8px 0 0}
.step{display:inline-block;background:#2271b1;color:#fff;font-size:11px;padding:2px 8px;border-radius:3px;margin-right:6px}
code{background:#f6f7f7;padding:1px 5px}
table{width:100%;border-collapse:collapse;margin-top:10px;font-size:13px}
th,td{border:1px solid #dcdcde;padding:8px;text-align:left}
th{background:#f6f7f7}
</style></head><body>
<div id="wpcontent">
  <div class="panel">
    <h2><span class="step">②</span>抽出設定（CSS） <span class="arrow">ここが本体</span></h2>
    <label>一覧の場所（範囲）</label>
    <input value="" placeholder="（空欄でOK・パターン1）" />
    <p class="hint">親の箱が無い一覧では空欄のままにします。</p>
    <label>1件ぶんの区切り</label>
    <input value=".crb-sample-news-item" />
    <p class="hint">1記事（1行）を囲む要素のセレクタです。</p>
    <h3 style="font-size:14px;margin:18px 0 8px">スロット（{%1%}〜）</h3>
    <table>
      <thead><tr><th>スロット</th><th>セレクタ</th><th>取り方</th></tr></thead>
      <tbody>
        <tr><td>{%1%} リンク</td><td><code>a</code></td><td>リンクURL</td></tr>
        <tr><td>{%2%} タイトル</td><td><code>a</code></td><td>表示テキスト</td></tr>
        <tr><td>{%3%} 日付など</td><td><code>.crb-sample-date</code></td><td>表示テキスト</td></tr>
      </tbody>
    </table>
    <p class="hint">スロットは「1件ぶん」の内側から相対指定します。Feed43 の {%1%}〜 と同じ考え方です。</p>
  </div>
</div>
</body></html>
"""

MOCK_PREVIEW_SAVE = """<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><title>プレビューと保存</title>
<style>
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f0f1;color:#1d2327}
#wpcontent{padding:20px 24px}
.panel{background:#fff;border:1px solid #c3c4c7;padding:16px 18px;max-width:900px;margin-bottom:12px}
.button-primary{background:#2271b1;border:1px solid #2271b1;color:#fff;padding:8px 14px;border-radius:3px;font-size:13px;margin-right:8px}
.button{background:#f6f7f7;border:1px solid #c3c4c7;padding:8px 14px;border-radius:3px;font-size:13px}
.arrow{display:inline-block;background:#d63638;color:#fff;font-size:11px;padding:2px 7px;border-radius:999px;margin-left:6px}
table{width:100%;border-collapse:collapse;font-size:13px;margin-top:10px}
th,td{border:1px solid #dcdcde;padding:8px;text-align:left}
th{background:#f6f7f7}
.ok{color:#00a32a;font-weight:600}
</style></head><body>
<div id="wpcontent">
  <div class="panel">
    <button class="button-primary">プレビュー</button> <span class="arrow">先に確認</span>
    <button class="button-primary">保存</button> <span class="arrow">問題なければ保存</span>
    <p style="margin:12px 0 0;color:#50575e;font-size:13px">プレビューで件数とタイトル・リンクが取れていれば OK です。</p>
  </div>
  <div class="panel">
    <div class="ok">プレビュー結果：3 件</div>
    <table>
      <thead><tr><th>#</th><th>タイトル</th><th>リンク</th><th>日付</th></tr></thead>
      <tbody>
        <tr><td>1</td><td>サンプル記事 A</td><td>https://example.com/a</td><td>2026-07-01</td></tr>
        <tr><td>2</td><td>サンプル記事 B</td><td>https://example.com/b</td><td>2026-07-02</td></tr>
        <tr><td>3</td><td>サンプル記事 C</td><td>https://example.com/c</td><td>2026-07-03</td></tr>
      </tbody>
    </table>
  </div>
</div>
</body></html>
"""

MOCK_IMPORT = """<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><title>取り込み</title>
<style>
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f0f0f1;color:#1d2327}
#wpcontent{padding:20px 24px}
.panel{background:#fff;border:1px solid #c3c4c7;padding:16px 18px;max-width:860px;margin-bottom:12px}
h2{font-size:16px;margin:0 0 10px}
.button-primary{background:#2271b1;border:1px solid #2271b1;color:#fff;padding:8px 14px;border-radius:3px;font-size:13px}
.arrow{display:inline-block;background:#d63638;color:#fff;font-size:11px;padding:2px 7px;border-radius:999px;margin-left:6px}
label{display:block;margin:10px 0 4px;font-size:13px;font-weight:600}
code{background:#f0f0f1;padding:2px 6px;border-radius:3px}
.hint{color:#50575e;font-size:12px;line-height:1.5}
</style></head><body>
<div id="wpcontent">
  <div class="panel">
    <h2>RSS 配信</h2>
    <p>フィード一覧または編集画面の <code>RSS URL</code> をブラウザで開きます。</p>
    <p class="hint">XML で item が並んでいれば配信成功です。他サービス連携にもこの URL を使えます。</p>
  </div>
  <div class="panel">
    <h2>WordPress へ取り込み <span class="arrow">任意</span></h2>
    <label>投稿テンプレート（タイトル）</label>
    <code>{%2%}</code>
    <label>投稿テンプレート（本文）</label>
    <code>&lt;p&gt;{%3%}&lt;/p&gt;&lt;p&gt;&lt;a href="{%1%}"&gt;元記事&lt;/a&gt;&lt;/p&gt;</code>
    <p style="margin-top:14px"><button class="button-primary">今すぐ取り込む</button></p>
    <p class="hint">手動取り込みのほか、プランにより時間指定の自動取り込みも使えます。</p>
  </div>
</div>
</body></html>
"""


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    mocks = [
        ("20-new-feed-basic.png", MOCK_NEW_FEED),
        ("21-selectors-slots.png", MOCK_SELECTORS),
        ("22-preview-save.png", MOCK_PREVIEW_SAVE),
        ("23-rss-import.png", MOCK_IMPORT),
    ]
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={"width": 1280, "height": 820},
            locale="ja-JP",
            device_scale_factor=1.25,
        )
        page = context.new_page()
        for name, html in mocks:
            page.set_content(html, wait_until="domcontentloaded")
            page.wait_for_timeout(150)
            path = OUT / name
            page.screenshot(path=str(path), full_page=False)
            print("saved", path.name, path.stat().st_size)
        # also capture pattern2 page for variety
        page.goto(
            "https://123789.jp/custom-rss-builder/crb-practice-samples/crb-sample-list-wrapper/",
            wait_until="networkidle",
            timeout=120000,
        )
        page.wait_for_timeout(800)
        page.screenshot(path=str(OUT / "24-sample-list-wrapper.png"), full_page=True)
        print("saved 24-sample-list-wrapper.png", (OUT / "24-sample-list-wrapper.png").stat().st_size)
        browser.close()


if __name__ == "__main__":
    main()
