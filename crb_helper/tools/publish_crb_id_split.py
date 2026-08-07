# -*- coding: utf-8 -*-
"""
CRB ID Split を 123789.jp へ配布:
  1) ZIP ビルド
  2) uploads/crb-helpers/ へ FTP 配置
  3) ダウンロード用固定ページを作成／更新
"""
from __future__ import annotations

import base64
import ftplib
import importlib.util
import json
import re
import sys
import time
import urllib.request
import xml.etree.ElementTree as ET
from io import BytesIO
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent
FZ_PATH = Path.home() / "AppData/Roaming/FileZilla/sitemanager.xml"
FTP_HOST = "sv7288.xserver.jp"
FTP_USER = "ideamart1"

SITE_ROOT = "/123789.jp/public_html/custom-rss-builder"
ZIP_REMOTE_DIR = f"{SITE_ROOT}/wp-content/uploads/crb-helpers"
ZIP_REMOTE = f"{ZIP_REMOTE_DIR}/crb-id-split.zip"
PUBLIC_ZIP_URL = "https://123789.jp/custom-rss-builder/wp-content/uploads/crb-helpers/crb-id-split.zip"
PAGE_URL_HINT = "https://123789.jp/custom-rss-builder/crb-id-split/"

DIST_ZIP = BASE / "dist" / "crb-id-split.zip"


def load_build_module():
    spec = importlib.util.spec_from_file_location(
        "build_crb_id_split_zip", BASE / "tools" / "build_crb_id_split_zip.py"
    )
    if spec is None or spec.loader is None:
        raise RuntimeError("build_crb_id_split_zip.py not found")
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def load_password() -> str:
    tree = ET.parse(FZ_PATH)
    for srv in tree.getroot().iter("Server"):
        if (srv.findtext("Host") or "").strip() == FTP_HOST and (
            srv.findtext("User") or ""
        ).strip() == FTP_USER:
            enc = srv.find("Pass")
            if enc is not None and enc.text:
                return base64.b64decode(enc.text.strip()).decode("utf-8", errors="replace")
    raise RuntimeError("FTP credentials not found")


def ftp_makedirs(ftp: ftplib.FTP, remote_dir: str) -> None:
    parts = [p for p in remote_dir.split("/") if p]
    path = ""
    for part in parts:
        path += "/" + part
        try:
            ftp.mkd(path)
        except ftplib.error_perm:
            pass


def zip_version(data: bytes) -> str:
    import zipfile

    with zipfile.ZipFile(BytesIO(data)) as zf:
        main = zf.read("crb-id-split/crb-id-split.php").decode("utf-8", "replace")
    m = re.search(r"Version:\s*([0-9.]+)", main)
    return m.group(1) if m else "?"


PAGE_INSTALLER = r"""<?php
/**
 * One-shot: create/update CRB ID Split download page. Delete after use.
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
header( 'Content-Type: application/json; charset=UTF-8' );

$secret = 'crb-id-split-page-20260714';
if ( ( $_GET['secret'] ?? '' ) !== $secret ) {
	http_response_code( 403 );
	echo json_encode( array( 'error' => 'forbidden' ) );
	exit;
}

require dirname( __FILE__ ) . '/wp-load.php';

if ( ! function_exists( 'wp_insert_post' ) ) {
	http_response_code( 500 );
	echo json_encode( array( 'error' => 'wp not loaded' ) );
	exit;
}

$slug      = 'crb-id-split';
$zip_url   = 'https://123789.jp/custom-rss-builder/wp-content/uploads/crb-helpers/crb-id-split.zip';
$crb_dl    = 'https://123789.jp/custom-rss-builder/download/695/';
$version   = isset( $_GET['version'] ) ? sanitize_text_field( wp_unslash( $_GET['version'] ) ) : '';

$content = <<<HTML
<!-- wp:group {"className":"crb-helper-dl"} -->
<div class="crb-helper-dl wp-block-group">

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">CRB ID Split（DUGA Helper）</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Custom RSS Builder 専用の無料補助プラグイン</strong>です。DUGA の作品 ID（例: <code>haisetsu-0684</code>）を前半 <code>{a}</code> と後半 <code>{b}</code> に分割し、サンプルプレイヤー HTML を本文テンプレートから出力できます。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>本体の Custom RSS Builder には DUGA 固有ロジックを入れていません。このプラグインは必要なサイトだけに追加してください。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">ダウンロード</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>バージョン: <strong>{$version}</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a class="button" style="display:inline-block;padding:12px 22px;background:#1d4f91;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;" href="{$zip_url}">crb-id-split.zip をダウンロード</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">導入手順</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list">
<li>先に <a href="{$crb_dl}">Custom RSS Builder</a> をインストール・有効化します。</li>
<li>上の ZIP を WordPress「プラグイン → 新規追加 → プラグインのアップロード」でインストールし、有効化します。</li>
<li>フィードの投稿本文テンプレートで次を使います。</li>
</ol>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">テンプレート記法</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>先にスロット（例: <code>{%9}</code>）へ作品 ID またはジャケット URL が入るよう設定してください。</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>記法</th><th>結果</th></tr></thead><tbody>
<tr><td><code>{{a:{%9}}}</code></td><td>前半（例: haisetsu）</td></tr>
<tr><td><code>{{b:{%9}}}</code></td><td>後半（例: 0684）</td></tr>
<tr><td><code>{{player:{%9}}}</code></td><td>サンプルプレイヤー一式（推奨）</td></tr>
<tr><td><code>{{flv:{%9}}}</code></td><td>動画 URL のみ</td></tr>
<tr><td><code>{{cap:{%9}}}</code></td><td>サムネ URL のみ</td></tr>
</tbody></table></figure>
<!-- /wp:table -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">注意</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list">
<li>非公式の補助プラグインです。DUGA 公式の配布物ではありません。</li>
<li>プレイヤーの見た目・再生用の簡易 CSS/JS も同梱しています（公式サイトの JS とは別物です）。</li>
<li>本体 CRB のアップデートではこの ZIP は更新されません。必要に応じてこのページから取り直してください。</li>
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p><a href="{$crb_dl}">← Custom RSS Builder 本体のダウンロード</a></p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
HTML;

$existing = get_page_by_path( $slug, OBJECT, 'page' );
$postarr  = array(
	'post_title'   => 'CRB ID Split（DUGA Helper）',
	'post_name'    => $slug,
	'post_content' => $content,
	'post_status'  => 'publish',
	'post_type'    => 'page',
	'post_author'  => 1,
);

if ( $existing instanceof WP_Post ) {
	$postarr['ID'] = (int) $existing->ID;
	$page_id       = wp_update_post( $postarr, true );
	$action        = 'updated';
} else {
	$page_id = wp_insert_post( $postarr, true );
	$action  = 'created';
}

if ( is_wp_error( $page_id ) ) {
	http_response_code( 500 );
	echo wp_json_encode(
		array(
			'ok'    => false,
			'error' => $page_id->get_error_message(),
		),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	);
	exit;
}

$page_id = (int) $page_id;
update_option( 'crb_id_split_download_page_id', $page_id, false );
if ( function_exists( 'flush_rewrite_rules' ) ) {
	flush_rewrite_rules( false );
}

echo wp_json_encode(
	array(
		'ok'      => true,
		'action'  => $action,
		'page_id' => $page_id,
		'url'     => get_permalink( $page_id ),
		'zip'     => $zip_url,
		'version' => $version,
	),
	JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
"""


