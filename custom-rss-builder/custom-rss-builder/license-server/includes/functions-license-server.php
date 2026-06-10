<?php
/**
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return string
 */
function crb_ls_get_option( $key, $default = '' ) {
	$opts = get_option( 'crb_ls_settings', array() );
	if ( ! is_array( $opts ) ) {
		$opts = array();
	}
	return isset( $opts[ $key ] ) ? (string) $opts[ $key ] : (string) $default;
}

/**
 * @param array<string, mixed> $patch Settings patch.
 */
function crb_ls_update_settings( array $patch ) {
	$opts = get_option( 'crb_ls_settings', array() );
	if ( ! is_array( $opts ) ) {
		$opts = array();
	}
	update_option( 'crb_ls_settings', array_merge( $opts, $patch ), false );
}

/**
 * @return string
 */
function crb_ls_api_secret() {
	$secret = crb_ls_get_option( 'api_secret', '' );
	if ( '' === $secret ) {
		$secret = wp_generate_password( 32, false, false );
		crb_ls_update_settings( array( 'api_secret' => $secret ) );
	}
	return $secret;
}

/**
 * 組み込みライセンスサーバーの Manager（同一 WordPress 内で直接呼び出す）。
 *
 * @return CRB_License_Server_License_Manager|null
 */
function crb_ls_get_manager() {
	static $manager = null;

	if ( null !== $manager ) {
		return $manager;
	}

	if ( ! class_exists( 'CRB_License_Server_License_Manager' ) ) {
		return null;
	}

	if ( class_exists( 'CRB_License_Server_Database' ) ) {
		CRB_License_Server_Database::maybe_install();
	}

	$manager = new CRB_License_Server_License_Manager();
	return $manager;
}

/**
 * @return string
 */
function crb_ls_pro_payment_url() {
	return function_exists( 'crb_license_pro_payment_url' ) ? crb_license_pro_payment_url() : '';
}

/**
 * @param string $email Email.
 * @return bool
 */
function crb_ls_is_valid_email( $email ) {
	$email = sanitize_email( (string) $email );
	return '' !== $email && is_email( $email );
}

/**
 * @return string
 */
function crb_ls_generate_license_key() {
	$segments = array();
	for ( $i = 0; $i < 4; $i++ ) {
		$segments[] = strtoupper( substr( bin2hex( random_bytes( 3 ) ), 0, 5 ) );
	}
	return 'CRB-' . implode( '-', $segments );
}

/**
 * @param string $site_url Site URL.
 * @return string
 */
function crb_ls_normalize_site_url( $site_url ) {
	$site_url = esc_url_raw( trim( (string) $site_url ) );
	if ( '' === $site_url ) {
		return '';
	}
	$parts = wp_parse_url( $site_url );
	if ( empty( $parts['host'] ) ) {
		return '';
	}
	$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
	$host   = strtolower( $parts['host'] );
	$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
	$path   = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';
	return $scheme . '://' . $host . $port . $path;
}

/**
 * @param string $provided Provided secret header.
 * @return bool
 */
function crb_ls_verify_api_secret( $provided ) {
	if ( '' === (string) $provided ) {
		return false;
	}
	return hash_equals( crb_ls_api_secret(), (string) $provided );
}

/**
 * @param array<string, mixed> $license Row.
 * @return bool
 */
function crb_ls_license_is_usable( array $license ) {
	$status = sanitize_key( (string) ( $license['status'] ?? '' ) );
	if ( 'active' === $status ) {
		return true;
	}
	if ( 'past_due' === $status ) {
		$grace_until = (string) ( $license['grace_until'] ?? '' );
		if ( '' !== $grace_until && strtotime( $grace_until ) >= time() ) {
			return true;
		}
	}
	return false;
}

/**
 * @param array<string, mixed> $license License row.
 * @return string free|standard|pro
 */
function crb_ls_license_plan( array $license ) {
	$plan = sanitize_key( (string) ( $license['plan'] ?? 'free' ) );
	return in_array( $plan, array( 'free', 'standard', 'pro' ), true ) ? $plan : 'free';
}

