<?php
/**
 * 受け入れテスト用プローブ（デプロイ後に run_client_acceptance_tests.py から呼ぶ）。
 * 使用後は削除すること。
 *
 * @package Custom_RSS_Builder
 */

define( 'CRB_PROBE_TOKEN', '__CRB_PROBE_TOKEN__' );

/**
 * @param array<string, mixed> $payload Payload.
 * @param int                  $code    HTTP status.
 * @return never
 */
function crb_probe_json_exit( array $payload, $code = 200 ) {
	header( 'Content-Type: application/json; charset=UTF-8' );
	if ( $code >= 400 ) {
		http_response_code( (int) $code );
	}
	echo wp_json_encode( $payload );
	exit;
}

/**
 * 受け入れテスト feed_save の既定 URL / セレクタ（クライアント ZIP に test-fixture は含まれない）。
 *
 * @return array{url: string, css: array<string, string>}
 */
function crb_probe_acceptance_feed_defaults() {
	$url = '';
	if ( function_exists( 'crb_demo_sample_url_by_slug' ) ) {
		$url = (string) crb_demo_sample_url_by_slug( 'crb-sample-simple-div' );
	}
	if ( '' === $url && function_exists( 'crb_demo_samples_authority_site_url' ) && function_exists( 'crb_demo_samples_parent_slug' ) ) {
		$url = trailingslashit( crb_demo_samples_authority_site_url() )
			. crb_demo_samples_parent_slug()
			. '/crb-sample-simple-div/';
	}
	if ( '' === $url ) {
		$url = 'https://123789.jp/custom-rss-builder/crb-practice-samples/crb-sample-simple-div/';
	}

	return array(
		'url' => $url,
		'css' => array(
			'item_selector' => '.crb-sample-news-item',
			'link_selector' => 'a',
		),
	);
}

$wp_load = '';
$dir     = __DIR__;
for ( $i = 0; $i < 8; $i++ ) {
	$candidate = $dir . '/wp-load.php';
	if ( is_readable( $candidate ) ) {
		$wp_load = $candidate;
		break;
	}
	$parent = dirname( $dir );
	if ( $parent === $dir ) {
		break;
	}
	$dir = $parent;
}
if ( '' === $wp_load ) {
	header( 'Content-Type: application/json; charset=UTF-8' );
	http_response_code( 500 );
	echo json_encode( array( 'error' => 'wp-load not found', 'from' => __DIR__ ) );
	exit;
}

if ( ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}

require_once $wp_load;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$token = isset( $_REQUEST['token'] ) ? (string) $_REQUEST['token'] : '';
if ( $token !== CRB_PROBE_TOKEN ) {
	crb_probe_json_exit( array( 'ok' => false, 'error' => 'forbidden' ), 403 );
}

header( 'Content-Type: application/json; charset=UTF-8' );

/**
 * @return void
 */
function crb_probe_set_admin() {
	$users = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	if ( ! empty( $users[0] ) ) {
		wp_set_current_user( (int) $users[0] );
	}
}

/**
 * @param mixed $data Data.
 * @return never
 */
function crb_probe_ok( $data ) {
	echo wp_json_encode( array( 'ok' => true, 'data' => $data ) );
	exit;
}

/**
 * @param string $message Message.
 * @param int    $code    HTTP code.
 * @return never
 */
function crb_probe_fail( $message, $code = 400 ) {
	http_response_code( $code );
	echo wp_json_encode( array( 'ok' => false, 'error' => (string) $message ) );
	exit;
}

crb_probe_set_admin();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) $_REQUEST['action'] ) : 'ping';