def main() -> int:
    build = load_build_module()
    rc = build.main()
    if rc != 0:
        return rc

    if not DIST_ZIP.is_file():
        print(f"Missing: {DIST_ZIP}", file=sys.stderr)
        return 1

    data = DIST_ZIP.read_bytes()
    version = zip_version(data)
    print(f"Local ZIP size: {len(data)} bytes, version {version}")

    pw = load_password()
    ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=180)
    ftp.set_pasv(True)
    ftp_makedirs(ftp, ZIP_REMOTE_DIR)
    print(f"Uploading ZIP → {ZIP_REMOTE}")
    ftp.storbinary(f"STOR {ZIP_REMOTE}", BytesIO(data))
    remote = BytesIO()
    ftp.retrbinary(f"RETR {ZIP_REMOTE}", remote.write)
    if remote.getvalue() != data:
        print("FAIL: remote ZIP mismatch", file=sys.stderr)
        ftp.quit()
        return 3
    print("ZIP OK")

    stamp = str(int(time.time()))
    probe_name = f"_crb_id_split_page_{stamp}.php"
    probe_remote = f"{SITE_ROOT}/{probe_name}"
    ftp.storbinary(f"STOR {probe_remote}", BytesIO(PAGE_INSTALLER.encode("utf-8")))
    ftp.quit()

    url = (
        f"https://123789.jp/custom-rss-builder/{probe_name}"
        f"?secret=crb-id-split-page-20260714&version={urllib.request.quote(version)}"
    )
    print(f"Creating page via {probe_name} ...")
    req = urllib.request.Request(
        url,
        headers={"User-Agent": "Mozilla/5.0", "Cache-Control": "no-cache"},
    )
    try:
        with urllib.request.urlopen(req, timeout=90) as resp:
            body = resp.read().decode("utf-8", "replace")
    except Exception as e:  # noqa: BLE001
        body = f"ERR {e!r}"
        if hasattr(e, "read"):
            try:
                body += "\n" + e.read().decode("utf-8", "replace")[:1000]
            except Exception:  # noqa: BLE001
                pass

    print(body)
    out_log = BASE / "dist" / "crb-id-split-page-publish.json"
    out_log.write_text(body, encoding="utf-8")

    # cleanup probe
    try:
        ftp = ftplib.FTP(FTP_HOST, FTP_USER, pw, timeout=60)
        ftp.delete(probe_remote)
        print(f"DEL {probe_name}")
        ftp.quit()
    except Exception as e:  # noqa: BLE001
        print(f"DEL skip: {e}")

    try:
        result = json.loads(body)
    except json.JSONDecodeError:
        print("FAIL: page installer did not return JSON", file=sys.stderr)
        return 4

    if not result.get("ok"):
        print("FAIL: page create/update failed", file=sys.stderr)
        return 4

    print("\n=== published ===")
    print("Page:", result.get("url") or PAGE_URL_HINT)
    print("ZIP: ", PUBLIC_ZIP_URL)
    print("Ver: ", version)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
