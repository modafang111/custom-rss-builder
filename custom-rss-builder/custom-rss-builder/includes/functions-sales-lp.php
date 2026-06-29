<?php
/**
 * 正本サーバー（123789.jp）の販売 LP 固定ページ（フロントページ）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_SALES_LP_VERSION', '3' );
define( 'CRB_SALES_LP_OPTION_PAGE_ID', 'crb_sales_lp_page_id' );

/**
 * LP 固定ページ slug（フロント指定時はサイトルート URL になる）。
 *
 * @return string
 */
function crb_sales_lp_page_slug() {
	$slug = 'crb-sales-lp';
	/**
	 * @param string $slug Sales LP page slug.
	 */
	return (string) apply_filters( 'crb_sales_lp_page_slug', $slug );
}

/**
 * @return int
 */
function crb_sales_lp_resolve_page_id() {
	$canonical_slug = crb_sales_lp_page_slug();
	if ( function_exists( 'crb_demo_samples_find_page_by_slug' ) ) {
		$canonical = crb_demo_samples_find_page_by_slug( $canonical_slug );
		if ( $canonical instanceof WP_Post ) {
			return (int) $canonical->ID;
		}
	}

	$page_id = (int) get_option( CRB_SALES_LP_OPTION_PAGE_ID, 0 );
	if ( $page_id > 0 ) {
		$post = get_post( $page_id );
		if ( $post instanceof WP_Post && 'page' === $post->post_type ) {
			return $page_id;
		}
	}

	return 0;
}

/**
 * @return string
 */
function crb_sales_lp_page_url() {
	$page_id = crb_sales_lp_resolve_page_id();
	if ( $page_id > 0 ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return $url;
		}
	}
	return function_exists( 'crb_demo_samples_authority_site_url' )
		? crb_demo_samples_authority_site_url()
		: home_url( '/' );
}

/**
 * @return bool
 */
function crb_sales_lp_is_current_page() {
	if ( ! is_singular( 'page' ) ) {
		return is_front_page() && crb_sales_lp_resolve_page_id() > 0
			&& (int) get_option( 'page_on_front', 0 ) === crb_sales_lp_resolve_page_id();
	}
	return (int) get_queried_object_id() === crb_sales_lp_resolve_page_id();
}

/**
 * @param int $page_id Page ID.
 */
function crb_sales_lp_assign_front_page( $page_id ) {
	$page_id = (int) $page_id;
	if ( $page_id <= 0 ) {
		return;
	}
	update_option( 'show_on_front', 'page', false );
	update_option( 'page_on_front', $page_id, false );
}

/**
 * @return string
 */
function crb_sales_lp_download_url() {
	if ( function_exists( 'crb_ls_mail_download_url' ) ) {
		$url = crb_ls_mail_download_url( 'free' );
		if ( '' !== $url ) {
			return $url;
		}
		$url = crb_ls_mail_download_url( '' );
		if ( '' !== $url ) {
			return $url;
		}
	}
	return 'https://123789.jp/custom-rss-builder/download/695/';
}

/**
 * @return string
 */
function crb_sales_lp_pro_payment_url() {
	if ( function_exists( 'crb_ls_pro_payment_url' ) ) {
		return (string) crb_ls_pro_payment_url();
	}
	if ( function_exists( 'crb_license_pro_payment_url' ) ) {
		return (string) crb_license_pro_payment_url();
	}
	return '';
}

/**
 * @param string $filename Basename under assets/images/lp/.
 * @return string
 */
function crb_sales_lp_image_url( $filename ) {
	$filename = ltrim( (string) $filename, '/' );
	if ( '' === $filename || ! defined( 'CRB_PLUGIN_FILE' ) ) {
		return '';
	}
	return (string) plugins_url( 'assets/images/lp/' . $filename, CRB_PLUGIN_FILE );
}

/**
 * @param string $filename Image basename.
 * @param string $alt      Alt text.
 * @return string HTML img tag or empty.
 */
function crb_sales_lp_image_tag( $filename, $alt ) {
	$url = crb_sales_lp_image_url( $filename );
	if ( '' === $url ) {
		return '';
	}

	$attrs = '';
	if ( defined( 'CRB_PLUGIN_DIR' ) ) {
		$path = CRB_PLUGIN_DIR . 'assets/images/lp/' . ltrim( (string) $filename, '/' );
		if ( is_readable( $path ) ) {
			$size = @getimagesize( $path );
			if ( is_array( $size ) ) {
				$attrs = sprintf( ' width="%d" height="%d"', (int) $size[0], (int) $size[1] );
			}
		}
	}

	return sprintf(
		'<img class="crb-sales-lp__img" src="%s" alt="%s" loading="lazy" decoding="async"%s />',
		esc_url( $url ),
		esc_attr( $alt ),
		$attrs
	);
}

