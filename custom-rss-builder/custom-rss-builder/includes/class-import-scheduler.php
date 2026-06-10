<?php
/**
 * WP-Cron による投稿取り込みスケジュール。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Import_Scheduler {

	const HOOK = 'crb_scheduled_feed_import';

	/** @var Custom_RSS_Builder_Feed_Manager */
	private $feed_manager;

	/** @var Custom_RSS_Builder_Post_Importer */
	private $post_importer;

	public function __construct( $feed_manager, $post_importer ) {
		$this->feed_manager  = $feed_manager;
		$this->post_importer = $post_importer;
	}

	public function register_hooks() {
		add_filter( 'cron_schedules', 'crb_import_schedule_filter_cron_schedules' );
		add_action( self::HOOK, array( $this, 'run_import' ), 10, 1 );
		add_action( 'init', array( $this, 'resync_all_feeds' ), 20 );
	}

	/**
	 * @param int $feed_id Feed ID.
	 */
	public function run_import( $feed_id ) {
		$feed_id = (int) $feed_id;
		if ( $feed_id <= 0 ) {
			return;
		}

		if ( function_exists( 'crb_license_can' ) && ! crb_license_can( 'cron_import' ) ) {
			return;
		}

		$this->post_importer->import_feed( $feed_id, 'cron' );
	}

	/**
	 * @param int $feed_id Feed ID.
	 */
	/**
	 * @param int  $feed_id       Feed ID.
	 * @param bool $kick_loopback 保存直後のみ true（毎リクエストの kickstart を防ぐ）。
	 */
	public function reschedule_feed( $feed_id, $kick_loopback = false ) {
		$feed_id = (int) $feed_id;
		$this->unschedule_feed( $feed_id );

		if ( $feed_id <= 0 ) {
			return;
		}

		$feed = $this->feed_manager->get_feed( $feed_id );
		if ( null === $feed ) {
			return;
		}

		$import = $this->feed_manager->get_import_settings( $feed );
		if ( ! crb_import_schedule_is_active( $import ) ) {
			return;
		}

		if ( function_exists( 'crb_license_can' ) && ! crb_license_can( 'cron_import' ) ) {
			return;
		}

		$recurrence = crb_import_schedule_recurrence( (string) ( $import['schedule'] ?? 'off' ) );
		if ( '' === $recurrence ) {
			return;
		}

		wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, self::HOOK, array( $feed_id ) );

		if ( $kick_loopback && function_exists( 'crb_import_loopback' ) ) {
			$loopback = crb_import_loopback();
			if ( $loopback ) {
				$loopback->kickstart_after_save();
			}
		}
	}

	/**
	 * @param int $feed_id Feed ID.
	 */
	public function unschedule_feed( $feed_id ) {
		wp_clear_scheduled_hook( self::HOOK, array( (int) $feed_id ) );

		if ( function_exists( 'crb_import_loopback' ) && $this->get_shortest_active_interval_seconds() <= 0 ) {
			$loopback = crb_import_loopback();
			if ( $loopback ) {
				$loopback->clear_scheduled_ping();
			}
		}
	}

	/**
	 * @return int
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
			if ( $hours < CRB_IMPORT_SCHEDULE_MIN_HOURS ) {
				continue;
			}
			$seconds = $hours * HOUR_IN_SECONDS;
			if ( $shortest <= 0 || $seconds < $shortest ) {
				$shortest = $seconds;
			}
		}
		return $shortest;
	}

	public function resync_all_feeds() {
		$feeds = $this->feed_manager->get_feeds();
		if ( ! is_array( $feeds ) ) {
			return;
		}

		foreach ( $feeds as $feed_id => $feed ) {
			$feed_id = (int) $feed_id;
			if ( $feed_id <= 0 || ! is_array( $feed ) ) {
				continue;
			}

			$import     = $this->feed_manager->get_import_settings( $feed );
			$should_run = crb_import_schedule_is_active( $import )
				&& ( ! function_exists( 'crb_license_can' ) || crb_license_can( 'cron_import' ) );
			$recurrence = $should_run ? crb_import_schedule_recurrence( (string) ( $import['schedule'] ?? 'off' ) ) : '';
			$next       = wp_next_scheduled( self::HOOK, array( $feed_id ) );
			$current    = $next ? wp_get_schedule( self::HOOK, array( $feed_id ) ) : false;

			if ( ! $should_run || '' === $recurrence ) {
				if ( $next ) {
					$this->unschedule_feed( $feed_id );
				}
				continue;
			}

			if ( ! $next || $current !== $recurrence ) {
				$this->reschedule_feed( $feed_id, false );
			}
		}
	}

	public function clear_all() {
		$feeds = $this->feed_manager->get_feeds();
		if ( ! is_array( $feeds ) ) {
			return;
		}
		foreach ( array_keys( $feeds ) as $feed_id ) {
			$this->unschedule_feed( (int) $feed_id );
		}
	}

	public function activate_all() {
		$this->resync_all_feeds();
	}
}

/**
 * @return Custom_RSS_Builder_Import_Scheduler|null
 */
function crb_import_scheduler() {
	if ( ! function_exists( 'crb_plugin' ) ) {
		return null;
	}
	$plugin = crb_plugin();
	return isset( $plugin->import_scheduler ) ? $plugin->import_scheduler : null;
}
