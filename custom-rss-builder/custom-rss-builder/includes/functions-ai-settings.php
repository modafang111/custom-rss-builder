<?php
/**
 * クライアントサイトの AI 連携設定（Gemini API キーは各サイトが保持）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_AI_OPTION_KEY', 'crb_ai_settings' );

/**
 * @return array<string, string>
 */
function crb_default_ai_settings() {
	return array(
		'gemini_api_key' => '',
	);
}

/**
 * @return array<string, string>
 */
function crb_get_ai_settings() {
	$raw = get_option( CRB_AI_OPTION_KEY, array() );
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}

	// 旧 OpenAI キー列は読み込まない（Gemini キーを再設定してもらう）。
	unset( $raw['openai_api_key'] );

	$defaults = crb_default_ai_settings();
	$out      = array();
	foreach ( $defaults as $key => $default ) {
		$out[ $key ] = isset( $raw[ $key ] ) ? (string) $raw[ $key ] : (string) $default;
	}
	return $out;
}

/**
 * @param array<string, string> $patch Settings patch.
 */
function crb_update_ai_settings( array $patch ) {
	$current = crb_get_ai_settings();
	$merged  = array_merge( $current, $patch );
	unset( $merged['openai_api_key'] );
	update_option( CRB_AI_OPTION_KEY, $merged, false );
}

/**
 * Gemini API キー（管理画面保存分。定数が定義されている場合はそちらを優先）。
 *
 * @return string
 */
function crb_gemini_api_key() {
	if ( defined( 'CRB_GEMINI_API_KEY' ) && '' !== (string) CRB_GEMINI_API_KEY ) {
		return trim( (string) CRB_GEMINI_API_KEY );
	}
	return trim( (string) ( crb_get_ai_settings()['gemini_api_key'] ?? '' ) );
}

/**
 * @return bool
 */
function crb_gemini_api_key_configured() {
	return '' !== crb_gemini_api_key();
}

/**
 * 管理画面表示用（キー本体は出さない・先頭と末尾のみ）。
 *
 * @return string
 */
function crb_gemini_api_key_masked_display() {
	if ( ! crb_gemini_api_key_configured() ) {
		return '';
	}
	if ( defined( 'CRB_GEMINI_API_KEY' ) && '' !== (string) CRB_GEMINI_API_KEY ) {
		$key = trim( (string) CRB_GEMINI_API_KEY );
		if ( 0 === strpos( $key, 'AQ.' ) ) {
			return 'AQ.••••••••';
		}
		return 'AIza••••••••';
	}

	$key = trim( (string) ( crb_get_ai_settings()['gemini_api_key'] ?? '' ) );
	if ( '' === $key ) {
		return '';
	}

	$len = strlen( $key );
	if ( $len <= 10 ) {
		return str_repeat( '•', $len );
	}

	if ( 0 === strpos( $key, 'AQ.' ) ) {
		return 'AQ.' . substr( $key, 3, 4 ) . str_repeat( '•', min( 12, max( 4, $len - 11 ) ) ) . substr( $key, -4 );
	}

	return substr( $key, 0, 8 ) . str_repeat( '•', min( 12, max( 4, $len - 12 ) ) ) . substr( $key, -4 );
}

/**
 * 管理画面表示用（キー本体は出さない）。
 *
 * @return string
 */
function crb_gemini_api_key_status_label() {
	if ( defined( 'CRB_GEMINI_API_KEY' ) && '' !== (string) CRB_GEMINI_API_KEY ) {
		return __( '設定済み', 'custom-rss-builder' );
	}
	if ( crb_gemini_api_key_configured() ) {
		return __( '設定済み', 'custom-rss-builder' );
	}
	return __( '未設定', 'custom-rss-builder' );
}

/**
 * @return string
 */
function crb_gemini_api_key_status_class() {
	if ( crb_gemini_api_key_configured() ) {
		return 'crb-ai-api-key-status--saved';
	}
	return 'crb-ai-api-key-status--empty';
}

/**
 * Gemini API キーの形式チェック。
 *
 * @param string $key API key.
 * @return bool
 */
function crb_gemini_api_key_is_valid_format( $key ) {
	$key = trim( (string) $key );
	// 従来形式（Google Cloud / AI Studio）と新形式（AQ. プレフィックス）の両方。
	if ( preg_match( '/^AIza[0-9A-Za-z_-]{10,}$/', $key ) ) {
		return true;
	}
	return (bool) preg_match( '/^AQ\.[0-9A-Za-z_-]{20,}$/', $key );
}

/**
 * POST から AI 設定を保存。
 *
 * @return string|WP_Error saved|cleared|unchanged
 */
function crb_save_ai_settings_from_post() {
	if ( defined( 'CRB_GEMINI_API_KEY' ) && '' !== (string) CRB_GEMINI_API_KEY ) {
		return new WP_Error( 'crb_ai_key_locked', __( 'API キーは固定設定のため、管理画面からは変更できません。', 'custom-rss-builder' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! empty( $_POST['gemini_api_key_clear'] ) ) {
		crb_update_ai_settings( array( 'gemini_api_key' => '' ) );
		return 'cleared';
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$key = isset( $_POST['gemini_api_key'] ) ? trim( (string) wp_unslash( $_POST['gemini_api_key'] ) ) : '';
	if ( '' === $key ) {
		return 'unchanged';
	}

	if ( ! crb_gemini_api_key_is_valid_format( $key ) ) {
		return new WP_Error( 'crb_ai_key_invalid', __( 'Gemini API キーの形式が正しくありません（AIza... または AQ.... で始まる Google API キー）。', 'custom-rss-builder' ) );
	}

	crb_update_ai_settings( array( 'gemini_api_key' => sanitize_text_field( $key ) ) );
	return 'saved';
}

/**
 * Pro プランで AI 設定 UI を編集できるか。
 *
 * @return bool
 */
function crb_ai_settings_editable_for_current_license() {
	if ( ! function_exists( 'crb_license_get_state' ) ) {
		return false;
	}
	$state = crb_license_get_state();
	return ! empty( $state['usable'] ) && 'pro' === (string) ( $state['plan'] ?? '' );
}
