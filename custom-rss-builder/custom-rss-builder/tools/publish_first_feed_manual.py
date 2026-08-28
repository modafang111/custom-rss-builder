#!/usr/bin/env python3
"""最初のフィード作成操作マニュアルを公開する。"""
from __future__ import annotations

import base64
import json
import mimetypes
import ssl
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANUAL_DIR = ROOT / "assets" / "images" / "manual"
ACCOUNTS = Path(r"D:\dev\auto-post\data\custom-rss-builder-123789-jp\wordpress_accounts.json")
BASE = "https://123789.jp/custom-rss-builder"
PARENT_ID = 620
SLUG = "crb-first-feed-manual"
CTX = ssl.create_default_context()

FIGURES = [
    ("02-practice-samples.png", "練習用サンプル一覧"),
    ("08-wp-feed-list.png", "フィード一覧と「新規フィード作成」"),
    ("20-new-feed-basic.png", "フィード名と対象 URL の入力イメージ"),
    ("03-sample-simple-div.png", "パターン1のサンプルページ（対象 URL の中身）"),
    ("21-selectors-slots.png", "範囲・1件・スロットの設定イメージ"),
    ("09-feed-edit.png", "実際のフィード編集画面（範囲・スロット）"),
    ("22-preview-save.png", "プレビュー結果の確認と保存"),
    ("10-preview.png", "プレビュー結果（抽出データ一覧）の実例"),
    ("11-rss.png", "RSS 配信 URL をブラウザで表示"),
    ("23-rss-import.png", "RSS 確認と取り込み設定のイメージ"),
    ("12-imported-post.png", "取り込んだ投稿の例"),
    ("24-sample-list-wrapper.png", "パターン2：箱 + 記事のサンプル"),
]


def load_auth() -> tuple[str, str]:
    data = json.loads(ACCOUNTS.read_text(encoding="utf-8"))
    row = data[0]
    return str(row["username"]), str(row["application_password"]).replace(" ", "")


def api_request(
    method: str,
    path: str,
    user: str,
    password: str,
    data: bytes | None = None,
    content_type: str | None = None,
    extra_headers: dict[str, str] | None = None,
):
    url = BASE.rstrip("/") + path
    headers = {
        "Authorization": "Basic "
        + base64.b64encode(f"{user}:{password}".encode("ascii")).decode("ascii"),
        "Accept": "application/json",
    }
    if content_type:
        headers["Content-Type"] = content_type
    if extra_headers:
        headers.update(extra_headers)
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, context=CTX, timeout=120) as resp:
            raw = resp.read().decode("utf-8", errors="replace")
            code = resp.status
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"{method} {path} -> {exc.code}: {raw[:800]}") from exc
    if not raw:
        return code, {}
    try:
        return code, json.loads(raw)
    except json.JSONDecodeError:
        return code, raw


def upload_media(user: str, password: str, path: Path) -> str:
    # reuse existing media if already mapped
    mime = mimetypes.guess_type(path.name)[0] or "application/octet-stream"
    code, payload = api_request(
        "POST",
        "/wp-json/wp/v2/media",
        user,
        password,
        data=path.read_bytes(),
        content_type=mime,
        extra_headers={"Content-Disposition": f'attachment; filename="{path.name}"'},
    )
    if not isinstance(payload, dict) or "source_url" not in payload:
        raise RuntimeError(f"upload failed {path.name}: {code} {payload}")
    print("uploaded", path.name, payload.get("id"), payload.get("source_url"))
    return str(payload["source_url"])


def fig(url: str, caption: str) -> str:
    return (
        '<figure class="crb-manual-figure" style="margin:1rem 0 1.5rem;max-width:920px">'
        f'<img src="{url}" alt="{caption}" style="display:block;width:100%;height:auto;border:1px solid #dcdcde;border-radius:4px" />'
        f'<figcaption style="margin-top:.5rem;color:#50575e;font-size:13px">{caption}</figcaption>'
        "</figure>"
    )


