<?php

/**

 * Plugin Name: Custom RSS Builder

 * Plugin URI:  https://example.com/custom-rss-builder

 * Description: RSS非対応WebページからFeed43風パターンで抽出し、RSS 2.0フィードを配信。抽出結果をWordPress投稿へ取り込み可能。

 * Version:     0.9.0

 * Author:      Custom RSS Builder

 * Text Domain: custom-rss-builder

 * Requires at least: 5.8

 * Requires PHP: 7.4

 *

 * @package Custom_RSS_Builder

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



define( 'CRB_VERSION', '0.9.0' );

/** デプロイごとに更新（管理画面 JS/CSS のバージョン用） */

define( 'CRB_BUILD_ID', '20260611g' );

define( 'CRB_PLUGIN_FILE', __FILE__ );

define( 'CRB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

define( 'CRB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

define( 'CRB_OPTION_FEEDS', 'custom_rss_builder_feeds' );

define( 'CRB_OPTION_NEXT_ID', 'custom_rss_builder_next_feed_id' );

define( 'CRB_OPTION_IMPORT_KEY', 'custom_rss_builder_import_key' );

define( 'CRB_MAX_ITEMS', 20 );



require_once CRB_PLUGIN_DIR . 'includes/functions-license.php';

require_once CRB_PLUGIN_DIR . 'includes/functions-demo-samples.php';
require_once CRB_PLUGIN_DIR . 'includes/functions-ai-manual.php';
require_once CRB_PLUGIN_DIR . 'includes/functions-install-manual.php';
require_once CRB_PLUGIN_DIR . 'includes/functions-feed-pack-manual.php';
require_once CRB_PLUGIN_DIR . 'includes/functions-sales-lp.php';
require_once CRB_PLUGIN_DIR . 'includes/functions-third-party-compat.php';

if ( crb_is_client_app_enabled() ) {
	require_once CRB_PLUGIN_DIR . 'includes/class-license-client.php';
}

if ( crb_is_license_server_app_enabled() ) {
	$crb_ls_bootstrap = CRB_PLUGIN_DIR . 'license-server/bootstrap.php';
	if ( is_readable( $crb_ls_bootstrap ) ) {
		require_once $crb_ls_bootstrap;
	}
}

if ( crb_is_client_app_enabled() ) {

	$crb_free_credit = CRB_PLUGIN_DIR . 'includes/functions-free-credit.php';
	if ( is_readable( $crb_free_credit ) ) {
		require_once $crb_free_credit;
	}

	require_once CRB_PLUGIN_DIR . 'includes/functions-sanitize.php';

	require_once CRB_PLUGIN_DIR . 'includes/functions-link-rewrite.php';

	require_once CRB_PLUGIN_DIR . 'includes/functions-ai-settings.php';

	require_once CRB_PLUGIN_DIR . 'includes/functions-ai-transform.php';

	require_once CRB_PLUGIN_DIR . 'includes/functions-html-cache.php';

	require_once CRB_PLUGIN_DIR . 'includes/functions-css.php';

	require_once CRB_PLUGIN_DIR . 'includes/functions-feed-pack.php';

	require_once CRB_PLUGIN_DIR . 'includes/functions-dom-scope.php';
	require_once CRB_PLUGIN_DIR . 'includes/functions-dom-discover.php';

	require_once CRB_PLUGIN_DIR . 'includes/functions-extract-candidates.php';

	require_once CRB_PLUGIN_DIR . 'includes/functions-feed43.php';

	require_once CRB_PLUGIN_DIR . 'includes/class-css-extractor.php';

	require_once CRB_PLUGIN_DIR . 'includes/class-element-discovery.php';

	require_once CRB_PLUGIN_DIR . 'includes/class-feed-manager.php';

	require_once CRB_PLUGIN_DIR . 'includes/class-html-fetcher.php';

	require_once CRB_PLUGIN_DIR . 'includes/class-item-builder.php';

	require_once CRB_PLUGIN_DIR . 'includes/class-content-template.php';

	require_once CRB_PLUGIN_DIR . 'includes/class-rss-generator.php';

	require_once CRB_PLUGIN_DIR . 'includes/class-post-importer.php';

	require_once CRB_PLUGIN_DIR . 'includes/functions-import-schedule.php';

	require_once CRB_PLUGIN_DIR . 'includes/class-import-scheduler.php';

	require_once CRB_PLUGIN_DIR . 'includes/class-import-loopback.php';

	require_once CRB_PLUGIN_DIR . 'includes/class-custom-rss-builder.php';

	require_once CRB_PLUGIN_DIR . 'admin/class-admin-license.php';

	require_once CRB_PLUGIN_DIR . 'admin/class-admin-page.php';

	require_once CRB_PLUGIN_DIR . 'public/class-rss-endpoint.php';

	require_once CRB_PLUGIN_DIR . 'public/class-import-endpoint.php';

} else {

	require_once CRB_PLUGIN_DIR . 'includes/class-custom-rss-builder-authority.php';

}



function crb_activate() {

	if ( ! function_exists( 'dbDelta' ) ) {

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	}



	if ( crb_is_license_server_app_enabled() && function_exists( 'crb_ls_install_tables' ) ) {

		crb_ls_install_tables();

	}

	if ( function_exists( 'crb_demo_samples_install' ) ) {
		crb_demo_samples_install( false );
	}

	if ( crb_is_client_app_enabled() ) {

		Custom_RSS_Builder_RSS_Endpoint::register_rewrite_rules();

		crb_get_import_secret();

		crb_plugin();

		if ( function_exists( 'crb_import_scheduler' ) ) {

			$scheduler = crb_import_scheduler();

			if ( $scheduler ) {

				$scheduler->activate_all();

			}

		}

	} else {

		crb_plugin();

	}



	flush_rewrite_rules();

}



function crb_deactivate() {

	crb_plugin();



	if ( crb_is_client_app_enabled() && function_exists( 'crb_import_scheduler' ) ) {

		$scheduler = crb_import_scheduler();

		if ( $scheduler ) {

			$scheduler->clear_all();

		}

	}



	if ( crb_is_client_app_enabled() && function_exists( 'crb_import_loopback' ) ) {

		$loopback = crb_import_loopback();

		if ( $loopback ) {

			$loopback->clear_scheduled_ping();

		}

	}



	flush_rewrite_rules();

}



register_activation_hook( __FILE__, 'crb_activate' );

register_deactivation_hook( __FILE__, 'crb_deactivate' );



function crb_plugin() {

	static $instance = null;

	if ( null === $instance ) {

		if ( crb_is_client_app_enabled() ) {

			$instance = new Custom_RSS_Builder();

		} else {

			$instance = new Custom_RSS_Builder_Authority();

		}

	}

	return $instance;

}



add_action( 'plugins_loaded', 'crb_plugin', 5 );



if ( crb_is_client_app_enabled() ) {

	add_action( 'plugins_loaded', 'crb_license_ensure_active', 6 );

}



/**

 * デプロイ後に RSS 用 rewrite ルールを再生成する。

 */

function crb_maybe_flush_rewrite_rules() {

	if ( ! crb_is_client_app_enabled() || ! class_exists( 'Custom_RSS_Builder_RSS_Endpoint' ) ) {

		return;

	}



	$build   = defined( 'CRB_BUILD_ID' ) ? (string) CRB_BUILD_ID : CRB_VERSION;

	$stored  = (string) get_option( 'crb_rewrite_flush_build', '' );

	if ( $stored === $build ) {

		return;

	}



	Custom_RSS_Builder_RSS_Endpoint::register_rewrite_rules();

	flush_rewrite_rules( false );

	update_option( 'crb_rewrite_flush_build', $build, false );

}



add_action( 'init', 'crb_maybe_flush_rewrite_rules', 99 );


