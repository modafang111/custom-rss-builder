<?php
/**
 * ライセンス状態・機能ゲート。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_LICENSE_OPTION_KEY', 'crb_license_settings' );
define( 'CRB_LICENSE_FREE_FEED_LIMIT', 1 );
/** Pro プランで作成できるフィード数の上限 */
define( 'CRB_LICENSE_PRO_FEED_LIMIT', 10 );
/** 無料プランで使えるスロット数（{%1%}〜{%n%}。タイトル・リンク含む） */
define( 'CRB_LICENSE_FREE_SLOT_LIMIT', 3 );
/** 無料プランの自動取り込み最短間隔（時間） */
define( 'CRB_LICENSE_FREE_IMPORT_SCHEDULE_MIN_HOURS', 24 );

/**
 * @return array<string, mixed>
 */
function crb_license_get_settings() {
	$settings = get_option( CRB_LICENSE_OPTION_KEY, array() );
	return is_array( $settings ) ? $settings : array();
}

/**
 * @param array<string, mixed> $patch Patch.
 */
function crb_license_update_settings( array $patch ) {
	$current = crb_license_get_settings();
	update_option( CRB_LICENSE_OPTION_KEY, array_merge( $current, $patch ), false );
}

/**
 * Pro 外部決済 URL（管理画面では編集不可。プラグイン組み込みまたは filter）。
 *
 * @return string
 */
function crb_license_pro_payment_url() {
	if ( defined( 'CRB_PRO_PAYMENT_URL' ) && '' !== (string) CRB_PRO_PAYMENT_URL ) {
		return esc_url_raw( (string) CRB_PRO_PAYMENT_URL );
	}

	/**
	 * @param string $url Default Pro payment URL.
	 */
	return (string) apply_filters(
		'crb_pro_payment_url',
		'https://www.wordpress-123.com/payment/f2pset.php?code=16&mode=button'
	);
}

/**
 * 無料ライセンス申請ページ URL（正本サイトの登録フォーム）。
 *
 * @return string
 */
function crb_license_registration_portal_url() {
	$url = '';
	if ( function_exists( 'crb_license_client_remote_base_url' ) ) {
		$url = crb_license_client_remote_base_url();
	}
	if ( '' === $url && function_exists( 'crb_license_suggested_client_api_base' ) ) {
		$url = crb_license_suggested_client_api_base();
	}
	if ( '' === $url ) {
		$url = defined( 'CRB_LICENSE_DEFAULT_CLIENT_API_BASE' )
			? (string) CRB_LICENSE_DEFAULT_CLIENT_API_BASE
			: 'https://123789.jp/custom-rss-builder';
	}
	$url = trailingslashit( crb_license_normalize_site_url( $url ) );

	/**
	 * @param string $url Registration portal URL on authority site.
	 */
	return (string) apply_filters( 'crb_license_registration_portal_url', $url );
}

/**
 * クライアント向け: ライセンス未設定時の案内（プレーンテキスト・AJAX 用）。
 *
 * @return string
 */
function crb_license_client_activation_guidance_message() {
	$license_url = admin_url( 'admin.php?page=custom-rss-builder-license' );
	$portal_url  = crb_license_registration_portal_url();

	return sprintf(
		/* translators: 1: registration portal URL, 2: license admin URL */
		__( 'ライセンスが有効ではありません。%1$s で無料ライセンスを申請し、届いたキーをライセンス画面（%2$s）で有効化してください。', 'custom-rss-builder' ),
		$portal_url,
		$license_url
	);
}

/**
 * クライアント向け: ライセンス未設定時の案内（管理画面 HTML）。
 *
 * @return string
 */
function crb_license_client_activation_guidance_html() {
	$license_url = admin_url( 'admin.php?page=custom-rss-builder-license' );
	$portal_url  = crb_license_registration_portal_url();

	return sprintf(
		/* translators: 1: registration portal URL, 2: license admin URL */
		__( 'ライセンスが有効ではありません。<a href="%1$s" target="_blank" rel="noopener noreferrer">販売元サイトで無料ライセンスを申請</a>し、届いたキーを<a href="%2$s">ライセンス画面</a>で有効化してください。', 'custom-rss-builder' ),
		esc_url( $portal_url ),
		esc_url( $license_url )
	);
}

/**
 * クライアント向けライセンス未設定の警告（リクエスト内で1回だけ）。
 */
function crb_license_echo_client_activation_notice() {
	static $shown = false;

	if ( $shown ) {
		return;
	}
	if ( ! function_exists( 'crb_license_ui_is_client_screen' ) || ! crb_license_ui_is_client_screen() ) {
		return;
	}
	if ( crb_license_get_state()['usable'] ) {
		return;
	}

	$shown = true;
	echo '<div class="notice notice-warning"><p>';
	echo wp_kses_post( crb_license_client_activation_guidance_html() );
	echo '</p></div>';
}

/**
 * クライアントでライセンス未設定の案内を出すべきか（ボタン等の補助 UI 用）。
 *
 * @return bool
 */
function crb_license_should_show_client_activation_ui() {
	if ( ! function_exists( 'crb_license_ui_is_client_screen' ) || ! crb_license_ui_is_client_screen() ) {
		return false;
	}
	return ! crb_license_get_state()['usable'];
}

/**
 * @return string
 */
function crb_license_api_base() {
	$settings = crb_license_get_settings();
	if ( ! empty( $settings['api_base'] ) ) {
		return untrailingslashit( (string) $settings['api_base'] );
	}
	if ( defined( 'CRB_LICENSE_API_BASE' ) ) {
		return untrailingslashit( (string) CRB_LICENSE_API_BASE );
	}
	return untrailingslashit( home_url() );
}

/**
 * @return string
 */
function crb_license_api_secret() {
	$settings = crb_license_get_settings();
	if ( ! empty( $settings['api_secret'] ) ) {
		return (string) $settings['api_secret'];
	}
	if ( defined( 'CRB_LICENSE_API_SECRET' ) ) {
		return (string) CRB_LICENSE_API_SECRET;
	}
	// 組み込みライセンスサーバー（同一 WordPress）では自動で同じ Secret を使う。
	if ( function_exists( 'crb_ls_api_secret' ) ) {
		return crb_ls_api_secret();
	}
	return '';
}

/**
 * ライセンス画面の入力欄用。保存済み Secret または組み込みサーバーの Secret。
 *
 * @return string
 */
