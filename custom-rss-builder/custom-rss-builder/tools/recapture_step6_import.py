#!/usr/bin/env python3
"""手順6「WordPress投稿への取り込み」を実UIに近い見た目で再キャプチャし、マニュアルを更新する。"""
from __future__ import annotations

import base64
import json
import mimetypes
import ssl
import urllib.error
import urllib.request
from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "assets" / "images" / "manual"
ADMIN_CSS = (ROOT / "assets" / "css" / "admin.css").read_text(encoding="utf-8")
ACCOUNTS = Path(r"D:\dev\auto-post\data\custom-rss-builder-123789-jp\wordpress_accounts.json")
BASE = "https://123789.jp/custom-rss-builder"
PAGE_ID = 878
CTX = ssl.create_default_context()

# Real labels from admin/views/partials/feed-import-settings.php + edit-feed.php
HTML_TMPL = """<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>WordPress投稿への取り込み</title>
<style>
__ADMIN_CSS__
body { margin:0; background:#f0f0f1; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif; color:#1d2327; }
#wpadminbar { height:32px; background:#1d2327; color:#f0f0f1; display:flex; align-items:center; padding:0 12px; font-size:13px; }
#adminmenumain { float:left; width:160px; background:#1d2327; min-height:100vh; color:#fff; padding-top:8px; box-sizing:border-box; }
#adminmenu .wp-menu-name { display:block; padding:8px 12px; font-size:13px; opacity:.85; }
#adminmenu .current .wp-menu-name { background:#2271b1; opacity:1; }
#wpcontent { margin-left:160px; padding:16px 20px 40px; }
.wrap { max-width:980px; }
.wrap h1 { font-size:23px; font-weight:400; margin:0 0 12px; padding:0; }
.form-table { border-collapse:collapse; width:100%; margin:0; }
.form-table th { text-align:left; width:200px; padding:15px 10px 15px 0; vertical-align:top; font-weight:600; }
.form-table td { padding:15px 10px; vertical-align:top; }
.form-table .description { color:#646970; font-size:13px; margin:.35em 0 0; }
.large-text { width:100%; max-width:100%; }
.regular-text { width:16em; }
.small-text { width:4.5em; }
.code { font-family:Consolas,Monaco,monospace; }
.button { display:inline-block; text-decoration:none; font-size:13px; line-height:2.15; min-height:30px; margin:0; padding:0 10px; cursor:pointer; border:1px solid #2271b1; background:#f6f7f7; color:#2271b1; border-radius:3px; }
.button-primary { background:#2271b1; border-color:#2271b1; color:#fff; }
.button-secondary { background:#f6f7f7; }
.crb-callout { position:absolute; background:#d63638; color:#fff; font-size:12px; padding:3px 8px; border-radius:999px; white-space:nowrap; z-index:5; }
</style>
</head>
<body>
<div id="wpadminbar">WordPress</div>
<div id="adminmenumain"><ul id="adminmenu" style="margin:0;padding:0;list-style:none">
  <li><div class="wp-menu-name">ダッシュボード</div></li>
  <li class="current"><div class="wp-menu-name">Custom RSS Builder</div></li>
  <li><div class="wp-menu-name">　ライセンス</div></li>
</ul></div>
<div id="wpcontent">
<div class="wrap crb-admin-wrap">
  <h1>フィードを編集 <span style="font-weight:400;color:#646970;font-size:14px">練習パターン1</span></h1>

  <section class="crb-panel" id="crb-import-panel" style="position:relative">
    <span class="crb-callout" style="top:10px;right:14px">手順6の画面</span>
    <header class="crb-panel__header">
      <h2 class="crb-panel__title">WordPress投稿への取り込み</h2>
    </header>
    <p class="crb-panel__lead">抽出結果をもとに、投稿の形（Feed43 の Item テンプレート）を指定します。</p>
    <table class="form-table crb-import-table" role="presentation">
      <tr>
        <th scope="row">取り込み</th>
        <td>
          <label><input type="checkbox" checked> 有効にする</label>
          <p class="description">有効にすると「投稿に取り込み」で WordPress 投稿を作成できます。同じリンクURLは1回だけ取り込みます。</p>
        </td>
      </tr>
      <tr class="crb-import-schedule-row">
        <th scope="row"><label>自動取り込みの間隔</label></th>
        <td>
          <label class="crb-import-schedule-field">
            <input type="number" class="small-text" value="24"> 時間ごと
          </label>
          <p class="description">数字を入力（1〜168）。0 は自動オフ。無料プランは最短 24 時間です。</p>
        </td>
      </tr>
      <tr>
        <th scope="row">投稿設定</th>
        <td class="crb-inline-fields">
          <span class="crb-import-post-status-field">
            <span class="crb-import-post-status-field__label">ステータス</span>
            <select><option selected>下書き</option><option>公開</option></select>
          </span>
          <span class="crb-import-post-type-field">
            <span class="crb-import-post-type-field__label">投稿タイプ</span>
            <input class="regular-text" value="post">
          </span>
          <span class="crb-import-category-field">
            <span class="crb-import-category-field__label">カテゴリー</span>
            <select class="crb-import-category-select"><option>未分類</option></select>
          </span>
          <span class="crb-import-author-field">
            <span class="crb-import-author-field__label">投稿者</span>
            <select class="crb-import-author-select"><option>既定</option></select>
          </span>
          <p class="description">カテゴリー・タグは投稿タイプが post のときのみ適用されます。</p>
        </td>
      </tr>
      <tr>
        <th scope="row"><label>投稿タイトル</label></th>
        <td>
          <input type="text" class="large-text code" value="{%1}">
          <p class="description">任意。空欄のときは抽出タイトルを使用。番号の意味: {%1}=タイトル、{%2}=リンクURL（本文の &lt;a&gt; も href="{%2}" 推奨）。</p>
        </td>
      </tr>
      <tr>
        <th scope="row"><label>投稿本文</label></th>
        <td>
          <textarea class="large-text code" rows="5">&lt;p&gt;&lt;a href="{%2}"&gt;{%1}&lt;/a&gt;&lt;/p&gt;
&lt;p&gt;{%3}&lt;/p&gt;</textarea>
          <p class="description">未入力のまま保存できます。取り込みを使う場合だけ、HTML とスロット番号を記述してください。 <code>{%1}</code> タイトル <code>{%2}</code> リンクURL <code>{%3}</code> … 例: &lt;a href="{%2}"&gt;{%1}&lt;/a&gt;</p>
        </td>
      </tr>
    </table>
    <p class="crb-panel__actions">
      <button type="button" class="button button-secondary">投稿プレビュー</button>
      <span class="description">抽出＋投稿テンプレートを反映して再取得します。</span>
    </p>
  </section>

  <section class="crb-panel crb-panel--preview crb-panel--post-preview" id="crb-post-preview">
    <header class="crb-panel__header">
      <h2 class="crb-panel__title">投稿プレビュー</h2>
    </header>
    <p class="crb-panel__lead">取り込み後の WordPress 投稿がどう見えるかを確認します。</p>
    <div class="crb-panel__body">
      <article style="border:1px solid #dcdcde;padding:14px 16px;background:#fff">
        <h3 style="margin:0 0 8px;font-size:18px">サンプル記事 A</h3>
        <div class="crb-import-preview-body">
          <p><a href="https://example.com/a">サンプル記事 A</a></p>
          <p>2026-07-01</p>
        </div>
      </article>
    </div>
  </section>

  <div class="crb-form-footer" id="crb-import-footer" style="position:relative">
    <span class="crb-callout" style="top:-8px;left:120px">ここを押す</span>
    <p class="submit">
      <button type="button" class="button button-primary">保存</button>
      <span class="crb-feed-tools">
        <button type="button" class="button">投稿に取り込み</button>
      </span>
    </p>
  </div>
</div>
</div>
</body>
</html>
"""

