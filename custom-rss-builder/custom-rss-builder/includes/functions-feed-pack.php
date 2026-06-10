<?php
/**
 * フィード設定パックのエクスポート・インポート。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return int
 */
function crb_feed_pack_version() {
	return 1;
}

/**
 * 保存済みフィード行からエクスポート用パックを組み立てる。
 *
 * @param array<string, mixed> $feed DB に保存されているフィード行。
 * @return array<string, mixed>|WP_Error
 */
function crb_export_feed_pack( array $feed ) {
	$css_raw = is_array( $feed['css'] ?? null ) ? $feed['css'] : array();
	$import  = is_array( $feed['import'] ?? null ) ? $feed['import'] : array();

	$pack = array(
		'pack_version'    => crb_feed_pack_version(),
		'exported_at'     => gmdate( 'c' ),
		'plugin_version'  => defined( 'CRB_VERSION' ) ? (string) CRB_VERSION : '',
		'feed'            => array(
			'name'            => sanitize_text_field( (string) ( $feed['name'] ?? '' ) ),
			'url'             => esc_url_raw( (string) ( $feed['url'] ?? '' ) ),
			'extraction_mode' => function_exists( 'crb_get_feed_extraction_mode' )
				? crb_get_feed_extraction_mode( $feed )
				: 'css',
			'css'             => crb_sanitize_css_config( $css_raw ),
			'import'          => array(
				'post_title_template' => function_exists( 'crb_sanitize_template' )
					? crb_sanitize_template( (string) ( $import['post_title_template'] ?? '' ) )
					: (string) ( $import['post_title_template'] ?? '' ),
				'content_template'    => function_exists( 'crb_sanitize_template' )
					? crb_sanitize_template( (string) ( $import['content_template'] ?? '' ) )
					: (string) ( $import['content_template'] ?? '' ),
			),
		),
	);

	return $pack;
}

/**
 * @param array<string, mixed> $pack crb_export_feed_pack() の戻り値。
 * @return string
 */
function crb_feed_pack_to_json( array $pack ) {
	$json = wp_json_encode(
		$pack,
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
	);
	return is_string( $json ) ? $json : '{}';
}

/**
 * エクスポートファイル名用の安全なスラッグ。
 *
 * @param array<string, mixed> $feed Feed row.
 * @param int                  $feed_id Feed ID.
 * @return string
 */
function crb_feed_pack_export_filename( array $feed, $feed_id ) {
	$name = sanitize_file_name( (string) ( $feed['name'] ?? 'feed' ) );
	if ( '' === $name ) {
		$name = 'feed';
	}
	return 'crb-feed-pack-' . $name . '-' . (int) $feed_id . '.json';
}

/**
 * JSON 文字列をパック配列にデコードし、バージョンを検証する。
 *
 * @param string $json_string Raw JSON.
 * @return array<string, mixed>|WP_Error
 */
function crb_parse_feed_pack_json( $json_string ) {
	$json_string = trim( (string) $json_string );
	if ( '' === $json_string ) {
		return new WP_Error( 'crb_feed_pack_empty', __( 'JSON が空です。', 'custom-rss-builder' ) );
	}

	$decoded = json_decode( $json_string, true );
	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
		return new WP_Error( 'crb_feed_pack_invalid_json', __( 'JSON の形式が正しくありません。', 'custom-rss-builder' ) );
	}

	return crb_validate_feed_pack( $decoded );
}

/**
 * @param array<string, mixed> $pack Decoded pack.
 * @return array<string, mixed>|WP_Error
 */
function crb_validate_feed_pack( array $pack ) {
	$version = isset( $pack['pack_version'] ) ? (int) $pack['pack_version'] : 0;
	if ( $version !== crb_feed_pack_version() ) {
		return new WP_Error(
			'crb_feed_pack_version',
			sprintf(
				/* translators: %d: supported pack version */
				__( '未対応の pack_version です（対応: %d）。', 'custom-rss-builder' ),
				crb_feed_pack_version()
			)
		);
	}

	if ( ! isset( $pack['feed'] ) || ! is_array( $pack['feed'] ) ) {
		return new WP_Error( 'crb_feed_pack_feed', __( 'feed オブジェクトがありません。', 'custom-rss-builder' ) );
	}

	return $pack;
}

/**
 * 検証済みパックを編集フォーム反映用の配列へ変換（DB には書かない）。
 *
 * @param array<string, mixed> $pack crb_validate_feed_pack() 済みのパック。
 * @return array<string, mixed>
 */
function crb_feed_pack_to_form_fields( array $pack ) {
	$feed   = is_array( $pack['feed'] ?? null ) ? $pack['feed'] : array();
	$css    = is_array( $feed['css'] ?? null ) ? $feed['css'] : array();
	$import = is_array( $feed['import'] ?? null ) ? $feed['import'] : array();

	$content_template = function_exists( 'crb_sanitize_template' )
		? crb_sanitize_template( (string) ( $import['content_template'] ?? '' ) )
		: (string) ( $import['content_template'] ?? '' );
	if ( function_exists( 'crb_import_content_template_for_ui' ) ) {
		$content_template = crb_import_content_template_for_ui( $content_template );
	}

	return array(
		'name' => sanitize_text_field( (string) ( $feed['name'] ?? '' ) ),
		'url'  => esc_url_raw( (string) ( $feed['url'] ?? '' ) ),
		'css'  => crb_sanitize_css_config( $css ),
		'import' => array(
			'post_title_template' => function_exists( 'crb_sanitize_template' )
				? crb_sanitize_template( (string) ( $import['post_title_template'] ?? '' ) )
				: (string) ( $import['post_title_template'] ?? '' ),
			'content_template'    => $content_template,
		),
	);
}