function crb_license_api_secret_for_form() {
	$settings = crb_license_get_settings();
	if ( ! empty( $settings['api_secret'] ) ) {
		return (string) $settings['api_secret'];
	}
	if ( function_exists( 'crb_ls_api_secret' ) ) {
		return crb_ls_api_secret();
	}
	if ( defined( 'CRB_LICENSE_API_SECRET' ) ) {
		return (string) CRB_LICENSE_API_SECRET;
	}
	return '';
}

/**
 * @param string $site_url Site URL.
 * @return string
 */
function crb_license_normalize_site_url( $site_url ) {
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
 * @return string
 */
function crb_license_site_url() {
	return crb_license_normalize_site_url( home_url( '/' ) );
}

/**
 * 組み込みライセンスサーバー（同一 WP 内）が利用可能か。
 *
 * @return bool
 */
function crb_license_has_bundled_server() {
	return function_exists( 'crb_ls_get_manager' ) && (bool) crb_ls_get_manager();
}

/**
 * この WordPress がライセンスを発行・管理する正本サーバーか。
 * authority ZIP では true、client ZIP では false。
 *
 * @return bool
 */
function crb_license_is_authoritative_server() {
	if ( defined( 'CRB_LICENSE_AUTHORITY' ) ) {
		return (bool) CRB_LICENSE_AUTHORITY;
	}
	$variant = crb_package_variant();
	if ( 'authority' === $variant ) {
		return true;
	}
	if ( 'client' === $variant ) {
		return false;
	}
	$settings = crb_license_get_settings();
	$mode     = sanitize_key( (string) ( $settings['connection_mode'] ?? '' ) );
	if ( '' === $mode || 'remote' === $mode ) {
		return false;
	}
	return crb_license_has_bundled_server();
}

/**
 * 組み込み接続を選べるか（ライセンス正本サーバー用。クライアント配布では false 既定）。
 *
 * @return bool
 */
function crb_license_may_use_embedded_connection() {
	if ( defined( 'CRB_LICENSE_AUTHORITY' ) ) {
		return (bool) CRB_LICENSE_AUTHORITY;
	}
	$settings = crb_license_get_settings();
	$saved    = sanitize_key( (string) ( $settings['connection_mode'] ?? '' ) );
	if ( 'embedded' === $saved ) {
		return true;
	}
	return (bool) apply_filters( 'crb_license_allow_embedded_connection', false );
}

/**
 * @return string embedded|remote
 */
function crb_license_get_connection_mode() {
	$settings = crb_license_get_settings();
	$mode     = sanitize_key( (string) ( $settings['connection_mode'] ?? '' ) );
	if ( '' === $mode ) {
		return 'remote';
	}
	return in_array( $mode, array( 'embedded', 'remote' ), true ) ? $mode : 'remote';
}

/**
 * REST API 経由でライセンスサーバーと通信するか。
 *
 * @return bool
 */
function crb_license_uses_remote_api() {
	if ( 'remote' === crb_license_get_connection_mode() ) {
		return true;
	}
	if ( ! crb_license_has_bundled_server() ) {
		return true;
	}
	$settings = crb_license_get_settings();
	if ( ! empty( $settings['api_base'] ) ) {
		$base = crb_license_normalize_site_url( (string) $settings['api_base'] );
		if ( '' !== $base && $base !== crb_license_site_url() ) {
			return true;
		}
	}
	return false;
}

/**
 * 組み込み DB 直結モード（自動無料化・テスト用ローカル操作が使える）。
 *
 * @return bool
 */
function crb_license_is_embedded_mode() {
	return crb_license_has_bundled_server() && ! crb_license_uses_remote_api();
}

/**
 * ライセンスサーバーがこの WordPress で管理・発行されるか（UI・自動無料化の判定）。
 *
 * @return bool
 */
function crb_license_is_local_server() {
	return crb_license_is_authoritative_server();
}

/**
 * 運用分離: このサイトの役割（authority=ライセンス正本 / client=REST クライアント）。
 *
 * @return string authority|client
 */
function crb_license_operations_role() {
	return crb_license_is_authoritative_server() ? 'authority' : 'client';
}

/**
 * 配布 ZIP の種別（Level 3）。未設定時は開発用フルツリー向けに設定で判定。
 *
 * @return string client|authority|''
 */
function crb_package_variant() {
	if ( ! defined( 'CRB_PACKAGE_VARIANT' ) ) {
		return '';
	}
	$variant = sanitize_key( (string) CRB_PACKAGE_VARIANT );
	return in_array( $variant, array( 'client', 'authority' ), true ) ? $variant : '';
}

/**
 * フィード/RSS/取り込みクライアント機能を起動するか。
 *
 * @return bool
 */
function crb_is_client_app_enabled() {
	$variant = crb_package_variant();
	if ( 'client' === $variant ) {
		return true;
	}
	if ( 'authority' === $variant ) {
		return false;
	}
	return ! crb_license_is_authoritative_server();
}

/**
 * 組み込みライセンスサーバー（キー発行・REST API）を起動するか。
 *
 * @return bool
 */
function crb_is_license_server_app_enabled() {
	$variant = crb_package_variant();
	if ( 'authority' === $variant ) {
		return true;
	}
	if ( 'client' === $variant ) {
		return false;
	}
	return crb_license_is_authoritative_server();
}

/**
 * クライアントサイト向けの推奨 API ベース URL（ZIP 既定または filter）。
 *
 * @return string
 */
function crb_license_suggested_client_api_base() {
	if ( defined( 'CRB_LICENSE_API_BASE' ) ) {
		return crb_license_normalize_site_url( (string) CRB_LICENSE_API_BASE );
	}
	$suggested = apply_filters( 'crb_license_suggested_api_base', '' );
	if ( '' !== (string) $suggested ) {
		return crb_license_normalize_site_url( (string) $suggested );
	}
	if ( function_exists( 'crb_package_variant' ) && 'client' === crb_package_variant() ) {
		$default = defined( 'CRB_LICENSE_DEFAULT_CLIENT_API_BASE' )
			? (string) CRB_LICENSE_DEFAULT_CLIENT_API_BASE
			: 'https://123789.jp/custom-rss-builder';
		/**
		 * クライアント配布 ZIP の既定認証サーバー URL。
		 *
		 * @param string $default Default authority site URL.
		 */
		$default = (string) apply_filters( 'crb_license_default_client_api_base', $default );
		return crb_license_normalize_site_url( $default );
	}
	return '';
}

/**
 * 正本サーバーとして運用分離の設定が整っているか。
 *
 * @return bool
 */
function crb_license_authority_ops_ready() {
	if ( ! crb_license_is_authoritative_server() ) {
		return false;
	}
	return crb_license_is_embedded_mode();
}

/**
 * クライアントとして REST 接続の前提が揃っているか（キー有効化は別）。
 *
 * @return bool
 */
function crb_license_client_ops_connection_ready() {
	if ( crb_license_is_authoritative_server() ) {
		return false;
	}
	if ( ! crb_license_uses_remote_api() ) {
		return false;
	}
	$base = crb_license_get_configured_api_base();
	if ( '' === $base || $base === crb_license_site_url() ) {
		return false;
	}
	return '' !== trim( crb_license_api_secret() );
}

/**
 * @return string
 */
function crb_license_get_configured_api_base() {
	if ( crb_license_ui_is_client_screen() && crb_license_client_has_valid_remote_base() ) {
		return crb_license_client_remote_base_url();
	}

	$settings = crb_license_get_settings();
	if ( ! empty( $settings['api_base'] ) ) {
		$base = crb_license_normalize_site_url( (string) $settings['api_base'] );
		if ( '' !== $base ) {
			return $base;
		}
	}
	if ( defined( 'CRB_LICENSE_API_BASE' ) ) {
		$base = crb_license_normalize_site_url( (string) CRB_LICENSE_API_BASE );
		if ( '' !== $base ) {
			return $base;
		}
	}
	$suggested = crb_license_suggested_client_api_base();
	if ( '' !== $suggested ) {
		return $suggested;
	}
	return crb_license_site_url();
}

/**
 * ライセンス画面を「お客様サイト向け」の簡易表示にするか（正本・開発用の組み込み UI は出さない）。
 *
 * @return bool
 */
function crb_license_ui_is_client_screen() {
	return crb_is_client_app_enabled() && ! crb_license_is_local_server();
}

/**
 * クライアントが接続すべきライセンスサーバー（123789.jp 等）の URL。
 *
 * @return string
 */
function crb_license_client_remote_base_url() {
	$settings = crb_license_get_settings();
	if ( ! empty( $settings['api_base'] ) ) {
		$base = crb_license_normalize_site_url( (string) $settings['api_base'] );
		if ( '' !== $base && $base !== crb_license_site_url() ) {
			return $base;
		}
	}
	if ( defined( 'CRB_LICENSE_API_BASE' ) ) {
		$base = crb_license_normalize_site_url( (string) CRB_LICENSE_API_BASE );
		if ( '' !== $base ) {
			return $base;
		}
	}
	return crb_license_suggested_client_api_base();
}

/**
 * クライアントの API ベースが自サイト URL ではなくサーバー向きか。
 *
 * @return bool
 */
function crb_license_client_has_valid_remote_base() {
	$base = crb_license_client_remote_base_url();
	return '' !== $base && $base !== crb_license_site_url();
}

/**
 * 配布 client ZIP に認証情報が埋め込まれているか（利用者の手入力不要）。
 *
 * @return bool
 */
function crb_license_client_connection_is_baked_in() {
	return defined( 'CRB_LICENSE_API_SECRET' ) && '' !== (string) CRB_LICENSE_API_SECRET;
}

/**
 * クライアントで認証サーバー URL / Secret の手入力がまだ必要か（未ライセンス時のみ UI 表示）。
 *
 * @return bool
 */
function crb_license_client_needs_manual_server_config() {
	if ( ! crb_license_ui_is_client_screen() ) {
		return false;
	}
	if ( crb_license_client_connection_is_baked_in() ) {
		return false;
	}
	if ( ! crb_license_client_has_valid_remote_base() ) {
		return true;
	}
	return '' === trim( crb_license_api_secret() );
}

/**
 * WordPress REST の permission_callback 失敗時などの汎用文言か。
 *
 * @param string $message Message.
 * @return bool
 */
function crb_license_is_rest_permission_denied_message( $message ) {
	$message = (string) $message;
	$needles = array(
		'その操作を実行する権限がありません',
		'Sorry, you are not allowed',
		'rest_cannot',
	);
	foreach ( $needles as $needle ) {
		if ( false !== strpos( $message, $needle ) ) {
			return true;
		}
	}
	return false;
}

/**
 * リモート API の HTTP エラー文言を利用者向けに変換。
 *
 * @param int    $http_code HTTP status.
 * @param string $message   Raw message.
 * @return string
 */
function crb_license_map_remote_http_message( $http_code, $message ) {
	$message = (string) $message;
	if ( crb_license_is_rest_permission_denied_message( $message )
		|| ( 403 === (int) $http_code && false !== strpos( $message, 'API Secret' ) )
		|| ( 403 === (int) $http_code && false !== strpos( $message, 'crb_ls_bad_secret' ) ) ) {
		if ( crb_license_ui_is_client_screen() ) {
			if ( function_exists( 'crb_license_client_connection_is_baked_in' ) && crb_license_client_connection_is_baked_in() ) {
				return __( '認証サーバーに接続できませんでした。しばらくしてから再度お試しください。解決しない場合は販売元までお問い合わせください。', 'custom-rss-builder' );
			}
			return __( 'プラグインの認証設定が未完了です。販売元にお問い合わせください。', 'custom-rss-builder' );
		}
		return __( 'API Secret がライセンスサーバーと一致しません。サーバー側のライセンス設定画面で Secret を確認してください。', 'custom-rss-builder' );
	}
	return $message;
}

/**
 * 管理画面の警告文（クライアント向けに API Secret 表記を避ける）。
 *
 * @param string $raw Stored or API message.
 * @return string
 */
function crb_license_admin_notice_message( $raw ) {
	$raw = (string) $raw;
	$client_msg = __( '認証の設定が未完了です。ライセンス画面を開き、必要な場合のみ認証コードを保存してください。', 'custom-rss-builder' );
	if ( ! crb_license_ui_is_client_screen() ) {
		return crb_license_map_remote_http_message( 403, $raw );
	}
	if ( $raw === $client_msg || false !== strpos( $raw, '認証の設定が未完了' ) ) {
		return $client_msg;
	}
	if ( false !== strpos( $raw, 'API Secret' ) || crb_license_is_rest_permission_denied_message( $raw ) ) {
		return crb_license_map_remote_http_message( 403, $raw );
	}
	if ( false !== strpos( $raw, '認証コードが正しくない' ) ) {
		return $raw;
	}
	return $raw;
}

/**
 * 一時エラーで usable だけ false になった保存値を修復する（開発用・組み込み正本のみ）。
 *
 * リモート接続のクライアントでは正本への check を信頼し、ローカル修復はしない。
 */
function crb_license_repair_stale_unusable() {
	if ( crb_license_uses_remote_api() ) {
		return;
	}

	$settings = crb_license_get_settings();
	$key      = trim( (string) ( $settings['license_key'] ?? '' ) );
	if ( '' === $key || ! empty( $settings['usable'] ) ) {
		return;
	}

	$status = sanitize_key( (string) ( $settings['status'] ?? 'inactive' ) );
	if ( 'active' !== $status ) {
		return;
	}

	if ( crb_license_uses_remote_api() && '' === trim( crb_license_api_secret() ) ) {
		return;
	}

	$message = (string) ( $settings['message'] ?? '' );
	if ( '' === $message || crb_license_is_transient_license_message( $message ) ) {
		crb_license_update_settings(
			array(
				'usable'  => true,
				'message' => '',
			)
		);
	}
}

/**
 * @param string $message Stored message.
 * @return bool
 */
function crb_license_is_transient_license_message( $message ) {
	$message = (string) $message;
	$needles = array(
		'API Secret',
		'認証の設定が未完了',
		'認証コードが正しくない',
		'その操作を実行する権限がありません',
		'接続できません',
		'通信に失敗',
		'応答が不正',
		'ライセンスサーバーとの通信',
		'ライセンスが無効',
	);
	foreach ( $needles as $needle ) {
		if ( false !== strpos( $message, $needle ) ) {
			return true;
		}
	}
	return false;
}

/**
 * 正本サーバーとライセンス状態を同期（古いキャッシュのまま Pro 利用を防ぐ）。
 *
 * @param int $max_age_seconds 0 で常に再確認。省略時もクライアントは常に 0（時間キャッシュなし）。
 */
function crb_license_sync_if_stale( $max_age_seconds = null ) {
	static $synced_this_request = false;

	if ( $synced_this_request ) {
		return;
	}

	if ( ! crb_is_client_app_enabled() ) {
		return;
	}

	$settings = crb_license_get_settings();
	$key      = trim( (string) ( $settings['license_key'] ?? '' ) );
	if ( '' === $key ) {
		return;
	}

	if ( null === $max_age_seconds ) {
		// クライアントは checked_at によるスキップを使わない（毎リクエスト正本へ再確認）。
		$max_age_seconds = 0;
	}

	$checked = (int) ( $settings['checked_at'] ?? 0 );
	if ( $max_age_seconds > 0 && $checked > 0 && ( time() - $checked ) < $max_age_seconds ) {
		return;
	}

	if ( crb_license_uses_remote_api() && '' === trim( crb_license_api_secret() ) ) {
		return;
	}

	$synced_this_request = true;

	if ( crb_license_has_bundled_server() && function_exists( 'crb_ls_get_manager' ) && ! crb_license_uses_remote_api() ) {
		$manager = crb_ls_get_manager();
		if ( $manager ) {
			$row = $manager->check( $key, crb_license_site_url() );
			if ( is_wp_error( $row ) ) {
				crb_license_apply_remote_result( $row );
			} else {
				crb_license_apply_remote_result(
					array(
						'success' => true,
						'license' => $row,
					)
				);
			}
		}
		return;
	}

	if ( class_exists( 'Custom_RSS_Builder_License_Client' ) ) {
		$client = new Custom_RSS_Builder_License_Client();
		$client->remote_check( $key );
	}
}

/**
 * ライセンス画面表示時にサーバーへ再確認（Secret 設定後の復旧用）。
 */
function crb_license_refresh_on_settings_page() {
	if ( ! crb_license_ui_is_client_screen() && ! crb_license_uses_remote_api() ) {
		return;
	}

	crb_license_sync_if_stale( 0 );
	if ( ! crb_license_uses_remote_api() ) {
		crb_license_repair_stale_unusable();
	}
}

/**
 * @param int $slot_count Slot count.
 * @return string
 */
function crb_license_format_slot_range_text( $slot_count ) {
	$slot_count = max( 1, (int) $slot_count );
	$max_label  = '{%' . $slot_count . '%}';

	return sprintf(
		/* translators: 1: slot count, 2: first slot token, 3: last slot token */
		__( '%1$d つ（%2$s〜%3$s）', 'custom-rss-builder' ),
		$slot_count,
		'{%1%}',
		$max_label
	);
}

/**
 * @return string
 */
function crb_license_connection_mode_label() {
	if ( crb_license_ui_is_client_screen() ) {
		return __( 'オンライン認証', 'custom-rss-builder' );
	}
	return crb_license_uses_remote_api()
		? __( 'REST（分離）', 'custom-rss-builder' )
		: __( '組み込み（同一DB）', 'custom-rss-builder' );
}

/**
 * 無料ライセンスを発行し、このサイトで有効化（同一 WordPress 内・HTTP 不要）。
 *
 * @return array<string,mixed>|WP_Error
 */
function crb_license_setup_free_local() {
	if ( ! crb_license_is_authoritative_server() ) {
		return new WP_Error(
			'crb_license_not_authority',
			__( 'このサイトはライセンス正本サーバーではありません。ライセンスサーバーで発行したキーを REST 接続で有効化してください。', 'custom-rss-builder' )
		);
	}
	if ( ! crb_license_has_bundled_server() ) {
		return new WP_Error(
			'crb_license_not_local',
			__( '組み込みライセンスサーバーがありません。', 'custom-rss-builder' )
		);
	}

	$manager = crb_ls_get_manager();
	if ( ! $manager ) {
		return new WP_Error(
			'crb_license_no_manager',
			__( 'ライセンスサーバーを初期化できません。', 'custom-rss-builder' )
		);
	}

	$site  = crb_license_site_url();
	$email = '';
	$user  = wp_get_current_user();
	if ( $user instanceof WP_User && is_email( $user->user_email ) ) {
		$email = sanitize_email( $user->user_email );
	}
	if ( '' === $email ) {
		$email = sanitize_email( (string) get_option( 'admin_email' ) );
	}

	$key = trim( (string) ( crb_license_get_settings()['license_key'] ?? '' ) );
	if ( '' !== $key ) {
		$row = $manager->get_by_key( $key );
		if ( $row && crb_ls_license_is_usable( $row ) ) {
			$activated = $manager->activate( $key, $site );
			if ( is_wp_error( $activated ) ) {
				return $activated;
			}
			$result = array(
				'success' => true,
				'license' => $manager->format_public_license( $activated ),
			);
			crb_license_apply_remote_result( $result );
			return $result;
		}
	}

	$row = $manager->create_license( $email, 'free' );
	if ( is_wp_error( $row ) ) {
		return $row;
	}

	$key       = (string) ( $row['license_key'] ?? '' );
	$activated = $manager->activate( $key, $site );
	if ( is_wp_error( $activated ) ) {
		return $activated;
	}

	crb_license_update_settings( array( 'license_key' => $key ) );

	$result = array(
		'success' => true,
		'license' => $manager->format_public_license( $activated ),
	);
	crb_license_apply_remote_result( $result );

	return $result;
}

/**
 * テスト用: 登録済みの無料キーへ切り替え（Pro から free へ戻す）。
 *
 * @return array<string,mixed>|WP_Error
 */
function crb_license_switch_to_free_local() {
	if ( ! crb_license_is_authoritative_server() ) {
		return new WP_Error(
			'crb_license_not_authority',
			__( 'このサイトはライセンス正本サーバーではありません。', 'custom-rss-builder' )
		);
	}
	if ( ! crb_license_has_bundled_server() ) {
		return new WP_Error(
			'crb_license_not_local',
			__( '組み込みライセンスサーバーがありません。', 'custom-rss-builder' )
		);
	}

	$manager = crb_ls_get_manager();
	if ( ! $manager ) {
		return new WP_Error(
			'crb_license_no_manager',
			__( 'ライセンスサーバーを初期化できません。', 'custom-rss-builder' )
		);
	}

	$email = '';
	$user  = wp_get_current_user();
	if ( $user instanceof WP_User && is_email( $user->user_email ) ) {
		$email = sanitize_email( $user->user_email );
	}
	if ( '' === $email ) {
		$email = sanitize_email( (string) get_option( 'admin_email' ) );
	}

	$row = $manager->register_free( $email );
	if ( is_wp_error( $row ) ) {
		return $row;
	}

	$key       = (string) ( $row['license_key'] ?? '' );
	$activated = $manager->activate( $key, crb_license_site_url() );
	if ( is_wp_error( $activated ) ) {
		return $activated;
	}

	crb_license_update_settings( array( 'license_key' => $key ) );

	$result = array(
		'success' => true,
		'license' => $manager->format_public_license( $activated ),
	);
	crb_license_apply_remote_result( $result );

	return $result;
}

/**
 * テスト用: プラグイン側のライセンス設定だけ削除（次回 ensure で無料を再発行）。
 */
function crb_license_clear_settings_for_test() {
	delete_option( CRB_LICENSE_OPTION_KEY );
	set_transient( 'crb_license_skip_ensure_once', '1', MINUTE_IN_SECONDS );
}

/**
 * Pro 無効化後、リモートクライアントを無料プランへ自動復帰する。
 *
 * @return array<string,mixed>|WP_Error|null
 */
function crb_license_try_fallback_to_free_remote() {
	static $running = false;

	if ( $running ) {
		return null;
	}

	if ( ! crb_is_client_app_enabled() || ! crb_license_uses_remote_api() ) {
		return null;
	}

	if ( crb_license_settings_are_usable() ) {
		return null;
	}

	if ( '' === trim( crb_license_api_secret() ) ) {
		return null;
	}

	if ( ! class_exists( 'Custom_RSS_Builder_License_Client' ) ) {
		return null;
	}

	$running = true;
	$client  = new Custom_RSS_Builder_License_Client();
	$result  = $client->issue_free( '' );
	$running = false;

	return $result;
}

/**
 * リクエスト前にライセンスを整える（未設定なら同一サイトで無料を自動発行）。
 */
function crb_license_prepare_request() {
	crb_license_ensure_active();
}

/**
 * 設定オプション上でライセンスが有効か（get_state を呼ばない）。
 *
 * @return bool
 */
function crb_license_settings_are_usable() {
	$settings = crb_license_get_settings();
	return '' !== trim( (string) ( $settings['license_key'] ?? '' ) ) && ! empty( $settings['usable'] );
}

/**
 * ライセンス未設定を許さず、組み込みサーバーでは無料を自動有効化する。
 */
function crb_license_ensure_active() {
	static $running = false;

	if ( ! crb_is_client_app_enabled() ) {
		return;
	}

	if ( get_transient( 'crb_license_skip_ensure_once' ) ) {
		return;
	}

	if ( $running ) {
		return;
	}

	if ( crb_license_settings_are_usable() ) {
		return;
	}

	if ( ! crb_license_is_embedded_mode() || ! crb_license_is_authoritative_server() ) {
		return;
	}

	if ( ! crb_license_has_bundled_server() ) {
		return;
	}

	$running = true;
	$result  = crb_license_setup_free_local();
	$running = false;

	if ( ! is_wp_error( $result ) ) {
		crb_license_apply_remote_result( $result );
		return;
	}

	if ( crb_license_uses_remote_api() && '' !== trim( crb_license_api_secret() ) ) {
		crb_license_try_fallback_to_free_remote();
	}
}

/**
 * @deprecated 1.0.1 Use crb_license_ensure_active().
 */
function crb_license_maybe_ensure_local_free() {
	crb_license_ensure_active();
}

/**
 * 利用可能なスロット数（{%1%} から連番）。
 *
 * @return int
 */
function crb_license_get_record_slot_count() {
	if ( ! crb_license_get_state()['usable'] ) {
		return (int) CRB_LICENSE_FREE_SLOT_LIMIT;
	}
	if ( 'pro' === crb_license_get_state()['plan'] ) {
		return defined( 'CRB_RECORD_SLOT_COUNT' ) ? (int) CRB_RECORD_SLOT_COUNT : 20;
	}
	return (int) CRB_LICENSE_FREE_SLOT_LIMIT;
}

/**
 * 0 始ままりの最大スロット index（{%1%}=0）。
 *
 * @return int
 */
function crb_license_get_max_slot_index() {
	return max( 0, crb_license_get_record_slot_count() - 1 );
}

/**
 * @param int $slot_index 0-based slot index.
 * @return bool
 */
function crb_license_can_use_slot_index( $slot_index ) {
	return (int) $slot_index <= crb_license_get_max_slot_index();
}

/**
 * 無料プラン向けに {%4%} 以降の CSS 設定をクリア。
 *
 * @param array<string, string> $css CSS config.
 * @return array<string, string>
 */
function crb_license_apply_slot_limits( array $css ) {
	$max_index = crb_license_get_max_slot_index();
	$full_max  = defined( 'CRB_RECORD_SLOT_COUNT' ) ? (int) CRB_RECORD_SLOT_COUNT - 1 : 19;

	if ( $max_index >= $full_max ) {
		return $css;
	}

	foreach ( crb_extra_slot_storage_map() as $index => $meta ) {
		if ( (int) $index > $max_index ) {
			$css[ $meta['config_key'] ] = '';
			$css[ $meta['mode_key'] ]   = $meta['default_mode'];
			$attr_key                   = 'slot_attr_' . ( (int) $index + 1 );
			if ( isset( $css[ $attr_key ] ) ) {
				$css[ $attr_key ] = '';
			}
		}
	}

	return $css;
}

/**
 * @param array<string, int> $mapping RSS mapping.
 * @return array<string, int>
 */
function crb_license_clamp_mapping( array $mapping ) {
	$max = crb_license_get_max_slot_index();
	foreach ( array( 'title', 'link', 'description' ) as $key ) {
		if ( ! isset( $mapping[ $key ] ) ) {
			continue;
		}
		$value = (int) $mapping[ $key ];
		if ( $value > $max ) {
			$mapping[ $key ] = $max;
		}
	}
	return $mapping;
}

/**
 * @param array<string, mixed> $feed_data Feed payload.
 * @return array<string, mixed>
 */
function crb_license_apply_feed_limits( array $feed_data ) {
	if ( isset( $feed_data['css'] ) && is_array( $feed_data['css'] ) ) {
		$feed_data['css'] = crb_license_apply_slot_limits( $feed_data['css'] );
	}
	if ( isset( $feed_data['mapping'] ) && is_array( $feed_data['mapping'] ) ) {
		$feed_data['mapping'] = crb_license_clamp_mapping( $feed_data['mapping'] );
	}
	if ( isset( $feed_data['ai'] ) && function_exists( 'crb_sanitize_ai_transform_settings' ) ) {
		$feed_data['ai'] = crb_sanitize_ai_transform_settings( $feed_data['ai'], true );
	}
	return $feed_data;
}

/**
 * @return array{plan:string,status:string,usable:bool,message:string,license_key:string}
 */
function crb_license_get_state() {
	static $repaired = false;

	crb_license_ensure_active();

	if ( function_exists( 'crb_license_sync_if_stale' ) ) {
		crb_license_sync_if_stale();
	}

	if ( ! $repaired && function_exists( 'crb_license_repair_stale_unusable' ) && ! crb_license_uses_remote_api() ) {
		$repaired = true;
		crb_license_repair_stale_unusable();
	}

	$settings = crb_license_get_settings();
	$key      = trim( (string) ( $settings['license_key'] ?? '' ) );

	if ( '' === $key ) {
		return array(
			'plan'        => 'free',
			'status'      => 'inactive',
			'usable'      => false,
			'message'     => crb_license_ui_is_client_screen()
				? __( 'ライセンスが有効ではありません。「ライセンス」画面でキーを入力し、有効化してください。', 'custom-rss-builder' )
				: __( 'ライセンスを有効化できません。ライセンス画面で Pro キーを入力するか、ライセンスサーバーで無料登録してください。', 'custom-rss-builder' ),
			'license_key' => '',
		);
	}

	if ( empty( $settings['usable'] ) ) {
		return array(
			'plan'        => sanitize_key( (string) ( $settings['plan'] ?? 'free' ) ),
			'status'      => sanitize_key( (string) ( $settings['status'] ?? 'inactive' ) ),
			'usable'      => false,
			'message'     => (string) ( $settings['message'] ?? '' ) !== ''
				? (string) $settings['message']
				: (
					crb_license_ui_is_client_screen()
					? __( 'ライセンスが有効ではありません。「ライセンス」画面でキーを入力し、有効化してください。', 'custom-rss-builder' )
					: __( 'ライセンスを有効化できません。ライセンス画面で Pro キーを入力するか、ライセンスサーバーで無料登録してください。', 'custom-rss-builder' )
				),
			'license_key' => $key,
		);
	}

	return array(
		'plan'        => sanitize_key( (string) ( $settings['plan'] ?? 'free' ) ),
		'status'      => sanitize_key( (string) ( $settings['status'] ?? '' ) ),
		'usable'      => true,
		'message'     => (string) ( $settings['message'] ?? '' ),
		'license_key' => $key,
	);
}

/**
 * @param string $feature Feature slug.
 * @return bool
 */
function crb_license_can( $feature ) {
	$state = crb_license_get_state();
	if ( ! $state['usable'] ) {
		return false;
	}

	$plan = $state['plan'];
	if ( 'pro' === $plan ) {
		$feed_count = crb_license_feed_count();
		switch ( $feature ) {
			case 'create_feed':
				return $feed_count < (int) CRB_LICENSE_PRO_FEED_LIMIT;
			case 'save':
				return $feed_count <= (int) CRB_LICENSE_PRO_FEED_LIMIT;
			default:
				return true;
		}
	}

	if ( 'free' !== $plan ) {
		return false;
	}

	switch ( $feature ) {
		case 'create_feed':
			return crb_license_feed_count() < (int) CRB_LICENSE_FREE_FEED_LIMIT;
		case 'ai_transform':
			return false;
		case 'rss':
		case 'discover':
		case 'preview':
		case 'save':
		default:
			return true;
	}
}

/**
 * @return int
 */
function crb_license_feed_count() {
	if ( ! crb_is_client_app_enabled() || ! function_exists( 'crb_plugin' ) ) {
		return 0;
	}
	$plugin = crb_plugin();
	if ( ! is_object( $plugin ) || ! isset( $plugin->feed_manager ) ) {
		return 0;
	}
	$feeds = $plugin->feed_manager->get_feeds();
	return is_array( $feeds ) ? count( $feeds ) : 0;
}

/**
 * @return int
 */
function crb_license_free_feed_count() {
	return crb_license_feed_count();
}

/**
 * @param string $plan Plan slug.
 * @return int
 */
function crb_license_feed_limit_for_plan( $plan ) {
	switch ( sanitize_key( (string) $plan ) ) {
		case 'pro':
			return (int) CRB_LICENSE_PRO_FEED_LIMIT;
		case 'free':
			return (int) CRB_LICENSE_FREE_FEED_LIMIT;
		default:
			return 0;
	}
}

/**
 * @return string
 */
function crb_license_denied_message( $feature ) {
	if ( ! crb_license_get_state()['usable'] ) {
		if ( crb_license_is_local_server() ) {
			return __( 'ライセンスが有効ではありません。ライセンス画面を開き直すか、「無料を今すぐ再発行」を実行してください。', 'custom-rss-builder' );
		}
		if ( function_exists( 'crb_license_ui_is_client_screen' ) && crb_license_ui_is_client_screen() ) {
			return crb_license_client_activation_guidance_message();
		}
		return __( 'ライセンスが有効ではありません。ライセンス画面でキーを有効化してください。', 'custom-rss-builder' );
	}

	$state = crb_license_get_state();
	$plan  = sanitize_key( (string) ( $state['plan'] ?? '' ) );

	switch ( $feature ) {
		case 'create_feed':
			if ( 'pro' === $plan ) {
				return sprintf(
					/* translators: %d: max feeds on pro plan */
					__( 'Pro プランではフィードは %d 件までです。', 'custom-rss-builder' ),
					(int) CRB_LICENSE_PRO_FEED_LIMIT
				);
			}
			return sprintf(
				/* translators: %d: max feeds on free plan */
				__( '無料プランではフィードは %d 件までです。', 'custom-rss-builder' ),
				(int) CRB_LICENSE_FREE_FEED_LIMIT
			);
		case 'save':
			if ( 'pro' === $plan && crb_license_feed_count() > (int) CRB_LICENSE_PRO_FEED_LIMIT ) {
				return sprintf(
					/* translators: %d: max feeds on pro plan */
					__( 'Pro プランではフィードは %d 件までです。上限を超えているため保存できません。', 'custom-rss-builder' ),
					(int) CRB_LICENSE_PRO_FEED_LIMIT
				);
			}
			return __( 'この操作は現在のプランでは利用できません。', 'custom-rss-builder' );
		case 'slot_limit':
			return sprintf(
				/* translators: %d: max slots on free plan */
				__( '無料プランではスロットは %d つ（{%%1%%}〜{%%3%%}）までです。', 'custom-rss-builder' ),
				CRB_LICENSE_FREE_SLOT_LIMIT
			);
		case 'ai_transform':
			return __( 'AI テキスト変換は Pro プラン専用です。Gemini API キーはライセンス画面で設定してください。', 'custom-rss-builder' );
		default:
			return __( 'この操作は現在のプランでは利用できません。', 'custom-rss-builder' );
	}
}

/**
 * 現在プランで許可される自動取り込みの最短間隔（時間）。0 はオフ専用。
 *
 * @return int
 */
function crb_license_import_schedule_min_hours() {
	$global_min = defined( 'CRB_IMPORT_SCHEDULE_MIN_HOURS' ) ? (int) CRB_IMPORT_SCHEDULE_MIN_HOURS : 1;

	if ( ! function_exists( 'crb_license_get_state' ) ) {
		return $global_min;
	}

	$state = crb_license_get_state();
	if ( empty( $state['usable'] ) ) {
		return $global_min;
	}

	if ( 'free' === sanitize_key( (string) ( $state['plan'] ?? '' ) ) ) {
		return (int) CRB_LICENSE_FREE_IMPORT_SCHEDULE_MIN_HOURS;
	}

	return $global_min;
}

/**
 * Pro プランのスロット数。
 *
 * @return int
 */
function crb_license_pro_slot_count() {
	return defined( 'CRB_RECORD_SLOT_COUNT' ) ? (int) CRB_RECORD_SLOT_COUNT : 20;
}

/**
 * @param string $plan Plan slug.
 * @return string
 */
function crb_license_plan_label( $plan ) {
	switch ( sanitize_key( (string) $plan ) ) {
		case 'pro':
			return __( 'Pro', 'custom-rss-builder' );
		case 'free':
			return __( '無料', 'custom-rss-builder' );
		default:
			return '—';
	}
}

/**
 * ライセンス画面のプラン表示（未登録キーは「未登録」）。
 *
 * @param array{plan?:string,usable?:bool,license_key?:string} $state State from crb_license_get_state().
 * @return string
 */
function crb_license_plan_label_for_state( array $state ) {
	if ( function_exists( 'crb_license_ui_is_client_screen' ) && crb_license_ui_is_client_screen() ) {
		$key = trim( (string) ( $state['license_key'] ?? '' ) );
		if ( '' === $key ) {
			return __( '未登録', 'custom-rss-builder' );
		}
	}

	return crb_license_plan_label( (string) ( $state['plan'] ?? 'free' ) );
}

/**
 * @param string $status Status slug.
 * @return string
 */
/**
 * 状態テーブル用ラベル（保存値と利用可の矛盾を吸収）。
 *
 * @param array{plan?:string,status?:string,usable?:bool} $state State from crb_license_get_state().
 * @return string
 */
function crb_license_status_label_for_display( array $state ) {
	$settings = crb_license_get_settings();
	$status   = sanitize_key( (string) ( $state['status'] ?? $settings['status'] ?? '' ) );
	$key      = trim( (string) ( $state['license_key'] ?? $settings['license_key'] ?? '' ) );

	if ( empty( $state['usable'] ) && '' !== $key && 'active' === $status ) {
		$message = (string) ( $settings['message'] ?? '' );
		if ( '' !== $message && crb_license_is_transient_license_message( $message ) ) {
			return __( '要確認（認証設定）', 'custom-rss-builder' );
		}
	}

	return crb_license_status_label( $status );
}

function crb_license_status_label( $status ) {
	switch ( sanitize_key( (string) $status ) ) {
		case 'active':
			return __( '有効', 'custom-rss-builder' );
		case 'past_due':
			return __( '支払い遅延（猶予中）', 'custom-rss-builder' );
		case 'expired':
			return __( '期限切れ', 'custom-rss-builder' );
		case 'cancelled':
			return __( '解約', 'custom-rss-builder' );
		case 'inactive':
			return __( '無効', 'custom-rss-builder' );
		default:
			return '' !== (string) $status ? (string) $status : '—';
	}
}

/**
 * Pro 月額（表示用・税込）。
 *
 * @return string
 */
function crb_pro_monthly_price_label() {
	return __( '月額 3,300 円（税込）', 'custom-rss-builder' );
}

/**
 * 初期設定代行（2 回目以降・表示用・税込）。
 *
 * @return string
 */
function crb_pro_setup_repeat_price_label() {
	return __( '1,100 円（税込）／回', 'custom-rss-builder' );
}

/**
 * プラン比較表（ライセンス画面用）。
 *
 * @return array<int, array{label:string, free:string, pro:string}>
 */
function crb_license_plan_comparison_rows() {
	$pro_slots = crb_license_pro_slot_count();

	return array(
		array(
			'label' => __( 'フィード数', 'custom-rss-builder' ),
			'free'  => sprintf(
				/* translators: %d: max feeds */
				__( '%d 件まで', 'custom-rss-builder' ),
				(int) CRB_LICENSE_FREE_FEED_LIMIT
			),
			'pro'   => sprintf(
				/* translators: %d: max feeds on pro plan */
				__( '%d 件まで', 'custom-rss-builder' ),
				(int) CRB_LICENSE_PRO_FEED_LIMIT
			),
		),
		array(
			'label' => __( 'スロット', 'custom-rss-builder' ),
			'free'  => crb_license_format_slot_range_text( (int) CRB_LICENSE_FREE_SLOT_LIMIT ),
			'pro'   => crb_license_format_slot_range_text( (int) $pro_slots ),
		),
		array(
			'label' => __( 'RSS 配信', 'custom-rss-builder' ),
			'free'  => '○',
			'pro'   => '○',
		),
		array(
			'label' => __( '抽出・プレビュー・保存', 'custom-rss-builder' ),
			'free'  => '○',
			'pro'   => '○',
		),
		array(
			'label' => __( '投稿取り込み（手動）', 'custom-rss-builder' ),
			'free'  => '○',
			'pro'   => '○',
		),
		array(
			'label' => __( '自動取り込み（時間指定）', 'custom-rss-builder' ),
			'free'  => '○',
			'pro'   => '○',
		),
		array(
			'label' => __( 'クレジット表示', 'custom-rss-builder' ),
			'free'  => __( 'あり（フッター・RSS・取り込み投稿）', 'custom-rss-builder' ),
			'pro'   => __( 'なし', 'custom-rss-builder' ),
		),
		array(
			'label' => __( 'AI テキスト変換', 'custom-rss-builder' ),
			'free'  => '—',
			'pro'   => __( '○（Gemini API キー要・BYOK）', 'custom-rss-builder' ),
		),
		array(
			'label' => __( '初期設定代行', 'custom-rss-builder' ),
			'free'  => '—',
			'pro'   => sprintf(
				/* translators: %s: price per extra setup e.g. 1,100 円（税込）／回 */
				__( '初回 1 フィード無料（2 回目以降 %s）', 'custom-rss-builder' ),
				crb_pro_setup_repeat_price_label()
			),
		),
	);
}

/**
 * @return string
 */
function crb_license_free_plan_summary() {
	return sprintf(
		/* translators: 1: feed limit, 2: slot limit */
		__( 'フィード %1$d 件・スロット %2$d つ・RSS・抽出・保存・投稿取り込み（手動・自動）', 'custom-rss-builder' ),
		(int) CRB_LICENSE_FREE_FEED_LIMIT,
		(int) CRB_LICENSE_FREE_SLOT_LIMIT
	);
}

/**
 * @return string
 */
function crb_license_pro_plan_summary() {
	return sprintf(
		/* translators: 1: feed limit, 2: pro slot range label */
		__( 'フィード %1$d 件まで・スロット %2$s', 'custom-rss-builder' ),
		(int) CRB_LICENSE_PRO_FEED_LIMIT,
		crb_license_format_slot_range_text( (int) crb_license_pro_slot_count() )
	);
}

/**
 * 管理画面アクセス時の再検証（リモートは get_state 経由で毎回同期）。
 */
function crb_license_maybe_refresh() {
	// ライセンス画面は refresh_on_settings_page が担当（二重チェック防止）。
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'custom-rss-builder-license' === $page ) {
		return;
	}

	crb_license_sync_if_stale();
}

