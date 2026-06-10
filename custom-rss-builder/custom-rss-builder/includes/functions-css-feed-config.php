<?php
/**
 * フィード CSS 設定・サニタイズ・範囲解決。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function crb_extract_items_from_html( $html, array $feed ) {
	$extractor = new Custom_RSS_Builder_Css_Extractor();
	$config    = crb_get_feed_css_config( $feed );
	$base_url  = (string) ( $feed['url'] ?? '' );
	$result    = $extractor->extract( $html, $config, $base_url );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$rows = crb_normalize_extract_rows_to_slots( $result );
	if ( function_exists( 'crb_feed_apply_link_rewrites_to_rows' ) ) {
		$rows = crb_feed_apply_link_rewrites_to_rows( $rows, $feed );
	}
	return $rows;
}

/**
 * @param array<string, mixed> $feed Feed.
 * @return string template|css
 */
function crb_get_feed_extraction_mode( array $feed ) {
	return 'css';
}

/**
 * RSS 生成向けの既定マッピング。
 *
 * @return array<string, int>
 */
function crb_default_rss_mapping() {
	return array(
		'title'       => 0,
		'link'        => 1,
		'description' => 2,
		'date'        => -1,
	);
}

/**
 * @param array<string, mixed> $feed Feed.
 * @return array<string, string>
 */
function crb_get_feed_css_config( array $feed ) {
	$css   = is_array( $feed['css'] ?? null ) ? $feed['css'] : array();
	$base  = array(
		'scope_selector'        => (string) ( $css['scope_selector'] ?? '' ),
		'item_selector'         => (string) ( $css['item_selector'] ?? '' ),
		'link_selector'         => (string) ( $css['link_selector'] ?? '' ),
		'title_mode'            => (string) ( $css['title_mode'] ?? 'attr' ),
		'title_attr'            => (string) ( $css['title_attr'] ?? 'title' ),
		'title_selector'        => (string) ( $css['title_selector'] ?? '' ),
		'image_selector'        => (string) ( $css['image_selector'] ?? '' ),
		'author_selector'       => (string) ( $css['author_selector'] ?? '' ),
		'review_title_selector' => (string) ( $css['review_title_selector'] ?? '' ),
		'summary_selector'      => (string) ( $css['summary_selector'] ?? '' ),
		'category_selector'     => (string) ( $css['category_selector'] ?? '' ),
		'review_body_selector'  => (string) ( $css['review_body_selector'] ?? '' ),
	);
	foreach ( crb_extra_slot_storage_map() as $meta ) {
		$key = $meta['config_key'];
		if ( ! isset( $base[ $key ] ) ) {
			$base[ $key ] = (string) ( $css[ $key ] ?? '' );
		}
		$base[ $meta['mode_key'] ] = (string) ( $css[ $meta['mode_key'] ] ?? $meta['default_mode'] );
	}
	return $base;
}

/**
 * 保存・検証用: 抽出に使う CSS キー一覧（scope 含む）。
 *
 * @return string[]
 */
function crb_css_selector_config_keys() {
	$keys = array(
		'scope_selector',
		'item_selector',
		'link_selector',
		'title_selector',
		'image_selector',
		'author_selector',
		'review_title_selector',
		'summary_selector',
		'category_selector',
		'review_body_selector',
	);
	foreach ( crb_extra_slot_storage_map() as $meta ) {
		$keys[] = $meta['config_key'];
	}
	return $keys;
}

/**
 * 範囲・1件ブロック・スロットのいずれかが入力されているか。
 *
 * @param array<string, mixed> $css CSS 設定。
 * @return bool
 */
