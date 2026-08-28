<?php
/**
 * ③ 試し読みプレビュー（discover 連携）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 範囲内の試し読みプレビュー行（{%n}・セレクタ・取り方・取れた値）。
 *
 * @param string                          $html              HTML.
 * @param string                          $scope_selector    範囲 CSS.
 * @param string                          $item_selector     1件ブロック CSS.
 * @param array<int, array<string,mixed>> $groups            discover groups.
 * @param string                          $base_url          Base URL.
 * @param bool                            $use_discover_dom  要素探索 AJAX 向け軽量 DOM.
 * @param array<string, string>|null           $probe_config           指定時はその CSS 設定で試し読み（④フォーム優先）。
 * @param array<int, array<string, mixed>>|null $sequential_proposals  連番提案（④が空のとき試し読みに使用）。
 * @return array{context_note: string, item_count: int, rows: array<int, array<string,mixed>>, preview_source?: string}
 */
function crb_build_discover_scope_preview( $html, $scope_selector, $item_selector, array $groups, $base_url = '', $use_discover_dom = false, $probe_config = null, $sequential_proposals = null ) {
	$empty = array(
		'context_note' => '',
		'item_count'   => 0,
		'rows'         => array(),
	);

	$dom = function_exists( 'crb_discover_dom_load_for_flow' )
		? crb_discover_dom_load_for_flow( $html, (bool) $use_discover_dom )
		: crb_dom_load_html( $html );
	if ( is_wp_error( $dom ) ) {
		$empty['context_note'] = $dom->get_error_message();
		return $empty;
	}

	$root = crb_dom_parse_root( $dom );
	if ( is_wp_error( $root ) ) {
		$empty['context_note'] = $root->get_error_message();
		return $empty;
	}

	$xpath          = new DOMXPath( $dom );
	$scope_selector = trim( (string) $scope_selector );
	$scope_el       = function_exists( 'crb_discover_resolve_scope_for_flow' )
		? crb_discover_resolve_scope_for_flow( $xpath, $root, $scope_selector, (bool) $use_discover_dom )
		: crb_resolve_scope_element( $xpath, $root, $scope_selector );
	if ( '' !== $scope_selector && ! ( $scope_el instanceof DOMElement ) ) {
		$empty['context_note'] = crb_scope_miss_message( $html, $base_url, $scope_selector, $xpath, $root );
		return $empty;
	}
	if ( ! ( $scope_el instanceof DOMElement ) ) {
		$scope_el = $root;
	}

	$item_selector = trim( (string) $item_selector );
	$item_count    = 1;
	$item_fallback = false;
	$preview_limit = (int) CRB_RECORD_PREVIEW_LIMIT;
	$containers = crb_collect_item_containers( $xpath, $scope_el, $item_selector, $preview_limit, $scope_selector );
	if ( is_wp_error( $containers ) ) {
		if ( '' !== $item_selector ) {
			$message = $containers->get_error_message();
			return array(
				'context_note'        => $message,
				'item_count'          => 0,
				'preview_shown'       => 0,
				'rows'                => array(),
				'item_previews'       => array(),
				'item_selector_error' => $message,
			);
		}
		$containers    = array( $scope_el );
		$item_fallback = true;
	} elseif ( '' !== $item_selector ) {
		$item_xpath = crb_css_to_xpath( crb_css_strip_selector_prefix( $scope_selector, $item_selector ) );
		if ( ! is_wp_error( $item_xpath ) ) {
			$all_nodes = crb_xpath_query( $xpath, (string) $item_xpath, $scope_el );
			if ( null !== $all_nodes ) {
				$all = array();
				for ( $i = 0; $i < $all_nodes->length; $i++ ) {
					$node = $all_nodes->item( $i );
					if ( $node instanceof DOMElement ) {
						$all[] = $node;
					}
				}
				$item_count = count( crb_filter_top_level_items( $scope_el, $all ) );
			}
		}
	} else {
		$item_count = 1;
	}

	$preview_source = 'suggested';
	if ( is_array( $probe_config ) && function_exists( 'crb_css_config_has_assigned_slots' ) && crb_css_config_has_assigned_slots( $probe_config ) ) {
		$config                   = $probe_config;
		$config['scope_selector'] = (string) $scope_selector;
		$config['item_selector']  = (string) $item_selector;
		$preview_source           = 'form';
	} elseif ( is_array( $sequential_proposals ) && ! empty( $sequential_proposals ) && function_exists( 'crb_css_config_from_sequential_proposals' ) ) {
		$config         = crb_css_config_from_sequential_proposals( $scope_selector, $item_selector, $sequential_proposals );
		$preview_source = 'sequential';
	} else {
		$suggested = crb_discover_suggest_slot_rules( $groups );
		$config    = crb_css_config_from_discover_suggested( $scope_selector, $item_selector, $suggested );
	}
	$extractor     = new Custom_RSS_Builder_Css_Extractor();
	$preview_slots = crb_get_effective_slot_count();

	$item_previews = array();
	foreach ( $containers as $idx => $container ) {
		$probed_rows     = $extractor->probe_record_slots_in_context( $xpath, $container, $config, $base_url, $preview_slots );
		$item_previews[] = array(
			'index' => (int) $idx,
			'rows'  => $probed_rows,
		);
	}
	$item_previews = crb_dedupe_scope_item_previews( $item_previews );
	usort(
		$item_previews,
		static function ( $a, $b ) {
			$a_rows = is_array( $a['rows'] ?? null ) ? $a['rows'] : array();
			$b_rows = is_array( $b['rows'] ?? null ) ? $b['rows'] : array();
			$a_has  = false;
			$b_has  = false;
			foreach ( $a_rows as $row ) {
				if ( ! empty( trim( (string) ( $row['value'] ?? '' ) ) ) ) {
					$a_has = true;
					break;
				}
			}
			foreach ( $b_rows as $row ) {
				if ( ! empty( trim( (string) ( $row['value'] ?? '' ) ) ) ) {
					$b_has = true;
					break;
				}
			}
			if ( $a_has !== $b_has ) {
				return $a_has ? -1 : 1;
			}
			return (int) ( $a['index'] ?? 0 ) <=> (int) ( $b['index'] ?? 0 );
		}
	);
	$has_non_empty = false;
	foreach ( $item_previews as $preview_item ) {
		$preview_rows = is_array( $preview_item['rows'] ?? null ) ? $preview_item['rows'] : array();
		if ( crb_scope_preview_rows_has_value( $preview_rows ) ) {
			$has_non_empty = true;
			break;
		}
	}
	if ( $has_non_empty ) {
		$item_previews = array_values(
			array_filter(
				$item_previews,
				static function ( $preview_item ) {
					$preview_rows = is_array( $preview_item['rows'] ?? null ) ? $preview_item['rows'] : array();
					return crb_scope_preview_rows_has_value( $preview_rows );
				}
			)
		);
	}

	$rows          = ! empty( $item_previews[0]['rows'] ) ? $item_previews[0]['rows'] : array();
	$preview_shown = count( $item_previews );

	$note = '';
	if ( '' !== $scope_selector ) {
		$note = $scope_selector;
	} else {
		$note = __( 'ページ全体', 'custom-rss-builder' );
	}
	if ( '' !== $item_selector ) {
		$note .= ' / ' . $item_selector;
		if ( $item_fallback ) {
			$note .= ' (' . __( '範囲内に1件ブロックなし→範囲を1件として試読', 'custom-rss-builder' ) . ')';
		} else {
			$note .= ' (' . sprintf(
				/* translators: %d: item count */
				__( '%d件', 'custom-rss-builder' ),
				$item_count
			) . ')';
		}
	} else {
		$note .= ' / ' . __( '範囲を1件として試読', 'custom-rss-builder' );
	}

	if ( $preview_shown > 1 ) {
		$note .= ' / ' . sprintf(
			/* translators: 1: shown preview count, 2: total items in scope */
			__( '試し読み %1$d件表示（範囲内 %2$d件）', 'custom-rss-builder' ),
			$preview_shown,
			$item_count
		);
	}

	return array(
		'context_note'    => $note,
		'item_count'      => $item_count,
		'preview_shown'   => $preview_shown,
		'rows'            => $rows,
		'item_previews'   => $item_previews,
		'preview_source'  => $preview_source,
	);
}