/**
 * @param string $plan Plan slug.
 * @return string
 */
function crb_ls_admin_plan_label( $plan ) {
	switch ( sanitize_key( (string) $plan ) ) {
		case 'pro':
			return __( 'Pro', 'crb-license-server' );
		case 'standard':
			return __( 'スタンダード', 'crb-license-server' );
		case 'free':
		default:
			return __( '無料', 'crb-license-server' );
	}
}

/**
 * @param string $plan Plan slug.
 * @return string
 */
function crb_ls_mail_plan_label( $plan ) {
	switch ( sanitize_key( (string) $plan ) ) {
		case 'pro':
			return __( 'Pro（有料）', 'crb-license-server' );
		case 'standard':
			return __( 'スタンダード（有料）', 'crb-license-server' );
		case 'free':
		default:
			return __( '無料', 'crb-license-server' );
	}
}

/**
 * @return string
 */
function crb_ls_mail_from_name() {
	if ( defined( 'CRB_LS_MAIL_FROM_NAME' ) && '' !== (string) CRB_LS_MAIL_FROM_NAME ) {
		return sanitize_text_field( (string) CRB_LS_MAIL_FROM_NAME );
	}
	$name = trim( crb_ls_get_option( 'mail_from_name', '' ) );
	if ( '' !== $name ) {
		return $name;
	}
	return 'Custom RSS Builder';
}

/**
 * @return string
 */
function crb_ls_mail_from_email() {
	if ( defined( 'CRB_LS_MAIL_FROM' ) && is_email( CRB_LS_MAIL_FROM ) ) {
		return sanitize_email( (string) CRB_LS_MAIL_FROM );
	}
	$email = sanitize_email( crb_ls_get_option( 'mail_from_email', '' ) );
	return is_email( $email ) ? $email : '';
}

/**
 * @param string $status Status slug.
 * @return string
 */
function crb_ls_admin_status_label( $status ) {
	switch ( sanitize_key( (string) $status ) ) {
		case 'active':
			return __( '有効', 'crb-license-server' );
		case 'expired':
			return __( '期限切れ', 'crb-license-server' );
		case 'cancelled':
			return __( '解約', 'crb-license-server' );
		case 'past_due':
			return __( '支払い遅延', 'crb-license-server' );
		case 'inactive':
			return __( '無効', 'crb-license-server' );
		default:
			return '' !== (string) $status ? (string) $status : '—';
	}
}

/**
 * @param string $plan free|pro.
 * @return string
 */
function crb_ls_mail_subject_template( $plan ) {
	if ( defined( 'CRB_LS_MAIL_SUBJECT' ) && '' !== (string) CRB_LS_MAIL_SUBJECT ) {
		$template = (string) CRB_LS_MAIL_SUBJECT;
	} else {
		$template = crb_ls_get_option(
			'mail_subject',
			'Custom RSS Builder ライセンスキーのご案内（{plan_label}）'
		);
	}

	return crb_ls_replace_mail_tags( $template, '', $plan );
}

/**
 * @param string $template Template.
 * @param string $license_key Key.
 * @param string $plan Plan.
 * @return string
 */
function crb_ls_replace_mail_tags( $template, $license_key, $plan ) {
	$plan_label = crb_ls_mail_plan_label( $plan );

	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	if ( '' === trim( $site_name ) || false !== stripos( $site_name, 'custom-rss-builder' ) ) {
		$site_name = 'Custom RSS Builder';
	}

	$replacements = array(
		'{license_key}'  => (string) $license_key,
		'{plan}'         => sanitize_key( (string) $plan ),
		'{plan_label}'   => $plan_label,
		'{site_name}'    => $site_name,
	);

	return strtr( (string) $template, $replacements );
}

/**
 * 推奨メール文面のバージョン（crb_ls_maybe_upgrade_mail_defaults で使用）。
 */
define( 'CRB_LS_MAIL_DEFAULTS_VERSION', '2' );

/**
 * @return string
 */