def build_content(urls: dict[str, str]) -> str:
    def f(name: str) -> str:
        cap = next(c for n, c in FIGURES if n == name)
        return fig(urls[name], cap)

    sample1 = "https://123789.jp/custom-rss-builder/crb-practice-samples/crb-sample-simple-div/"
    samples = "https://123789.jp/custom-rss-builder/crb-practice-samples/"
    install = "https://123789.jp/custom-rss-builder/crb-practice-samples/crb-plugin-install-guide/"
    feedpack = "https://123789.jp/custom-rss-builder/crb-practice-samples/crb-feed-pack-manual/"
    ai = "https://123789.jp/custom-rss-builder/crb-practice-samples/crb-openai-api-key-setup/"

    return "\n".join(
        [
            '<div class="crb-ai-manual crb-feed-ops-manual">',
            "<p><strong>操作マニュアル（最初のフィード作成）</strong> — プラグイン導入済み・ライセンス有効化済みの方向けです。インストール手順は別ページです。</p>",
            '<nav class="crb-ai-manual-toc" aria-label="目次"><h2>目次</h2><ol>',
            '<li><a href="#crb-fops-prep">前提・準備</a></li>',
            '<li><a href="#crb-fops-new">手順 1：新規フィードを開く</a></li>',
            '<li><a href="#crb-fops-url">手順 2：フィード名・対象 URL</a></li>',
            '<li><a href="#crb-fops-css">手順 3：範囲・1件・スロット</a></li>',
            '<li><a href="#crb-fops-preview">手順 4：プレビューして保存</a></li>',
            '<li><a href="#crb-fops-rss">手順 5：RSS を確認</a></li>',
            '<li><a href="#crb-fops-import">手順 6：WordPress へ取り込み（任意）</a></li>',
            '<li><a href="#crb-fops-patterns">練習パターン別セレクタ早見表</a></li>',
            '<li><a href="#crb-fops-tips">うまく取れないとき</a></li>',
            '<li><a href="#crb-fops-next">次のステップ</a></li>',
            "</ol></nav>",
            '<h2 id="crb-fops-prep">前提・準備</h2>',
            "<ul>",
            "<li>Custom RSS Builder が有効で、ライセンスが「利用可」になっている</li>",
            "<li>最初は練習用サンプルで手順を通し、その後に本番サイトの URL へ切り替える</li>",
            "</ul>",
            f'<p>練習用トップ: <a href="{samples}" target="_blank" rel="noopener noreferrer">製品・練習用サンプル</a></p>',
            f("02-practice-samples.png"),
            '<h2 id="crb-fops-new">手順 1：新規フィードを開く</h2>',
            "<ol>",
            "<li>WordPress 管理画面 → Custom RSS Builder</li>",
            "<li>「新規フィード作成」を開く（一覧のボタンからも可）</li>",
            "</ol>",
            f("08-wp-feed-list.png"),
            '<h2 id="crb-fops-url">手順 2：フィード名・対象 URL</h2>',
            "<ol>",
            "<li>フィード名：管理しやすい名前（例: 練習パターン1）</li>",
            "<li>対象 URL：記事が一覧で並んでいるページの URL（個別記事ではなく一覧）</li>",
            "</ol>",
            f'<p>練習ではパターン1を推奨: <a href="{sample1}" target="_blank" rel="noopener noreferrer">crb-sample-simple-div</a></p>',
            f("20-new-feed-basic.png"),
            f("03-sample-simple-div.png"),
            '<h2 id="crb-fops-css">手順 3：範囲・1件・スロット</h2>',
            "<p>Feed43 と同様に「一覧の場所（範囲）」「1件ぶんの区切り」「スロット {%1%}〜」で抽出します。</p>",
            "<h3>パターン1（div が並ぶ）の設定例</h3>",
            '<table class="widefat" style="max-width:920px"><thead><tr><th>項目</th><th>値</th><th>メモ</th></tr></thead><tbody>',
            "<tr><td>一覧の場所（範囲）</td><td><code>（空欄）</code></td><td>親の箱が無いので空欄</td></tr>",
            "<tr><td>1件ぶんの区切り</td><td><code>.crb-sample-news-item</code></td><td>1記事を囲む要素</td></tr>",
            "<tr><td>{%1%} リンク</td><td><code>a</code> / リンクURL</td><td>1件内の a</td></tr>",
            "<tr><td>{%2%} タイトル</td><td><code>a</code> / 表示テキスト</td><td>同じ a の文字</td></tr>",
            "<tr><td>{%3%} 日付など</td><td><code>.crb-sample-date</code> / 表示テキスト</td><td>任意スロット</td></tr>",
            "</tbody></table>",
            f("21-selectors-slots.png"),
            f("09-feed-edit.png"),
            '<p class="crb-ai-manual-note">スロット数はプランにより異なります（無料 {%1%}〜{%3%}、スタンダード〜{%5%}、Pro〜{%20%}）。</p>',
            '<h2 id="crb-fops-preview">手順 4：プレビューして保存</h2>',
            "<ol>",
            "<li>「プレビュー」を押し、件数・タイトル・リンクが取れているか確認</li>",
            "<li>空欄や誤った URL ばかりのときは、1件セレクタ／スロットを見直す</li>",
            "<li>問題なければ「保存」</li>",
            "</ol>",
            f("22-preview-save.png"),
            f("10-preview.png"),
            '<h2 id="crb-fops-rss">手順 5：RSS を確認</h2>',
            "<ol>",
            "<li>フィード一覧（または編集画面）の RSS URL をコピー</li>",
            "<li>ブラウザで開き、item が並ぶ XML になっていることを確認</li>",
            "</ol>",
            f("11-rss.png"),
            '<h2 id="crb-fops-import">手順 6：WordPress へ取り込み（任意）</h2>',
            "<ol>",
            "<li>投稿テンプレートでタイトル・本文に {%1%}〜 を埋め込む（例: タイトル {%2%}、本文に {%3%} とリンク {%1%}）</li>",
            "<li>「今すぐ取り込む」で手動取り込み、またはスケジュールで自動取り込み（プランによる）</li>",
            "</ol>",
            f("23-rss-import.png"),
            f("12-imported-post.png"),
            '<h2 id="crb-fops-patterns">練習パターン別セレクタ早見表</h2>',
            '<table class="widefat" style="max-width:920px"><thead><tr><th>パターン</th><th>範囲</th><th>1件</th><th>リンク例</th></tr></thead><tbody>',
            "<tr><td>1 div</td><td><code>（空欄）</code></td><td><code>.crb-sample-news-item</code></td><td><code>a</code></td></tr>",
            "<tr><td>2 箱+記事</td><td><code>.crb-sample-list</code></td><td><code>.crb-sample-item</code></td><td><code>.crb-sample-title a</code></td></tr>",
            "<tr><td>3 ul/li</td><td><code>ul.crb-sample-ul</code></td><td><code>li.crb-sample-li</code></td><td><code>a.crb-sample-link</code></td></tr>",
            "<tr><td>4 入れ子</td><td><code>.crb-sample-feed</code></td><td><code>.crb-sample-feed_list_item</code></td><td><code>a.crb-sample-feed_link</code></td></tr>",
            "<tr><td>5 table</td><td><code>table.crb-sample-table</code></td><td><code>tbody tr</code></td><td><code>a</code></td></tr>",
            "</tbody></table>",
            f("24-sample-list-wrapper.png"),
            '<h2 id="crb-fops-tips">うまく取れないとき</h2>',
            "<ul>",
            "<li>対象 URL がログイン必須・会員限定の場合は取得できないことがあります</li>",
            "<li>プレビュー 0 件 → まず「1件ぶんの区切り」を見直す（範囲は後回しでも可）</li>",
            "<li>タイトルは取れるがリンクが空 → スロットの取り方を「リンクURL」にする</li>",
            "<li>本番サイトではブラウザの検証ツールで要素を確認し、class をセレクタにする</li>",
            "</ul>",
            '<h2 id="crb-fops-next">次のステップ</h2>',
            "<ul>",
            f'<li><a href="{install}">インストール手順（導入前）</a></li>',
            f'<li><a href="{feedpack}">フィード設定パック（エクスポート／インポート）</a></li>',
            f'<li><a href="{ai}">Gemini API キー設定（Pro AI 変換）</a></li>',
            "</ul>",
            "<hr />",
            '<p><small>本ページは Custom RSS Builder の操作マニュアル（最初のフィード作成）です（2026-07-30）。</small></p>',
            "</div>",
        ]
    )


