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

/**
 * 提案行の重複判定キー。
 *
 * @param string $selector Selector.
 * @param string $mode     Mode.
 * @param string $attr     Attr name.
 * @return string
 */
function crb_sequential_proposal_dedupe_key( $selector, $mode, $attr = '' ) {
	return strtolower( trim( (string) $selector ) ) . '|' . trim( (string) $mode ) . '|' . strtolower( trim( (string) $attr ) );
}

/**
 * 候補表の1行を連番提案用の mode / attr に正規化。
 *
 * @param array<string, string> $row Candidate row.
 * @return array{mode: string, attr: string}
 */
function crb_sequential_proposal_from_candidate_row( array $row ) {
	$rule = crb_candidate_mode_to_slot_rule( (string) ( $row['mode'] ?? '' ) );
	$mode = (string) ( $rule['mode'] ?? 'text' );
	$attr = (string) ( $rule['attr'] ?? '' );
	return array(
		'mode' => $mode,
		'attr' => $attr,
	);
}

/**
 * 追加フィールドの並び優先度（大きいほど先）。
 *
 * @param string $mode        Mode.
 * @param int    $match_count Match count.
 * @return int
 */
function crb_sequential_extra_sort_score( $mode, $match_count ) {
	$base = max( 0, (int) $match_count ) * 10;
	if ( 'src' === $mode ) {
		return 1000 + $base;
	}
	if ( 'attr' === $mode ) {
		return 500 + $base;
	}
	if ( 'href' === $mode ) {
		return 100 + $base;
	}
	return $base;
}

/**
 * 要素調べる結果を {%1%}・{%2%}・{%3%}… と飛び番号なく連番で提案する。
 *
 * @param array<int, array<string, mixed>>  $groups         Discovery groups.
 * @param array<int, array<string, string>> $candidate_rows Extract candidates rows.
 * @return array<int, array<string, mixed>>
 */