function crb_ls_mail_default_intro() {
	return __(
		"Custom RSS Builder をご利用いただきありがとうございます。\nライセンスキーを発行しました。以下の順に、ZIP の取得 → インストール → 有効化を行ってください。",
		'crb-license-server'
	);
}

/**
 * @param string $plan free|standard|pro
 * @return string
 */
function crb_ls_mail_plan_features_line( $plan ) {
	$plan = sanitize_key( (string) $plan );
	if ( 'standard' === $plan ) {
		$feed_limit = defined( 'CRB_LICENSE_STANDARD_FEED_LIMIT' ) ? (int) CRB_LICENSE_STANDARD_FEED_LIMIT : 3;
		$slots      = defined( 'CRB_LICENSE_STANDARD_SLOT_LIMIT' ) ? (int) CRB_LICENSE_STANDARD_SLOT_LIMIT : 5;
		$range      = function_exists( 'crb_license_format_slot_range_text' )
			? crb_license_format_slot_range_text( $slots )
			: sprintf( '{%1%%}〜{%d%%}', $slots );

		return sprintf(
			/* translators: 1: max feeds, 2: slot range e.g. {%1%}〜{%5%} */
			__( 'ご利用内容: フィード %1$d 件まで・スロット %2$s・自動取り込み 1 時間〜', 'crb-license-server' ),
			$feed_limit,
			$range
		);
	}
	if ( 'pro' === $plan ) {
		$feed_limit = defined( 'CRB_LICENSE_PRO_FEED_LIMIT' ) ? (int) CRB_LICENSE_PRO_FEED_LIMIT : 10;
		$slots      = function_exists( 'crb_license_pro_slot_count' )
			? (int) crb_license_pro_slot_count()
			: ( defined( 'CRB_RECORD_SLOT_COUNT' ) ? (int) CRB_RECORD_SLOT_COUNT : 20 );
		$range      = function_exists( 'crb_license_format_slot_range_text' )
			? crb_license_format_slot_range_text( $slots )
			: sprintf( '{%1%%}〜{%d%%}', $slots );

		return sprintf(
			/* translators: 1: max feeds, 2: slot range e.g. {%1%}〜{%20%} */
			__( 'ご利用内容: フィード %1$d 件まで・スロット %2$s・AI テキスト変換', 'crb-license-server' ),
			$feed_limit,
			$range
		);
	}

	$free_slots = defined( 'CRB_LICENSE_FREE_SLOT_LIMIT' ) ? (int) CRB_LICENSE_FREE_SLOT_LIMIT : 3;
	$range      = function_exists( 'crb_license_format_slot_range_text' )
		? crb_license_format_slot_range_text( $free_slots )
		: sprintf( '{%1%%}〜{%d%%}', $free_slots );

	return sprintf(
		/* translators: 1: max feeds, 2: slot range */
		__( 'ご利用内容: フィード %1$d 件・スロット %2$s', 'crb-license-server' ),
		defined( 'CRB_LICENSE_FREE_FEED_LIMIT' ) ? (int) CRB_LICENSE_FREE_FEED_LIMIT : 1,
		$range
	);
}

/**
 * @return string
 */
function crb_ls_mail_install_manual_url() {
	if ( ! function_exists( 'crb_install_manual_page_url' ) ) {
		return '';
	}

	return esc_url_raw( crb_install_manual_page_url() );
}

/**
 * @return string
 */
function crb_ls_mail_ai_manual_url() {
	if ( ! function_exists( 'crb_ai_manual_page_url' ) ) {
		return '';
	}

	return esc_url_raw( crb_ai_manual_page_url() );
}

/**
 * @return string
 */
