<?php
/**
 * DOM 読み込み・XPath ヘルパー（RSS 抽出共通基盤）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function crb_is_usable_image_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || 0 === stripos( $url, 'data:' ) ) {
		return false;
	}
	if ( preg_match( '#^(https?:)?//#i', $url ) ) {
		return true;
	}
	if ( preg_match( '#^(/|\./|\.\./)#', $url ) ) {
		return true;
	}
	return (bool) preg_match( '#\.(?:jpe?g|webp|png|gif|avif|svg)(?:\?|$)#i', $url );
}

/**
 * Vue バインド属性値などから画像 URL を抽出する。
 *
 * @param string $value Raw attribute value.
 * @return array<int, string>
 */
function crb_urls_from_media_attribute_value( $value ) {
	$value = html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	if ( ! preg_match_all( '#(?:https?:)?//[^\s\'"\\]]+\.(?:jpe?g|webp|png|gif)(?:\?[^\s\'"\\]]*)?#i', $value, $matches ) ) {
		return array();
	}
	$urls = array();
	foreach ( $matches[0] as $url ) {
		$url = trim( $url );
		if ( '' !== $url && crb_is_usable_image_url( $url ) ) {
			$urls[] = $url;
		}
	}
	return array_values( array_unique( $urls ) );
}

/**
 * Vue 等の属性名・値が画像 URL を含むか（候補リスト属性 / data-samples 等）。
 *
 * @param string $name  Attribute name.
 * @param string $value Attribute value.
 * @return bool
 */
function crb_attribute_may_contain_image_urls( $name, $value ) {
	$name  = strtolower( (string) $name );
	$value = (string) $value;
	if ( '' === $value ) {
		return false;
	}
	if ( false !== strpos( $name, 'thumb' ) && false !== strpos( $name, 'candidate' ) ) {
		return true;
	}
	if ( 'data-samples' === $name ) {
		return true;
	}
	if ( false !== strpos( $value, '//img.' ) || false !== strpos( $value, '/modpub/' ) || false !== strpos( $value, '_img_main' ) ) {
		return true;
	}
	return false;
}

/**
 * 要素の属性値から画像 URL を候補配列へ追加する。
 *
 * @param DOMElement         $element    Element.
 * @param array<int, string> $candidates URLs（参照渡し）。
 */
function crb_collect_image_urls_from_element_attributes( DOMElement $element, array &$candidates ) {
	if ( ! $element->hasAttributes() ) {
		return;
	}
	foreach ( $element->attributes as $attr ) {
		if ( ! $attr instanceof DOMAttr ) {
			continue;
		}
		if ( ! crb_attribute_may_contain_image_urls( $attr->name, $attr->value ) ) {
			continue;
		}
		foreach ( crb_urls_from_media_attribute_value( $attr->value ) as $url ) {
			$candidates[] = $url;
		}
	}
}

/**
 * 要素と子孫の Vue バインド属性などから画像 URL を集める。
 *
 * @param DOMXPath           $xpath      XPath.
 * @param DOMElement         $root       走査ルート。
 * @param array<int, string> $candidates URLs（参照渡し）。
 */
function crb_collect_image_urls_from_subtree_attributes( DOMXPath $xpath, DOMElement $root, array &$candidates ) {
	crb_collect_image_urls_from_element_attributes( $root, $candidates );
	$nodes = crb_xpath_node_list( $xpath->query( './/*', $root ) );
	if ( ! $nodes ) {
		return;
	}
	for ( $i = 0; $i < $nodes->length; $i++ ) {
		$node = $nodes->item( $i );
		if ( $node instanceof DOMElement ) {
			crb_collect_image_urls_from_element_attributes( $node, $candidates );
		}
	}
}

/**
 * DOMDocument を読み込む共通ヘルパー。
 *
 * @param string $html HTML.
 * @return DOMDocument|WP_Error
 */
function crb_dom_load_html( $html ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return new WP_Error( 'crb_dom_unavailable', __( 'DOM 拡張が利用できません。', 'custom-rss-builder' ) );
	}
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$wrapped = '<?xml encoding="utf-8" ?><div id="crb-root">' . (string) $html . '</div>';
	$loaded  = $dom->loadHTML(
		mb_convert_encoding( $wrapped, 'HTML-ENTITIES', 'UTF-8' ),
		LIBXML_NOWARNING | LIBXML_NOERROR
	);
	libxml_clear_errors();
	if ( ! $loaded ) {
		return new WP_Error( 'crb_dom_parse_failed', __( 'HTML の解析に失敗しました。', 'custom-rss-builder' ) );
	}
	return $dom;
}

/**
 * XPath 結果が DOMNodeList かどうか（false/null 対策）。
 *
 * @param mixed $result query() の戻り値。
 * @return DOMNodeList|null
 */
function crb_xpath_node_list( $result ) {
	return $result instanceof DOMNodeList ? $result : null;
}

/**
 * @param mixed $result query() の戻り値。
 * @return int
 */
function crb_dom_node_list_length( $result ) {
	$list = crb_xpath_node_list( $result );
	return $list ? $list->length : 0;
}

