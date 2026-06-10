<?php
/**
 * 他プラグインとの互換（当プラグイン側のワークアラウンド）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Download Monitor: WP 6.9.1+ で dlm-reports-upsells が未登録の dlm-reports-app に依存して Notice が出る。
 * 正本サイト等で DLM を併用する場合、空のスタブを先に register して警告を防ぐ。
 */
function crb_compat_register_dlm_reports_app_stub() {
	if ( wp_script_is( 'dlm-reports-app', 'registered' ) ) {
		return;
	}

	wp_register_script( 'dlm-reports-app', false, array(), null, true );
}

add_action( 'admin_enqueue_scripts', 'crb_compat_register_dlm_reports_app_stub', 0 );
add_action( 'wp_enqueue_scripts', 'crb_compat_register_dlm_reports_app_stub', 0 );

/**
 * http:// → https://（同一ホストのみ）。Chrome の「安全でないダウンロードがブロックされました」対策。
 *
 * WordPress の siteurl/home が http のままだと Download Monitor のボタン URL も http になり、
 * HTTPS ページからのダウンロードがブロックされる。
 *
 * @param string $url URL.
 * @return string
 */
function crb_compat_force_https_url( $url ) {
	if ( ! is_string( $url ) || '' === $url || 0 !== strpos( $url, 'http://' ) ) {
		return $url;
	}

	$host      = (string) wp_parse_url( $url, PHP_URL_HOST );
	$site_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	if ( '' !== $host && '' !== $site_host && $host !== $site_host ) {
		return $url;
	}

	return 'https://' . substr( $url, 7 );
}

/**
 * TLS 配信時は home/site URL を https で生成する。
 *
 * @param string      $url     URL.
 * @param string      $path    Path.
 * @param string|null $scheme  Scheme.
 * @param int|null    $blog_id Blog ID.
 * @return string
 */
function crb_compat_home_url_force_https( $url, $path, $scheme, $blog_id ) {
	unset( $path, $scheme, $blog_id );

	if ( is_ssl() ) {
		return set_url_scheme( $url, 'https' );
	}

	return $url;
}

add_filter( 'home_url', 'crb_compat_home_url_force_https', 20, 4 );
add_filter( 'site_url', 'crb_compat_home_url_force_https', 20, 4 );
add_filter( 'content_url', 'crb_compat_force_https_url', 20 );
add_filter( 'plugins_url', 'crb_compat_force_https_url', 20 );

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'dlm_download_get_the_download_link', 'crb_compat_force_https_url', 20 );
	add_filter( 'dlm_download_version_file_url', 'crb_compat_force_https_url', 20 );
}
