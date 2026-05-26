<?php
/**
 * サニタイズ用ヘルパー。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function crb_sanitize_template( $template ) {
	$template = wp_check_invalid_utf8( (string) $template );
	$template = str_replace( "\0", '', $template );
	$template = preg_replace( '#<script\b[^>]*>[\s\S]*?</script>#iu', '', $template );
	$template = preg_replace( '#<style\b[^>]*>[\s\S]*?</style>#iu', '', $template );
	return trim( $template );
}

function crb_get_template_from_post( $field = 'template' ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$value = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';
	return crb_sanitize_template( $value );
}

/**
 * 投稿取り込み用テンプレート（HTML可）。
 *
 * @param string $field POST field name.
 * @return string
 */
function crb_get_import_template_from_post( $field = 'import_content_template' ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$value = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';
	return crb_sanitize_template( $value );
}

/**
 * 抽出されたリンク文字列を正規化（属性ごと取り込んだ場合の補正）。
 *
 * @param string $url Raw URL.
 * @return string
 */
function crb_normalize_link( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	if ( preg_match( '#^(https?://[^\s"\'<>]+)#i', $url, $matches ) ) {
		return esc_url_raw( $matches[1] );
	}

	return esc_url_raw( $url );
}

/**
 * OS cron 用の共有シークレット（サイトごとに1つ）。
 *
 * @return string
 */
function crb_get_import_secret() {
	$secret = get_option( CRB_OPTION_IMPORT_KEY, '' );
	if ( ! is_string( $secret ) || strlen( $secret ) < 20 ) {
		$secret = wp_generate_password( 32, false, false );
		update_option( CRB_OPTION_IMPORT_KEY, $secret, false );
	}
	return $secret;
}

/**
 * @return string
 */
function crb_get_import_cron_url( $feed_id ) {
	return add_query_arg(
		array(
			'crb_run_import' => '1',
			'feed_id'        => (int) $feed_id,
			'key'            => crb_get_import_secret(),
		),
		home_url( '/' )
	);
}