function crb_ls_mail_install_manual_block() {
	$url = crb_ls_mail_install_manual_url();
	if ( '' === $url ) {
		return '';
	}

	$heading = crb_ls_get_option(
		'mail_install_manual_heading',
		__( '【インストールマニュアル】', 'crb-license-server' )
	);
	$summary = crb_ls_get_option(
		'mail_install_manual_summary',
		__( 'ZIP のアップロード、プラグイン有効化、ライセンス入力、初回フィード作成までの手順を掲載しています。', 'crb-license-server' )
	);

	$lines   = array();
	$lines[] = trim( (string) $heading );
	$lines[] = sprintf(
		/* translators: %s: install manual URL */
		__( 'URL: %s', 'crb-license-server' ),
		$url
	);
	if ( '' !== trim( (string) $summary ) ) {
		$lines[] = trim( (string) $summary );
	}

	return implode( "\n", $lines );
}

/**
 * @param string $plan free|pro
 * @return string
 */
function crb_ls_mail_ai_manual_block( $plan ) {
	if ( 'pro' !== sanitize_key( (string) $plan ) ) {
		return '';
	}

	$url = crb_ls_mail_ai_manual_url();
	if ( '' === $url ) {
		return '';
	}

	$lines   = array();
	$lines[] = __( '【Pro: Gemini API 設定手順】', 'crb-license-server' );
	$lines[] = sprintf(
		/* translators: %s: AI manual URL */
		__( 'URL: %s', 'crb-license-server' ),
		$url
	);
	$lines[] = __( 'AI テキスト変換を使う場合は、上記ページの手順で API キーを設定してください。', 'crb-license-server' );

	return implode( "\n", $lines );
}

/**
 * 初期設定代行（2 回目以降）の決済 URL（ライセンス設定で登録）。
 *
 * @return string
 */
function crb_ls_setup_service_payment_url() {
	if ( defined( 'CRB_SETUP_SERVICE_PAYMENT_URL' ) && '' !== (string) CRB_SETUP_SERVICE_PAYMENT_URL ) {
		return esc_url_raw( (string) CRB_SETUP_SERVICE_PAYMENT_URL );
	}

	$url = esc_url_raw( (string) crb_ls_get_option( 'mail_setup_service_payment_url', '' ) );

	/**
	 * @param string $url Setup service payment URL.
	 */
	return (string) apply_filters( 'crb_setup_service_payment_url', $url );
}

/**
 * @return string
 */
function crb_ls_mail_feed_pack_manual_url() {
	if ( ! function_exists( 'crb_feed_pack_manual_page_url' ) ) {
		return '';
	}

	return esc_url_raw( crb_feed_pack_manual_page_url() );
}

/**
 * Pro キー送付メール用：初期設定代行の案内ブロック。
 *
 * @param string $plan free|standard|pro
 * @return string
 */
function crb_ls_mail_setup_service_block( $plan ) {
	$plan = sanitize_key( (string) $plan );
	if ( ! in_array( $plan, array( 'standard', 'pro' ), true ) ) {
		return '';
	}

	$manual_url   = crb_ls_mail_feed_pack_manual_url();
	$payment_url  = crb_ls_setup_service_payment_url();
	$custom_block = trim( (string) crb_ls_get_option( 'mail_setup_service_block', '' ) );
	if ( '' !== $custom_block ) {
		return crb_ls_mail_apply_tags( $custom_block, '', $plan );
	}

	$lines   = array();
	$lines[] = __( '【有料プラン特典：初期設定代行（初回1フィード・1回無料）】', 'crb-license-server' );
	$lines[] = __( 'フィード設定（セレクタ等）を当方で作成し、設定パック（JSON）でお渡しします。', 'crb-license-server' );
	$lines[] = '';
	$lines[] = __( '■ お申し込み方法（初回・無料）', 'crb-license-server' );
	$lines[] = __( '1. 上記キーでライセンスを有効化する', 'crb-license-server' );
	$lines[] = __( '2. このメールに返信し、次をお知らせください', 'crb-license-server' );
	$lines[] = __( '   ・対象ページの URL', 'crb-license-server' );
	$lines[] = __( '   ・RSS に載せたい内容の概要（任意）', 'crb-license-server' );
	$lines[] = '';
	$repeat_price = function_exists( 'crb_pro_setup_repeat_price_label' )
		? crb_pro_setup_repeat_price_label()
		: __( '1,100 円（税込）／回', 'crb-license-server' );
	$lines[]      = sprintf(
		/* translators: %s: repeat setup price label */
		__( '■ 2フィード目以降・作り直し（%s）', 'crb-license-server' ),
		$repeat_price
	);
	if ( '' !== $payment_url ) {
		$lines[] = sprintf(
			/* translators: %s: payment URL */
			__( '決済 URL: %s', 'crb-license-server' ),
			$payment_url
		);
		$lines[] = __( 'お申し込みは上記決済後、このメールに返信で対象 URL をお知らせください。', 'crb-license-server' );
	} else {
		$lines[] = __( 'お申し込みはこのメールへの返信でご連絡ください。', 'crb-license-server' );
	}
	if ( '' !== $manual_url ) {
		$lines[] = sprintf(
			/* translators: %s: feed pack manual URL */
			__( '詳細: %s', 'crb-license-server' ),
			$manual_url . '#crb-fpack-pro-service'
		);
	}

	return implode( "\n", $lines );
}