switch ( $action ) {
	case 'ping':
		crb_probe_ok(
			array(
				'home'    => home_url(),
				'build'   => defined( 'CRB_BUILD_ID' ) ? (string) CRB_BUILD_ID : '',
				'version' => defined( 'CRB_VERSION' ) ? (string) CRB_VERSION : '',
			)
		);

	case 'flags':
		crb_probe_ok(
			array(
				'client_app'          => function_exists( 'crb_is_client_app_enabled' ) && crb_is_client_app_enabled(),
				'license_server_app'  => function_exists( 'crb_is_license_server_app_enabled' ) && crb_is_license_server_app_enabled(),
				'authority'           => function_exists( 'crb_license_is_authoritative_server' ) && crb_license_is_authoritative_server(),
				'ui_client'           => function_exists( 'crb_license_ui_is_client_screen' ) && crb_license_ui_is_client_screen(),
				'remote_api'          => function_exists( 'crb_license_uses_remote_api' ) && crb_license_uses_remote_api(),
				'site_url'            => function_exists( 'crb_license_site_url' ) ? crb_license_site_url() : home_url(),
			)
		);

	case 'api_secret':
		if ( ! function_exists( 'crb_ls_api_secret' ) ) {
			crb_probe_fail( 'crb_ls_api_secret unavailable' );
		}
		crb_probe_ok( array( 'api_secret' => crb_ls_api_secret() ) );

	case 'install_demo_samples':
		if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
			crb_probe_fail( 'authority server only' );
		}
		if ( ! function_exists( 'crb_demo_samples_install' ) ) {
			crb_probe_fail( 'crb_demo_samples_install missing' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$force = ! empty( $_REQUEST['force'] );
		$result = crb_demo_samples_install( $force );
		$result['patterns'] = function_exists( 'crb_get_demo_sample_patterns' ) ? crb_get_demo_sample_patterns() : array();
		crb_probe_ok( $result );

	case 'purge_all_licenses':
		if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
			crb_probe_fail( 'authority server only' );
		}
		if ( ! class_exists( 'CRB_License_Server_Database' ) ) {
			crb_probe_fail( 'database class missing' );
		}
		global $wpdb;
		$table = CRB_License_Server_Database::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		crb_probe_ok(
			array(
				'deleted'   => $count,
				'remaining' => $remaining,
			)
		);

	case 'create_license':
		if ( ! function_exists( 'crb_ls_get_manager' ) || ! crb_ls_get_manager() ) {
			crb_probe_fail( 'license manager unavailable' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$plan  = isset( $_REQUEST['plan'] ) ? sanitize_key( (string) $_REQUEST['plan'] ) : 'free';
		$email = 'acceptance+' . wp_generate_password( 8, false, false ) . '@crb-test.local';
		$row   = crb_ls_get_manager()->create_license( $email, $plan );
		if ( is_wp_error( $row ) ) {
			crb_probe_fail( $row->get_error_message() );
		}
		crb_probe_ok(
			array(
				'license_key' => (string) ( $row['license_key'] ?? '' ),
				'plan'        => (string) ( $row['plan'] ?? '' ),
				'email'       => $email,
			)
		);

	case 'set_license_status':
		if ( ! function_exists( 'crb_ls_get_manager' ) || ! crb_ls_get_manager() ) {
			crb_probe_fail( 'license manager unavailable' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key    = isset( $_REQUEST['license_key'] ) ? sanitize_text_field( (string) $_REQUEST['license_key'] ) : '';
		$status = isset( $_REQUEST['status'] ) ? sanitize_key( (string) $_REQUEST['status'] ) : 'expired';
		if ( '' === $key ) {
			crb_probe_fail( 'license_key required' );
		}
		global $wpdb;
		$updated = $wpdb->update(
			CRB_License_Server_Database::table_name(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'license_key' => $key ),
			array( '%s', '%s' ),
			array( '%s' )
		);
		if ( false === $updated ) {
			crb_probe_fail( 'db update failed' );
		}
		crb_probe_ok( array( 'license_key' => $key, 'status' => $status ) );

	case 'bind_license_site':
		if ( ! function_exists( 'crb_ls_get_manager' ) || ! crb_ls_get_manager() ) {
			crb_probe_fail( 'license manager unavailable' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key  = isset( $_REQUEST['license_key'] ) ? sanitize_text_field( (string) $_REQUEST['license_key'] ) : '';
		$site = isset( $_REQUEST['site_url'] ) ? crb_ls_normalize_site_url( (string) $_REQUEST['site_url'] ) : '';
		if ( '' === $key || '' === $site ) {
			crb_probe_fail( 'license_key and site_url required' );
		}
		$result = crb_ls_get_manager()->activate( $key, $site );
		if ( is_wp_error( $result ) ) {
			crb_probe_fail( $result->get_error_message() );
		}
		crb_probe_ok( array( 'license_key' => $key, 'site_url' => $site ) );

	case 'license_state':
		crb_probe_ok(
			array(
				'state'    => function_exists( 'crb_license_get_state' ) ? crb_license_get_state() : array(),
				'settings' => function_exists( 'crb_license_get_settings' ) ? crb_license_get_settings() : array(),
				'slots'    => function_exists( 'crb_license_get_record_slot_count' ) ? crb_license_get_record_slot_count() : 0,
				'feeds'    => function_exists( 'crb_plugin' ) && crb_plugin()->feed_manager
					? count( crb_plugin()->feed_manager->get_feeds() )
					: 0,
			)
		);

	case 'license_save_secret':
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$secret = isset( $_REQUEST['secret'] ) ? sanitize_text_field( (string) $_REQUEST['secret'] ) : '';
		if ( '' === $secret ) {
			crb_probe_fail( 'secret required' );
		}
		crb_license_update_settings(
			array(
				'connection_mode' => 'remote',
				'api_secret'      => $secret,
			)
		);
		if ( function_exists( 'crb_license_suggested_client_api_base' ) ) {
			$base = crb_license_suggested_client_api_base();
			if ( '' !== $base ) {
				crb_license_update_settings( array( 'api_base' => $base ) );
			}
		}
		crb_probe_ok( array( 'saved' => true ) );

	case 'license_activate':
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = isset( $_REQUEST['license_key'] ) ? sanitize_text_field( (string) $_REQUEST['license_key'] ) : '';
		if ( '' === $key || ! class_exists( 'Custom_RSS_Builder_License_Client' ) ) {
			crb_probe_fail( 'license_key or client missing' );
		}
		crb_license_update_settings( array( 'license_key' => $key ) );
		$client = new Custom_RSS_Builder_License_Client();
		$result = $client->activate( $key );
		if ( is_wp_error( $result ) ) {
			crb_probe_fail( $result->get_error_message() );
		}
		if ( function_exists( 'crb_license_apply_remote_result' ) ) {
			crb_license_apply_remote_result( $result );
		}
		crb_probe_ok(
			array(
				'state' => crb_license_get_state(),
				'license' => isset( $result['license'] ) ? $result['license'] : array(),
			)
		);

	case 'license_check':
		$settings = crb_license_get_settings();
		$key      = trim( (string) ( $settings['license_key'] ?? '' ) );
		if ( '' === $key || ! class_exists( 'Custom_RSS_Builder_License_Client' ) ) {
			crb_probe_fail( 'no license key' );
		}
		$client = new Custom_RSS_Builder_License_Client();
		$result = $client->remote_check( $key );
		if ( is_wp_error( $result ) ) {
			crb_probe_fail( $result->get_error_message() );
		}
		crb_probe_ok( array( 'state' => crb_license_get_state() ) );

	case 'license_remote_error_probe':
		if ( ! class_exists( 'Custom_RSS_Builder_License_Client' ) ) {
			crb_probe_fail( 'client missing' );
		}
		$settings = crb_license_get_settings();
		$key      = trim( (string) ( $settings['license_key'] ?? '' ) );
		$saved    = crb_license_api_secret();
		crb_license_update_settings( array( 'api_secret' => 'wrong-secret-probe' ) );
		$client = new Custom_RSS_Builder_License_Client();
		$result = $key ? $client->remote_check( $key ) : $client->activate( 'CRB-TEST' );
		crb_license_update_settings( array( 'api_secret' => $saved ) );
		if ( is_wp_error( $result ) ) {
			crb_probe_ok(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				)
			);
		}
		crb_probe_fail( 'expected WP_Error' );

	case 'license_html':
		crb_probe_set_admin();
		$state    = crb_license_get_state();
		$settings = crb_license_get_settings();
		ob_start();
		include CRB_PLUGIN_DIR . 'admin/views/license-settings.php';
		$html = (string) ob_get_clean();
		crb_probe_ok(
			array(
				'html'              => $html,
				'pro_h2_count'      => substr_count( $html, 'Pro にアップグレード' ),
				'ol_steps_count'    => substr_count( $html, 'crb-license-steps' ),
				'has_auth_server'   => false !== strpos( $html, '認証サーバー' ),
				'has_plan_table'    => false !== strpos( $html, 'プランの違い' ),
			)
		);

	case 'admin_menus':
		crb_probe_set_admin();
		if ( ! defined( 'WP_ADMIN' ) ) {
			define( 'WP_ADMIN', true );
		}
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		if ( function_exists( 'crb_plugin' ) ) {
			crb_plugin();
		}
		do_action( 'admin_menu' );
		global $menu, $submenu;
		$top    = array();
		$subs   = array();
		$crb_sub = array();
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && false !== strpos( (string) $item[2], 'custom-rss' ) ) {
					$top[] = (string) $item[2];
				}
				if ( isset( $item[2] ) && false !== strpos( (string) $item[2], 'crb-license' ) ) {
					$top[] = (string) $item[2];
				}
			}
		}
		if ( is_array( $submenu ) && isset( $submenu['custom-rss-builder'] ) ) {
			foreach ( $submenu['custom-rss-builder'] as $sub ) {
				$crb_sub[] = isset( $sub[2] ) ? (string) $sub[2] : '';
			}
		}
		crb_probe_ok(
			array(
				'top_slugs' => $top,
				'crb_sub'   => $crb_sub,
			)
		);

	case 'license_reset':
		crb_license_update_settings(
			array(
				'license_key'     => '',
				'plan'            => 'free',
				'status'          => 'inactive',
				'usable'          => false,
				'message'         => '',
				'connection_mode' => 'remote',
			)
		);
		crb_probe_ok( array( 'reset' => true ) );

	case 'license_simulate_fresh':
		if ( function_exists( 'crb_license_clear_settings_for_test' ) ) {
			crb_license_clear_settings_for_test();
		} else {
			delete_option( CRB_LICENSE_OPTION_KEY );
		}
		crb_probe_ok(
			array(
				'deleted' => true,
				'option'  => CRB_LICENSE_OPTION_KEY,
			)
		);

	case 'license_seed_stale':
		update_option(
			CRB_LICENSE_OPTION_KEY,
			array(
				'license_key'     => '',
				'plan'            => 'pro',
				'status'          => 'active',
				'usable'          => true,
				'message'         => '',
				'connection_mode' => 'remote',
			),
			false
		);
		crb_probe_ok( array( 'seeded' => 'pro_usable_no_key' ) );

	case 'license_issue_free':
		if ( ! class_exists( 'Custom_RSS_Builder_License_Client' ) ) {
			crb_probe_fail( 'client missing' );
		}
		$client = new Custom_RSS_Builder_License_Client();
		$result = $client->issue_free( '' );
		if ( is_wp_error( $result ) ) {
			crb_probe_fail( $result->get_error_message() );
		}
		crb_probe_ok(
			array(
				'state' => crb_license_get_state(),
			)
		);

	case 'feed_cleanup':
		if ( ! function_exists( 'crb_plugin' ) ) {
			crb_probe_fail( 'plugin missing' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$all   = ! empty( $_REQUEST['all'] );
		$fm    = crb_plugin()->feed_manager;
		$feeds = $fm->get_feeds();
		$del   = 0;
		foreach ( array_keys( $feeds ) as $fid ) {
			$name = (string) ( $feeds[ $fid ]['name'] ?? '' );
			if ( $all || 0 === strpos( $name, 'CRB_ACCEPT_' ) ) {
				$fm->delete_feed( (int) $fid );
				++$del;
			}
		}
		crb_probe_ok( array( 'deleted' => $del, 'remaining' => count( $fm->get_feeds() ) ) );

	case 'feed_save':
		if ( ! function_exists( 'crb_plugin' ) ) {
			crb_probe_fail( 'plugin missing' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$fid  = isset( $_REQUEST['feed_id'] ) ? (int) $_REQUEST['feed_id'] : 0;
		$name = isset( $_REQUEST['name'] ) ? sanitize_text_field( (string) $_REQUEST['name'] ) : 'CRB_ACCEPT_feed';
		$url  = isset( $_REQUEST['url'] ) ? esc_url_raw( (string) $_REQUEST['url'] ) : '';
		$defaults = crb_probe_acceptance_feed_defaults();
		if ( '' === $url ) {
			$url = $defaults['url'];
		}
		$is_new = $fid <= 0 || ! crb_plugin()->feed_manager->get_feed( $fid );
		if ( $is_new && function_exists( 'crb_license_can' ) && ! crb_license_can( 'create_feed' ) ) {
			crb_probe_fail( function_exists( 'crb_license_denied_message' ) ? crb_license_denied_message( 'create_feed' ) : 'create_feed denied', 403 );
		}
		if ( function_exists( 'crb_license_can' ) && ! crb_license_can( 'save' ) ) {
			crb_probe_fail( 'save denied', 403 );
		}
		$css = $defaults['css'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['slot4'] ) ) {
			$css['image_selector'] = '.slot4-test';
		}
		$payload = array(
			'id'              => $fid,
			'name'            => $name,
			'url'             => $url,
			'extraction_mode' => 'css',
			'css'             => $css,
		);
		if ( function_exists( 'crb_license_apply_feed_limits' ) ) {
			$payload = crb_license_apply_feed_limits( $payload );
		}
		$new_id = crb_plugin()->feed_manager->save_feed( $payload );
		$feed = crb_plugin()->feed_manager->get_feed( $new_id );
		crb_probe_ok(
			array(
				'feed_id' => $new_id,
				'feed'    => $feed,
				'count'   => count( crb_plugin()->feed_manager->get_feeds() ),
			)
		);

	case 'can_create_feed':
		crb_probe_ok(
			array(
				'can'     => function_exists( 'crb_license_can' ) ? crb_license_can( 'create_feed' ) : false,
				'message' => function_exists( 'crb_license_denied_message' ) ? crb_license_denied_message( 'create_feed' ) : '',
			)
		);

	case 'slot_meta':
		crb_probe_ok(
			array(
				'max_index' => function_exists( 'crb_license_get_max_slot_index' ) ? crb_license_get_max_slot_index() : -1,
				'slots'     => function_exists( 'crb_license_get_record_slot_count' ) ? crb_license_get_record_slot_count() : 0,
			)
		);

	case 'rss_status':
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$feed_id = isset( $_REQUEST['feed_id'] ) ? (int) $_REQUEST['feed_id'] : 0;
		if ( $feed_id <= 0 ) {
			crb_probe_fail( 'feed_id required' );
		}
		$can = function_exists( 'crb_license_can' ) ? crb_license_can( 'rss' ) : false;
		crb_probe_ok( array( 'can_rss' => $can, 'feed_id' => $feed_id ) );

	case 'cron_meta':
		crb_probe_ok(
			array(
				'can_cron' => function_exists( 'crb_license_can' ) ? crb_license_can( 'cron_import' ) : false,
				'secret'   => function_exists( 'crb_get_import_secret' ) ? crb_get_import_secret() : '',
			)
		);

	case 'extract_preview':
		if ( ! function_exists( 'crb_plugin' ) ) {
			crb_probe_fail( 'plugin missing' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$feed_id = isset( $_REQUEST['feed_id'] ) ? (int) $_REQUEST['feed_id'] : 0;
		$feed    = crb_plugin()->feed_manager->get_feed( $feed_id );
		if ( ! $feed ) {
			crb_probe_fail( 'feed not found' );
		}
		if ( '' === trim( (string) ( $feed['import']['content_template'] ?? '' ) ) ) {
			$feed['import']['content_template'] = '<p>{%1%} <a href="{%2%}">link</a></p>';
			crb_plugin()->feed_manager->save_feed( $feed );
			$feed = crb_plugin()->feed_manager->get_feed( $feed_id );
		}
		$html = crb_plugin()->html_fetcher->fetch_html( $feed['url'] );
		if ( is_wp_error( $html ) ) {
			crb_probe_fail( $html->get_error_message() );
		}
		$parsed = crb_extract_items_from_html( $html, $feed );
		if ( is_wp_error( $parsed ) ) {
			crb_probe_fail( $parsed->get_error_message() );
		}
		$import_preview = array( 'error' => 'skipped' );
		if ( function_exists( 'crb_license_can' ) && crb_license_can( 'preview_posts' ) ) {
			$import_preview = crb_plugin()->post_importer->preview_import_items( $feed, $parsed, 3 );
		}
		crb_probe_ok(
			array(
				'item_count'     => is_array( $parsed ) ? count( $parsed ) : 0,
				'import_preview' => $import_preview,
			)
		);

	case 'rest_forbidden_message':
		$base = function_exists( 'crb_license_api_base' ) ? crb_license_api_base() : '';
		crb_probe_ok( array( 'api_base' => $base ) );

	case 'license_can_feature':
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$feature = isset( $_REQUEST['feature'] ) ? sanitize_key( (string) $_REQUEST['feature'] ) : '';
		if ( '' === $feature ) {
			crb_probe_fail( 'feature required' );
		}
		crb_probe_ok(
			array(
				'feature' => $feature,
				'can'     => function_exists( 'crb_license_can' ) ? crb_license_can( $feature ) : false,
				'message' => function_exists( 'crb_license_denied_message' ) ? crb_license_denied_message( $feature ) : '',
			)
		);

	case 'import_schedule_min':
		crb_probe_ok(
			array(
				'plan_min_hours' => function_exists( 'crb_license_import_schedule_min_hours' )
					? (int) crb_license_import_schedule_min_hours()
					: -1,
				'slug_1h'        => function_exists( 'crb_import_schedule_slug_from_hours' )
					? crb_import_schedule_slug_from_hours( 1 )
					: '',
				'slug_24h'       => function_exists( 'crb_import_schedule_slug_from_hours' )
					? crb_import_schedule_slug_from_hours( 24 )
					: '',
			)
		);

	case 'free_credit_flag':
		crb_probe_ok(
			array(
				'required' => function_exists( 'crb_license_requires_free_credit' ) && crb_license_requires_free_credit(),
			)
		);

	case 'feed_count':
		$count = 0;
		if ( function_exists( 'crb_plugin' ) && crb_plugin()->feed_manager ) {
			$count = count( crb_plugin()->feed_manager->get_feeds() );
		}
		crb_probe_ok( array( 'count' => $count ) );

	default:
		crb_probe_fail( 'unknown action: ' . $action );
}