def find_existing_page(user: str, password: str) -> int:
    code, payload = api_request(
        "GET",
        f"/wp-json/wp/v2/pages?slug={SLUG}&per_page=1",
        user,
        password,
    )
    if isinstance(payload, list) and payload:
        return int(payload[0]["id"])
    return 0


def main() -> None:
    user, password = load_auth()
    urls: dict[str, str] = {}
    # Prefer reusing previous map for overlapping filenames
    map_path = MANUAL_DIR / "media_url_map.json"
    old = {}
    if map_path.is_file():
        old = json.loads(map_path.read_text(encoding="utf-8"))
    for name, _cap in FIGURES:
        path = MANUAL_DIR / name
        if not path.is_file():
            raise FileNotFoundError(path)
        if name in old and name.startswith(("02-", "03-", "08-", "09-", "10-", "11-", "12-")):
            # still re-upload new shots; reuse only if HEAD ok
            try:
                req = urllib.request.Request(old[name], method="HEAD")
                with urllib.request.urlopen(req, context=CTX, timeout=30) as resp:
                    if resp.status == 200:
                        urls[name] = old[name]
                        print("reuse", name)
                        continue
            except Exception:
                pass
        urls[name] = upload_media(user, password, path)

    content = build_content(urls)
    page_id = find_existing_page(user, password)
    body = {
        "title": "最初のフィード作成（操作マニュアル）",
        "content": content,
        "status": "publish",
        "slug": SLUG,
        "parent": PARENT_ID,
    }
    payload = json.dumps(body, ensure_ascii=False).encode("utf-8")
    if page_id > 0:
        code, result = api_request(
            "POST",
            f"/wp-json/wp/v2/pages/{page_id}",
            user,
            password,
            data=payload,
            content_type="application/json",
        )
    else:
        code, result = api_request(
            "POST",
            "/wp-json/wp/v2/pages",
            user,
            password,
            data=payload,
            content_type="application/json",
        )
    if not isinstance(result, dict):
        raise RuntimeError(f"page upsert failed: {code} {result}")
    print("page", result.get("id"), result.get("link"))

    # Patch install guide next-steps to link here
    feed_ops_url = str(result.get("link") or "")
    if feed_ops_url:
        code, install_page = api_request(
            "GET",
            "/wp-json/wp/v2/pages/691?context=edit",
            user,
            password,
        )
        if isinstance(install_page, dict):
            html = str(install_page.get("content", {}).get("raw") or "")
            marker = "最初のフィード作成（操作マニュアル）"
            if marker not in html and "crb-install-next" in html:
                insert = (
                    f'<li><a href="{feed_ops_url}"><strong>{marker}</strong></a></li>\n'
                )
                html = html.replace(
                    '<h2 id="crb-install-next">次のステップ</h2>\n<ul class="crb-manual-index-list">\n',
                    '<h2 id="crb-install-next">次のステップ</h2>\n<ul class="crb-manual-index-list">\n'
                    + insert,
                )
                # alternate list without class
                if marker not in html:
                    html = html.replace(
                        '<h2 id="crb-install-next">次のステップ</h2>',
                        '<h2 id="crb-install-next">次のステップ</h2>\n'
                        f'<p><a href="{feed_ops_url}"><strong>{marker}</strong></a> ← 導入後はこちら</p>',
                    )
                upd = json.dumps({"content": html}, ensure_ascii=False).encode("utf-8")
                api_request(
                    "POST",
                    "/wp-json/wp/v2/pages/691",
                    user,
                    password,
                    data=upd,
                    content_type="application/json",
                )
                print("patched install guide link")

    merged = {**old, **urls}
    map_path.write_text(json.dumps(merged, ensure_ascii=False, indent=2), encoding="utf-8")
    print("wrote", map_path)


if __name__ == "__main__":
    main()