/**
 * @param string $plan free|pro
 * @return string
 */
function crb_ls_mail_activation_block( $plan ) {
	$lines   = array();
	$lines[] = __( '【ライセンス有効化】', 'crb-license-server' );
	$lines[] = __( '1. プラグイン ZIP をインストール済みの場合、この手順から開始できます', 'crb-license-server' );
	$lines[] = __( '2. WordPress 管理画面 → Custom RSS Builder → ライセンス を開く', 'crb-license-server' );
	$lines[] = __( '3. 「ライセンスキーを有効化」欄に、下記キーを貼り付けて「有効化」を押す', 'crb-license-server' );
	$lines[] = __( '4. 「現在の状態」でプランと「このサイトで利用可：はい」を確認', 'crb-license-server' );

	if ( in_array( sanitize_key( (string) $plan ), array( 'standard', 'pro' ), true ) ) {
		$lines[] = __( '※ 1 つのキーは 1 サイトのみで利用できます（別サイトでは有効化できません）', 'crb-license-server' );
	}

	return implode( "\n", $lines );
}

/**
 * @return string
 */
function crb_ls_mail_default_body_template() {
	return implode(
		"\n\n",
		array(
			'{intro}',
			"{plan_label_line}\n{plan_features_line}",
			"{license_key_line}\n{license_key}",
			'{download_block}',
			'{install_manual_block}',
			'{activation_block}',
			'{setup_service_block}',
			'{ai_manual_block}',
		)
	);
}

/**
 * 推奨メール文面を設定に反映（本文テンプレート空欄＝標準構成を使用）。
 */
function crb_ls_apply_recommended_mail_defaults() {
	crb_ls_update_settings(
		array(
			'mail_intro'                 => crb_ls_mail_default_intro(),
			'mail_activation_hint'       => '',
			'mail_body'                  => '',
			'mail_download_install_hint' => '',
			'mail_defaults_version'      => CRB_LS_MAIL_DEFAULTS_VERSION,
		)
	);
}

/**
 * 初回または旧文面のとき推奨テンプレートへ移行。
 */
function crb_ls_maybe_upgrade_mail_defaults() {
	if ( CRB_LS_MAIL_DEFAULTS_VERSION === crb_ls_get_option( 'mail_defaults_version', '' ) ) {
		return;
	}

	$body = trim( crb_ls_get_option( 'mail_body', '' ) );
	if ( '' !== $body ) {
		crb_ls_update_settings( array( 'mail_defaults_version' => CRB_LS_MAIL_DEFAULTS_VERSION ) );
		return;
	}

	crb_ls_apply_recommended_mail_defaults();
}

add_action( 'admin_init', 'crb_ls_maybe_upgrade_mail_defaults', 5 );

/**
 * メール用プラグイン ZIP のダウンロード URL（プラン別上書き可）。
 *
 * @param string $plan free|pro
 * @return string
 */
