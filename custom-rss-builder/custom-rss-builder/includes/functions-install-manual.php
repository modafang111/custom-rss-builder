<?php
/**
 * 正本サーバー（123789.jp）のプラグインインストール手順（固定ページ）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_INSTALL_MANUAL_VERSION', '1' );
define( 'CRB_INSTALL_MANUAL_OPTION_PAGE_ID', 'crb_install_manual_page_id' );

/**
 * 手順ページ slug（製品・練習用親ページの子）。
 *
 * @return string
 */
function crb_install_manual_page_slug() {
	$slug = 'crb-plugin-install-guide';
	/**
	 * @param string $slug Install manual page slug.
	 */
	return (string) apply_filters( 'crb_install_manual_page_slug', $slug );
}

/**
 * 手順ページの公開 URL（未作成時は推測 URL）。
 *
 * @return string
 */
function crb_install_manual_page_url() {
	$page_id = (int) get_option( CRB_INSTALL_MANUAL_OPTION_PAGE_ID, 0 );
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
	return trailingslashit( $base ) . $parent . '/' . crb_install_manual_page_slug() . '/';
}

/**
 * @param string $url   URL.
 * @param string $label Link text.
 * @return string
 */
function crb_install_manual_link_li( $url, $label ) {
	if ( function_exists( 'crb_ai_manual_link_li' ) ) {
		return crb_ai_manual_link_li( $url, $label );
	}
	return sprintf(
		'<li><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></li>',
		esc_url( $url ),
		esc_html( $label )
	);
}

/**
 * @return int
 */
