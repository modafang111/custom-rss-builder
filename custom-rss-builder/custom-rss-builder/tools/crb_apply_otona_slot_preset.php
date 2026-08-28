<?php
/**
 * otona-column.com 向け：全フィードにスロットプリセットを一度だけ適用するワンショット。
 * 使用後は必ず削除すること。
 *
 * 適用内容:
 * - AI 変換対象: {%7%}（インデックス 6）
 * - タグ（スロットから生成）: {%5%}, {%9%}, {%11%}
 *
 * @package Custom_RSS_Builder
 */

define( 'CRB_OTONA_SLOT_PRESET_TOKEN', 'crb-otona-slot-20260710' );

/**
 * @param array<string, mixed> $payload Payload.
 * @param int                  $code    HTTP status.
 * @return never
 */
function crb_otona_slot_preset_json_exit( array $payload, $code = 200 ) {
	header( 'Content-Type: application/json; charset=UTF-8' );
	if ( $code >= 400 ) {
		http_response_code( (int) $code );
	}
	echo wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	exit;
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
	echo wp_json_encode( array( 'ok' => false, 'error' => 'wp-load not found' ) );
	exit;
}

require_once $wp_load;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$token = isset( $_REQUEST['token'] ) ? (string) $_REQUEST['token'] : '';
if ( $token !== CRB_OTONA_SLOT_PRESET_TOKEN ) {
	crb_otona_slot_preset_json_exit( array( 'ok' => false, 'error' => 'forbidden' ), 403 );
}

if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	crb_otona_slot_preset_json_exit(
		array(
			'ok'    => false,
			'error' => 'login required',
			'hint'  => 'WordPress 管理画面にログインした状態で再度アクセスしてください。',
		),
		403
	);
}

if ( function_exists( 'crb_apply_feed_slot_preset_to_all_feeds' ) ) {
	$result = crb_apply_feed_slot_preset_to_all_feeds();
	crb_otona_slot_preset_json_exit(
		array(
			'ok'        => true,
			'updated'   => (int) ( $result['updated'] ?? 0 ),
			'feed_ids'  => array_map( 'intval', (array) ( $result['feed_ids'] ?? array() ) ),
			'ai_slots'  => function_exists( 'crb_feed_slot_preset_ai_indices' ) ? crb_feed_slot_preset_ai_indices() : array( 6 ),
			'tag_slots' => function_exists( 'crb_feed_slot_preset_tag_slot_numbers' ) ? crb_feed_slot_preset_tag_slot_numbers() : array( 5, 9, 11 ),
		)
	);
}

if ( ! function_exists( 'crb_plugin' ) || ! crb_plugin()->feed_manager ) {
	crb_otona_slot_preset_json_exit( array( 'ok' => false, 'error' => 'plugin not loaded' ), 500 );
}

$ai_indices = array( 6 );
$tag_slots  = array( 5, 9, 11 );
$manager    = crb_plugin()->feed_manager;
$feeds      = $manager->get_feeds();
$updated    = 0;
$feed_ids   = array();

foreach ( $feeds as $feed ) {
	if ( ! is_array( $feed ) ) {
		continue;
	}
	$feed_id = (int) ( $feed['id'] ?? 0 );
	if ( $feed_id <= 0 ) {
		continue;
	}

	$ai = is_array( $feed['ai'] ?? null ) ? $feed['ai'] : array();
	$ai['slots'] = $ai_indices;
	if ( function_exists( 'crb_sanitize_ai_transform_settings' ) ) {
		$ai = crb_sanitize_ai_transform_settings( $ai, false );
	}
	$feed['ai'] = $ai;

	$import = $manager->get_import_settings( $feed );
	$fixed  = array();
	foreach ( (array) ( $import['tag_sources'] ?? array() ) as $item ) {
		if ( is_array( $item ) && 'fixed' === (string) ( $item['type'] ?? '' ) ) {
			$fixed[] = $item;
		}
	}
	$slot_sources = array();
	foreach ( $tag_slots as $slot_number ) {
		$slot_sources[] = array(
			'type' => 'slot',
			'slot' => (int) $slot_number,
		);
	}
	if ( function_exists( 'crb_sanitize_import_tag_sources' ) ) {
		$import['tag_sources'] = crb_sanitize_import_tag_sources( array_merge( $fixed, $slot_sources ), false );
	} else {
		$import['tag_sources'] = array_merge( $fixed, $slot_sources );
	}
	$feed['import'] = $import;

	$manager->save_feed( $feed );
	$feed_ids[] = $feed_id;
	++$updated;
}

crb_otona_slot_preset_json_exit(
	array(
		'ok'        => true,
		'updated'   => $updated,
		'feed_ids'  => $feed_ids,
		'ai_slots'  => $ai_indices,
		'tag_slots' => $tag_slots,
		'fallback'  => true,
	)
);