function crb_ls_mail_download_url( $plan = '' ) {
	$plan   = sanitize_key( (string) $plan );
	$common = esc_url_raw( crb_ls_get_option( 'mail_download_url', '' ) );
	$pro    = esc_url_raw( crb_ls_get_option( 'mail_download_url_pro', '' ) );
	$free   = esc_url_raw( crb_ls_get_option( 'mail_download_url_free', '' ) );

	if ( 'pro' === $plan && '' !== $pro ) {
		return $pro;
	}
	if ( 'free' === $plan && '' !== $free ) {
		return $free;
	}

	return $common;
}

/**
 * メール用プラグイン ZIP のダウンロードパスワード。
 *
 * @return string
 */
function crb_ls_mail_download_password() {
	return (string) crb_ls_get_option( 'mail_download_password', '' );
}

/**
 * ライセンスメールに挿入するダウンロード案内ブロック（URL 未設定時は空文字）。
 *
 * @param string $plan free|pro
 * @return string
 */
function crb_ls_mail_download_block( $plan = '' ) {
	$url = crb_ls_mail_download_url( $plan );
	if ( '' === $url ) {
		return '';
	}

	$heading  = crb_ls_get_option(
		'mail_download_heading',
		__( '【プラグインのダウンロード】', 'crb-license-server' )
	);
	$password = crb_ls_mail_download_password();
	$hint     = trim( (string) crb_ls_get_option( 'mail_download_install_hint', '' ) );
	if ( '' === $hint ) {
		if ( '' !== crb_ls_mail_install_manual_url() ) {
			$hint = __(
				'取得した ZIP は、下記インストールマニュアルの「手順 1」からインストールしてください。',
				'crb-license-server'
			);
		} else {
			$hint = __(
				'WordPress 管理画面 → プラグイン → 新規追加 → アップロード で ZIP をインストールしてください。',
				'crb-license-server'
			);
		}
	}

	$lines   = array();
	$lines[] = trim( (string) $heading );
	$lines[] = sprintf(
		/* translators: %s: download URL */
		__( 'URL: %s', 'crb-license-server' ),
		$url
	);
	if ( '' !== $password ) {
		$lines[] = sprintf(
			/* translators: %s: download password */
			__( 'パスワード: %s', 'crb-license-server' ),
			$password
		);
	}
	if ( '' !== trim( (string) $hint ) ) {
		$lines[] = '';
		$lines[] = trim( (string) $hint );
	}

	return implode( "\n", $lines );
}

/**
 * ライセンス通知メールの置換タグ一覧。
 *
 * @param string $license_key Key.
 * @param string $plan        free|pro
 * @return array<string, string>
 */
