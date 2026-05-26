<?php
/**
 * 外部HTML取得とキャッシュ。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_HTML_Fetcher {

	public function fetch_html( $url, $force_refresh = false ) {
		$url = esc_url_raw( $url );
		if ( empty( $url ) ) {
			return new WP_Error( 'crb_invalid_url', __( '対象URLが不正です。', 'custom-rss-builder' ) );
		}

		$cache_key = 'custom_rss_builder_html_' . md5( $url );
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_string( $cached ) && '' !== $cached ) {
				return $cached;
			}
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 5,
				'user-agent'  => 'Custom RSS Builder/' . CRB_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'crb_fetch_failed',
				sprintf(
					__( '対象URLにアクセスできませんでした: %s', 'custom-rss-builder' ),
					$response->get_error_message()
				)
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return new WP_Error(
				'crb_http_error',
				sprintf(
					__( '対象URLにアクセスできませんでした (HTTPステータス: %d)', 'custom-rss-builder' ),
					$status
				)
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );
		$html = $this->convert_to_utf8( $body, wp_remote_retrieve_headers( $response ), $url );

		set_transient( $cache_key, $html, CRB_CACHE_TTL );
		return $html;
	}

	public function convert_to_utf8( $html_content, $headers, $url ) {
		$charset = '';

		if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
			$content_type = (string) $headers->offsetGet( 'content-type' );
			if ( preg_match( '/charset=([^\s;]+)/i', $content_type, $matches ) ) {
				$charset = trim( $matches[1], "\"'" );
			}
		}

		if ( '' === $charset && preg_match( '/<meta[^>]+charset=["\']?\s*([^\s"\'>\/]+)/i', $html_content, $matches ) ) {
			$charset = trim( $matches[1] );
		}

		if ( '' === $charset ) {
			$charset = 'UTF-8';
		}

		$charset = strtoupper( $charset );
		if ( 'UTF-8' === $charset || 'UTF8' === $charset ) {
			return $html_content;
		}

		if ( function_exists( 'mb_convert_encoding' ) ) {
			$converted = @mb_convert_encoding( $html_content, 'UTF-8', $charset );
			if ( false !== $converted ) {
				return $converted;
			}
		}

		return $html_content;
	}
}
