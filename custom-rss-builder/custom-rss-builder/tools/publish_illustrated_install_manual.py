#!/usr/bin/env python3
"""操作マニュアル画像を WP メディアへ上げ、インストール手順ページを更新する。"""
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
PAGE_ID = 691
CTX = ssl.create_default_context()

FIGURES = [
    ("01-lp-free-license.png", "販売ページの無料ライセンス申請フォーム"),
    ("05-wp-plugin-upload.png", "WordPress：プラグイン ZIP のアップロード画面（イメージ）"),
    ("06-wp-license-activate.png", "Custom RSS Builder → ライセンス：キー有効化（イメージ）"),
    ("08-wp-feed-list.png", "フィード一覧と「新規フィード作成」（イメージ）"),
    ("03-sample-simple-div.png", "練習パターン1：div が並ぶサンプルページ"),
    ("09-feed-edit.png", "フィード編集画面（範囲・スロット設定）"),
    ("10-preview.png", "プレビュー結果（抽出データ一覧）"),
    ("11-rss.png", "RSS 配信 URL をブラウザで表示"),
    ("12-imported-post.png", "WordPress へ取り込んだ記事の例"),
    ("02-practice-samples.png", "練習用サンプル一覧（セレクタ練習）"),
]


def load_auth() -> tuple[str, str]:
    data = json.loads(ACCOUNTS.read_text(encoding="utf-8"))
    row = data[0]
    user = str(row["username"])
    password = str(row["application_password"]).replace(" ", "")
    return user, password


def api_request(
    method: str,
    path: str,
    user: str,
    password: str,
    data: bytes | None = None,
    content_type: str | None = None,
    extra_headers: dict[str, str] | None = None,
) -> tuple[int, dict | list | str]:
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
        raise RuntimeError(f"{method} {path} -> {exc.code}: {raw[:500]}") from exc
    if not raw:
        return code, {}
    try:
        return code, json.loads(raw)
    except json.JSONDecodeError:
        return code, raw


def upload_media(user: str, password: str, path: Path) -> dict:
    mime = mimetypes.guess_type(path.name)[0] or "application/octet-stream"
    body = path.read_bytes()
    code, payload = api_request(
        "POST",
        "/wp-json/wp/v2/media",
        user,
        password,
        data=body,
        content_type=mime,
        extra_headers={
            "Content-Disposition": f'attachment; filename="{path.name}"',
        },
    )
    if not isinstance(payload, dict) or "source_url" not in payload:
        raise RuntimeError(f"upload failed {path.name}: {code} {payload}")
    print("uploaded", path.name, "->", payload.get("id"), payload.get("source_url"))
    return payload


def figure_html(url: str, caption: str) -> str:
    return (
        '<figure class="crb-manual-figure" style="margin:1rem 0 1.5rem;max-width:920px">'
        f'<img src="{url}" alt="{caption}" '
        'style="display:block;width:100%;height:auto;border:1px solid #dcdcde;border-radius:4px" />'
        f'<figcaption style="margin-top:.5rem;color:#50575e;font-size:13px">{caption}</figcaption>'
        "</figure>"
    )


