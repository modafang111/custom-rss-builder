<?php
/**
 * フィード設定プリセット（スロット一括適用など）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI 変換（Pro）の既定対象スロット（0 始まりインデックス）。
 *
 * @return int[]
 */
function crb_feed_slot_preset_ai_indices() {
	return array( 6 );
}

/**
 * 取り込みタグ「スロットから生成」の既定スロット番号（1 始まり、{%n%} の n）。
 *
 * @return int[]
 */
function crb_feed_slot_preset_tag_slot_numbers() {
	return array( 5, 9, 11 );
}

/**
 * tag_sources の固定タグを残し、スロット由来だけをプリセットに差し替える。
 *
 * @param array<int, array{type:string,term_id?:int,slot?:int}> $tag_sources Existing sources.
 * @return array<int, array{type:string,term_id?:int,slot?:int}>
 */
function crb_feed_slot_preset_merge_tag_sources( array $tag_sources ) {
	$fixed = array();
	foreach ( $tag_sources as $item ) {
		if ( ! is_array( $item ) || 'fixed' !== (string) ( $item['type'] ?? '' ) ) {
			continue;
		}
		$fixed[] = $item;
	}

	$slot_sources = array();
	foreach ( crb_feed_slot_preset_tag_slot_numbers() as $slot_number ) {
		$slot_sources[] = array(
			'type' => 'slot',
			'slot' => (int) $slot_number,
		);
	}

	if ( ! function_exists( 'crb_sanitize_import_tag_sources' ) ) {
		return array_merge( $fixed, $slot_sources );
	}

	return crb_sanitize_import_tag_sources( array_merge( $fixed, $slot_sources ), false );
}

/**
 * 1 フィードにスロットプリセット（AI {%7%}、タグ {%5%}{%9%}{%11%}）を適用。
 *
 * @param array<string, mixed> $feed Feed row.
 * @return array<string, mixed>
 */
function crb_apply_feed_slot_preset( array $feed ) {
	$ai = is_array( $feed['ai'] ?? null ) ? $feed['ai'] : array();
	$ai['slots']       = crb_feed_slot_preset_ai_indices();
	$feed['ai']        = function_exists( 'crb_sanitize_ai_transform_settings' )
		? crb_sanitize_ai_transform_settings( $ai, false )
		: $ai;

	$import_defaults = function_exists( 'crb_plugin' ) && crb_plugin()->feed_manager
		? crb_plugin()->feed_manager->get_import_settings( $feed )
		: ( is_array( $feed['import'] ?? null ) ? $feed['import'] : array() );

	$existing_sources = is_array( $import_defaults['tag_sources'] ?? null )
		? $import_defaults['tag_sources']
		: array();

	$import_defaults['tag_sources'] = crb_feed_slot_preset_merge_tag_sources( $existing_sources );
	$feed['import']                 = $import_defaults;

	return $feed;
}

/**
 * 全フィードにスロットプリセットを適用して保存。
 *
 * @return array{updated:int,feed_ids:int[]}
 */
function crb_apply_feed_slot_preset_to_all_feeds() {
	if ( ! function_exists( 'crb_plugin' ) || ! crb_plugin()->feed_manager ) {
		return array(
			'updated'  => 0,
			'feed_ids' => array(),
		);
	}

	$manager = crb_plugin()->feed_manager;
	$feeds   = $manager->get_feeds();
	$updated = 0;
	$ids     = array();

	foreach ( $feeds as $feed ) {
		if ( ! is_array( $feed ) ) {
			continue;
		}
		$feed_id = (int) ( $feed['id'] ?? 0 );
		if ( $feed_id <= 0 ) {
			continue;
		}
		$manager->save_feed( crb_apply_feed_slot_preset( $feed ) );
		$ids[] = $feed_id;
		++$updated;
	}

	return array(
		'updated'  => $updated,
		'feed_ids' => $ids,
	);
}
