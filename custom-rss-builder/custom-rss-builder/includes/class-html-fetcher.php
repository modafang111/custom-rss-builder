<?php
/**
 * 外部 HTML 取得（キャッシュなし・毎回 wp_remote_get）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_HTML_Fetcher {

	/**
	 * @param string                           $url     Target URL.
	 * @param array<string, mixed>|bool|null $options 互換用（無視）。
	 * @return string|WP_Error
	 */
	public function fetch_html( $url, $options = false ) {
		unset( $options );
		$url = esc_url_raw( $url );
		if ( empty( $url ) ) {
			return new WP_Error( 'crb_invalid_url', __( '対象URLが不正です。', 'custom-rss-builder' ) );
		}

		if ( function_exists( 'crb_clear_html_cache' ) ) {
			crb_clear_html_cache( $url );
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
					/* translators: %s: error message */
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
					/* translators: %d: HTTP status code */
					__( '対象URLにアクセスできませんでした (HTTPステータス: %d)', 'custom-rss-builder' ),
					$status
				)
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );
		return $this->convert_to_utf8( $body, wp_remote_retrieve_headers( $response ), $url );
	}

	/**
	 * @param string       $html_content Body.
	 * @param array|object $headers      Response headers.
	 * @param string       $url          URL.
	 * @return string
	 */
	public function convert_to_utf8( $html_content, $headers, $url ) {
		unset( $url );
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
