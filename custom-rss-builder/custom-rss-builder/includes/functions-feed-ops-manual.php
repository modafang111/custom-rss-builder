<?php
/**
 * 正本サーバー：最初のフィード作成〜運用操作マニュアル（固定ページ）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_FEED_OPS_MANUAL_VERSION', '1' );
define( 'CRB_FEED_OPS_MANUAL_OPTION_PAGE_ID', 'crb_feed_ops_manual_page_id' );

/**
 * @return string
 */
function crb_feed_ops_manual_page_slug() {
	$slug = 'crb-first-feed-manual';
	/**
	 * @param string $slug Feed ops manual page slug.
	 */
	return (string) apply_filters( 'crb_feed_ops_manual_page_slug', $slug );
}

/**
 * @return string
 */
function crb_feed_ops_manual_page_url() {
	$page_id = (int) get_option( CRB_FEED_OPS_MANUAL_OPTION_PAGE_ID, 0 );
	if ( $page_id > 0 ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return $url;
		}
	}
	$parent = function_exists( 'crb_demo_samples_parent_slug' ) ? crb_demo_samples_parent_slug() : 'crb-practice-samples';
	$base   = function_exists( 'crb_demo_samples_authority_site_url' )
		? crb_demo_samples_authority_site_url()
		: 'https://123789.jp/custom-rss-builder';
	return trailingslashit( $base ) . $parent . '/' . crb_feed_ops_manual_page_slug() . '/';
}

/**
 * @param string $filename Basename under assets/images/manual/.
 * @return string
 */
function crb_feed_ops_manual_image_url( $filename ) {
	$filename = ltrim( (string) $filename, '/' );
	if ( '' === $filename || ! defined( 'CRB_PLUGIN_FILE' ) ) {
		return '';
	}
	$path = CRB_PLUGIN_DIR . 'assets/images/manual/' . $filename;
	if ( ! is_readable( $path ) ) {
		return '';
	}
	return (string) plugins_url( 'assets/images/manual/' . $filename, CRB_PLUGIN_FILE );
}

/**
 * @param string $filename Basename.
 * @param string $caption  Caption.
 * @return string
 */
function crb_feed_ops_manual_figure( $filename, $caption ) {
	$url = crb_feed_ops_manual_image_url( $filename );
	if ( '' === $url ) {
		return '';
	}
	return sprintf(
		'<figure class="crb-manual-figure"><img src="%1$s" alt="%2$s" loading="lazy" decoding="async" /><figcaption>%2$s</figcaption></figure>',
		esc_url( $url ),
		esc_html( $caption )
	);
}

/**
 * @return int
 */
function crb_feed_ops_manual_resolve_page_id() {
	$canonical_slug = crb_feed_ops_manual_page_slug();
	if ( function_exists( 'crb_demo_samples_find_page_by_slug' ) ) {
		$canonical = crb_demo_samples_find_page_by_slug( $canonical_slug );
		if ( $canonical instanceof WP_Post ) {
			return (int) $canonical->ID;
		}
	}
	$page_id = (int) get_option( CRB_FEED_OPS_MANUAL_OPTION_PAGE_ID, 0 );
	if ( $page_id > 0 ) {
		$post = get_post( $page_id );
		if ( $post instanceof WP_Post && 'page' === $post->post_type ) {
			return $page_id;
		}
	}
	return 0;
}

/**
 * @return string HTML
 */
