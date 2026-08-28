<?php
/**
 * Plugin Name: CRB One-shot: DLsite Affiliate Rewrite
 * Description: 既存投稿の DLsite 直リンクを dlaf.jp アフィリエイト URL に一括変換（実行後に無効化・削除してください）。
 * Version: 1.0.0
 * Author: Custom RSS Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array{scanned:int,changed:int,replacements:int,error:string}
 */
function crb_oneshot_dlsite_affiliate_run() {
	global $wpdb;

	$pattern     = '#https://www\.dlsite\.com/([^/]+)/work/=/product_id/#iu';
	$replacement = 'https://dlaf.jp/$1/dlaf/=/t/s/link/work/aid/dslite_123/id/';

	$result = array(
		'scanned'      => 0,
		'changed'      => 0,
		'replacements' => 0,
		'error'        => '',
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts}
		WHERE post_type = 'post'
		AND post_status IN ('publish','draft','pending','private','future')
		AND post_content LIKE '%dlsite.com%'
		ORDER BY ID ASC"
	);
	if ( ! is_array( $ids ) ) {
		$ids = array();
	}

	foreach ( $ids as $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			continue;
		}
		++$result['scanned'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$content = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id )
		);
		if ( '' === $content ) {
			continue;
		}

		$count = 0;
		$next  = preg_replace( $pattern, $replacement, $content, -1, $count );
		if ( ! is_string( $next ) || $count < 1 || $next === $content ) {
			continue;
		}

		++$result['changed'];
		$result['replacements'] += (int) $count;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->posts,
			array( 'post_content' => $next ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );
	}

	return $result;
}

register_activation_hook(
	__FILE__,
	static function () {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$result = crb_oneshot_dlsite_affiliate_run();
		update_option(
			'crb_oneshot_dlsite_affiliate_result',
			array(
				'time'         => time(),
				'scanned'      => (int) ( $result['scanned'] ?? 0 ),
				'changed'      => (int) ( $result['changed'] ?? 0 ),
				'replacements' => (int) ( $result['replacements'] ?? 0 ),
				'error'        => (string) ( $result['error'] ?? '' ),
			),
			false
		);
	}
);

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$result = get_option( 'crb_oneshot_dlsite_affiliate_result' );
		if ( ! is_array( $result ) ) {
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>CRB One-shot:</strong> DLsite → dlaf 変換完了 — 対象 %1$d 件 / 更新 %2$d 件 / 置換 %3$d 箇所。このプラグインは無効化して削除してください。</p></div>',
			(int) ( $result['scanned'] ?? 0 ),
			(int) ( $result['changed'] ?? 0 ),
			(int) ( $result['replacements'] ?? 0 )
		);
	}
);
