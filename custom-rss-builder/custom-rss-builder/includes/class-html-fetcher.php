<?php
/**
 * 外部 HTML 取得（キャッシュなし・毎回 wp_remote_get）。
 *
 * 対象URLが非200（またはボット対策の loading ページ）のとき:
 * 1) 同一オリジンのトップへ warmup して Cookie を引き継ぎ再取得（方式A）
 * 2) なお 503 + checkjs の場合は ajaxhelper を叩いて再取得（方式B）
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

		$args     = $this->get_request_args( $url );
		$response = wp_remote_get( $url, $args );
		$result   = $this->response_to_html( $response, $url );

		if ( ! is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Whether to retry with origin homepage warmup + cookies after a failed fetch.
		 *
		 * @param bool   $enabled Default true.
		 * @param string $url     Target URL.
		 * @param mixed  $result  WP_Error from the first attempt.
		 */
		$warmup_enabled = (bool) apply_filters( 'crb_html_fetch_warmup_enabled', true, $url, $result );
		if ( ! $warmup_enabled ) {
			return $result;
		}

		$cookie_map = array();
		if ( ! is_wp_error( $response ) ) {
			$cookie_map = $this->merge_cookie_map( $cookie_map, $this->cookies_from_response( $response ) );
		}

		$warmed = $this->fetch_html_with_origin_warmup( $url, $args, $cookie_map );
		if ( ! is_wp_error( $warmed['html'] ) ) {
			return $warmed['html'];
		}

		$cookie_map = $warmed['cookies'];
		$challenged = $this->fetch_html_with_js_challenge( $url, $args, $cookie_map, $warmed['response'], $response );
		if ( ! is_wp_error( $challenged ) ) {
			return $challenged;
		}

		// チャレンジ非該当なら warmup 側のエラー、それ以外はチャレンジ失敗理由を返す。
		if ( 'crb_challenge_skipped' === $challenged->get_error_code() ) {
			return $warmed['html'];
		}
		return $challenged;
	}

	/**
	 * GET origin home, then retry target URL with collected cookies and Referer.
	 *
	 * @param string               $url        Target URL.
	 * @param array<string, mixed> $args       Base request args.
	 * @param array<string, string> $cookie_map Existing cookies.
	 * @return array{html: string|WP_Error, cookies: array<string, string>, response: array|WP_Error|null}
	 */
	protected function fetch_html_with_origin_warmup( $url, $args, $cookie_map = array() ) {
		$origin = $this->get_origin_home_url( $url );
		if ( '' === $origin ) {
			return array(
				'html'     => new WP_Error( 'crb_warmup_unavailable', __( 'オリジンURLを特定できませんでした。', 'custom-rss-builder' ) ),
				'cookies'  => $cookie_map,
				'response' => null,
			);
		}

		if ( untrailingslashit( $origin ) === untrailingslashit( $url ) ) {
			return array(
				'html'     => new WP_Error( 'crb_warmup_skipped', __( '対象URLがオリジントップのため warmup をスキップしました。', 'custom-rss-builder' ) ),
				'cookies'  => $cookie_map,
				'response' => null,
			);
		}

		$warm_response = wp_remote_get( $origin, $args );
		if ( is_wp_error( $warm_response ) ) {
			return array(
				'html'     => new WP_Error(
					'crb_fetch_failed',
					sprintf(
						/* translators: %s: error message */
						__( '対象URLにアクセスできませんでした: %s', 'custom-rss-builder' ),
						$warm_response->get_error_message()
					)
				),
				'cookies'  => $cookie_map,
				'response' => $warm_response,
			);
		}

		$cookie_map = $this->merge_cookie_map( $cookie_map, $this->cookies_from_response( $warm_response ) );
		$response   = $this->request_with_cookies( $url, $args, $cookie_map, $origin );
		$html       = $this->response_to_html( $response, $url );

		return array(
			'html'     => $html,
			'cookies'  => is_wp_error( $response ) ? $cookie_map : $this->merge_cookie_map( $cookie_map, $this->cookies_from_response( $response ) ),
			'response' => $response,
		);
	}

	/**
	 * Resolve DUGA-style checkjs challenge then retry.
	 *
	 * @param string                $url            Target URL.
	 * @param array<string, mixed>  $args           Base request args.
	 * @param array<string, string> $cookie_map     Cookies so far.
	 * @param array|WP_Error|null   $latest_response Latest failed response (preferred for challenge HTML).
	 * @param array|WP_Error|null   $first_response  First response fallback.
	 * @return string|WP_Error
	 */
	protected function fetch_html_with_js_challenge( $url, $args, $cookie_map, $latest_response, $first_response ) {
		$body = '';
		foreach ( array( $latest_response, $first_response ) as $resp ) {
			if ( is_array( $resp ) ) {
				$code = (int) wp_remote_retrieve_response_code( $resp );
				$b    = (string) wp_remote_retrieve_body( $resp );
				if ( $this->is_js_challenge_body( $b ) || 503 === $code ) {
					$body = $b;
					$cookie_map = $this->merge_cookie_map( $cookie_map, $this->cookies_from_response( $resp ) );
					if ( $this->is_js_challenge_body( $b ) ) {
						break;
					}
				}
			}
		}

		if ( '' === $body || ! $this->is_js_challenge_body( $body ) ) {
			return new WP_Error( 'crb_challenge_skipped', __( 'JS チャレンジは検出されませんでした。', 'custom-rss-builder' ) );
		}

		$ajax_path = $this->extract_checkjs_path( $body );
		if ( '' === $ajax_path ) {
			return new WP_Error( 'crb_challenge_parse', __( 'ボット対策ページの解析に失敗しました。', 'custom-rss-builder' ) );
		}

		$origin   = $this->get_origin_home_url( $url );
		$ajax_url = $this->absolutize_url( $ajax_path, $origin ? $origin : $url );
		if ( '' === $ajax_url ) {
			return new WP_Error( 'crb_challenge_parse', __( 'ボット対策URLを組み立てられませんでした。', 'custom-rss-builder' ) );
		}

		$ajax_args = $args;
		$ajax_args['headers'] = isset( $ajax_args['headers'] ) && is_array( $ajax_args['headers'] ) ? $ajax_args['headers'] : array();
		$ajax_args['headers']['Referer']           = $url;
		$ajax_args['headers']['X-Requested-With']  = 'XMLHttpRequest';
		$ajax_args = $this->apply_cookie_header( $ajax_args, $cookie_map );

		$ajax_response = wp_remote_get( $ajax_url, $ajax_args );
		if ( is_wp_error( $ajax_response ) ) {
			return new WP_Error(
				'crb_fetch_failed',
				sprintf(
					/* translators: %s: error message */
					__( '対象URLにアクセスできませんでした: %s', 'custom-rss-builder' ),
					$ajax_response->get_error_message()
				)
			);
		}

		$cookie_map = $this->merge_cookie_map( $cookie_map, $this->cookies_from_response( $ajax_response ) );
		$ajax_body  = (string) wp_remote_retrieve_body( $ajax_response );
		$ok         = false;
		if ( '' !== $ajax_body ) {
			$data = json_decode( $ajax_body, true );
			$ok   = is_array( $data ) && ! empty( $data['success'] );
		}
		if ( ! $ok ) {
			return new WP_Error(
				'crb_challenge_failed',
				__( 'ボット対策の解除に失敗しました。', 'custom-rss-builder' )
			);
		}

		$response = $this->request_with_cookies( $url, $args, $cookie_map, $origin ? $origin : $url );
		return $this->response_to_html( $response, $url );
	}

	/**
	 * @param string                $url     Target URL.
	 * @param array<string, mixed>  $args    Base args.
	 * @param array<string, string> $cookies Cookie map.
	 * @param string                $referer Referer URL.
	 * @return array|WP_Error
	 */
	protected function request_with_cookies( $url, $args, $cookies, $referer ) {
		$args = $this->apply_cookie_header( $args, $cookies );
		$headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		if ( '' !== $referer ) {
			$headers['Referer'] = $referer;
		}
		$args['headers'] = $headers;
		return wp_remote_get( $url, $args );
	}

	/**
	 * @param array<string, mixed>  $args    Request args.
	 * @param array<string, string> $cookies Cookie map.
	 * @return array<string, mixed>
	 */
	protected function apply_cookie_header( $args, $cookies ) {
		if ( empty( $cookies ) ) {
			return $args;
		}
		$parts = array();
		foreach ( $cookies as $name => $value ) {
			$parts[] = $name . '=' . $value;
		}
		$headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$headers['Cookie'] = implode( '; ', $parts );
		$args['headers']   = $headers;
		// WP の cookies 配列と二重送信しない。
		unset( $args['cookies'] );
		return $args;
	}

	/**
	 * @param array|WP_Error $response Response.
	 * @return array<string, string>
	 */
	protected function cookies_from_response( $response ) {
		$map = array();
		if ( ! is_array( $response ) ) {
			return $map;
		}

		$headers = wp_remote_retrieve_headers( $response );
		$raw     = array();
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$all = $headers->getAll();
			if ( isset( $all['set-cookie'] ) ) {
				$raw = (array) $all['set-cookie'];
			}
		} elseif ( is_array( $headers ) && isset( $headers['set-cookie'] ) ) {
			$raw = (array) $headers['set-cookie'];
		}

		foreach ( $raw as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$pair = explode( ';', $line, 2 )[0];
			if ( false === strpos( $pair, '=' ) ) {
				continue;
			}
			list( $name, $value ) = explode( '=', $pair, 2 );
			$name = trim( $name );
			if ( '' !== $name ) {
				$map[ $name ] = trim( $value );
			}
		}

		// フォールバック: WP_Http_Cookie オブジェクト。
		if ( empty( $map ) ) {
			foreach ( wp_remote_retrieve_cookies( $response ) as $cookie ) {
				if ( is_object( $cookie ) && isset( $cookie->name ) ) {
					$map[ (string) $cookie->name ] = (string) $cookie->value;
				}
			}
		}

		return $map;
	}

	/**
	 * @param array<string, string> $base  Base map.
	 * @param array<string, string> $extra Extra map.
	 * @return array<string, string>
	 */
	protected function merge_cookie_map( $base, $extra ) {
		foreach ( $extra as $name => $value ) {
			$base[ $name ] = $value;
		}
		return $base;
	}

	/**
	 * @param string $html Body HTML.
	 * @return bool
	 */
	protected function is_js_challenge_body( $html ) {
		if ( '' === $html ) {
			return false;
		}
		return ( false !== stripos( $html, 'loading page' ) && false !== stripos( $html, 'checkjs' ) )
			|| ( false !== stripos( $html, 'action=checkjs' ) && false !== stripos( $html, 'mode=error503' ) );
	}

	/**
	 * @param string $html Challenge HTML.
	 * @return string Relative or absolute ajax path.
	 */
	protected function extract_checkjs_path( $html ) {
		if ( preg_match( '/ajax\.get\(\s*["\']([^"\']*checkjs[^"\']*)["\']/', $html, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '/["\'](\/prog\/ajaxhelper\?[^"\']*checkjs[^"\']*)["\']/', $html, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * @param string $path   Absolute path or URL.
	 * @param string $base   Base URL.
	 * @return string
	 */
	protected function absolutize_url( $path, $base ) {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $path ) ) {
			return $path;
		}
		$parts = wp_parse_url( $base );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$origin = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . (int) $parts['port'];
		}
		if ( isset( $path[0] ) && '/' === $path[0] ) {
			return $origin . $path;
		}
		return trailingslashit( $origin ) . ltrim( $path, '/' );
	}

	/**
	 * @param string $url Target URL.
	 * @return string Origin home URL with trailing slash, or empty string.
	 */
	protected function get_origin_home_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$home = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$home .= ':' . (int) $parts['port'];
		}

		return trailingslashit( $home );
	}

	/**
	 * @param string $url Target URL (for filters).
	 * @return array<string, mixed>
	 */
	protected function get_request_args( $url ) {
		$args = array(
			'timeout'     => 20,
			'redirection' => 5,
			'user-agent'  => 'Custom RSS Builder/' . CRB_VERSION . '; ' . home_url( '/' ),
		);

		/**
		 * Filter HTTP request args for HTML fetch.
		 *
		 * @param array<string, mixed> $args Request args.
		 * @param string               $url  Target URL.
		 */
		return (array) apply_filters( 'crb_html_fetch_request_args', $args, $url );
	}

	/**
	 * @param array|WP_Error $response wp_remote_get response.
	 * @param string         $url      Target URL.
	 * @return string|WP_Error
	 */
	protected function response_to_html( $response, $url ) {
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
		$body   = (string) wp_remote_retrieve_body( $response );

		if ( 200 === $status && $this->is_js_challenge_body( $body ) ) {
			return new WP_Error(
				'crb_http_error',
				__( '対象URLにアクセスできませんでした (HTTPステータス: 503)', 'custom-rss-builder' )
			);
		}

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
