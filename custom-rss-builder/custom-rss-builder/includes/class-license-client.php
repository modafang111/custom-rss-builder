<?php
/**
 * ライセンスサーバー REST クライアント。
 * 同一 WordPress に組み込みサーバーがある場合は HTTP を使わず直接呼び出す。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_License_Client {

	/**
	 * @param string $license_key License key.
	 * @return array<string,mixed>|WP_Error
	 */
	public function activate( $license_key ) {
		return $this->request(
			'activate',
			array(
				'license_key' => $license_key,
				'site_url'    => crb_license_site_url(),
			)
		);
	}

	/**
	 * @param string $license_key License key.
	 * @return array<string,mixed>|WP_Error
	 */
	public function deactivate( $license_key ) {
		return $this->request(
			'deactivate',
			array(
				'license_key' => $license_key,
				'site_url'    => crb_license_site_url(),
			)
		);
	}

	/**
	 * @param string $license_key License key.
	 * @return array<string,mixed>|WP_Error
	 */
	public function remote_check( $license_key ) {
		$result = $this->request(
			'check',
			array(
				'license_key' => $license_key,
				'site_url'    => crb_license_site_url(),
			)
		);

		crb_license_apply_remote_result( $result );

		return $result;
	}

	/**
	 * 無料キーを正本で発行し、このサイト URL に有効化する。
	 *
	 * @param string $email Admin email (empty = site admin_email).
	 * @return array<string,mixed>|WP_Error
	 */
	public function issue_free( $email = '' ) {
		$email = sanitize_email( (string) $email );
		if ( '' === $email ) {
			$user = wp_get_current_user();
			if ( $user instanceof WP_User && is_email( $user->user_email ) ) {
				$email = sanitize_email( $user->user_email );
			}
		}
		if ( '' === $email ) {
			$email = sanitize_email( (string) get_option( 'admin_email' ) );
		}

		$result = $this->request(
			'issue-free',
			array(
				'email'    => $email,
				'site_url' => crb_license_site_url(),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! empty( $result['license']['license_key'] ) ) {
			crb_license_update_settings(
				array(
					'license_key' => sanitize_text_field( (string) $result['license']['license_key'] ),
				)
			);
		}

		crb_license_apply_remote_result( $result );

		return $result;
	}

	/**
	 * @param string               $endpoint activate|deactivate|check.
	 * @param array<string, mixed> $body Body.
	 * @return array<string,mixed>|WP_Error
	 */
	private function request( $endpoint, array $body ) {
		if ( crb_license_uses_remote_api() ) {
			return $this->request_remote( $endpoint, $body );
		}

		return $this->request_local( $endpoint, $body );
	}

	/**
	 * @param string               $endpoint Endpoint.
	 * @param array<string, mixed> $body Body.
	 * @return array<string,mixed>|WP_Error
	 */
	private function request_local( $endpoint, array $body ) {
		$manager = crb_ls_get_manager();
		if ( ! $manager ) {
			return new WP_Error(
				'crb_license_no_manager',
				__( 'ライセンスサーバーを初期化できません。', 'custom-rss-builder' )
			);
		}

		$key  = sanitize_text_field( (string) ( $body['license_key'] ?? '' ) );
		$site = (string) ( $body['site_url'] ?? crb_license_site_url() );
		$endpoint = sanitize_key( $endpoint );

		switch ( $endpoint ) {
			case 'activate':
				$result = $manager->activate( $key, $site );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return array(
					'success' => true,
					'license' => $manager->format_public_license( $result, $site ),
				);

			case 'deactivate':
				$result = $manager->deactivate( $key, $site );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return array( 'success' => true );

			case 'check':
				$result = $manager->check( $key, $site );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return array(
					'success' => true,
					'license' => $result,
				);

			case 'issue-free':
				$email = sanitize_email( (string) ( $body['email'] ?? '' ) );
				if ( ! crb_ls_is_valid_email( $email ) ) {
					return new WP_Error( 'crb_ls_bad_email', __( 'メールアドレスが不正です。', 'crb-license-server' ) );
				}
				$row = $manager->register_free( $email );
				if ( is_wp_error( $row ) ) {
					return $row;
				}
				$free_key = (string) ( $row['license_key'] ?? '' );
				$activated = $manager->activate( $free_key, $site );
				if ( is_wp_error( $activated ) ) {
					return $activated;
				}
				return array(
					'success' => true,
					'license' => $manager->format_public_license( $activated, $site ),
				);
		}

		return new WP_Error(
			'crb_license_bad_endpoint',
			__( 'ライセンス API の指定が不正です。', 'custom-rss-builder' )
		);
	}

	/**
	 * 別サイトのライセンスサーバーへ HTTP 接続（将来のリモート販売用）。
	 *
	 * @param string               $endpoint Endpoint.
	 * @param array<string, mixed> $body Body.
	 * @return array<string,mixed>|WP_Error
	 */
	private function request_remote( $endpoint, array $body ) {
		$secret = crb_license_api_secret();
		if ( '' === $secret ) {
			return new WP_Error(
				'crb_license_no_secret',
				__( 'API Secret が未設定です。ライセンス設定を確認してください。', 'custom-rss-builder' )
			);
		}

		$route = 'crb-license/v1/' . sanitize_key( $endpoint );
		$base  = untrailingslashit( crb_license_api_base() );
		$urls  = array(
			$base . '/?rest_route=/' . $route,
			$base . '/wp-json/' . $route,
		);

		$headers = array(
			'Content-Type'         => 'application/json',
			'X-CRB-License-Secret' => $secret,
		);
		$body['secret'] = $secret;
		$payload        = wp_json_encode( $body );

		$last_error = null;
		foreach ( $urls as $url ) {
			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 25,
					'headers' => $headers,
					'body'    => $payload,
				)
			);

			if ( is_wp_error( $response ) ) {
				$last_error = $response;
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 404 === $code ) {
				continue;
			}

			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

			if ( $code >= 400 ) {
				$raw_message = is_array( $data ) && ! empty( $data['message'] )
					? (string) $data['message']
					: __( 'ライセンスサーバーとの通信に失敗しました。', 'custom-rss-builder' );
				$api_code    = is_array( $data ) && ! empty( $data['code'] ) ? (string) $data['code'] : '';
				$error_code  = 'crb_license_http_' . $code;
				// 正本の業務エラー（無効化・未登録等）は JSON code をそのまま使う（403 でも usable を落とす）。
				if ( '' !== $api_code && 0 === strpos( $api_code, 'crb_ls_' ) ) {
					$error_code = $api_code;
				} elseif ( 403 === $code && (
					'crb_ls_bad_secret' === $api_code
					|| ( function_exists( 'crb_license_is_rest_permission_denied_message' )
						&& crb_license_is_rest_permission_denied_message( $raw_message ) )
					|| false !== stripos( $raw_message, 'API Secret' )
				) ) {
					$error_code = 'crb_license_bad_secret';
				}
				$message = function_exists( 'crb_license_map_remote_http_message' )
					? crb_license_map_remote_http_message( $code, $raw_message )
					: $raw_message;
				return new WP_Error( $error_code, $message );
			}

			if ( ! is_array( $data ) ) {
				return new WP_Error(
					'crb_license_bad_json',
					__( 'ライセンスサーバーの応答が不正です。', 'custom-rss-builder' )
				);
			}

			return $data;
		}

		if ( $last_error instanceof WP_Error ) {
			return $last_error;
		}

		return new WP_Error(
			'crb_license_unreachable',
			__( 'ライセンスサーバーに接続できません。API ベース URL を確認してください。', 'custom-rss-builder' )
		);
	}
}
