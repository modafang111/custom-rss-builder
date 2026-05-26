<?php
/**
 * Feed43 風スロット提案（サイト非依存）。
 *
 * {%1} = 主リンクのタイトル（表示テキスト / title 属性）
 * {%2} = 主リンクの URL（href）
 * {%3}…{%8} = 1件ブロック内の追加フィールド（画像・本文テキスト等）
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 要素調べる結果 → Feed43 用の suggested 配列（④と同じキー）。
 *
 * @param array<int, array<string, mixed>> $groups Discovery groups.
 * @return array<string, mixed>
 */
function crb_feed43_discover_suggest_slot_rules( array $groups ) {
	$map       = crb_extra_slot_storage_map();
	$suggested = array();
	$best_pri  = array();

	$link_groups  = array();
	$image_groups = array();
	$text_groups  = array();

	foreach ( $groups as $group ) {
		$kind = (string) ( $group['kind'] ?? '' );
		$role = (string) ( $group['role'] ?? '' );
		if ( 'link' === $kind || in_array( $role, array( 'link', 'primary_link', 'product_link' ), true ) ) {
			$link_groups[] = $group;
		} elseif ( 'image' === $kind || in_array( $role, array( 'image', 'eyecatch' ), true ) ) {
			$image_groups[] = $group;
		} elseif ( 'text' === $kind || 'text' === $role ) {
			$text_groups[] = $group;
		}
	}

	usort(
		$link_groups,
		static function ( $a, $b ) {
			return (int) ( $b['priority'] ?? 0 ) <=> (int) ( $a['priority'] ?? 0 );
		}
	);

	$primary = $link_groups[0] ?? null;
	if ( is_array( $primary ) && '' !== trim( (string) ( $primary['selector'] ?? '' ) ) ) {
		$suggested['link_selector'] = (string) $primary['selector'];
	}
	$primary_sel = trim( (string) ( $suggested['link_selector'] ?? '' ) );

	usort(
		$image_groups,
		static function ( $a, $b ) {
			return (int) ( $b['priority'] ?? 0 ) <=> (int) ( $a['priority'] ?? 0 );
		}
	);
	if ( ! empty( $image_groups ) ) {
		crb_feed43_apply_group_to_slot( $suggested, $best_pri, $map, 3, $image_groups[0], 'src' );
	}

	usort(
		$text_groups,
		static function ( $a, $b ) {
			$la = crb_feed43_group_text_length( $a );
			$lb = crb_feed43_group_text_length( $b );
			if ( $la !== $lb ) {
				return $lb <=> $la;
			}
			return (int) ( $b['priority'] ?? 0 ) <=> (int) ( $a['priority'] ?? 0 );
		}
	);

	$text_slot_indices = array( 2, 4, 5, 6, 7 );
	$ti                = 0;
	foreach ( $text_groups as $text_group ) {
		if ( $ti >= count( $text_slot_indices ) ) {
			break;
		}
		$sel = trim( (string) ( $text_group['selector'] ?? '' ) );
		if ( '' === $sel || $sel === $primary_sel ) {
			continue;
		}
		crb_feed43_apply_group_to_slot( $suggested, $best_pri, $map, $text_slot_indices[ $ti ], $text_group, 'text' );
		++$ti;
	}

	$image_sel = crb_discover_pick_image_selector( $groups );
	if ( '' !== $image_sel && '' === trim( (string) ( $suggested['image_selector'] ?? '' ) ) ) {
		$suggested['image_selector'] = $image_sel;
		$suggested['slot_mode_4']    = array(
			'selector' => $image_sel,
			'mode'     => 'src',
		);
	}

	return $suggested;
}

/**
 * @param array<string, mixed> $group Group.
 */
function crb_feed43_group_text_length( array $group ) {
	$max = 0;
	if ( ! empty( $group['samples'] ) && is_array( $group['samples'] ) ) {
		foreach ( $group['samples'] as $sample ) {
			if ( ! is_array( $sample ) ) {
				continue;
			}
			$len = mb_strlen( trim( (string) ( $sample['text'] ?? '' ) ) );
			if ( $len > $max ) {
				$max = $len;
			}
		}
	}
	return $max;
}

/**
 * @param array<string, mixed>                              $suggested Suggested (by ref).
 * @param array<string, int>                                $best_pri  Best priority.
 * @param array<int, array{config_key: string, mode_key: string, default_mode: string}> $map       Slot map.
 * @param int                                                 $idx       Slot index.
 * @param array<string, mixed>                              $group     Group.
 * @param string                                              $mode      Extract mode.
 */
function crb_feed43_apply_group_to_slot( array &$suggested, array &$best_pri, array $map, $idx, array $group, $mode ) {
	if ( ! isset( $map[ $idx ] ) ) {
		return;
	}
	$selector = trim( (string) ( $group['selector'] ?? '' ) );
	if ( '' === $selector ) {
		return;
	}
	$pri      = (int) ( $group['priority'] ?? 0 );
	$mode_key = $map[ $idx ]['mode_key'];
	if ( isset( $suggested[ $mode_key ] ) && $pri <= (int) ( $best_pri[ $mode_key ] ?? -1 ) ) {
		return;
	}
	$mode = crb_sanitize_slot_extract_mode( $mode );
	$suggested[ $mode_key ]                  = array(
		'selector' => $selector,
		'mode'     => $mode,
	);
	$suggested[ $map[ $idx ]['config_key'] ] = $selector;
	$best_pri[ $mode_key ]                   = $pri;
}
