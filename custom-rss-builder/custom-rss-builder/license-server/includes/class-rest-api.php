<?php
/**
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRB_License_Server_REST_API {

	/** @var CRB_License_Server_License_Manager */
	private $manager;

	public function __construct( CRB_License_Server_License_Manager $manager ) {
		$this->manager = $manager;
	}

	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$ns = 'crb-license/v1';

		register_rest_route(
			$ns,
			'/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'activate' ),
				// JSON body の secret は permission_callback では読めないことがあるためコールバック内で検証。
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'deactivate' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/check',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'check' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/register-free',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'register_free' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$ns,
			'/issue-free',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'issue_free' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function verify_secret( $request ) {
		foreach ( $this->collect_secret_candidates( $request ) as $provided ) {
			if ( crb_ls_verify_api_secret( $provided ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return string[]
	 */
	private function collect_secret_candidates( $request ) {
		$candidates = array(
			$request->get_header( 'x-crb-license-secret' ),
			$request->get_param( 'secret' ),
		);
		$json = $request->get_json_params();
		if ( is_array( $json ) && isset( $json['secret'] ) ) {
			$candidates[] = $json['secret'];
		}
		return array_filter(
			array_map(
				static function ( $value ) {
					return is_string( $value ) ? trim( $value ) : '';
				},
				$candidates
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private function require_secret( $request ) {
		if ( $this->verify_secret( $request ) ) {
			return true;
		}
		return new WP_Error(
			'crb_ls_bad_secret',
			__( 'API Secret が一致しません。ライセンスサーバー設定の Secret を確認してください。', 'crb-license-server' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function activate( $request ) {
		$auth = $this->require_secret( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$key  = sanitize_text_field( (string) $request->get_param( 'license_key' ) );
		$site = (string) $request->get_param( 'site_url' );

		$result = $this->manager->activate( $key, $site );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'license' => $this->manager->format_public_license( $result, $site ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function deactivate( $request ) {
		$auth = $this->require_secret( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$key  = sanitize_text_field( (string) $request->get_param( 'license_key' ) );
		$site = (string) $request->get_param( 'site_url' );

		$result = $this->manager->deactivate( $key, $site );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function check( $request ) {
		$auth = $this->require_secret( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$key  = sanitize_text_field( (string) $request->get_param( 'license_key' ) );
		$site = (string) $request->get_param( 'site_url' );

		$result = $this->manager->check( $key, $site );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'license' => $result,
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function register_free( $request ) {
		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		$nonce = (string) $request->get_param( 'nonce' );

		if ( ! wp_verify_nonce( $nonce, 'crb_ls_register_free' ) ) {
			return new WP_Error( 'crb_ls_bad_nonce', __( 'セキュリティチェックに失敗しました。', 'crb-license-server' ), array( 'status' => 403 ) );
		}

		$result = $this->manager->register_free( $email );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'ライセンスキーをメールで送信しました。', 'crb-license-server' ),
			)
		);
	}

	/**
	 * Secret 付き: 無料キー発行＋サイトへ有効化（Pro 無効化後のクライアント自動復帰用）。
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function issue_free( $request ) {
		$auth = $this->require_secret( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		$site  = (string) $request->get_param( 'site_url' );
		if ( ! crb_ls_is_valid_email( $email ) ) {
			return new WP_Error( 'crb_ls_bad_email', __( 'メールアドレスが不正です。', 'crb-license-server' ), array( 'status' => 400 ) );
		}

		$row = $this->manager->register_free( $email );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$key        = (string) ( $row['license_key'] ?? '' );
		$activated  = $this->manager->activate( $key, $site );
		if ( is_wp_error( $activated ) ) {
			return $activated;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'license' => $this->manager->format_public_license( $activated, $site ),
			)
		);
	}
}