/**
 * 試し読み候補の重複を除去する。
 * URL など単一スロットを特別扱いせず、表示値の完全一致だけを重複とみなす。
 *
 * @param array<int, array<string, mixed>> $item_previews preview rows.
 * @return array<int, array<string, mixed>>
 */
function crb_dedupe_scope_item_previews( array $item_previews ) {
	$seen = array();
	$out  = array();
	foreach ( $item_previews as $item ) {
		$rows = is_array( $item['rows'] ?? null ) ? $item['rows'] : array();
		$sig  = crb_scope_item_preview_signature( $rows );
		if ( '' !== $sig && isset( $seen[ $sig ] ) ) {
			continue;
		}
		if ( '' !== $sig ) {
			$seen[ $sig ] = true;
		}
		$out[] = $item;
	}
	return $out;
}

/**
 * 1件試し読みのシグネチャを作る。
 * 各スロットの表示値を正規化して連結し、完全一致のみ同一と判定する。
 *
 * @param array<int, array<string, mixed>> $rows slot rows.
 * @return string
 */
function crb_scope_item_preview_signature( array $rows ) {
	$parts = array();
	foreach ( $rows as $row ) {
		$idx = (int) ( $row['index'] ?? -1 );
		if ( $idx < 0 ) {
			continue;
		}
		$val = trim( (string) ( $row['value'] ?? '' ) );
		$val          = preg_replace( '/\s+/u', ' ', $val );
		$parts[ $idx ] = (string) $val;
	}
	if ( empty( $parts ) ) {
		return '';
	}
	ksort( $parts );
	return 'rows:' . substr( md5( wp_json_encode( $parts ) ), 0, 24 );
}

/**
 * 試し読み行に 1 つでも値があるか。
 *
 * @param array<int, array<string, mixed>> $rows Slot rows.
 * @return bool
 */
function crb_scope_preview_rows_has_value( array $rows ) {
	foreach ( $rows as $row ) {
		if ( ! empty( trim( (string) ( $row['value'] ?? '' ) ) ) ) {
			return true;
		}
	}
	return false;
}