HTML = HTML_TMPL.replace("__ADMIN_CSS__", ADMIN_CSS)


def capture() -> Path:
    OUT.mkdir(parents=True, exist_ok=True)
    path = OUT / "23-wp-import-settings.png"
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(
            viewport={"width": 1360, "height": 1600},
            locale="ja-JP",
            device_scale_factor=1.25,
        )
        page = context.new_page()
        page.set_content(HTML, wait_until="domcontentloaded")
        page.wait_for_timeout(400)
        page.locator(".wrap.crb-admin-wrap").screenshot(path=str(path))
        browser.close()
    print("saved", path, path.stat().st_size)
    return path


def load_auth() -> tuple[str, str]:
    data = json.loads(ACCOUNTS.read_text(encoding="utf-8"))
    row = data[0]
    return str(row["username"]), str(row["application_password"]).replace(" ", "")


def api(method: str, path: str, user: str, password: str, data: bytes | None = None, content_type: str | None = None, extra=None):
    headers = {
        "Authorization": "Basic "
        + base64.b64encode(f"{user}:{password}".encode("ascii")).decode("ascii"),
        "Accept": "application/json",
    }
    if content_type:
        headers["Content-Type"] = content_type
    if extra:
        headers.update(extra)
    req = urllib.request.Request(BASE.rstrip("/") + path, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, context=CTX, timeout=120) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            return resp.status, json.loads(raw) if raw else {}
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"{method} {path} -> {exc.code}: {raw[:500]}") from exc


