<?php
/**
 * 投稿取り込みのループバック自己起動（保存時のみ起動、連鎖防止付き）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Import_Loopback {

	const PING_HOOK   = 'crb_loopback_ping';
	const LOCK_KEY    = 'crb_loopback_lock';
	const GUARD_KEY   = 'crb_loopback_guard';

	/** @var Custom_RSS_Builder_Feed_Manager */
	private $feed_manager;

	/** @var Custom_RSS_Builder_Post_Importer */
	private $post_importer;

	public function __construct( $feed_manager, $post_importer ) {
		$this->feed_manager  = $feed_manager;
		$this->post_importer = $post_importer;
	}

	public function register_hooks() {
		add_action( 'init', array( $this, 'maybe_handle_tick' ), 0 );
		add_action( self::PING_HOOK, array( $this, 'on_scheduled_ping' ) );
	}

	/**
	 * フィード保存直後のみ: 次回予約 + 取り込みは shutdown で非同期起動。
	 */
	public function kickstart_after_save() {
		$interval = $this->get_shortest_active_interval_seconds();
		if ( $interval <= 0 ) {
			$this->clear_scheduled_ping();
			return;
		}

		$this->schedule_next_ping( $interval );

		if ( $this->is_guarded() ) {
			return;
		}

		$this->set_guard( 2 * MINUTE_IN_SECONDS );
		add_action( 'shutdown', array( $this, 'fire_tick_request' ), 999 );
	}

	public function clear_scheduled_ping() {
		wp_clear_scheduled_hook( self::PING_HOOK );
		delete_option( 'crb_loopback_next_at' );
	}

	public function on_scheduled_ping() {
		if ( $this->is_guarded() ) {
			return;
		}
		$this->set_guard( 5 * MINUTE_IN_SECONDS );
		$this->fire_tick_request();
	}

	public function maybe_handle_tick() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['crb_loopback_tick'] ) ) {
			return;
		}

		if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
			session_write_close();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		if ( '' === $key || ! hash_equals( crb_get_import_secret(), $key ) ) {
			status_header( 403 );
			nocache_headers();
			exit;
		}

		if ( function_exists( 'crb_license_can' ) && ! crb_license_can( 'cron_import' ) ) {
			status_header( 403 );
			nocache_headers();
			exit;
		}

		if ( get_transient( self::LOCK_KEY ) ) {
			$this->respond_tick( array( 'status' => 'locked' ) );
		}

		set_transient( self::LOCK_KEY, '1', 10 * MINUTE_IN_SECONDS );
		$this->set_guard( 5 * MINUTE_IN_SECONDS );

		$ran = $this->run_due_feed_imports();

		$interval = $this->get_shortest_active_interval_seconds();
		if ( $interval > 0 ) {
			$this->schedule_next_ping( $interval );
		} else {
			$this->clear_scheduled_ping();
		}

		delete_transient( self::LOCK_KEY );

		$this->respond_tick(
			array(
				'status'   => 'ok',
				'ran'      => $ran,
				'next_sec' => $interval,
			)
		);
	}

	public function fire_tick_request() {
		$url = crb_get_loopback_tick_url();
		if ( '' === $url ) {
			return;
		}

		wp_remote_get(
			$url,
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
	}

	/**
	 * @return int[] Feed IDs that ran.
	 */
	private function run_due_feed_imports() {
		$ran   = array();
		$feeds = $this->feed_manager->get_feeds();
		if ( ! is_array( $feeds ) ) {
			return $ran;
		}

		foreach ( $feeds as $feed_id => $feed ) {
			$feed_id = (int) $feed_id;
			if ( $feed_id <= 0 || ! is_array( $feed ) || ! $this->is_feed_import_due( $feed ) ) {
				continue;
			}

			$result = $this->post_importer->import_feed( $feed_id, 'loopback' );
			if ( ! is_wp_error( $result ) ) {
				$ran[] = $feed_id;
			}
		}

		return $ran;
	}

	/**
	 * @param array<string, mixed> $feed Feed row.
	 * @return bool
	 */
	private function is_feed_import_due( $feed ) {
		$import = $this->feed_manager->get_import_settings( $feed );
		if ( ! crb_import_schedule_is_active( $import ) ) {
			return false;
		}

		$hours = crb_import_schedule_hours_from_slug( (string) ( $import['schedule'] ?? 'off' ) );
		if ( $hours < crb_import_schedule_plan_min_hours() ) {
			return false;
		}

		$interval = $hours * HOUR_IN_SECONDS;
		$last     = isset( $feed['last_imported'] ) ? strtotime( (string) $feed['last_imported'] ) : false;
		if ( false === $last || $last <= 0 ) {
			return true;
		}

		return ( time() - $last ) >= ( $interval - 30 );
	}

	/**
	 * @return int Seconds, 0 if none.
	 */
	private function get_shortest_active_interval_seconds() {
		$shortest = 0;
		$feeds    = $this->feed_manager->get_feeds();
		if ( ! is_array( $feeds ) ) {
			return 0;
		}

		foreach ( $feeds as $feed ) {
			if ( ! is_array( $feed ) ) {
				continue;
			}
			$import = $this->feed_manager->get_import_settings( $feed );
			if ( ! crb_import_schedule_is_active( $import ) ) {
				continue;
			}
			$hours = crb_import_schedule_hours_from_slug( (string) ( $import['schedule'] ?? 'off' ) );
			if ( $hours < crb_import_schedule_plan_min_hours() ) {
				continue;
			}
			$seconds = $hours * HOUR_IN_SECONDS;
			if ( $shortest <= 0 || $seconds < $shortest ) {
				$shortest = $seconds;
			}
		}

		return $shortest;
	}

	/**
	 * @param int $delay_seconds Delay before next tick.
	 */
	private function schedule_next_ping( $delay_seconds ) {
		$delay_seconds = max( (int) crb_import_schedule_plan_min_hours() * HOUR_IN_SECONDS, (int) $delay_seconds );
		$run_at        = time() + $delay_seconds;

		update_option( 'crb_loopback_next_at', $run_at, false );

		wp_clear_scheduled_hook( self::PING_HOOK );
		wp_schedule_single_event( $run_at, self::PING_HOOK );
	}

	private function is_guarded() {
		return (bool) get_transient( self::GUARD_KEY );
	}

	/**
	 * @param int $seconds TTL.
	 */
	private function set_guard( $seconds ) {
		set_transient( self::GUARD_KEY, '1', max( 60, (int) $seconds ) );
	}

	/**
	 * @param array<string, mixed> $body Response body.
	 */
	private function respond_tick( $body ) {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		echo wp_json_encode( $body );
		exit;
	}
}

/**
 * @return bool
 */
function crb_import_loopback_is_tick_request() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return ! empty( $_GET['crb_loopback_tick'] );
}

/**
 * @return string
 */
function crb_get_loopback_tick_url() {
	return add_query_arg(
		array(
			'crb_loopback_tick' => '1',
			'key'               => crb_get_import_secret(),
		),
		home_url( '/' )
	);
}

/**
 * @return Custom_RSS_Builder_Import_Loopback|null
 */
function crb_import_loopback() {
	if ( ! function_exists( 'crb_plugin' ) ) {
		return null;
	}
	$plugin = crb_plugin();
	return isset( $plugin->import_loopback ) ? $plugin->import_loopback : null;
}