/**
 * @param WP_Error|array<string,mixed> $result API result.
 */
function crb_license_apply_remote_result( $result ) {
	if ( is_wp_error( $result ) ) {
		$key  = trim( (string) ( crb_license_get_settings()['license_key'] ?? '' ) );
		$code = $result->get_error_code();
		// 一時的な通信エラーで usable を落とさない（キーは維持、メッセージのみ）。
		if ( '' !== $key && in_array( $code, array( 'crb_license_unreachable', 'crb_license_bad_json', 'crb_license_http_404', 'crb_license_no_secret', 'crb_license_bad_secret', 'crb_license_http_403' ), true ) ) {
			crb_license_update_settings(
				array(
					'message'    => $result->get_error_message(),
					'checked_at' => time(),
				)
			);
			return;
		}

		$patch = array(
			'usable'     => false,
			'message'    => $result->get_error_message(),
			'checked_at' => time(),
		);
		if ( 'crb_ls_inactive' === $code ) {
			$patch['status'] = 'expired';
		} elseif ( 'crb_ls_not_found' === $code ) {
			$patch['status'] = 'inactive';
		} elseif ( 'crb_ls_site_mismatch' === $code ) {
			$patch['status'] = 'inactive';
		}
		crb_license_update_settings( $patch );
		return;
	}

	$license = isset( $result['license'] ) && is_array( $result['license'] ) ? $result['license'] : array();
	crb_license_update_settings(
		array(
			'plan'       => sanitize_key( (string) ( $license['plan'] ?? 'free' ) ),
			'status'     => sanitize_key( (string) ( $license['status'] ?? '' ) ),
			'usable'     => ! empty( $license['usable'] ),
			'message'    => '',
			'checked_at' => time(),
		)
	);
}