function crb_feed_ops_manual_build_page_content() {
	$sample1 = 'https://123789.jp/custom-rss-builder/crb-practice-samples/crb-sample-simple-div/';
	$samples = 'https://123789.jp/custom-rss-builder/crb-practice-samples/';
	if ( function_exists( 'crb_demo_samples_index_url' ) ) {
		$idx = crb_demo_samples_index_url();
		if ( '' !== $idx ) {
			$samples = $idx;
		}
	}

	$lines   = array();
	$lines[] = '<div class="crb-ai-manual crb-feed-ops-manual">';
	$lines[] = '<style>.crb-feed-ops-manual .crb-manual-figure{margin:1rem 0 1.5rem;max-width:920px}.crb-feed-ops-manual .crb-manual-figure img{display:block;width:100%;height:auto;border:1px solid #dcdcde;border-radius:4px}.crb-feed-ops-manual .crb-manual-figure figcaption{margin-top:.5rem;color:#50575e;font-size:13px}.crb-feed-ops-manual table.crb-manual-sel{width:100%;max-width:920px;border-collapse:collapse;margin:1rem 0}.crb-feed-ops-manual table.crb-manual-sel th,.crb-feed-ops-manual table.crb-manual-sel td{border:1px solid #dcdcde;padding:8px 10px;text-align:left;font-size:14px}.crb-feed-ops-manual table.crb-manual-sel th{background:#f6f7f7}</style>';

	$lines[] = '<p><strong>' . esc_html__( '操作マニュアル（最初のフィード作成）', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( 'プラグイン導入済み・ライセンス有効化済みの方向けです。インストール手順は別ページです。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<nav class="crb-ai-manual-toc" aria-label="' . esc_attr__( '目次', 'custom-rss-builder' ) . '">';
	$lines[] = '<h2>' . esc_html__( '目次', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li><a href="#crb-fops-prep">' . esc_html__( '前提・準備', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fops-new">' . esc_html__( '手順 1：新規フィードを開く', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fops-url">' . esc_html__( '手順 2：フィード名・対象 URL', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fops-css">' . esc_html__( '手順 3：範囲・1件・スロット', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fops-preview">' . esc_html__( '手順 4：プレビューして保存', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fops-rss">' . esc_html__( '手順 5：RSS を確認', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fops-import">' . esc_html__( '手順 6：WordPress へ取り込み（任意）', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fops-patterns">' . esc_html__( '練習パターン別セレクタ早見表', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fops-tips">' . esc_html__( 'うまく取れないとき', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fops-next">' . esc_html__( '次のステップ', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '</ol>';
	$lines[] = '</nav>';

	$lines[] = '<h2 id="crb-fops-prep">' . esc_html__( '前提・準備', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ul>';
	$lines[] = '<li>' . esc_html__( 'Custom RSS Builder が有効で、ライセンスが「利用可」になっている', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '最初は練習用サンプルで手順を通し、その後に本番サイトの URL へ切り替える', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';
	$lines[] = '<p>' . sprintf(
		/* translators: %s: practice samples URL */
		wp_kses_post( __( '練習用トップ: <a href="%s" target="_blank" rel="noopener noreferrer">製品・練習用サンプル</a>', 'custom-rss-builder' ) ),
		esc_url( $samples )
	) . '</p>';
	$fig = crb_feed_ops_manual_figure( '02-practice-samples.png', __( '練習用サンプル一覧', 'custom-rss-builder' ) );
	if ( '' !== $fig ) {
		$lines[] = $fig;
	}

	$lines[] = '<h2 id="crb-fops-new">' . esc_html__( '手順 1：新規フィードを開く', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'WordPress 管理画面 → Custom RSS Builder', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「新規フィード作成」を開く（一覧のボタンからも可）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$fig = crb_feed_ops_manual_figure( '08-wp-feed-list.png', __( 'フィード一覧と「新規フィード作成」', 'custom-rss-builder' ) );
	if ( '' !== $fig ) {
		$lines[] = $fig;
	}

	$lines[] = '<h2 id="crb-fops-url">' . esc_html__( '手順 2：フィード名・対象 URL', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'フィード名：管理しやすい名前（例: 練習パターン1）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '対象 URL：記事が一覧で並んでいるページの URL（個別記事ではなく一覧）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$lines[] = '<p>' . sprintf(
		/* translators: %s: sample pattern 1 URL */
		wp_kses_post( __( '練習ではパターン1を推奨: <a href="%s" target="_blank" rel="noopener noreferrer">crb-sample-simple-div</a>', 'custom-rss-builder' ) ),
		esc_url( $sample1 )
	) . '</p>';
	$fig = crb_feed_ops_manual_figure( '20-new-feed-basic.png', __( 'フィード名と対象 URL の入力イメージ', 'custom-rss-builder' ) );
	if ( '' !== $fig ) {
		$lines[] = $fig;
	}
	$fig = crb_feed_ops_manual_figure( '03-sample-simple-div.png', __( 'パターン1のサンプルページ（対象 URL の中身）', 'custom-rss-builder' ) );
	if ( '' !== $fig ) {
		$lines[] = $fig;
	}

	$lines[] = '<h2 id="crb-fops-css">' . esc_html__( '手順 3：範囲・1件・スロット', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<p>' . esc_html__( 'Feed43 と同様に「一覧の場所（範囲）」「1件ぶんの区切り」「スロット {%1%}〜」で抽出します。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( 'パターン1（div が並ぶ）の設定例', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<table class="crb-manual-sel"><thead><tr><th>' . esc_html__( '項目', 'custom-rss-builder' ) . '</th><th>' . esc_html__( '値', 'custom-rss-builder' ) . '</th><th>' . esc_html__( 'メモ', 'custom-rss-builder' ) . '</th></tr></thead><tbody>';
	$lines[] = '<tr><td>' . esc_html__( '一覧の場所（範囲）', 'custom-rss-builder' ) . '</td><td><code>' . esc_html__( '（空欄）', 'custom-rss-builder' ) . '</code></td><td>' . esc_html__( '親の箱が無いので空欄', 'custom-rss-builder' ) . '</td></tr>';
	$lines[] = '<tr><td>' . esc_html__( '1件ぶんの区切り', 'custom-rss-builder' ) . '</td><td><code>.crb-sample-news-item</code></td><td>' . esc_html__( '1記事を囲む要素', 'custom-rss-builder' ) . '</td></tr>';
	$lines[] = '<tr><td>{%1%} ' . esc_html__( 'リンク', 'custom-rss-builder' ) . '</td><td><code>a</code> / ' . esc_html__( 'リンクURL', 'custom-rss-builder' ) . '</td><td>' . esc_html__( '1件内の a', 'custom-rss-builder' ) . '</td></tr>';
	$lines[] = '<tr><td>{%2%} ' . esc_html__( 'タイトル', 'custom-rss-builder' ) . '</td><td><code>a</code> / ' . esc_html__( '表示テキスト', 'custom-rss-builder' ) . '</td><td>' . esc_html__( '同じ a の文字', 'custom-rss-builder' ) . '</td></tr>';
	$lines[] = '<tr><td>{%3%} ' . esc_html__( '日付など', 'custom-rss-builder' ) . '</td><td><code>.crb-sample-date</code> / ' . esc_html__( '表示テキスト', 'custom-rss-builder' ) . '</td><td>' . esc_html__( '任意スロット', 'custom-rss-builder' ) . '</td></tr>';
	$lines[] = '</tbody></table>';
	$fig = crb_feed_ops_manual_figure( '21-selectors-slots.png', __( '範囲・1件・スロットの設定イメージ', 'custom-rss-builder' ) );
	if ( '' !== $fig ) {
		$lines[] = $fig;
	}
	$fig = crb_feed_ops_manual_figure( '09-feed-edit.png', __( '実際のフィード編集画面（範囲・スロット）', 'custom-rss-builder' ) );
	if ( '' !== $fig ) {
		$lines[] = $fig;
	}
	$lines[] = '<p class="crb-ai-manual-note">' . esc_html__( 'スロット数はプランにより異なります（無料 {%1%}〜{%3%}、スタンダード〜{%5%}、Pro〜{%20%}）。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<h2 id="crb-fops-preview">' . esc_html__( '手順 4：プレビューして保存', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( '「プレビュー」を押し、件数・タイトル・リンクが取れているか確認', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '空欄や誤った URL ばかりのときは、1件セレクタ／スロットを見直す', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '問題なければ「保存」', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$fig = crb_feed_ops_manual_figure( '22-preview-save.png', __( 'プレビュー結果の確認と保存', 'custom-rss-builder' ) );
	if ( '' !== $fig ) {
		$lines[] = $fig;
	}
	$fig = crb_feed_ops_manual_figure( '10-preview.png', __( 'プレビュー結果（抽出データ一覧）の実例', 'custom-rss-builder' ) );
	if ( '' !== $fig ) {
		$lines[] = $fig;
	}

	$lines[] = '<h2 id="crb-fops-rss">' . esc_html__( '手順 5：RSS を確認', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'フィード一覧（または編集画面）の RSS URL をコピー', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'ブラウザで開き、item が並ぶ XML になっていることを確認', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$fig = crb_feed_ops_manual_figure( '11-rss.png', __( 'RSS 配信 URL をブラウザで表示', 'custom-rss-builder' ) );
	if ( '' !== $fig ) {
		$lines[] = $fig;
	}

	$lines[] = '<h2 id="crb-fops-import">' . esc_html__( '手順 6：WordPress へ取り込み（任意）', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( '同じフィード編集画面の下部「WordPress投稿への取り込み」で「取り込み」を有効にする', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '必要なら自動取り込みの間隔（時間）、ステータス／カテゴリーなどを設定する', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . wp_kses_post( __( '投稿タイトル・投稿本文にスロットを書く（実画面の番号: <code>{%1}</code>=タイトル、<code>{%2}</code>=リンクURL）', 'custom-rss-builder' ) ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「投稿プレビュー」で仕上がりを確認し、「保存」したうえで「投稿に取り込み」を押す', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$fig = crb_feed_ops_manual_figure( '23-wp-import-settings.png', __( 'フィード編集の「WordPress投稿への取り込み」パネル', 'custom-rss-builder' ) );
	if ( '' !== $fig ) {
		$lines[] = $fig;
	}

	$lines[] = '<h2 id="crb-fops-patterns">' . esc_html__( '練習パターン別セレクタ早見表', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<table class="crb-manual-sel"><thead><tr><th>' . esc_html__( 'パターン', 'custom-rss-builder' ) . '</th><th>' . esc_html__( '範囲', 'custom-rss-builder' ) . '</th><th>' . esc_html__( '1件', 'custom-rss-builder' ) . '</th><th>' . esc_html__( 'リンク例', 'custom-rss-builder' ) . '</th></tr></thead><tbody>';
	$lines[] = '<tr><td>1 div</td><td><code>' . esc_html__( '（空欄）', 'custom-rss-builder' ) . '</code></td><td><code>.crb-sample-news-item</code></td><td><code>a</code></td></tr>';
	$lines[] = '<tr><td>2 箱+記事</td><td><code>.crb-sample-list</code></td><td><code>.crb-sample-item</code></td><td><code>.crb-sample-title a</code></td></tr>';
	$lines[] = '<tr><td>3 ul/li</td><td><code>ul.crb-sample-ul</code></td><td><code>li.crb-sample-li</code></td><td><code>a.crb-sample-link</code></td></tr>';
	$lines[] = '<tr><td>4 入れ子</td><td><code>.crb-sample-feed</code></td><td><code>.crb-sample-feed_list_item</code></td><td><code>a.crb-sample-feed_link</code></td></tr>';
	$lines[] = '<tr><td>5 table</td><td><code>table.crb-sample-table</code></td><td><code>tbody tr</code></td><td><code>a</code></td></tr>';
	$lines[] = '</tbody></table>';
	$fig = crb_feed_ops_manual_figure( '24-sample-list-wrapper.png', __( 'パターン2：箱 + 記事のサンプル', 'custom-rss-builder' ) );
	if ( '' !== $fig ) {
		$lines[] = $fig;
	}

	$lines[] = '<h2 id="crb-fops-tips">' . esc_html__( 'うまく取れないとき', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ul>';
	$lines[] = '<li>' . esc_html__( '対象 URL がログイン必須・会員限定の場合は取得できないことがあります', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'プレビュー 0 件 → まず「1件ぶんの区切り」を見直す（範囲は後回しでも可）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'タイトルは取れるがリンクが空 → スロットの取り方を「リンクURL」にする', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '本番サイトではブラウザの検証ツールで要素を確認し、class をセレクタにする', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';

	$lines[] = '<h2 id="crb-fops-next">' . esc_html__( '次のステップ', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ul class="crb-manual-index-list">';
	if ( function_exists( 'crb_install_manual_page_url' ) ) {
		$url = crb_install_manual_page_url();
		if ( '' !== $url ) {
			$lines[] = '<li><a href="' . esc_url( $url ) . '">' . esc_html__( 'インストール手順（導入前）', 'custom-rss-builder' ) . '</a></li>';
		}
	}
	if ( function_exists( 'crb_feed_pack_manual_page_url' ) ) {
		$url = crb_feed_pack_manual_page_url();
		if ( '' !== $url ) {
			$lines[] = '<li><a href="' . esc_url( $url ) . '">' . esc_html__( 'フィード設定パック（エクスポート／インポート）', 'custom-rss-builder' ) . '</a></li>';
		}
	}
	if ( function_exists( 'crb_ai_manual_page_url' ) ) {
		$url = crb_ai_manual_page_url();
		if ( '' !== $url ) {
			$lines[] = '<li><a href="' . esc_url( $url ) . '">' . esc_html__( 'Gemini API キー設定（Pro AI 変換）', 'custom-rss-builder' ) . '</a></li>';
		}
	}
	$lines[] = '</ul>';

	$lines[] = '<hr />';
	$lines[] = '<p class="crb-ai-manual-footer"><small>' . esc_html__( '本ページは Custom RSS Builder の操作マニュアル（最初のフィード作成）です。', 'custom-rss-builder' ) . '</small></p>';
	$lines[] = '</div>';

	return implode( "\n", $lines );
}

/**
 * @param bool $force Force reinstall.
 * @return array{ok:bool,message:string,url:string}
 */
function crb_feed_ops_manual_install( $force = false ) {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return array(
			'ok'      => false,
			'message' => 'not authority',
			'url'     => '',
		);
	}

	$stored_ver = (string) get_option( 'crb_feed_ops_manual_install_version', '' );
	if ( ! $force && CRB_FEED_OPS_MANUAL_VERSION === $stored_ver ) {
		return array(
			'ok'      => true,
			'message' => 'already installed',
			'url'     => crb_feed_ops_manual_page_url(),
		);
	}

	if ( ! function_exists( 'crb_demo_samples_upsert_page' ) || ! function_exists( 'crb_demo_samples_get_sales_page_id' ) ) {
		return array(
			'ok'      => false,
			'message' => 'demo samples helpers missing',
			'url'     => crb_feed_ops_manual_page_url(),
		);
	}

	$parent_id = crb_demo_samples_get_sales_page_id();
	if ( $parent_id <= 0 && function_exists( 'crb_demo_samples_find_page_by_slug' ) && function_exists( 'crb_demo_samples_parent_slug' ) ) {
		$parent = crb_demo_samples_find_page_by_slug( crb_demo_samples_parent_slug() );
		if ( $parent instanceof WP_Post ) {
			$parent_id = (int) $parent->ID;
		}
	}
	if ( $parent_id <= 0 ) {
		return array(
			'ok'      => false,
			'message' => 'parent page missing',
			'url'     => crb_feed_ops_manual_page_url(),
		);
	}

	$title   = __( '最初のフィード作成（操作マニュアル）', 'custom-rss-builder' );
	$content = crb_feed_ops_manual_build_page_content();
	$slug    = crb_feed_ops_manual_page_slug();
	$page_id = crb_feed_ops_manual_resolve_page_id();

	if ( $page_id > 0 ) {
		$result = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_parent'  => $parent_id,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'message' => 'page update failed',
				'url'     => crb_feed_ops_manual_page_url(),
			);
		}
		$page_id = (int) $result;
	} else {
		$page_id = crb_demo_samples_upsert_page( $slug, $title, $content, $parent_id );
	}

	if ( $page_id <= 0 ) {
		return array(
			'ok'      => false,
			'message' => 'page upsert failed',
			'url'     => crb_feed_ops_manual_page_url(),
		);
	}

	update_option( CRB_FEED_OPS_MANUAL_OPTION_PAGE_ID, (int) $page_id, false );
	update_option( 'crb_feed_ops_manual_install_version', CRB_FEED_OPS_MANUAL_VERSION, false );

	return array(
		'ok'      => true,
		'message' => 'installed',
		'url'     => (string) get_permalink( $page_id ),
	);
}

/**
 * init / activate 用。
 */
function crb_feed_ops_manual_maybe_install() {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return;
	}
	crb_feed_ops_manual_install( false );
}

add_action( 'init', 'crb_feed_ops_manual_maybe_install', 23 );
