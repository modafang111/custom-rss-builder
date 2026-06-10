<?php
/**
 * プラグイン削除時に DB 上の設定を消す（再インストールで Pro が残る問題の対策）。
 *
 * WordPress は uninstall.php が無いと「削除」しても wp_options が残る。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * @param array<int, string> $names Option names.
 */
function crb_uninstall_delete_options( array $names ) {
	foreach ( $names as $name ) {
		if ( '' !== $name ) {
			delete_option( $name );
		}
	}
}

$plugin_dir = __DIR__;
$is_client  = is_readable( $plugin_dir . '/includes/class-feed-manager.php' );

if ( $is_client ) {
	crb_uninstall_delete_options(
		array(
			'crb_license_settings',
			'custom_rss_builder_feeds',
			'custom_rss_builder_next_feed_id',
			'custom_rss_builder_import_key',
			'crb_ai_settings',
			'crb_rewrite_flush_build',
		)
	);
} else {
	// 正本（license-server）: 顧客ライセンス DB は消さない。組み込み用の設定のみ。
	crb_uninstall_delete_options(
		array(
			'crb_license_settings',
			'crb_rewrite_flush_build',
		)
	);
}