function crb_ls_mail_tag_map( $license_key, $plan ) {
	$plan_label = crb_ls_mail_plan_label( $plan );

	$intro = crb_ls_get_option( 'mail_intro', '' );
	if ( '' === trim( $intro ) ) {
		$intro = crb_ls_mail_default_intro();
	}

	$activation_custom = crb_ls_get_option( 'mail_activation_hint', '' );
	$activation_block  = crb_ls_mail_activation_block( $plan );
	$activation_hint   = '' !== trim( (string) $activation_custom )
		? (string) $activation_custom
		: $activation_block;

	$download_url        = crb_ls_mail_download_url( $plan );
	$download_password   = crb_ls_mail_download_password();
	$download_block      = crb_ls_mail_download_block( $plan );
	$install_manual_url  = crb_ls_mail_install_manual_url();
	$install_manual_block = crb_ls_mail_install_manual_block();
	$ai_manual_block        = crb_ls_mail_ai_manual_block( $plan );
	$setup_service_block    = crb_ls_mail_setup_service_block( $plan );
	$setup_service_pay_url  = crb_ls_setup_service_payment_url();
	$feed_pack_manual_url   = crb_ls_mail_feed_pack_manual_url();
	$plan_features_line     = crb_ls_mail_plan_features_line( $plan );

	$map = array(
		'{intro}'                => (string) $intro,
		'{plan_label}'           => $plan_label,
		'{plan_label_line}'      => sprintf(
			/* translators: %s: plan label */
			__( 'プラン: %s', 'crb-license-server' ),
			$plan_label
		),
		'{plan_features_line}'   => $plan_features_line,
		'{license_key}'          => (string) $license_key,
		'{license_key_line}'     => __( '【ライセンスキー】', 'crb-license-server' ),
		'{activation_hint}'      => (string) $activation_hint,
		'{activation_block}'     => $activation_block,
		'{download_url}'         => $download_url,
		'{download_password}'    => $download_password,
		'{download_block}'       => $download_block,
		'{install_manual_url}'   => $install_manual_url,
		'{install_manual_block}' => $install_manual_block,
		'{ai_manual_url}'              => crb_ls_mail_ai_manual_url(),
		'{ai_manual_block}'            => $ai_manual_block,
		'{setup_service_block}'        => $setup_service_block,
		'{setup_service_payment_url}'  => $setup_service_pay_url,
		'{feed_pack_manual_url}'       => $feed_pack_manual_url,
	);

	// intro / activation_hint 内にもタグを書けるよう二段置換（最大2パス）。
	foreach ( array( '{intro}', '{activation_hint}' ) as $nested_key ) {
		if ( ! isset( $map[ $nested_key ] ) || '' === $map[ $nested_key ] || false === strpos( $map[ $nested_key ], '{' ) ) {
			continue;
		}
		$nested = $map;
		unset( $nested[ $nested_key ] );
		$map[ $nested_key ] = strtr( $map[ $nested_key ], $nested );
	}

	return $map;
}

/**
 * 空ブロック除去と空行整理。
 *
 * @param string $body Mail body.
 * @return string
 */
function crb_ls_mail_normalize_body( $body ) {
	$body = preg_replace( "/\n{3,}/", "\n\n", (string) $body );
	$body = preg_replace( "/^\s*\n/m", "\n", $body );

	return trim( (string) $body );
}

/**
 * @param string $text        Template fragment.
 * @param string $license_key Key.
 * @param string $plan        free|pro
 * @return string
 */
function crb_ls_mail_apply_tags( $text, $license_key, $plan ) {
	return strtr( (string) $text, crb_ls_mail_tag_map( $license_key, $plan ) );
}

/**
 * @param string $license_key Key.
 * @param string $plan Plan.
 * @return string
 */
function crb_ls_mail_body( $license_key, $plan ) {
	$template = crb_ls_get_option( 'mail_body', '' );
	if ( '' === trim( $template ) ) {
		$template = crb_ls_mail_default_body_template();
	}

	$body = crb_ls_mail_apply_tags( $template, $license_key, $plan );

	return crb_ls_mail_normalize_body(
		(string) apply_filters( 'crb_ls_license_email_body', $body, $license_key, $plan )
	);
}

/**
 * Send license key email.
 *
 * @param string $email Email.
 * @param string $license_key Key.
 * @param string $plan Plan.
 * @return bool
 */
function crb_ls_email_license_key( $email, $license_key, $plan ) {
	$email = sanitize_email( (string) $email );
	if ( ! is_email( $email ) ) {
		return false;
	}

	$subject = crb_ls_mail_subject_template( $plan );
	$body    = crb_ls_mail_body( $license_key, $plan );
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

	$from_email = crb_ls_mail_from_email();
	$from_name  = crb_ls_mail_from_name();
	if ( is_email( $from_email ) ) {
		$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_email );
	}

	/**
	 * @param string[] $headers Headers.
	 * @param string   $email To.
	 * @param string   $license_key Key.
	 * @param string   $plan Plan.
	 */
	$headers = apply_filters( 'crb_ls_license_email_headers', $headers, $email, $license_key, $plan );

	/**
	 * @param string $subject Subject.
	 * @param string $email To.
	 * @param string $license_key Key.
	 * @param string $plan Plan.
	 */
	$subject = (string) apply_filters( 'crb_ls_license_email_subject', $subject, $email, $license_key, $plan );

	return wp_mail( $email, $subject, $body, $headers );
}
