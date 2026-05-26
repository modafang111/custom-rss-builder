<?php
/**
 * Plugin Name: Custom RSS Builder
 * Plugin URI:  https://example.com/custom-rss-builder
 * Description: RSS非対応WebページからFeed43風パターンで抽出し、RSS 2.0フィードを配信。抽出結果をWordPress投稿へ取り込み可能。
 * Version:     0.6.0
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

define( 'CRB_VERSION', '0.5.1' );
/** デプロイごとに更新（キャッシュ切り分け用） */
define( 'CRB_BUILD_ID', '20260527a' );
define( 'CRB_PLUGIN_FILE', __FILE__ );
define( 'CRB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CRB_OPTION_FEEDS', 'custom_rss_builder_feeds' );
define( 'CRB_OPTION_NEXT_ID', 'custom_rss_builder_next_feed_id' );
define( 'CRB_OPTION_IMPORT_KEY', 'custom_rss_builder_import_key' );
define( 'CRB_MAX_ITEMS', 20 );
define( 'CRB_CACHE_TTL', HOUR_IN_SECONDS );

require_once CRB_PLUGIN_DIR . 'includes/functions-sanitize.php';
require_once CRB_PLUGIN_DIR . 'includes/functions-css.php';
require_once CRB_PLUGIN_DIR . 'includes/functions-feed43.php';
require_once CRB_PLUGIN_DIR . 'includes/class-css-extractor.php';
require_once CRB_PLUGIN_DIR . 'includes/class-element-discovery.php';
require_once CRB_PLUGIN_DIR . 'includes/class-feed-manager.php';
require_once CRB_PLUGIN_DIR . 'includes/class-html-fetcher.php';
require_once CRB_PLUGIN_DIR . 'includes/class-html-parser.php';
require_once CRB_PLUGIN_DIR . 'includes/class-item-builder.php';
require_once CRB_PLUGIN_DIR . 'includes/class-content-template.php';
require_once CRB_PLUGIN_DIR . 'includes/class-rss-generator.php';
require_once CRB_PLUGIN_DIR . 'includes/class-post-importer.php';
require_once CRB_PLUGIN_DIR . 'includes/class-custom-rss-builder.php';
require_once CRB_PLUGIN_DIR . 'admin/class-admin-page.php';
require_once CRB_PLUGIN_DIR . 'public/class-rss-endpoint.php';
require_once CRB_PLUGIN_DIR . 'public/class-import-endpoint.php';

function crb_activate() {
	Custom_RSS_Builder_RSS_Endpoint::register_rewrite_rules();
	crb_get_import_secret();
	flush_rewrite_rules();
}

function crb_deactivate() {
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'crb_activate' );
register_deactivation_hook( __FILE__, 'crb_deactivate' );

function crb_plugin() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new Custom_RSS_Builder();
	}
	return $instance;
}

crb_plugin();