def build_content(url_map: dict[str, str]) -> str:
    def fig(name: str) -> str:
        caption = next(c for n, c in FIGURES if n == name)
        return figure_html(url_map[name], caption)

    parts = [
        '<div class="crb-ai-manual crb-install-manual">',
        '<p><strong>画像入り操作マニュアル</strong> — インストールから最初のフィード作成・RSS 確認までの流れです。</p>',
        '<nav class="crb-ai-manual-toc" aria-label="目次">',
        "<h2>目次</h2>",
        "<ol>",
        '<li><a href="#crb-install-overview">概要</a></li>',
        '<li><a href="#crb-install-requirements">必要環境</a></li>',
        '<li><a href="#crb-install-checklist">作業チェックリスト</a></li>',
        '<li><a href="#crb-install-license-get">手順 A：無料ライセンスを申請</a></li>',
        '<li><a href="#crb-install-upload">手順 B：ZIP のアップロード</a></li>',
        '<li><a href="#crb-install-activate">手順 C：プラグインの有効化</a></li>',
        '<li><a href="#crb-install-license">手順 D：ライセンスキーの有効化</a></li>',
        '<li><a href="#crb-install-first-feed">手順 E：最初のフィードを作成</a></li>',
        '<li><a href="#crb-install-rss">手順 F：RSS と取り込みを確認</a></li>',
        '<li><a href="#crb-install-plans">プランの違い（参考）</a></li>',
        '<li><a href="#crb-install-next">次のステップ</a></li>',
        '<li><a href="#crb-install-faq">よくある質問</a></li>',
        "</ol></nav>",
        '<h2 id="crb-install-overview">概要</h2>',
        "<p>Custom RSS Builder は、RSS 非対応の Web ページから記事一覧を抽出し、RSS 2.0 フィードとして配信する WordPress プラグインです。抽出結果を WordPress 投稿へ取り込むこともできます。</p>",
        "<p>本ページでは、無料プランでの導入から、練習用サンプルで最初のフィードを作るまでを、画面イメージ付きで説明します。</p>",
        '<h2 id="crb-install-requirements">必要環境</h2>',
        "<ul>",
        "<li>WordPress 5.8 以上</li>",
        "<li>PHP 7.4 以上</li>",
        "<li>プラグイン ZIP ファイル（custom-rss-builder-client.zip）</li>",
        "<li>ライセンスキー（無料登録で取得、または有料プラン購入後にメールで受け取り）</li>",
        "</ul>",
        '<h2 id="crb-install-checklist">作業チェックリスト</h2>',
        "<ol>",
        "<li>無料ライセンスを申請し、メールでキーと ZIP 案内を受け取った</li>",
        "<li>ZIP を WordPress にアップロードし、プラグインを有効化した</li>",
        "<li>管理画面に「Custom RSS Builder」メニューが表示されている</li>",
        "<li>ライセンスキーを入力し、「利用可」になった</li>",
        "<li>フィードを 1 件作成し、プレビューで記事が取れることを確認した</li>",
        "<li>RSS URL をブラウザで開き、取り込み（任意）まで確認した</li>",
        "</ol>",
        '<h2 id="crb-install-license-get">手順 A：無料ライセンスを申請</h2>',
        "<ol>",
        "<li>販売ページ下部の「無料プラン」フォームにメールアドレスを入力する</li>",
        "<li>「無料ライセンスを申請」を押す</li>",
        "<li>届いたメールのキーと、ZIP ダウンロード案内を控える</li>",
        "</ol>",
        fig("01-lp-free-license.png"),
        '<h2 id="crb-install-upload">手順 B：ZIP のアップロード</h2>',
        "<ol>",
        "<li>WordPress 管理画面に管理者としてログインする</li>",
        "<li>プラグイン → 新規追加 → プラグインのアップロード を開く</li>",
        "<li>「ファイルを選択」で custom-rss-builder-client.zip を選び、「今すぐインストール」を押す</li>",
        "<li>インストールが完了したら「プラグインを有効化」を押す</li>",
        "</ol>",
        '<p class="crb-ai-manual-note">既に旧バージョンが入っている場合は、同じ手順で ZIP をアップロードすると上書き更新されます。</p>',
        fig("05-wp-plugin-upload.png"),
        '<h2 id="crb-install-activate">手順 C：プラグインの有効化</h2>',
        "<ol>",
        "<li>プラグイン一覧で「Custom RSS Builder」が有効になっていることを確認</li>",
        "<li>左メニューに「Custom RSS Builder」が表示されることを確認</li>",
        "</ol>",
        fig("08-wp-feed-list.png"),
        '<h2 id="crb-install-license">手順 D：ライセンスキーの有効化</h2>',
        "<p>プラグインを利用するには、ライセンスキーの有効化が必要です。</p>",
        "<h3>D-1. キーの入手</h3>",
        "<ul>",
        "<li><strong>無料プラン</strong> — 販売元サイトの無料登録フォームからキーを取得（メール送信あり）</li>",
        "<li><strong>スタンダードプラン</strong> — 月額サブスクリプション。決済完了後、メールでキーを受け取る</li>",
        "<li><strong>Pro プラン</strong> — 決済完了後、メールで Pro キーを受け取る（同一キーで最大 10 台の WordPress まで有効化可）</li>",
        "</ul>",
        "<h3>D-2. WordPress で有効化</h3>",
        "<ol>",
        "<li>Custom RSS Builder → ライセンス を開く</li>",
        "<li>画面下部の「ライセンスキーを有効化」にキーを貼り付ける</li>",
        "<li>「有効化」を押す</li>",
        "<li>「現在の状態」でプランと「このサイトで利用可：はい」を確認</li>",
        "</ol>",
        fig("06-wp-license-activate.png"),
        '<p class="crb-ai-manual-note">無料・スタンダードのキーは 1 台の WordPress のみで使えます。Pro は同じキーを別サイトのライセンス画面でも入力できます（最大 10 台）。</p>',
        '<h2 id="crb-install-first-feed">手順 E：最初のフィードを作成</h2>',
        "<p>まずは練習用サンプルで、セレクタの感覚をつかむのがおすすめです。</p>",
        "<ol>",
        "<li>Custom RSS Builder → 新規フィード作成 を開く</li>",
        "<li>フィード名を入力し、対象 URL に練習パターン1の URL を貼る</li>",
        "<li>CSS セレクタで「1 記事ぶん」と「リンク」を指定する</li>",
        "<li>「プレビュー」で記事が取れることを確認して保存</li>",
        "</ol>",
        fig("02-practice-samples.png"),
        fig("03-sample-simple-div.png"),
        fig("09-feed-edit.png"),
        fig("10-preview.png"),
        '<p>練習用サンプル一覧: <a href="https://123789.jp/custom-rss-builder/crb-practice-samples/" target="_blank" rel="noopener noreferrer">製品・練習用トップ</a></p>',
        '<h2 id="crb-install-rss">手順 F：RSS と取り込みを確認</h2>',
        "<ol>",
        "<li>フィード一覧の RSS URL をブラウザで開き、XML が表示されることを確認</li>",
        "<li>（任意）フィード編集または一覧から取り込みを実行し、投稿が作られることを確認</li>",
        "</ol>",
        fig("11-rss.png"),
        fig("12-imported-post.png"),
        '<h2 id="crb-install-plans">プランの違い（参考）</h2>',
        "<p>「フィード数」は 1 つの WordPress サイト内で作れる RSS 設定の本数です。「WordPress サイト数」は、同じライセンスキーを有効化できるサイトの台数です。</p>",
        '<table class="widefat crb-manual-plan-table"><thead><tr>',
        "<th>項目</th><th>無料</th><th>スタンダード</th><th>Pro</th>",
        "</tr></thead><tbody>",
        "<tr><td>WordPress サイト数</td><td>1 台まで</td><td>1 台まで</td><td>10 台まで</td></tr>",
        "<tr><td>フィード数</td><td>1 件まで</td><td>3 件まで</td><td>10 件まで</td></tr>",
        "<tr><td>スロット</td><td>3 つ（{%1%}〜{%3%}）</td><td>5 つ（{%1%}〜{%5%}）</td><td>20 つ（{%1%}〜{%20%}）</td></tr>",
        "<tr><td>RSS 配信</td><td>○</td><td>○</td><td>○</td></tr>",
        "<tr><td>抽出・プレビュー・保存</td><td>○</td><td>○</td><td>○</td></tr>",
        "<tr><td>投稿取り込み（手動）</td><td>○</td><td>○</td><td>○</td></tr>",
        "<tr><td>自動取り込み（時間指定）</td><td>24 時間に 1 回（固定）</td><td>1 時間〜</td><td>1 時間〜</td></tr>",
        "<tr><td>クレジット表示</td><td>あり</td><td>なし</td><td>なし</td></tr>",
        "<tr><td>AI テキスト変換</td><td>—</td><td>—</td><td>○（Gemini API キー要・BYOK）</td></tr>",
        "</tbody></table>",
        '<h2 id="crb-install-next">次のステップ</h2>',
        '<ul class="crb-manual-index-list">',
        '<li><a href="https://123789.jp/custom-rss-builder/crb-practice-samples/">練習用サンプル（CSS セレクタの例）</a></li>',
        '<li><a href="https://123789.jp/custom-rss-builder/crb-practice-samples/crb-openai-api-key-setup/">Gemini API キー設定手順（Pro AI 変換）</a></li>',
        '<li><a href="https://123789.jp/custom-rss-builder/crb-practice-samples/crb-feed-pack-manual/">フィード設定パック（エクスポート／インポート）手順</a></li>',
        "</ul>",
        '<h2 id="crb-install-faq">よくある質問</h2>',
        "<h3>Q. サーバーに追加設定は必要ですか？</h3>",
        "<p>A. いいえ。ZIP をインストールして有効化し、ライセンスキーを入力するだけで利用できます。</p>",
        "<h3>Q. キーを有効化できない</h3>",
        "<p>A. キーのコピーミス、別サイトでの有効化済み（無料・スタンダードは 1 台のみ）、Pro の 10 台上限超過、無効化されたキーなどが考えられます。販売元にお問い合わせください。</p>",
        "<hr />",
        '<p class="crb-ai-manual-footer"><small>本ページは Custom RSS Builder の画像入り操作マニュアルです（2026-07-30 更新）。</small></p>',
        "</div>",
    ]
    return "\n".join(parts)


def main() -> None:
    user, password = load_auth()
    url_map: dict[str, str] = {}
    for name, _caption in FIGURES:
        path = MANUAL_DIR / name
        if not path.is_file():
            raise FileNotFoundError(path)
        media = upload_media(user, password, path)
        url_map[name] = str(media["source_url"])

    content = build_content(url_map)
    payload = json.dumps(
        {
            "title": "Custom RSS Builder インストール手順（画像入り操作マニュアル）",
            "content": content,
            "status": "publish",
        },
        ensure_ascii=False,
    ).encode("utf-8")
    code, result = api_request(
        "POST",
        f"/wp-json/wp/v2/pages/{PAGE_ID}",
        user,
        password,
        data=payload,
        content_type="application/json",
    )
    if not isinstance(result, dict):
        raise RuntimeError(f"page update failed: {code} {result}")
    print("page updated", result.get("id"), result.get("link"))
    map_path = MANUAL_DIR / "media_url_map.json"
    map_path.write_text(json.dumps(url_map, ensure_ascii=False, indent=2), encoding="utf-8")
    print("wrote", map_path)


if __name__ == "__main__":
    main()