function crb_css_config_has_extraction_path( array $css ) {
	foreach ( crb_css_selector_config_keys() as $key ) {
		if ( '' !== trim( (string) ( $css[ $key ] ?? '' ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * 保存・プレビュー時に抽出指定が無いときの案内（範囲は必須ではないことを明示）。
 *
 * @return string
 */
function crb_css_missing_extraction_path_message() {
	return __(
		'保存・プレビューには、次のいずれかを1つ以上指定してください。「1件ぶんの区切り」だけでも構います（その場合「一覧の場所」は空欄のままで問題ありません）。④のスロット（{%2%} など）だけでも可。',
		'custom-rss-builder'
	);
}

/**
 * scope 以外で、1件ぶんの抽出に使うセレクタがあるか。
 *
 * @param array<string, mixed> $css CSS 設定。
 * @return bool
 */
function crb_css_config_has_record_extraction( array $css ) {
	foreach ( crb_css_selector_config_keys() as $key ) {
		if ( 'scope_selector' === $key ) {
			continue;
		}
		if ( '' !== trim( (string) ( $css[ $key ] ?? '' ) ) ) {
			return true;
		}
	}
	return false;
}
function crb_sanitize_css_config( $raw ) {
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}
	$title_mode = sanitize_key( (string) ( $raw['title_mode'] ?? 'attr' ) );
	$title_modes = array( 'attr', 'text', 'selector', 'el_text', 'el_html', 'el_src', 'el_href', 'el_attr' );
	if ( ! in_array( $title_mode, $title_modes, true ) ) {
		$title_mode = 'attr';
	}
	$out = array(
		'scope_selector'        => crb_sanitize_css_selector( (string) ( $raw['scope_selector'] ?? '' ) ),
		'item_selector'         => crb_sanitize_css_selector( (string) ( $raw['item_selector'] ?? '' ) ),
		'link_selector'         => crb_sanitize_css_selector( (string) ( $raw['link_selector'] ?? '' ) ),
		'title_mode'            => $title_mode,
		'title_attr'            => crb_sanitize_css_attr_name( (string) ( $raw['title_attr'] ?? 'title' ) ),
		'title_selector'        => crb_sanitize_css_selector( (string) ( $raw['title_selector'] ?? '' ) ),
		'image_selector'        => crb_sanitize_css_selector( (string) ( $raw['image_selector'] ?? '' ) ),
		'author_selector'       => crb_sanitize_css_selector( (string) ( $raw['author_selector'] ?? '' ) ),
		'review_title_selector' => crb_sanitize_css_selector( (string) ( $raw['review_title_selector'] ?? '' ) ),
		'summary_selector'      => crb_sanitize_css_selector( (string) ( $raw['summary_selector'] ?? '' ) ),
		'category_selector'     => crb_sanitize_css_selector( (string) ( $raw['category_selector'] ?? '' ) ),
		'review_body_selector'  => crb_sanitize_css_selector( (string) ( $raw['review_body_selector'] ?? '' ) ),
	);
	foreach ( crb_extra_slot_storage_map() as $index => $meta ) {
		$key = $meta['config_key'];
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = crb_sanitize_css_selector( (string) ( $raw[ $key ] ?? '' ) );
		}
		$out[ $meta['mode_key'] ] = crb_sanitize_slot_extract_mode(
			(string) ( $raw[ $meta['mode_key'] ] ?? $meta['default_mode'] )
		);
		if ( 'attr' === $out[ $meta['mode_key'] ] ) {
			$attr_key = 'slot_attr_' . ( $index + 1 );
			$out[ $attr_key ] = crb_sanitize_css_attr_name( (string) ( $raw[ $attr_key ] ?? 'title' ) );
		}
	}
	if ( function_exists( 'crb_license_apply_slot_limits' ) ) {
		$out = crb_license_apply_slot_limits( $out );
	}
	return $out;
}
function crb_hash_feed_css_config( array $css ) {
	$copy = $css;
	ksort( $copy );
	return md5( (string) wp_json_encode( $copy ) );
}

/**
 * @param string $selector CSS selector.
 * @return string
 */
function crb_sanitize_css_selector( $selector ) {
	$selector = trim( (string) $selector );
	if ( '' === $selector ) {
		return '';
	}
	// 全角＃・BOM などの入力ゆれ。
	$selector = str_replace( array( '＃', "\xEF\xBB\xBF" ), array( '#', '' ), $selector );
	// 入力ミス *:tag → tag（*:li など）。
	if ( preg_match( '/^\*:(\w[\w-]*)$/u', $selector, $m ) ) {
		$selector = $m[1];
	}
	// :nth-child(2) などに必要な () , を許可。
	$selector = preg_replace( '/[^a-zA-Z0-9\\s\\.\\#\\[\\]\\*\\=\\"\\\'\\_\\-\\>\\:\\(\\),+~|]/u', '', $selector );
	return trim( (string) $selector );
}

/**
 * @param string $name Attribute name.
 * @return string
 */
function crb_sanitize_css_attr_name( $name ) {
	$name = sanitize_key( (string) $name );
	return '' !== $name ? $name : 'title';
}

/**
 * レビュー／記事リスト型ページ向けの入力例（サイト非依存の汎用値）。
 *
 * @return array<string, string>
 */
/**
 * 新規フィード用の空の CSS 設定（プリセットは入力例ボタンのみ）。
 *
 * @return array<string, string>
 */
function crb_empty_css_config() {
	$out = array(
		'scope_selector'        => '',
		'item_selector'         => '',
		'link_selector'         => '',
		'title_mode'            => 'attr',
		'title_attr'            => 'title',
		'title_selector'        => '',
		'image_selector'        => '',
		'author_selector'       => '',
		'review_title_selector' => '',
		'summary_selector'      => '',
		'category_selector'     => '',
		'review_body_selector'  => '',
	);
	foreach ( crb_extra_slot_storage_map() as $index => $meta ) {
		$out[ $meta['config_key'] ] = '';
		$out[ $meta['mode_key'] ]   = $meta['default_mode'];
	}
	return $out;
}

/**
 * 要素調べる: 発見結果だけから CSS 設定を組み立てる（フォーム値は使わない）。
 *
 * @param string                            $scope_selector Scope.
 * @param string                            $item_selector  Item block.
 * @param array<string, string|array>       $suggested      crb_discover_suggest_slot_rules() の戻り値。
 * @return array<string, string>
 */
function crb_css_config_from_discover_suggested( $scope_selector, $item_selector, array $suggested ) {
	$raw = crb_empty_css_config();
	$raw['scope_selector'] = (string) $scope_selector;
	$raw['item_selector']  = (string) $item_selector;
	if ( ! empty( $suggested['link_selector'] ) ) {
		$raw['link_selector'] = (string) $suggested['link_selector'];
	}
	foreach ( crb_extra_slot_storage_map() as $index => $meta ) {
		$key = $meta['config_key'];
		if ( isset( $suggested[ $key ] ) && is_string( $suggested[ $key ] ) ) {
			$raw[ $key ] = (string) $suggested[ $key ];
		}
		$mode_key = $meta['mode_key'];
		if ( isset( $suggested[ $mode_key ] ) && is_array( $suggested[ $mode_key ] ) ) {
			$raw[ $mode_key ] = (string) ( $suggested[ $mode_key ]['mode'] ?? $meta['default_mode'] );
		}
	}
	return crb_sanitize_css_config( $raw );
}

/**
 * Discover 試し読み用の汎用セレクタ補完（リンク・画像のみ。サイト固有クラスは入れない）。
 *
 * @param array<string, string> $config CSS config.
 * @return array<string, string>
 */
function crb_apply_discover_fallback_selectors( array $config ) {
	if ( '' === trim( (string) ( $config['link_selector'] ?? '' ) ) ) {
		$config['link_selector'] = 'a[href]';
	}
	if ( '' === trim( (string) ( $config['image_selector'] ?? '' ) ) ) {
		$config['image_selector'] = 'img';
		$config['slot_mode_4']    = 'src';
	}
	return $config;
}

/**
 * ユーザー指定の範囲セレクタのみで解決（フォールバックなし）。
 *
 * @param DOMXPath   $xpath          XPath.
 * @param DOMElement $root           crb-root.
 * @param string     $scope_selector 範囲 CSS（空なら root）。
 * @return DOMElement|null
 */
function crb_resolve_scope_element_strict( DOMXPath $xpath, DOMElement $root, $scope_selector ) {
	$scope_selector = trim( (string) $scope_selector );
	if ( '' === $scope_selector ) {
		return $root;
	}
	$scope_xpath = crb_css_to_xpath( $scope_selector );
	if ( is_wp_error( $scope_xpath ) ) {
		return null;
	}
	$scoped = crb_xpath_query( $xpath, $scope_xpath, $root );
	if ( null === $scoped || 0 === $scoped->length || ! ( $scoped->item( 0 ) instanceof DOMElement ) ) {
		return null;
	}
	return $scoped->item( 0 );
}

/**
 * 範囲要素を解決（空欄なら root、指定ありで未一致なら null）。
 *
 * @param DOMXPath   $xpath          XPath.
 * @param DOMElement $root           crb-root.
 * @param string     $scope_selector 範囲 CSS.
 * @return DOMElement|null
 */
function crb_resolve_scope_element( DOMXPath $xpath, DOMElement $root, $scope_selector ) {
	return crb_resolve_scope_element_strict( $xpath, $root, $scope_selector );
}
