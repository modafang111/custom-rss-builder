<?php
/**
 * OS cron 等から投稿取り込みを実行するエンドポイント。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Import_Endpoint {

	/** @var Custom_RSS_Builder_Post_Importer */
	private $post_importer;

	public function __construct( $post_importer ) {
		$this->post_importer = $post_importer;
	}

	public function register_hooks() {
		add_action( 'init', array( $this, 'maybe_run_import' ), 0 );
	}

	public function maybe_run_import() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['crb_run_import'] ) ) {
			return;
		}

		$feed_id = isset( $_GET['feed_id'] ) ? (int) $_GET['feed_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

		if ( $feed_id <= 0 || '' === $key ) {
			$this->send_response( 400, array( 'error' => 'feed_id and key are required.' ) );
		}

		if ( ! hash_equals( crb_get_import_secret(), $key ) ) {
			$this->send_response( 403, array( 'error' => 'Invalid key.' ) );
		}

		if ( ! crb_license_can( 'cron_import' ) ) {
			$this->send_response( 403, array( 'error' => 'License does not allow import.' ) );
		}

		$result = $this->post_importer->import_feed( $feed_id, 'url' );
		if ( is_wp_error( $result ) ) {
			$this->send_response(
				500,
				array(
					'error' => $result->get_error_message(),
					'code'  => $result->get_error_code(),
				)
			);
		}

		$this->send_response(
			200,
			array(
				'feed_id' => $feed_id,
				'created' => (int) $result['created'],
				'skipped' => (int) $result['skipped'],
				'errors'  => $result['errors'],
			)
		);
	}

	private function send_response( $status, $body ) {
		status_header( (int) $status );
		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		echo wp_json_encode( $body );
		exit;
	}
}
