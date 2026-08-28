# -*- coding: utf-8 -*-
"""
CRB Title Redirect を 123789.jp へ配布:
  ZIP ビルド → uploads/crb-helpers/ → 固定ページ作成／更新
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
ZIP_REMOTE = f"{ZIP_REMOTE_DIR}/crb-title-redirect.zip"
PUBLIC_ZIP_URL = (
    "https://123789.jp/custom-rss-builder/wp-content/uploads/crb-helpers/crb-title-redirect.zip"
)
PAGE_URL_HINT = "https://123789.jp/custom-rss-builder/crb-title-redirect/"
DIST_ZIP = BASE / "dist" / "crb-title-redirect.zip"


def load_build_module():
    spec = importlib.util.spec_from_file_location(
        "build_crb_title_redirect_zip",
        BASE / "tools" / "build_crb_title_redirect_zip.py",
    )
    if spec is None or spec.loader is None:
        raise RuntimeError("build_crb_title_redirect_zip.py not found")
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
        main = zf.read("crb-title-redirect/crb-title-redirect.php").decode(
            "utf-8", "replace"
        )
    m = re.search(r"Version:\s*([0-9.]+)", main)
    return m.group(1) if m else "?"


PAGE_INSTALLER = r"""<?php
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
header( 'Content-Type: application/json; charset=UTF-8' );

$secret = 'crb-title-redirect-page-20260714';
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

$slug    = 'crb-title-redirect';
$zip_url = 'https://123789.jp/custom-rss-builder/wp-content/uploads/crb-helpers/crb-title-redirect.zip';
$crb_dl  = 'https://123789.jp/custom-rss-builder/download/695/';
$id_split= 'https://123789.jp/custom-rss-builder/crb-id-split/';
$version = isset( $_GET['version'] ) ? sanitize_text_field( wp_unslash( $_GET['version'] ) ) : '';

$content = <<<HTML
<!-- wp:group {"className":"crb-helper-dl"} -->
<div class="crb-helper-dl wp-block-group">

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">CRB Title Redirect</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Custom RSS Builder 向けの無料補助プラグイン</strong>です。タイトルが完全に同じ公開投稿が重複したとき、<strong>旧投稿から新投稿へ 301 リダイレクト</strong>します。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>自動取り込みでは同じタイトルの記事が増えやすく、同一ドメイン内の重複コンテンツになりがちです。検索エンジンはどちらか一方しか採用しないことが多いため、価値の高い新しい側を残し、古い URL は転送します。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">ダウンロード</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>バージョン: <strong>{$version}</strong></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a class="button" style="display:inline-block;padding:12px 22px;background:#1d4f91;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;" href="{$zip_url}">crb-title-redirect.zip をダウンロード</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">導入手順</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list">
<li>（推奨）先に <a href="{$crb_dl}">Custom RSS Builder</a> を入れておく。</li>
<li>上の ZIP を「プラグイン → 新規追加 → アップロード」でインストールし、有効化する。</li>
<li>「設定 → CRB Title Redirect」で対象投稿タイプなどを確認する。</li>
<li>既存の重複がある場合は「今すぐスキャン」を実行する。</li>
</ol>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">動作まとめ</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list">
<li>比較: 投稿タイトルの完全一致（前後空白は無視）</li>
<li>残す側: 投稿日が新しい方（同時刻なら ID が大きい方）</li>
<li>転送: 旧 URL → 新 URL（HTTP 301）</li>
<li>オプション: CRB 取り込み投稿（<code>_crb_item_key</code>）のみ対象</li>
</ul>
<!-- /wp:list -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">関連</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><a href="{$crb_dl}">Custom RSS Builder 本体</a> · <a href="{$id_split}">CRB ID Split（DUGA Helper）</a></p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
HTML;

$existing = get_page_by_path( $slug, OBJECT, 'page' );
$postarr  = array(
	'post_title'   => 'CRB Title Redirect',
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
		array( 'ok' => false, 'error' => $page_id->get_error_message() ),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	);
	exit;
}

$page_id = (int) $page_id;
update_option( 'crb_title_redirect_download_page_id', $page_id, false );
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
    probe_name = f"_crb_title_redirect_page_{stamp}.php"
    probe_remote = f"{SITE_ROOT}/{probe_name}"
    ftp.storbinary(f"STOR {probe_remote}", BytesIO(PAGE_INSTALLER.encode("utf-8")))
    ftp.quit()

    url = (
        f"https://123789.jp/custom-rss-builder/{probe_name}"
        f"?secret=crb-title-redirect-page-20260714&version={urllib.request.quote(version)}"
    )
    print(f"Creating page via {probe_name} ...")
    req = urllib.request.Request(
        url, headers={"User-Agent": "Mozilla/5.0", "Cache-Control": "no-cache"}
    )
    try:
        with urllib.request.urlopen(req, timeout=90) as resp:
            body = resp.read().decode("utf-8", "replace")
    except Exception as e:  # noqa: BLE001
        body = f"ERR {e!r}"

    print(body)
    (BASE / "dist" / "crb-title-redirect-page-publish.json").write_text(
        body, encoding="utf-8"
    )

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
