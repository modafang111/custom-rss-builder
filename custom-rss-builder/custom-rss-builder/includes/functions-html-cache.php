<?php
/**
 * HTML 取得（キャッシュなし・毎回ネット取得）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 常に false（後方互換）。
 *
 * @param array<string, mixed>|null $feed Unused.
 * @return bool
 */
function crb_html_cache_enabled( $feed = null ) {
	unset( $feed );
	return false;
}

/**
 * @param array<string, mixed>|bool $options Unused.
 * @return array{fresh: bool, store: bool}
 */
function crb_html_fetch_resolve_options( $options ) {
	unset( $options );
	return array(
		'fresh' => true,
		'store' => false,
	);
}

/**
 * 旧 transient の掃除。
 *
 * @param string $url Target URL.
 */
function crb_clear_html_cache( $url ) {
	$url = esc_url_raw( (string) $url );
	if ( '' === $url ) {
		return;
	}
	delete_transient( 'custom_rss_builder_html_' . md5( $url ) );
}