def upload(user: str, password: str, path: Path) -> str:
    mime = mimetypes.guess_type(path.name)[0] or "image/png"
    _code, payload = api(
        "POST",
        "/wp-json/wp/v2/media",
        user,
        password,
        data=path.read_bytes(),
        content_type=mime,
        extra={"Content-Disposition": f'attachment; filename="{path.name}"'},
    )
    url = str(payload.get("source_url") or "")
    if not url:
        raise RuntimeError(f"upload failed: {payload}")
    print("uploaded", payload.get("id"), url)
    return url


def patch_page(user: str, password: str, new_img_url: str) -> None:
    _code, page = api("GET", f"/wp-json/wp/v2/pages/{PAGE_ID}?context=edit", user, password)
    html = str(page.get("content", {}).get("raw") or "")
    if not html:
        raise RuntimeError("empty page content")

    # Replace old step-6 image block(s)
    import re

    # Remove previous 23-rss-import / old import mock figures near step 6
    html2 = re.sub(
        r'<figure class="crb-manual-figure"[^>]*>\s*<img src="[^"]*(?:23-rss-import|23-wp-import-settings)[^"]*"[^>]*>\s*<figcaption>[^<]*</figcaption>\s*</figure>',
        "",
        html,
        flags=re.I,
    )

    new_fig = (
        '<figure class="crb-manual-figure" style="margin:1rem 0 1.5rem;max-width:920px">'
        f'<img src="{new_img_url}" alt="WordPress投稿への取り込み（フィード編集画面）" '
        'style="display:block;width:100%;height:auto;border:1px solid #dcdcde;border-radius:4px" />'
        "<figcaption style=\"margin-top:.5rem;color:#50575e;font-size:13px\">"
        "フィード編集の「WordPress投稿への取り込み」パネル（取り込みON・テンプレート・投稿プレビュー・投稿に取り込み）"
        "</figcaption></figure>"
    )

    # Correct step 6 instructions to match real UI
    new_step6 = (
        '<h2 id="crb-fops-import">手順 6：WordPress へ取り込み（任意）</h2>\n'
        "<ol>\n"
        "<li>同じフィード編集画面の下部「<strong>WordPress投稿への取り込み</strong>」で「取り込み」を有効にする</li>\n"
        "<li>必要なら自動取り込みの間隔（時間）、ステータス／カテゴリーなどを設定する</li>\n"
        "<li>投稿タイトル・投稿本文にスロットを書く（実画面の番号: <code>{%1}</code>=タイトル、<code>{%2}</code>=リンクURL）</li>\n"
        "<li>「投稿プレビュー」で仕上がりを確認し、「保存」したうえで「投稿に取り込み」を押す</li>\n"
        "</ol>\n"
        f"{new_fig}\n"
    )

    if '<h2 id="crb-fops-import">' in html2:
        html2 = re.sub(
            r'<h2 id="crb-fops-import">.*?(?=<h2 id="crb-fops-patterns">)',
            new_step6,
            html2,
            count=1,
            flags=re.S,
        )
    else:
        raise RuntimeError("step 6 heading not found")

    # Drop the separate "imported post" figure only if it still sits inside step6 - patterns section keeps 12-
    body = json.dumps({"content": html2}, ensure_ascii=False).encode("utf-8")
    _code, result = api(
        "POST",
        f"/wp-json/wp/v2/pages/{PAGE_ID}",
        user,
        password,
        data=body,
        content_type="application/json",
    )
    print("page updated", result.get("link"))


def main() -> None:
    path = capture()
    user, password = load_auth()
    url = upload(user, password, path)
    patch_page(user, password, url)
    map_path = OUT / "media_url_map.json"
    data = {}
    if map_path.is_file():
        data = json.loads(map_path.read_text(encoding="utf-8"))
    data["23-wp-import-settings.png"] = url
    map_path.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")


if __name__ == "__main__":
    main()
