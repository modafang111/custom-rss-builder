<?php
/**
 * PayPal Webhook → ライセンス状態更新。
 *
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRB_License_Server_PayPal_Webhook {

	/** @var CRB_License_Server_License_Manager */
	private $manager;

	public function __construct( CRB_License_Server_License_Manager $manager ) {
		$this->manager = $manager;
	}

	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	public function register_route() {
		register_rest_route(
			'crb-license/v1',
			'/paypal-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @return string
	 */
	public static function webhook_url() {
		return rest_url( 'crb-license/v1/paypal-webhook' );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle( $request ) {
		$raw = $request->get_body();
		if ( '' === $raw ) {
			return new WP_Error( 'crb_ls_empty_body', __( 'Empty body', 'crb-license-server' ), array( 'status' => 400 ) );
		}

		$event = json_decode( $raw, true );
		if ( ! is_array( $event ) ) {
			return new WP_Error( 'crb_ls_bad_json', __( 'Invalid JSON', 'crb-license-server' ), array( 'status' => 400 ) );
		}

		$verified = $this->verify_signature( $request, $raw, $event );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$type = sanitize_text_field( (string) ( $event['event_type'] ?? '' ) );
		$res  = $this->dispatch_event( $type, $event );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return rest_ensure_response( array( 'success' => true, 'event_type' => $type ) );
	}

	/**
	 * @param WP_REST_Request     $request Request.
	 * @param string              $raw Raw body.
	 * @param array<string,mixed> $event Event.
	 * @return true|WP_Error
	 */
	private function verify_signature( $request, $raw, array $event ) {
		if ( '1' === crb_ls_get_option( 'paypal_skip_verify', '' ) && crb_ls_paypal_sandbox() ) {
			return true;
		}

		$webhook_id = crb_ls_get_option( 'paypal_webhook_id', '' );
		$client_id  = crb_ls_get_option( 'paypal_client_id', '' );
		$secret     = crb_ls_get_option( 'paypal_client_secret', '' );

		if ( '' === $webhook_id || '' === $client_id || '' === $secret ) {
			return new WP_Error(
				'crb_ls_paypal_config',
				__( 'PayPal Webhook 設定が未完了です。', 'crb-license-server' ),
				array( 'status' => 503 )
			);
		}

		$transmission_id   = $request->get_header( 'paypal-transmission-id' );
		$transmission_time = $request->get_header( 'paypal-transmission-time' );
		$cert_url          = $request->get_header( 'paypal-cert-url' );
		$auth_algo         = $request->get_header( 'paypal-auth-algo' );
		$transmission_sig  = $request->get_header( 'paypal-transmission-sig' );

		if ( '' === $transmission_id || '' === $transmission_sig ) {
			return new WP_Error( 'crb_ls_paypal_headers', __( 'PayPal headers missing', 'crb-license-server' ), array( 'status' => 400 ) );
		}

		$token = $this->get_access_token( $client_id, $secret );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_post(
			crb_ls_paypal_api_base() . '/v1/notifications/verify-webhook-signature',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'auth_algo'         => $auth_algo,
						'cert_url'          => $cert_url,
						'transmission_id'   => $transmission_id,
						'transmission_sig'  => $transmission_sig,
						'transmission_time' => $transmission_time,
						'webhook_id'        => $webhook_id,
						'webhook_event'     => $event,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) ) {
			return new WP_Error( 'crb_ls_paypal_verify_http', __( 'PayPal verify request failed', 'crb-license-server' ), array( 'status' => 502 ) );
		}

		if ( 'SUCCESS' !== (string) ( $body['verification_status'] ?? '' ) ) {
			return new WP_Error( 'crb_ls_paypal_verify_fail', __( 'PayPal signature invalid', 'crb-license-server' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * @param string $client_id Client ID.
	 * @param string $secret Secret.
	 * @return string|WP_Error
	 */
	private function get_access_token( $client_id, $secret ) {
		$response = wp_remote_post(
			crb_ls_paypal_api_base() . '/v1/oauth2/token',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => 'grant_type=client_credentials',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'crb_ls_paypal_token', __( 'PayPal token failed', 'crb-license-server' ) );
		}

		return (string) $body['access_token'];
	}

	/**
	 * @param string              $type Event type.
	 * @param array<string,mixed> $event Event payload.
	 * @return true|WP_Error
	 */
	private function dispatch_event( $type, array $event ) {
		$resource = isset( $event['resource'] ) && is_array( $event['resource'] ) ? $event['resource'] : array();
		$sub_id   = $this->extract_subscription_id( $type, $resource );
		$email    = sanitize_email( (string) ( $resource['subscriber']['email_address'] ?? '' ) );

		switch ( $type ) {
			case 'BILLING.SUBSCRIPTION.ACTIVATED':
			case 'BILLING.SUBSCRIPTION.RE-ACTIVATED':
				if ( '' === $sub_id ) {
					return true;
				}
				$this->manager->activate_pro_subscription( $sub_id, $email );
				return true;

			case 'BILLING.SUBSCRIPTION.SUSPENDED':
			case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
				if ( '' === $sub_id ) {
					return true;
				}
				$this->manager->set_subscription_status( $sub_id, 'past_due' );
				return true;

			case 'BILLING.SUBSCRIPTION.CANCELLED':
			case 'BILLING.SUBSCRIPTION.EXPIRED':
				if ( '' === $sub_id ) {
					return true;
				}
				$this->manager->set_subscription_status( $sub_id, 'expired' );
				return true;

			default:
				return true;
		}
	}

	/**
	 * @param string              $type Event type.
	 * @param array<string,mixed> $resource Resource.
	 * @return string
	 */
	private function extract_subscription_id( $type, array $resource ) {
		if ( ! empty( $resource['id'] ) && false !== strpos( $type, 'SUBSCRIPTION' ) ) {
			return sanitize_text_field( (string) $resource['id'] );
		}
		if ( ! empty( $resource['billing_agreement_id'] ) ) {
			return sanitize_text_field( (string) $resource['billing_agreement_id'] );
		}
		return '';
	}
}
