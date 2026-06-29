<?php
/**
 * 正本サーバー（123789.jp）のフィード設定パック（エクスポート／インポート）手順（固定ページ）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_FEED_PACK_MANUAL_VERSION', '2' );
define( 'CRB_FEED_PACK_MANUAL_OPTION_PAGE_ID', 'crb_feed_pack_manual_page_id' );

/**
 * @return string
 */
function crb_feed_pack_manual_page_slug() {
	$slug = 'crb-feed-pack-manual';
	/**
	 * @param string $slug Feed pack manual page slug.
	 */
	return (string) apply_filters( 'crb_feed_pack_manual_page_slug', $slug );
}

/**
 * @return string
 */
function crb_feed_pack_manual_page_url() {
	$page_id = (int) get_option( CRB_FEED_PACK_MANUAL_OPTION_PAGE_ID, 0 );
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
	return trailingslashit( $base ) . $parent . '/' . crb_feed_pack_manual_page_slug() . '/';
}

/**
 * @return int
 */
function crb_feed_pack_manual_resolve_page_id() {
	$canonical_slug = crb_feed_pack_manual_page_slug();
	if ( function_exists( 'crb_demo_samples_find_page_by_slug' ) ) {
		$canonical = crb_demo_samples_find_page_by_slug( $canonical_slug );
		if ( $canonical instanceof WP_Post ) {
			return (int) $canonical->ID;
		}
	}

	$page_id = (int) get_option( CRB_FEED_PACK_MANUAL_OPTION_PAGE_ID, 0 );
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
function crb_feed_pack_manual_build_page_content() {
	$pack_ver = function_exists( 'crb_feed_pack_version' ) ? (int) crb_feed_pack_version() : 1;

	$lines   = array();
	$lines[] = '<div class="crb-ai-manual crb-feed-pack-manual">';

	$lines[] = '<nav class="crb-ai-manual-toc" aria-label="' . esc_attr__( '目次', 'custom-rss-builder' ) . '">';
	$lines[] = '<h2>' . esc_html__( '目次', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li><a href="#crb-fpack-overview">' . esc_html__( '概要', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fpack-included">' . esc_html__( 'JSON に含まれる項目', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fpack-excluded">' . esc_html__( 'JSON に含まれない項目', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fpack-export">' . esc_html__( 'エクスポート手順', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fpack-import">' . esc_html__( 'インポート手順', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fpack-pro-service">' . esc_html__( 'Pro 初期設定代行', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fpack-concierge">' . esc_html__( '代行の作業手順（運用者向け）', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-fpack-faq">' . esc_html__( 'よくある質問', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '</ol>';
	$lines[] = '</nav>';

	$lines[] = '<h2 id="crb-fpack-overview">' . esc_html__( '概要', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<p>' . esc_html__( 'フィード設定パックは、1 件のフィード設定を JSON ファイルとして出力・読み込みする機能です。Pro プラン向けに、あらかじめ組んだ抽出設定（URL・CSS セレクタ・投稿テンプレートなど）をお客様サイトへ渡す用途を想定しています。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<p>' . esc_html__( '操作はお客様 WordPress の管理画面「Custom RSS Builder → フィード編集」下部の「設定パック」パネルから行います。本ページはその手順を説明します。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<p class="crb-ai-manual-note">' . sprintf(
		/* translators: %d: pack version number */
		esc_html__( '現在の設定パック形式: pack_version = %d', 'custom-rss-builder' ),
		$pack_ver
	) . '</p>';

	$lines[] = '<h2 id="crb-fpack-included">' . esc_html__( 'JSON に含まれる項目', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ul>';
	$lines[] = '<li>' . esc_html__( 'フィード名', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '対象 URL', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '抽出モード（CSS）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '一覧の場所（範囲）・1 件ぶんの区切り', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'スロット {%1%}〜{%n%} の CSS セレクタと取り方', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'WordPress 投稿への取り込み用テンプレート（タイトル・本文）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';

	$lines[] = '<h2 id="crb-fpack-excluded">' . esc_html__( 'JSON に含まれない項目', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<p>' . esc_html__( 'インポート後も、お客様サイト上の次の設定はそのまま残ります（上書きされません）。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<ul>';
	$lines[] = '<li>' . esc_html__( '投稿への取り込み ON/OFF', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '取り込みスケジュール', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'カテゴリー・タグ・投稿者', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'リンク書き換え（link_rewrite）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'AI テキスト変換', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'ライセンスキー・フィード ID', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';

	$lines[] = '<h2 id="crb-fpack-export">' . esc_html__( 'エクスポート手順', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'お客様（または検証用）WordPress にログインし、Custom RSS Builder → 対象フィードの編集画面を開く', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'フィードを一度「保存」する（未保存の変更はエクスポートに含まれません）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '画面下部「設定パック」の「設定をエクスポート」を 1 回クリックする', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'crb-feed-pack-*.json がダウンロードされる', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$lines[] = '<p class="crb-ai-manual-note">' . esc_html__( '新規フィード（まだ保存していない）ではエクスポートできません。先に保存してください。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<h2 id="crb-fpack-import">' . esc_html__( 'インポート手順', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'お客様 WordPress で、新規作成または既存フィードの編集画面を開く', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「設定をインポート」を 1 回クリックし、受け取った JSON ファイルを選ぶ', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「フォームに反映しました。保存で確定します。」と表示され、フィード名・URL・セレクタ・テンプレート欄が更新される', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'プレビューで抽出結果を確認する', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '問題なければ「保存」を押して DB に確定する', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$lines[] = '<p class="crb-ai-manual-note">' . esc_html__( 'インポート直後は DB は更新されません。保存するまで他のフィードやライセンスは変わりません。既存フィードを削除する必要はありません。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<h2 id="crb-fpack-pro-service">' . esc_html__( 'Pro 初期設定代行', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<p>' . esc_html__( 'Pro プランでは、フィード設定パック（JSON）の作成と、お客様サイトへの反映支援を当方で代行できます。ご自身でセレクタを調べる時間を短縮したい方向けのオプションサービスです。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( '料金', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ul>';
	$lines[] = '<li><strong>' . esc_html__( '初回', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( '無料（Pro お申し込み特典・最初の 1 フィード・1 回限り）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li><strong>' . esc_html__( '2 回目以降', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( '1,000 円（税別）／回（税込 1,100 円）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';
	$lines[] = '<h3>' . esc_html__( '含まれる作業', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ul>';
	$lines[] = '<li>' . esc_html__( '対象 URL に合わせたフィード設定（範囲・1 件・スロット・投稿テンプレート）の作成', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '設定パック（JSON）のお渡しと、インポート〜プレビュー確認までのご案内', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';
	$lines[] = '<h3>' . esc_html__( '含まれない作業', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ul>';
	$lines[] = '<li>' . esc_html__( 'お客様 WordPress への管理者ログイン代行（原則、お客様操作または画面共有）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '取り込み ON/OFF・スケジュール・カテゴリなど、設定パックに含まれない項目の設定', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '2 フィード目以降の初回無料（初回無料は Pro 契約ごとに 1 フィード 1 回のみ）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';
	$lines[] = '<h3>' . esc_html__( 'お客様側の流れ', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'Pro ライセンスキーを有効化する', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '対象 URL など必要情報を販売元へ連絡する', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '届いた JSON を「設定をインポート」→ プレビュー →「保存」', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$contact_url = function_exists( 'crb_license_registration_portal_url' ) ? crb_license_registration_portal_url() : '';
	if ( '' !== $contact_url ) {
		$lines[] = '<p>' . wp_kses_post(
			sprintf(
				/* translators: %s: authority site URL */
				__( '代行のお申し込み: <a href="%s" target="_blank" rel="noopener noreferrer">販売元サイト</a>からご連絡ください。', 'custom-rss-builder' ),
				esc_url( $contact_url )
			)
		) . '</p>';
	} else {
		$lines[] = '<p class="crb-ai-manual-note">' . esc_html__( '代行のお申し込みは販売元までご連絡ください。', 'custom-rss-builder' ) . '</p>';
	}

	$lines[] = '<h2 id="crb-fpack-concierge">' . esc_html__( '代行の作業手順（運用者向け）', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( '検証環境または作業用サイトでフィードを組み、プレビューで問題ないことを確認する', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「設定をエクスポート」で JSON を取得する', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'お客様に JSON を安全な経路（メール添付・共有ストレージ等）で渡す', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'お客様に本ページの「インポート手順」に従い、保存まで行ってもらう', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '取り込み ON・スケジュール・カテゴリ等は、お客様サイトのポリシーに合わせて別途設定してもらう', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';

	$lines[] = '<h2 id="crb-fpack-faq">' . esc_html__( 'よくある質問', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<h3>' . esc_html__( 'Q. 既存フィードを消さずに設定だけ差し替えられますか？', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p>' . esc_html__( 'A. はい。更新したいフィードの編集画面でインポートし、保存すれば同じフィード ID の設定が上書きされます。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( 'Q. JSON を選んでもエラーになる', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p>' . esc_html__( 'A. ファイルが壊れている、pack_version が古い／新しすぎる、feed オブジェクトがない場合に失敗します。正しいエクスポート JSON か確認してください。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( 'Q. インポートでライセンスや他フィードが消えますか？', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p>' . esc_html__( 'A. いいえ。インポートは開いている 1 件のフォームに反映するだけです。保存前は DB も更新されません。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( 'Q. 初回無料の代行は何回まで？', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p>' . esc_html__( 'A. Pro お申し込み後、最初の 1 フィードにつき 1 回限り無料です。2 フィード目や設定の作り直し（2 回目以降）は 1,000 円（税別）／回となります。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<h2>' . esc_html__( '関連リンク', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ul class="crb-manual-index-list">';
	if ( function_exists( 'crb_install_manual_page_url' ) ) {
		$url = crb_install_manual_page_url();
		if ( '' !== $url ) {
			$lines[] = sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( $url ),
				esc_html__( 'Custom RSS Builder インストール手順', 'custom-rss-builder' )
			);
		}
	}
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
	$lines[] = '</ul>';

	$lines[] = '<hr />';
	$lines[] = '<p class="crb-ai-manual-footer"><small>' . esc_html__( '本ページは Custom RSS Builder プラグインにより自動更新されます。', 'custom-rss-builder' ) . '</small></p>';
	$lines[] = '</div>';

	return implode( "\n", $lines );
}

/**
 * @param bool $force Force reinstall.
 * @return array{ok:bool,message:string,url:string}
 */
function crb_feed_pack_manual_install( $force = false ) {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return array(
			'ok'      => false,
			'message' => 'not authority',
			'url'     => '',
		);
	}

	$stored_ver = (string) get_option( 'crb_feed_pack_manual_install_version', '' );
	if ( ! $force && CRB_FEED_PACK_MANUAL_VERSION === $stored_ver ) {
		return array(
			'ok'      => true,
			'message' => 'already installed',
			'url'     => crb_feed_pack_manual_page_url(),
		);
	}

	if ( ! function_exists( 'crb_demo_samples_upsert_page' ) || ! function_exists( 'crb_demo_samples_get_sales_page_id' ) ) {
		return array(
			'ok'      => false,
			'message' => 'demo samples helpers missing',
			'url'     => crb_feed_pack_manual_page_url(),
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
			'url'     => crb_feed_pack_manual_page_url(),
		);
	}

	$title   = __( 'フィード設定パック（エクスポート／インポート）手順', 'custom-rss-builder' );
	$content = crb_feed_pack_manual_build_page_content();
	$slug    = crb_feed_pack_manual_page_slug();
	$page_id = crb_feed_pack_manual_resolve_page_id();

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
				'url'     => crb_feed_pack_manual_page_url(),
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
			'url'     => crb_feed_pack_manual_page_url(),
		);
	}

	update_option( CRB_FEED_PACK_MANUAL_OPTION_PAGE_ID, (int) $page_id, false );
	update_option( 'crb_feed_pack_manual_install_version', CRB_FEED_PACK_MANUAL_VERSION, false );

	return array(
		'ok'      => true,
		'message' => 'installed',
		'url'     => (string) get_permalink( $page_id ),
	);
}

/**
 * init / activate 用。
 */
function crb_feed_pack_manual_maybe_install() {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return;
	}
	crb_feed_pack_manual_install( false );
}

add_action( 'init', 'crb_feed_pack_manual_maybe_install', 23 );