function crb_build_sequential_slot_proposals( array $groups, array $candidate_rows = array() ) {
	$max_slots = defined( 'CRB_RECORD_SLOT_COUNT' ) ? (int) CRB_RECORD_SLOT_COUNT : 20;
	$proposals = array();
	$seen      = array();

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

	$primary_sel = '';
	$primary     = $link_groups[0] ?? null;
	if ( is_array( $primary ) ) {
		$primary_sel = trim( (string) ( $primary['selector'] ?? '' ) );
	}
	if ( '' === $primary_sel && ! empty( $candidate_rows ) ) {
		$href_rows = array();
		foreach ( $candidate_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$parsed = crb_sequential_proposal_from_candidate_row( $row );
			if ( 'href' !== (string) ( $parsed['mode'] ?? '' ) ) {
				continue;
			}
			$sel = trim( (string) ( $row['selector'] ?? '' ) );
			if ( '' === $sel ) {
				continue;
			}
			$href_rows[] = array(
				'selector'    => $sel,
				'match_count' => (int) ( $row['match_count'] ?? 0 ),
			);
		}
		usort(
			$href_rows,
			static function ( $a, $b ) {
				return (int) ( $b['match_count'] ?? 0 ) <=> (int) ( $a['match_count'] ?? 0 );
			}
		);
		if ( ! empty( $href_rows ) ) {
			$primary_sel = (string) $href_rows[0]['selector'];
		}
	}
	if ( '' !== $primary_sel ) {
		$proposals[] = array(
			'index'    => 0,
			'token'    => '{%1}',
			'selector' => $primary_sel,
			'mode'     => 'text',
			'attr'     => '',
		);
		$proposals[] = array(
			'index'    => 1,
			'token'    => '{%2}',
			'selector' => $primary_sel,
			'mode'     => 'href',
			'attr'     => '',
		);
		$seen[ crb_sequential_proposal_dedupe_key( $primary_sel, 'text' ) ]  = true;
		$seen[ crb_sequential_proposal_dedupe_key( $primary_sel, 'href' ) ] = true;
	}

	$extras = array();

	usort(
		$image_groups,
		static function ( $a, $b ) {
			return (int) ( $b['priority'] ?? 0 ) <=> (int) ( $a['priority'] ?? 0 );
		}
	);
	foreach ( $image_groups as $image_group ) {
		$sel = trim( (string) ( $image_group['selector'] ?? '' ) );
		if ( '' === $sel || $sel === $primary_sel ) {
			continue;
		}
		$extras[] = array(
			'selector'    => $sel,
			'mode'        => 'src',
			'attr'        => '',
			'match_count' => 0,
			'score'       => crb_sequential_extra_sort_score( 'src', 0 ) + (int) ( $image_group['priority'] ?? 0 ),
		);
	}

	foreach ( $candidate_rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$candidate_mode = (string) ( $row['mode'] ?? '' );
		if ( 'html' === $candidate_mode ) {
			continue;
		}
		$parsed   = crb_sequential_proposal_from_candidate_row( $row );
		$mode     = (string) ( $parsed['mode'] ?? 'text' );
		$attr     = (string) ( $parsed['attr'] ?? '' );
		$selector = trim( (string) ( $row['selector'] ?? '' ) );
		if ( '' === $selector ) {
			continue;
		}
		if ( 'href' === $mode && ( $selector === $primary_sel || '' === $primary_sel ) ) {
			continue;
		}
		if ( 'text' === $mode && $selector === $primary_sel ) {
			continue;
		}
		$match_count = (int) ( $row['match_count'] ?? 0 );
		$extras[]    = array(
			'selector'    => $selector,
			'mode'        => $mode,
			'attr'        => $attr,
			'match_count' => $match_count,
			'score'       => crb_sequential_extra_sort_score( $mode, $match_count ),
		);
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
	foreach ( $text_groups as $text_group ) {
		$sel = trim( (string) ( $text_group['selector'] ?? '' ) );
		if ( '' === $sel || $sel === $primary_sel ) {
			continue;
		}
		$extras[] = array(
			'selector'    => $sel,
			'mode'        => 'text',
			'attr'        => '',
			'match_count' => 0,
			'score'       => crb_sequential_extra_sort_score( 'text', 0 ) + (int) ( $text_group['priority'] ?? 0 ),
		);
	}

	usort(
		$extras,
		static function ( $a, $b ) {
			$sa = (int) ( $a['score'] ?? 0 );
			$sb = (int) ( $b['score'] ?? 0 );
			if ( $sa !== $sb ) {
				return $sb <=> $sa;
			}
			return strcmp( (string) ( $a['selector'] ?? '' ), (string) ( $b['selector'] ?? '' ) );
		}
	);

	$next_index = 2;
	foreach ( $extras as $extra ) {
		if ( $next_index >= $max_slots ) {
			break;
		}
		$selector = trim( (string) ( $extra['selector'] ?? '' ) );
		$mode     = (string) ( $extra['mode'] ?? 'text' );
		$attr     = (string) ( $extra['attr'] ?? '' );
		if ( '' === $selector ) {
			continue;
		}
		$key = crb_sequential_proposal_dedupe_key( $selector, $mode, $attr );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$proposals[]  = array(
			'index'    => $next_index,
			'token'    => '{%' . ( $next_index + 1 ) . '}',
			'selector' => $selector,
			'mode'     => $mode,
			'attr'     => $attr,
		);
		++$next_index;
	}

	return $proposals;
}

/**
 * 連番提案 → ④の CSS 設定配列。
 *
 * @param string                              $scope_selector Scope.
 * @param string                              $item_selector  Item block.
 * @param array<int, array<string, mixed>>    $proposals      crb_build_sequential_slot_proposals() の戻り値。
 * @return array<string, string>
 */
function crb_css_config_from_sequential_proposals( $scope_selector, $item_selector, array $proposals ) {
	$raw                   = crb_empty_css_config();
	$raw['scope_selector'] = (string) $scope_selector;
	$raw['item_selector']  = (string) $item_selector;
	$map                   = crb_extra_slot_storage_map();

	foreach ( $proposals as $proposal ) {
		if ( ! is_array( $proposal ) ) {
			continue;
		}
		$index    = (int) ( $proposal['index'] ?? -1 );
		$selector = trim( (string) ( $proposal['selector'] ?? '' ) );
		$mode     = trim( (string) ( $proposal['mode'] ?? 'text' ) );
		$attr     = trim( (string) ( $proposal['attr'] ?? '' ) );
		if ( '' === $selector ) {
			continue;
		}

		if ( 0 === $index ) {
			if ( 'text' === $mode ) {
				$raw['title_mode']     = 'selector';
				$raw['title_selector'] = $selector;
			} elseif ( 'href' === $mode ) {
				$raw['title_mode']     = 'el_href';
				$raw['title_selector'] = $selector;
			} elseif ( 'attr' === $mode && '' !== $attr ) {
				$raw['title_mode']     = 'el_attr';
				$raw['title_selector'] = $selector;
				$raw['title_attr']     = $attr;
			}
			continue;
		}

		if ( 1 === $index ) {
			$raw['link_selector'] = $selector;
			continue;
		}

		if ( ! isset( $map[ $index ] ) ) {
			continue;
		}
		$meta                         = $map[ $index ];
		$raw[ $meta['config_key'] ]   = $selector;
		$raw[ $meta['mode_key'] ]     = crb_sanitize_slot_extract_mode( $mode );
		$attr_key                     = 'slot_attr_' . ( $index + 1 );
		$raw[ $attr_key ]             = ( 'attr' === $mode && '' !== $attr ) ? $attr : '';
	}

	return crb_sanitize_css_config( $raw );
}