/**
 * @return array<int, array{label:string, free:string, pro:string}>
 */
function crb_sales_lp_plan_rows() {
	if ( function_exists( 'crb_license_plan_comparison_rows' ) ) {
		return crb_license_plan_comparison_rows();
	}
	return array();
}

/**
 * @return string HTML
 */
function crb_sales_lp_build_page_content() {
	$download_url = crb_sales_lp_download_url();
	$pro_url      = crb_sales_lp_pro_payment_url();
	$install_url  = function_exists( 'crb_install_manual_page_url' ) ? crb_install_manual_page_url() : '';
	$samples_url  = function_exists( 'crb_demo_samples_index_url' ) ? crb_demo_samples_index_url() : '';
	$plan_rows    = crb_sales_lp_plan_rows();

	$lines   = array();
	$lines[] = '<div class="crb-sales-lp">';

	// Hero.
	$lines[] = '<header class="crb-sales-lp__hero">';
	$lines[] = '<p class="crb-sales-lp__eyebrow">' . esc_html__( 'WordPress 向け RSS・取り込みプラグイン', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h1 class="crb-sales-lp__title">' . esc_html__( 'RSSがないサイトから、あなたの WordPress へ。', 'custom-rss-builder' ) . '</h1>';
	$lines[] = '<p class="crb-sales-lp__lead">' . esc_html__( 'Feed43 感覚で HTML を指定するだけ。抽出・RSS 配信・投稿取り込みまで、1 本のプラグインで完結します。DLsite レビュー、まとめサイト、会員制ページ——「更新できない」を今日で終わりに。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<div class="crb-sales-lp__hero-cta">';
	$lines[] = '<a class="crb-sales-lp__btn crb-sales-lp__btn--primary" href="#crb-lp-register">' . esc_html__( '無料ライセンスを申請する', 'custom-rss-builder' ) . '</a>';
	if ( '' !== $download_url ) {
		$lines[] = '<a class="crb-sales-lp__btn crb-sales-lp__btn--secondary" href="' . esc_url( $download_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'プラグイン ZIP をダウンロード', 'custom-rss-builder' ) . '</a>';
	}
	$lines[] = '</div>';
	$lines[] = '<p class="crb-sales-lp__hero-note">' . esc_html__( '無料プランあり · クレジットカード不要 · お手持ちの WordPress にインストール', 'custom-rss-builder' ) . '</p>';
	$hero_img = crb_sales_lp_image_tag(
		'lp-hero-feed-edit.png',
		__( 'フィード編集画面（範囲・スロット設定）', 'custom-rss-builder' )
	);
	if ( '' !== $hero_img ) {
		$lines[] = '<div class="crb-sales-lp__hero-visual">' . $hero_img . '</div>';
	} else {
		$lines[] = '<div class="crb-sales-lp__hero-visual crb-sales-lp__placeholder" aria-hidden="true">';
		$lines[] = '<span>' . esc_html__( '＜ここへヒーロー用の製品イメージ画像＞', 'custom-rss-builder' ) . '</span>';
		$lines[] = '</div>';
	}
	$lines[] = '</header>';

	// Pain points.
	$lines[] = '<section class="crb-sales-lp__section" id="crb-lp-problem">';
	$lines[] = '<h2>' . esc_html__( 'こんな課題、ありませんか？', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ul class="crb-sales-lp__checks">';
	$lines[] = '<li>' . esc_html__( '欲しいページに RSS がなく、IFTTT や自動投稿が使えない', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'Feed43 は便利だが、WordPress への取り込みが別作業になる', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '海外レビューを日本語サイトに載せたいが、コピペ運用はつらい', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'セレクタ設定が難しく、毎回エンジニア頼みになっている', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';
	$lines[] = '<p class="crb-sales-lp__solution">' . esc_html__( 'Custom RSS Builder は「抽出 → RSS → WordPress 投稿」をひと続きで行えます。範囲・1件ブロック・スロット（{%1%}〜）は Feed43 ユーザーにも馴染みやすい設計です。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '</section>';

	// Features.
	$lines[] = '<section class="crb-sales-lp__section" id="crb-lp-features">';
	$lines[] = '<h2>' . esc_html__( 'できること', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<div class="crb-sales-lp__cards">';
	$features = array(
		array(
			'title' => __( 'Feed43 風の抽出', 'custom-rss-builder' ),
			'body'  => __( 'CSS セレクタで範囲・1件ブロック・各スロットを指定。プレビューで中身を確認してから保存できます。', 'custom-rss-builder' ),
		),
		array(
			'title' => __( 'RSS 2.0 配信', 'custom-rss-builder' ),
			'body'  => __( '生成したフィード URL をそのまま配信。他サービスとの連携にも使えます。', 'custom-rss-builder' ),
		),
		array(
			'title' => __( 'WordPress へ自動取り込み', 'custom-rss-builder' ),
			'body'  => __( '手動取り込みに加え、時間指定の自動取り込みにも対応（プランにより利用範囲が異なります）。', 'custom-rss-builder' ),
		),
		array(
			'title' => __( 'Pro: AI テキスト変換', 'custom-rss-builder' ),
			'body'  => __( 'レビュー本文などを Gemini API で整形・翻訳（BYOK）。外国語レビューの日本語化に。', 'custom-rss-builder' ),
		),
	);
	foreach ( $features as $card ) {
		$lines[] = '<article class="crb-sales-lp__card">';
		$lines[] = '<h3>' . esc_html( $card['title'] ) . '</h3>';
		$lines[] = '<p>' . esc_html( $card['body'] ) . '</p>';
		$lines[] = '</article>';
	}
	$lines[] = '</div>';
	$lines[] = '</section>';

	// Screenshots.
	$lines[] = '<section class="crb-sales-lp__section" id="crb-lp-screenshots">';
	$lines[] = '<h2>' . esc_html__( '画面イメージ', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<div class="crb-sales-lp__shots">';
	$shots = array(
		array(
			'file'    => 'lp-shot-preview.png',
			'caption' => __( 'プレビュー結果（抽出データ一覧）', 'custom-rss-builder' ),
		),
		array(
			'file'    => 'lp-shot-rss.png',
			'caption' => __( 'RSS 配信 URL をブラウザで表示', 'custom-rss-builder' ),
		),
		array(
			'file'    => 'lp-shot-imported.png',
			'caption' => __( 'WordPress へ取り込んだレビュー記事', 'custom-rss-builder' ),
		),
	);
	foreach ( $shots as $shot ) {
		$img = crb_sales_lp_image_tag( $shot['file'], $shot['caption'] );
		if ( '' === $img ) {
			$lines[] = '<figure class="crb-sales-lp__shot crb-sales-lp__placeholder"><span>' . esc_html( $shot['caption'] ) . '</span></figure>';
			continue;
		}
		$lines[] = '<figure class="crb-sales-lp__shot">';
		$lines[] = $img;
		$lines[] = '<figcaption>' . esc_html( $shot['caption'] ) . '</figcaption>';
		$lines[] = '</figure>';
	}
	$lines[] = '</div>';
	$lines[] = '</section>';

	// Steps.
	$lines[] = '<section class="crb-sales-lp__section" id="crb-lp-steps">';
	$lines[] = '<h2>' . esc_html__( '導入の流れ', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol class="crb-sales-lp__steps">';
	$lines[] = '<li><strong>' . esc_html__( '無料ライセンスを申請', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( '下のフォームからメールアドレスを送信。キーが届きます。', 'custom-rss-builder' ) . '</li>';
	if ( '' !== $download_url ) {
		$lines[] = '<li><strong>' . esc_html__( 'ZIP をダウンロード', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( '届いたメールの URL・パスワードで client ZIP を取得。', 'custom-rss-builder' ) . '</li>';
	}
	$lines[] = '<li><strong>' . esc_html__( 'WordPress にインストール', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( 'プラグイン → 新規追加 → アップロードで有効化。', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li><strong>' . esc_html__( 'キーを有効化してフィード作成', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( 'ライセンス画面でキーを入力。対象 URL のセレクタを設定します。', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	if ( '' !== $install_url ) {
		$lines[] = '<p><a href="' . esc_url( $install_url ) . '">' . esc_html__( '詳しいインストール手順はこちら', 'custom-rss-builder' ) . '</a></p>';
	}
	$lines[] = '</section>';

	// Pricing.
	$lines[] = '<section class="crb-sales-lp__section" id="crb-lp-pricing">';
	$lines[] = '<h2>' . esc_html__( 'プラン比較', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<div class="crb-sales-lp__pricing-grid">';
	$lines[] = '<div class="crb-sales-lp__price-card">';
	$lines[] = '<h3>' . esc_html__( '無料', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p class="crb-sales-lp__price">' . esc_html__( '0 円', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<p class="crb-sales-lp__price-note">' . esc_html__( 'フィード 1 件 · スロット {%1%}〜{%3%}', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<a class="crb-sales-lp__btn crb-sales-lp__btn--primary" href="#crb-lp-register">' . esc_html__( '無料で始める', 'custom-rss-builder' ) . '</a>';
	$lines[] = '</div>';
	$lines[] = '<div class="crb-sales-lp__price-card crb-sales-lp__price-card--pro">';
	$lines[] = '<h3>Pro</h3>';
	$lines[] = '<p class="crb-sales-lp__price">' . esc_html( function_exists( 'crb_pro_monthly_price_label' ) ? crb_pro_monthly_price_label() : __( '月額 3,300 円（税込）', 'custom-rss-builder' ) ) . '</p>';
	$lines[] = '<p class="crb-sales-lp__price-note">' . esc_html__( 'フィード無制限 · スロット {%1%}〜{%20%} · AI 変換 · クレジット非表示', 'custom-rss-builder' ) . '</p>';
	if ( '' !== $pro_url ) {
		$lines[] = '<a class="crb-sales-lp__btn crb-sales-lp__btn--pro" href="' . esc_url( $pro_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Pro を申し込む', 'custom-rss-builder' ) . '</a>';
	}
	$lines[] = '</div>';
	$lines[] = '</div>';

	if ( ! empty( $plan_rows ) ) {
		$lines[] = '<table class="crb-sales-lp__plan-table widefat">';
		$lines[] = '<thead><tr><th>' . esc_html__( '機能', 'custom-rss-builder' ) . '</th><th>' . esc_html__( '無料', 'custom-rss-builder' ) . '</th><th>Pro</th></tr></thead><tbody>';
		foreach ( $plan_rows as $row ) {
			$lines[] = sprintf(
				'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( (string) ( $row['label'] ?? '' ) ),
				esc_html( (string) ( $row['free'] ?? '' ) ),
				esc_html( (string) ( $row['pro'] ?? '' ) )
			);
		}
		$lines[] = '</tbody></table>';
	}
	$lines[] = '</section>';

	// Register.
	$lines[] = '<section class="crb-sales-lp__section crb-sales-lp__section--register" id="crb-lp-register">';
	$lines[] = '<h2>' . esc_html__( '無料ライセンスの申請', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<p>' . esc_html__( 'メールアドレスを入力すると、無料プランのライセンスキーをお送りします。ZIP のダウンロード案内も同じメールに含まれます。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '[crb_free_license]';
	$lines[] = '</section>';

	// Download.
	if ( '' !== $download_url ) {
		$lines[] = '<section class="crb-sales-lp__section" id="crb-lp-download">';
		$lines[] = '<h2>' . esc_html__( 'プラグインのダウンロード', 'custom-rss-builder' ) . '</h2>';
		$lines[] = '<p>' . esc_html__( 'すでにキーをお持ちの方は、こちらから client ZIP を取得できます（パスワードはライセンスメールに記載）。', 'custom-rss-builder' ) . '</p>';
		$lines[] = '<p><a class="crb-sales-lp__btn crb-sales-lp__btn--secondary" href="' . esc_url( $download_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'ダウンロードページを開く', 'custom-rss-builder' ) . '</a></p>';
		$lines[] = '</section>';
	}

	// Resources.
	$lines[] = '<section class="crb-sales-lp__section" id="crb-lp-resources">';
	$lines[] = '<h2>' . esc_html__( 'サポート・練習用', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ul class="crb-sales-lp__links">';
	if ( '' !== $samples_url ) {
		$lines[] = '<li><a href="' . esc_url( $samples_url ) . '">' . esc_html__( '練習用サンプル HTML（セレクタの練習）', 'custom-rss-builder' ) . '</a></li>';
	}
	if ( '' !== $install_url ) {
		$lines[] = '<li><a href="' . esc_url( $install_url ) . '">' . esc_html__( 'インストール手順', 'custom-rss-builder' ) . '</a></li>';
	}
	if ( function_exists( 'crb_ai_manual_page_url' ) ) {
		$ai_url = crb_ai_manual_page_url();
		if ( '' !== $ai_url ) {
			$lines[] = '<li><a href="' . esc_url( $ai_url ) . '">' . esc_html__( 'Gemini API キー設定手順（Pro）', 'custom-rss-builder' ) . '</a></li>';
		}
	}
	$lines[] = '</ul>';
	$lines[] = '</section>';

	$lines[] = '<footer class="crb-sales-lp__footer">';
	$lines[] = '<p><small>' . esc_html__( '本ページは Custom RSS Builder プラグインにより自動更新されます。', 'custom-rss-builder' ) . '</small></p>';
	$lines[] = '</footer>';

	$lines[] = '</div>';

	return implode( "\n", $lines );
}

/**
 * @param bool $force Force reinstall.
 * @return array{ok:bool,message:string,url:string}
 */
function crb_sales_lp_install( $force = false ) {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return array(
			'ok'      => false,
			'message' => 'not authority',
			'url'     => '',
		);
	}

	$stored_ver = (string) get_option( 'crb_sales_lp_install_version', '' );
	if ( ! $force && CRB_SALES_LP_VERSION === $stored_ver ) {
		return array(
			'ok'      => true,
			'message' => 'already installed',
			'url'     => crb_sales_lp_page_url(),
		);
	}

	if ( ! function_exists( 'crb_demo_samples_upsert_page' ) ) {
		return array(
			'ok'      => false,
			'message' => 'upsert helper missing',
			'url'     => '',
		);
	}

	$title   = __( 'Custom RSS Builder', 'custom-rss-builder' );
	$content = crb_sales_lp_build_page_content();
	$slug    = crb_sales_lp_page_slug();
	$page_id = crb_sales_lp_resolve_page_id();

	if ( $page_id > 0 ) {
		$result = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_parent'  => 0,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'message' => 'page update failed',
				'url'     => crb_sales_lp_page_url(),
			);
		}
		$page_id = (int) $result;
	} else {
		$page_id = crb_demo_samples_upsert_page( $slug, $title, $content, 0 );
	}

	if ( $page_id <= 0 ) {
		return array(
			'ok'      => false,
			'message' => 'page upsert failed',
			'url'     => '',
		);
	}

	update_option( CRB_SALES_LP_OPTION_PAGE_ID, (int) $page_id, false );
	crb_sales_lp_assign_front_page( $page_id );
	update_option( 'crb_sales_lp_install_version', CRB_SALES_LP_VERSION, false );

	return array(
		'ok'      => true,
		'message' => 'installed',
		'url'     => (string) get_permalink( $page_id ),
	);
}

/**
 * init / activate 用。
 */
function crb_sales_lp_maybe_install() {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return;
	}
	crb_sales_lp_install( false );
}

/**
 * フロントの LP 用スタイル・登録フォーム CSS。
 */
function crb_sales_lp_enqueue_assets() {
	if ( ! function_exists( 'crb_sales_lp_is_current_page' ) || ! crb_sales_lp_is_current_page() ) {
		return;
	}

	$css_path = CRB_PLUGIN_DIR . 'assets/css/sales-lp.css';
	$reg_path = CRB_PLUGIN_DIR . 'license-server/assets/register.css';
	$build    = defined( 'CRB_BUILD_ID' ) ? CRB_BUILD_ID : CRB_VERSION;
	$ver_css  = CRB_VERSION . '.' . $build . '.' . ( is_readable( $css_path ) ? (string) filemtime( $css_path ) : $build );

	wp_enqueue_style( 'crb-sales-lp', CRB_PLUGIN_URL . 'assets/css/sales-lp.css', array(), $ver_css );
	if ( is_readable( $reg_path ) ) {
		wp_enqueue_style( 'crb-ls-register', CRB_PLUGIN_URL . 'license-server/assets/register.css', array(), $ver_css );
	}
}

/**
 * @return bool
 */
function crb_sales_lp_can_manage() {
	return function_exists( 'crb_license_is_authoritative_server' )
		&& crb_license_is_authoritative_server()
		&& current_user_can( 'edit_pages' );
}

/**
 * ライセンスサーバー設定画面用: 再生成 POST。
 */
function crb_sales_lp_handle_admin_reinstall() {
	if ( ! crb_sales_lp_can_manage() ) {
		wp_die( esc_html__( '権限がありません。', 'custom-rss-builder' ) );
	}
	check_admin_referer( 'crb_sales_lp_reinstall' );
	$result   = crb_sales_lp_install( true );
	$redirect = add_query_arg(
		array(
			'page'                   => 'crb-license-server-settings',
			'crb-sales-lp-reinstalled' => $result['ok'] ? '1' : '0',
		),
		admin_url( 'admin.php' )
	);
	wp_safe_redirect( $redirect );
	exit;
}

add_action( 'init', 'crb_sales_lp_maybe_install', 19 );
add_action( 'wp_enqueue_scripts', 'crb_sales_lp_enqueue_assets', 20 );
add_action( 'admin_post_crb_sales_lp_reinstall', 'crb_sales_lp_handle_admin_reinstall' );