/**
 * @param DOMXPath   $xpath   XPath.
 * @param string     $query   XPath 式。
 * @param DOMNode|null $context Context.
 * @return DOMNodeList|null
 */
function crb_xpath_query( DOMXPath $xpath, $query, $context = null ) {
	$result = null === $context ? $xpath->query( $query ) : $xpath->query( $query, $context );
	return crb_xpath_node_list( $result );
}

/**
 * DOM から crb-root を取得。
 *
 * @param DOMDocument $dom DOM.
 * @return DOMElement|WP_Error
 */
function crb_dom_parse_root( DOMDocument $dom ) {
	$root = $dom->getElementById( 'crb-root' );
	if ( ! $root instanceof DOMElement ) {
		return new WP_Error( 'crb_dom_no_root', __( 'HTML ルートが見つかりません。', 'custom-rss-builder' ) );
	}
	return $root;
}

/**
 * 1件ブロック候補のうち、他の候補の子孫になっている入れ子を除外する。
 *
 * @param DOMElement        $scope 範囲要素。
 * @param array<int, DOMElement> $items 候補。
 * @return array<int, DOMElement>
 */
function crb_filter_top_level_items( DOMElement $scope, array $items ) {
	if ( empty( $items ) ) {
		return array();
	}
	$ids = array();
	foreach ( $items as $node ) {
		$ids[ spl_object_id( $node ) ] = true;
	}
	$out = array();
	foreach ( $items as $node ) {
		$ancestor = $node->parentNode;
		$nested   = false;
		while ( $ancestor instanceof DOMElement ) {
			if ( isset( $ids[ spl_object_id( $ancestor ) ] ) ) {
				$nested = true;
				break;
			}
			if ( $ancestor->isSameNode( $scope ) ) {
				break;
			}
			$ancestor = $ancestor->parentNode;
		}
		if ( ! $nested ) {
			$out[] = $node;
		}
	}
	return $out;
}

/**
 * 範囲・1件ブロック用に、文書全体向けの冗長な先頭セレクタを取り除く。
 *
 * 例: 範囲が table.foo のとき、1件が「table.foo tbody tr」なら「tbody tr」にする。
 *
 * @param string $prefix_selector 親（範囲または1件ブロック）の CSS。
 * @param string $selector        子孫向け CSS。
 * @return string
 */
function crb_css_strip_selector_prefix( $prefix_selector, $selector ) {
	$prefix_selector = trim( (string) $prefix_selector );
	$selector        = trim( (string) $selector );
	if ( '' === $prefix_selector || '' === $selector ) {
		return $selector;
	}
	$prefixes = array( $prefix_selector . ' ', $prefix_selector . ' > ' );
	do {
		$changed = false;
		foreach ( $prefixes as $prefix ) {
			if ( 0 === stripos( $selector, $prefix ) ) {
				$selector = trim( substr( $selector, strlen( $prefix ) ) );
				$changed  = true;
				break;
			}
		}
	} while ( $changed );
	return $selector;
}

/**
 * 範囲内の1件ブロック要素を収集（入れ子 item は除外）。
 *
 * @param DOMXPath   $xpath           XPath.
 * @param DOMElement $scope           範囲。
 * @param string     $item_selector   1件ブロック CSS（空なら範囲を1件として返す）。
 * @param int        $max             0=無制限。
 * @param string     $scope_selector  範囲 CSS（冗長な item 先頭を除く用、省略可）。
 * @return array<int, DOMElement>|WP_Error
 */
function crb_collect_item_containers( DOMXPath $xpath, DOMElement $scope, $item_selector, $max = 0, $scope_selector = '' ) {
	$item_selector = crb_css_strip_selector_prefix( $scope_selector, (string) $item_selector );
	if ( '' === $item_selector ) {
		return array( $scope );
	}

	$item_xpath = crb_css_to_xpath( $item_selector );
	if ( is_wp_error( $item_xpath ) ) {
		return $item_xpath;
	}

	$nodes = crb_xpath_query( $xpath, (string) $item_xpath, $scope );
	if ( null === $nodes || 0 === $nodes->length ) {
		return new WP_Error(
			'crb_css_item_miss',
			sprintf(
				/* translators: %s: CSS selector */
				__( '1件ブロックのセレクタに一致する要素がありません: %s', 'custom-rss-builder' ),
				$item_selector
			)
		);
	}

	$all = array();
	for ( $i = 0; $i < $nodes->length; $i++ ) {
		$node = $nodes->item( $i );
		if ( $node instanceof DOMElement ) {
			$all[] = $node;
		}
	}

	$containers = crb_filter_top_level_items( $scope, $all );
	if ( empty( $containers ) ) {
		return new WP_Error(
			'crb_css_item_miss',
			sprintf(
				/* translators: %s: CSS selector */
				__( '1件ブロックのセレクタに一致する要素がありません: %s', 'custom-rss-builder' ),
				$item_selector
			)
		);
	}

	$max = (int) $max;
	if ( $max > 0 ) {
		$containers = array_slice( $containers, 0, $max );
	}

	return $containers;
}