function crb_install_manual_resolve_page_id() {
	$canonical_slug = crb_install_manual_page_slug();
	if ( function_exists( 'crb_demo_samples_find_page_by_slug' ) ) {
		$canonical = crb_demo_samples_find_page_by_slug( $canonical_slug );
		if ( $canonical instanceof WP_Post ) {
			return (int) $canonical->ID;
		}
	}

	$page_id = (int) get_option( CRB_INSTALL_MANUAL_OPTION_PAGE_ID, 0 );
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
function crb_install_manual_build_page_content() {
	$lines   = array();
	$lines[] = '<div class="crb-ai-manual crb-install-manual">';

	$lines[] = '<nav class="crb-ai-manual-toc" aria-label="' . esc_attr__( '目次', 'custom-rss-builder' ) . '">';
	$lines[] = '<h2>' . esc_html__( '目次', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li><a href="#crb-install-overview">' . esc_html__( '概要', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-install-requirements">' . esc_html__( '必要環境', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-install-checklist">' . esc_html__( '作業チェックリスト', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-install-upload">' . esc_html__( '手順 1：ZIP のアップロード', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-install-activate">' . esc_html__( '手順 2：プラグインの有効化', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-install-license">' . esc_html__( '手順 3：ライセンスキーの有効化', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-install-first-feed">' . esc_html__( '手順 4：最初のフィードを作成', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-install-next">' . esc_html__( '次のステップ', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-install-faq">' . esc_html__( 'よくある質問', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '</ol>';
	$lines[] = '</nav>';

	$lines[] = '<h2 id="crb-install-overview">' . esc_html__( '概要', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<p>' . esc_html__( 'Custom RSS Builder は、RSS 非対応の Web ページから記事一覧を抽出し、RSS 2.0 フィードとして配信する WordPress プラグインです。抽出結果を WordPress 投稿へ取り込むこともできます。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<p>' . esc_html__( '本ページでは、お客様の WordPress サイトへのインストールから、無料プランでの利用開始までを説明します。追加のサーバー設定は不要です。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<h2 id="crb-install-requirements">' . esc_html__( '必要環境', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ul>';
	$lines[] = '<li>' . esc_html__( 'WordPress 5.8 以上', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'PHP 7.4 以上', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'プラグイン ZIP ファイル（custom-rss-builder-client.zip）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'ライセンスキー（無料登録で取得、または Pro 購入後にメールで受け取り）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';

	$lines[] = '<h2 id="crb-install-checklist">' . esc_html__( '作業チェックリスト', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'ZIP を WordPress にアップロードし、プラグインを有効化した', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '管理画面に「Custom RSS Builder」メニューが表示されている', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'ライセンスキーを入力し、「利用可」になった', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'フィードを 1 件作成し、プレビューで記事が取れることを確認した', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';

	$lines[] = '<h2 id="crb-install-upload">' . esc_html__( '手順 1：ZIP のアップロード', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'WordPress 管理画面に管理者としてログインする', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'プラグイン → 新規追加 → プラグインのアップロード を開く', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「ファイルを選択」で custom-rss-builder-client.zip を選び、「今すぐインストール」を押す', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'インストールが完了したら「プラグインを有効化」を押す（次の手順でも可）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$lines[] = '<p class="crb-ai-manual-note">' . esc_html__( '既に旧バージョンが入っている場合は、同じ手順で ZIP をアップロードすると上書き更新されます。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<h2 id="crb-install-activate">' . esc_html__( '手順 2：プラグインの有効化', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'プラグイン一覧で「Custom RSS Builder」が有効になっていることを確認', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '左メニューに「Custom RSS Builder」が表示されることを確認', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';

	$lines[] = '<h2 id="crb-install-license">' . esc_html__( '手順 3：ライセンスキーの有効化', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<p>' . esc_html__( 'プラグインを利用するには、ライセンスキーの有効化が必要です。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( '3-1. キーの入手', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ul>';
	$lines[] = '<li><strong>' . esc_html__( '無料プラン', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( '販売元サイトの無料登録フォームからキーを取得（メール送信あり）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li><strong>' . esc_html__( 'Pro プラン', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( '決済完了後、メールで Pro キーを受け取る', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';
	$lines[] = '<h3>' . esc_html__( '3-2. WordPress で有効化', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'Custom RSS Builder → ライセンス を開く', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '画面下部の「ライセンスキーを有効化」にキーを貼り付ける', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「有効化」を押す', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「現在の状態」でプランと「このサイトで利用可：はい」を確認', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$lines[] = '<p class="crb-ai-manual-note">' . esc_html__( '認証サーバー URL はプラグインに組み込まれています。通常は「初期設定」での追加入力は不要です。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<h2 id="crb-install-first-feed">' . esc_html__( '手順 4：最初のフィードを作成', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'Custom RSS Builder → 新規フィード作成 を開く', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'フィード名と対象 URL（記事一覧があるページ）を入力', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'CSS セレクタで「1 記事ぶん」と「リンク」を指定（練習用サンプルページも利用可）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「プレビュー」で記事が取れることを確認して保存', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '一覧画面の RSS URL からフィードを確認', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	if ( function_exists( 'crb_demo_samples_index_url' ) ) {
		$index_url = crb_demo_samples_index_url();
		if ( '' !== $index_url ) {
			$lines[] = '<p>' . sprintf(
				/* translators: %s: practice samples index URL */
				wp_kses_post( __( '練習用サンプルページ: <a href="%s" target="_blank" rel="noopener noreferrer">製品・練習用トップ</a>（各パターンの URL とセレクタ例あり）', 'custom-rss-builder' ) ),
				esc_url( $index_url )
			) . '</p>';
		}
	}

	$lines[] = '<h2 id="crb-install-next">' . esc_html__( '次のステップ', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ul class="crb-manual-index-list">';
	if ( function_exists( 'crb_demo_samples_index_url' ) ) {
		$index_url = crb_demo_samples_index_url();
		if ( '' !== $index_url ) {
			$lines[] = sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( $index_url ),
				esc_html__( '練習用サンプル（CSS セレクタの例）', 'custom-rss-builder' )
			);
		}
	}
	if ( function_exists( 'crb_ai_manual_page_url' ) ) {
		$ai_url = crb_ai_manual_page_url();
		if ( '' !== $ai_url ) {
			$lines[] = sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( $ai_url ),
				esc_html__( 'Gemini API キー設定手順（Pro AI 変換）', 'custom-rss-builder' )
			);
		}
	}
	$lines[] = '</ul>';

	$lines[] = '<h2 id="crb-install-faq">' . esc_html__( 'よくある質問', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<h3>' . esc_html__( 'Q. サーバーに追加設定は必要ですか？', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p>' . esc_html__( 'A. いいえ。ZIP をインストールして有効化し、ライセンスキーを入力するだけで利用できます。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( 'Q. 無料プランでできることは？', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p>' . esc_html__( 'A. 無料プランではフィード 1 件、スロット {%1%}〜{%3%} まで利用できます。Pro プランではフィード無制限・スロット {%1%}〜{%20%}・AI テキスト変換が利用できます。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( 'Q. キーを有効化できない', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p>' . esc_html__( 'A. キーのコピーミス、別サイトでの有効化済み、無効化されたキーなどが考えられます。販売元にお問い合わせください。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<hr />';
	$lines[] = '<p class="crb-ai-manual-footer"><small>' . esc_html__( '本ページは Custom RSS Builder プラグインにより自動更新されます。', 'custom-rss-builder' ) . '</small></p>';
	$lines[] = '</div>';

	return implode( "\n", $lines );
}

/**
 * 正本サーバーに手順固定ページを作成・更新。
 *
 * @param bool $force Force reinstall.
 * @return array{ok:bool,message:string,url:string}
 */
function crb_install_manual_install( $force = false ) {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return array(
			'ok'      => false,
			'message' => 'not authority',
			'url'     => '',
		);
	}

	$stored_ver = (string) get_option( 'crb_install_manual_install_version', '' );
	if ( ! $force && CRB_INSTALL_MANUAL_VERSION === $stored_ver ) {
		return array(
			'ok'      => true,
			'message' => 'already installed',
			'url'     => crb_install_manual_page_url(),
		);
	}

	if ( ! function_exists( 'crb_demo_samples_upsert_page' ) || ! function_exists( 'crb_demo_samples_get_sales_page_id' ) ) {
		return array(
			'ok'      => false,
			'message' => 'demo samples helpers missing',
			'url'     => crb_install_manual_page_url(),
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
			'url'     => crb_install_manual_page_url(),
		);
	}

	$title   = __( 'Custom RSS Builder インストール手順', 'custom-rss-builder' );
	$content = crb_install_manual_build_page_content();
	$slug    = crb_install_manual_page_slug();
	$page_id = crb_install_manual_resolve_page_id();

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
				'url'     => crb_install_manual_page_url(),
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
			'url'     => crb_install_manual_page_url(),
		);
	}

	update_option( CRB_INSTALL_MANUAL_OPTION_PAGE_ID, (int) $page_id, false );
	update_option( 'crb_install_manual_install_version', CRB_INSTALL_MANUAL_VERSION, false );

	return array(
		'ok'      => true,
		'message' => 'installed',
		'url'     => (string) get_permalink( $page_id ),
	);
}

/**
 * init / activate 用。
 */
function crb_install_manual_maybe_install() {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return;
	}
	crb_install_manual_install( false );
}

add_action( 'init', 'crb_install_manual_maybe_install', 22 );
