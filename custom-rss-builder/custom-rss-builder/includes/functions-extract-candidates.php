<?php
/**
 * 範囲内の抽出候補を列挙（セレクタ・取り方・値）。推測・スコアリングなし。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1件ブロック（または範囲）内の抽出候補をすべて列挙する。
 *
 * @param string $html           HTML.
 * @param string $scope_selector 範囲 CSS.
 * @param string $item_selector  1件ブロック CSS（空なら範囲全体）.
 * @param string $base_url       Base URL.
 * @param bool   $use_discover_dom 要素探索 AJAX 向け軽量 DOM。
 * @return array{context_note: string, row_count: int, rows: array<int, array<string, string>>}|WP_Error
 */
function crb_build_extract_candidates( $html, $scope_selector, $item_selector = '', $base_url = '', $use_discover_dom = false ) {
	$empty = array(
		'context_note' => '',
		'row_count'    => 0,
		'rows'         => array(),
	);

	$dom = function_exists( 'crb_discover_dom_load_for_flow' )
		? crb_discover_dom_load_for_flow( $html, (bool) $use_discover_dom )
		: crb_dom_load_html( $html );
	if ( is_wp_error( $dom ) ) {
		return $dom;
	}

	$root = crb_dom_parse_root( $dom );
	if ( is_wp_error( $root ) ) {
		return $root;
	}

	$xpath = new DOMXPath( $dom );

	$scope_selector = trim( (string) $scope_selector );
	$scope_el       = function_exists( 'crb_discover_resolve_scope_for_flow' )
		? crb_discover_resolve_scope_for_flow( $xpath, $root, $scope_selector, (bool) $use_discover_dom )
		: crb_resolve_scope_element( $xpath, $root, $scope_selector );
	if ( '' !== $scope_selector && ! ( $scope_el instanceof DOMElement ) ) {
		$message = crb_scope_miss_message( $html, $base_url, $scope_selector, $xpath, $root );
		return new WP_Error( 'crb_scope_miss', $message );
	}

	$count_scope  = ( $scope_el instanceof DOMElement ) ? $scope_el : $root;
	$context      = $count_scope;
	$context_note = crb_extract_candidates_context_note( $scope_selector, $item_selector, $context );

	$item_selector = trim( (string) $item_selector );
	if ( '' !== $item_selector ) {
		$containers = crb_collect_item_containers( $xpath, $count_scope, $item_selector, 1, $scope_selector );
		if ( is_wp_error( $containers ) ) {
			return $containers;
		}
		$context = $containers[0];
		$context_note .= ' / ' . sprintf(
			/* translators: %s: item selector */
			__( '列挙対象: 先頭の %s', 'custom-rss-builder' ),
			$item_selector
		);
	}

	$rows = crb_walk_extract_candidates( $context, $base_url, $xpath );
	$rows = crb_extract_candidates_attach_match_counts( $xpath, $count_scope, $item_selector, $rows );

	return array(
		'context_note' => $context_note,
		'row_count'    => count( $rows ),
		'rows'         => $rows,
	);
}

/**
 * 範囲内での CSS セレクタ一致件数（1件ブロック指定時は item + 子孫として数える）。
 *
 * @param DOMXPath   $xpath          XPath.
 * @param DOMElement $scope          範囲要素。
 * @param string     $item_selector  1件ブロック CSS（空可）。
 * @param string     $selector       候補行の CSS（1件ブロック起点の相対パス）。
 * @return int
 */
function crb_extract_candidates_selector_match_count( DOMXPath $xpath, DOMElement $scope, $item_selector, $selector ) {
	$selector = trim( (string) $selector );
	if ( '' === $selector ) {
		return 0;
	}

	$query_sel = $selector;
	$item_selector = trim( (string) $item_selector );
	if ( '' !== $item_selector ) {
		$query_sel = $item_selector . ' ' . $selector;
	}

	$field_xpath = crb_css_to_xpath( $query_sel );
	if ( is_wp_error( $field_xpath ) ) {
		return 0;
	}

	$nodes = crb_xpath_query( $xpath, (string) $field_xpath, $scope );
	if ( null === $nodes ) {
		return 0;
	}

	return (int) $nodes->length;
}

/**
 * 各行に match_count を付与し、出現回数の多い順に並べ替える。
 *
 * @param DOMXPath                          $xpath          XPath.
 * @param DOMElement                        $scope          範囲。
 * @param string                            $item_selector  1件ブロック CSS。
 * @param array<int, array<string, string>> $rows           候補行。
 * @return array<int, array<string, string|int>>
 */
function crb_extract_candidates_attach_match_counts( DOMXPath $xpath, DOMElement $scope, $item_selector, array $rows ) {
	$selector_counts = array();

	foreach ( $rows as $idx => $row ) {
		$sel = trim( (string) ( $row['selector'] ?? '' ) );
		if ( ! isset( $selector_counts[ $sel ] ) ) {
			$selector_counts[ $sel ] = crb_extract_candidates_selector_match_count( $xpath, $scope, $item_selector, $sel );
		}
		$rows[ $idx ]['match_count'] = (int) $selector_counts[ $sel ];
	}

	usort(
		$rows,
		static function ( $a, $b ) {
			$ca = (int) ( $a['match_count'] ?? 0 );
			$cb = (int) ( $b['match_count'] ?? 0 );
			if ( $ca !== $cb ) {
				return $cb <=> $ca;
			}
			$sa = (string) ( $a['selector'] ?? '' );
			$sb = (string) ( $b['selector'] ?? '' );
			if ( $sa !== $sb ) {
				return strcmp( $sa, $sb );
			}
			return strcmp( (string) ( $a['mode'] ?? '' ), (string) ( $b['mode'] ?? '' ) );
		}
	);

	return $rows;
}

/**
 * @param string     $scope_selector Scope.
 * @param string     $item_selector  Item.
 * @param DOMElement $context        Context.
 * @return string
 */
function crb_extract_candidates_context_note( $scope_selector, $item_selector, DOMElement $context ) {
	$note = '' !== $scope_selector ? $scope_selector : __( 'ページ全体', 'custom-rss-builder' );
	$tag  = strtolower( $context->tagName );
	$cls  = trim( (string) $context->getAttribute( 'class' ) );
	if ( '' !== $cls ) {
		$parts = preg_split( '/\s+/', $cls );
		if ( is_array( $parts ) && ! empty( $parts[0] ) ) {
			$note .= ' → ' . $tag . '.' . $parts[0];
		}
	} else {
		$note .= ' → ' . $tag;
	}
	if ( '' === trim( (string) $item_selector ) ) {
		$note .= ' / ' . __( '範囲全体を列挙', 'custom-rss-builder' );
	}
	return $note;
}

/**
 * @param DOMElement  $scope      Scope.
 * @param DOMNodeList $item_nodes Items.
 * @return DOMElement|null
 */
function crb_extract_candidates_first_top_level_item( DOMElement $scope, DOMNodeList $item_nodes ) {
	$all = array();
	for ( $i = 0; $i < $item_nodes->length; $i++ ) {
		$node = $item_nodes->item( $i );
		if ( $node instanceof DOMElement ) {
			$all[] = $node;
		}
	}
	$filtered = crb_filter_top_level_items( $scope, $all );
	return ! empty( $filtered ) ? $filtered[0] : null;
}

/**
 * @param DOMElement $context  Context.
 * @param string     $base_url Base URL.
 * @param DOMXPath|null $xpath XPath（省略時は context から生成）。
 * @return array<int, array<string, string>>
 */
function crb_walk_extract_candidates( DOMElement $context, $base_url, DOMXPath $xpath = null ) {
	$rows      = array();
	$seen      = array();
	$to_visit  = array( $context );
	$doc       = $context->ownerDocument;
	$extractor = new Custom_RSS_Builder_Css_Extractor();
	if ( ! $doc ) {
		return $rows;
	}
	if ( ! $xpath instanceof DOMXPath ) {
		$xpath = new DOMXPath( $doc );
	}
	$list  = crb_xpath_query( $xpath, './/*', $context );
	if ( null !== $list ) {
		for ( $i = 0; $i < $list->length; $i++ ) {
			$node = $list->item( $i );
			if ( $node instanceof DOMElement ) {
				$to_visit[] = $node;
			}
		}
	}

	foreach ( $to_visit as $element ) {
		$tag = strtolower( $element->tagName );
		if ( in_array( $tag, array( 'script', 'style', 'noscript' ), true ) ) {
			continue;
		}

		$selector = crb_candidate_selector_for_element( $element, $context );
		if ( '' === $selector ) {
			continue;
		}

		crb_extract_candidates_push_attrs( $rows, $seen, $selector, $element, $xpath, $context, $base_url, $extractor );

		if ( 'a' === $tag ) {
			crb_extract_candidates_push_link_rows( $rows, $seen, $selector, $element, $xpath, $context, $base_url, $extractor );
		} elseif ( 'img' === $tag ) {
			crb_extract_candidates_push_image_rows( $rows, $seen, $selector, $element, $xpath, $context, $base_url, $extractor );
		} elseif ( 'source' === $tag ) {
			crb_extract_candidates_push_source_rows( $rows, $seen, $selector, $element, $xpath, $context, $base_url, $extractor );
		} elseif ( 'input' === $tag || 'button' === $tag ) {
			crb_extract_candidates_push_input_rows( $rows, $seen, $selector, $element, $xpath, $context, $base_url, $extractor );
		}

		if ( 'img' !== $tag && crb_extract_candidates_should_offer_container_src( $xpath, $element ) ) {
			crb_extract_candidates_add_row( $rows, $seen, $selector, 'src', '@src', $xpath, $context, $base_url, $extractor );
		}

		if ( crb_extract_candidates_element_has_child_elements( $element ) ) {
			if ( in_array( $tag, array( 'p', 'div', 'td', 'dd', 'blockquote', 'li' ), true ) ) {
				crb_extract_candidates_add_row( $rows, $seen, $selector, 'html', __( 'HTML', 'custom-rss-builder' ), $xpath, $context, $base_url, $extractor );
			}
		} else {
			crb_extract_candidates_add_row( $rows, $seen, $selector, 'text', __( 'テキスト', 'custom-rss-builder' ), $xpath, $context, $base_url, $extractor );
		}
	}

	return $rows;
}

/**
 * @param DOMElement $element Element.
 * @param DOMElement $scope   Scope.
 * @return string
 */
function crb_candidate_selector_for_element( DOMElement $element, DOMElement $scope ) {
	$parts = array();
	$node  = $element;
	$depth = 0;
	while ( $node instanceof DOMElement && ! $node->isSameNode( $scope ) && $depth < 8 ) {
		$parts[] = crb_candidate_selector_segment( $node );
		$parent  = $node->parentNode;
		$node    = ( $parent instanceof DOMElement ) ? $parent : null;
		++$depth;
	}
	if ( empty( $parts ) ) {
		return '';
	}
	return implode( ' ', array_reverse( $parts ) );
}

/**
 * @param DOMElement $element Element.
 * @return string
 */
function crb_candidate_selector_segment( DOMElement $element ) {
	$tag = strtolower( $element->tagName );
	$cls = crb_extract_candidates_class_tokens( $element );
	$seg = $tag;
	if ( ! empty( $cls ) ) {
		$seg .= '.' . implode( '.', array_slice( $cls, 0, 2 ) );
	}
	$nth = crb_candidate_nth_child_index( $element );
	if ( $nth > 1 ) {
		$seg .= ':nth-child(' . $nth . ')';
	}
	return $seg;
}

/**
 * @param DOMElement $element Element.
 * @return array<int, string>
 */
function crb_extract_candidates_class_tokens( DOMElement $element ) {
	$raw = trim( (string) $element->getAttribute( 'class' ) );
	if ( '' === $raw ) {
		return array();
	}
	$parts = preg_split( '/\s+/', $raw );
	return is_array( $parts ) ? array_values( array_filter( $parts ) ) : array();
}

/**
 * @param DOMElement $element Element.
 * @return int
 */
function crb_candidate_nth_child_index( DOMElement $element ) {
	$parent = $element->parentNode;
	if ( ! $parent instanceof DOMElement ) {
		return 1;
	}
	$nth = 0;
	foreach ( $parent->childNodes as $child ) {
		if ( ! ( $child instanceof DOMElement ) ) {
			continue;
		}
		++$nth;
		if ( $child->isSameNode( $element ) ) {
			return $nth;
		}
	}
	return 1;
}

/**
 * @param DOMElement $element Element.
 * @return bool
 */
function crb_extract_candidates_element_has_child_elements( DOMElement $element ) {
	foreach ( $element->childNodes as $child ) {
		if ( $child instanceof DOMElement ) {
			return true;
		}
	}
	return false;
}

/**
 * @param array<int, array<string, string>> $rows Rows.
 * @param array<string, bool>               $seen Seen.
 * @param string                            $selector Selector.
 * @param DOMElement                        $element Element.
 * @param string                            $base_url Base.
 */
function crb_extract_candidates_push_attrs( array &$rows, array &$seen, $selector, DOMElement $element, DOMXPath $xpath, DOMElement $context, $base_url, Custom_RSS_Builder_Css_Extractor $extractor ) {
	if ( ! $element->hasAttributes() ) {
		return;
	}
	foreach ( $element->attributes as $attr ) {
		$name = (string) $attr->name;
		if ( in_array( $name, array( 'class', 'style' ), true ) ) {
			continue;
		}
		$val = trim( (string) $attr->value );
		if ( '' === $val ) {
			continue;
		}
		crb_extract_candidates_add_row( $rows, $seen, $selector, 'attr:' . $name, '@' . $name, $xpath, $context, $base_url, $extractor );
	}
}

/**
 * @param array<int, array<string, string>> $rows Rows.
 * @param array<string, bool>               $seen Seen.
 * @param string                            $selector Selector.
 * @param DOMElement                        $element Anchor.
 * @param string                            $base_url Base.
 */
function crb_extract_candidates_push_link_rows( array &$rows, array &$seen, $selector, DOMElement $element, DOMXPath $xpath, DOMElement $context, $base_url, Custom_RSS_Builder_Css_Extractor $extractor ) {
	$href = trim( (string) $element->getAttribute( 'href' ) );
	if ( '' !== $href && '#' !== $href && 0 !== strpos( $href, 'javascript:' ) ) {
		crb_extract_candidates_add_row( $rows, $seen, $selector, 'href', __( 'リンクURL (href)', 'custom-rss-builder' ), $xpath, $context, $base_url, $extractor );
	}
	$title = trim( (string) $element->getAttribute( 'title' ) );
	if ( '' !== $title ) {
		crb_extract_candidates_add_row( $rows, $seen, $selector, 'attr:title', __( 'リンクの属性 (title)', 'custom-rss-builder' ), $xpath, $context, $base_url, $extractor );
	}
	if ( '' !== crb_extract_candidates_clean_text( $element->textContent ) ) {
		crb_extract_candidates_add_row( $rows, $seen, $selector, 'text', __( 'テキスト', 'custom-rss-builder' ), $xpath, $context, $base_url, $extractor );
	}
}

/**
 * @param array<int, array<string, string>> $rows Rows.
 * @param array<string, bool>               $seen Seen.
 * @param string                            $selector Selector.
 * @param DOMElement                        $element Image.
 * @param string                            $base_url Base.
 */
function crb_extract_candidates_push_image_rows( array &$rows, array &$seen, $selector, DOMElement $element, DOMXPath $xpath, DOMElement $context, $base_url, Custom_RSS_Builder_Css_Extractor $extractor ) {
	$has_usable = false;
	foreach ( array( 'src', 'data-src', 'data-original', 'data-lazy-src' ) as $attr ) {
		$val = trim( (string) $element->getAttribute( $attr ) );
		if ( '' === $val || ! crb_is_usable_image_url( $val ) ) {
			continue;
		}
		$has_usable = true;
		crb_extract_candidates_add_row( $rows, $seen, $selector, 'attr:' . $attr, '@' . $attr, $xpath, $context, $base_url, $extractor );
	}
	if ( ! $has_usable ) {
		crb_extract_candidates_add_row( $rows, $seen, $selector, 'src', '@src', $xpath, $context, $base_url, $extractor );
	}
	$alt = trim( (string) $element->getAttribute( 'alt' ) );
	if ( '' !== $alt ) {
		crb_extract_candidates_add_row( $rows, $seen, $selector, 'attr:alt', '@alt', $xpath, $context, $base_url, $extractor );
	}
}

/**
 * @param array<int, array<string, string>> $rows Rows.
 * @param array<string, bool>               $seen Seen.
 * @param string                            $selector Selector.
 * @param DOMElement                        $element Source.
 * @param string                            $base_url Base.
 */
function crb_extract_candidates_push_source_rows( array &$rows, array &$seen, $selector, DOMElement $element, DOMXPath $xpath, DOMElement $context, $base_url, Custom_RSS_Builder_Css_Extractor $extractor ) {
	$srcset = trim( (string) $element->getAttribute( 'srcset' ) );
	if ( '' !== $srcset ) {
		crb_extract_candidates_add_row( $rows, $seen, $selector, 'attr:srcset', '@srcset', $xpath, $context, $base_url, $extractor );
	}
	$type = trim( (string) $element->getAttribute( 'type' ) );
	if ( '' !== $type ) {
		crb_extract_candidates_add_row( $rows, $seen, $selector, 'attr:type', '@type', $xpath, $context, $base_url, $extractor );
	}
}

/**
 * @param array<int, array<string, string>> $rows Rows.
 * @param array<string, bool>               $seen Seen.
 * @param string                            $selector Selector.
 * @param DOMElement                        $element Input.
 */
function crb_extract_candidates_push_input_rows( array &$rows, array &$seen, $selector, DOMElement $element, DOMXPath $xpath, DOMElement $context, $base_url, Custom_RSS_Builder_Css_Extractor $extractor ) {
	$val = trim( (string) $element->getAttribute( 'value' ) );
	if ( '' !== $val ) {
		crb_extract_candidates_add_row( $rows, $seen, $selector, 'attr:value', '@value', $xpath, $context, $base_url, $extractor );
	}
}

/**
 * @param array<int, array<string, string>> $rows Rows.
 * @param array<string, bool>               $seen Seen.
 * @param string                            $selector Selector.
 * @param string                            $mode Mode.
 * @param string                            $mode_label Label.
 * @param DOMXPath                          $xpath XPath.
 * @param DOMElement                        $context 1件ブロック。
 * @param string                            $base_url Base URL.
 * @param Custom_RSS_Builder_Css_Extractor  $extractor Extractor.
 */
function crb_extract_candidates_add_row( array &$rows, array &$seen, $selector, $mode, $mode_label, DOMXPath $xpath, DOMElement $context, $base_url, Custom_RSS_Builder_Css_Extractor $extractor ) {
	$value = crb_extract_slot_value( $xpath, $context, $selector, $mode, $base_url, $extractor );
	if ( 'html' !== crb_candidate_mode_to_slot_rule( $mode )['mode'] ) {
		$value = crb_extract_candidates_clean_text( $value );
	} else {
		$value = trim( (string) $value );
	}
	if ( '' === $value ) {
		return;
	}
	$key = $selector . "\0" . $mode;
	if ( isset( $seen[ $key ] ) ) {
		return;
	}
	$seen[ $key ] = true;
	$rows[]       = array(
		'selector'   => $selector,
		'mode'       => $mode,
		'mode_label' => $mode_label,
		'value'      => crb_extract_candidates_truncate_value( $value ),
	);
}

/**
 * @param string $text Text.
 * @return string
 */
function crb_extract_candidates_clean_text( $text ) {
	$text = preg_replace( '/\s+/u', ' ', (string) $text );
	return trim( (string) $text );
}

/**
 * @param string $value Value.
 * @return string
 */
function crb_extract_candidates_truncate_value( $value ) {
	$max = 800;
	if ( mb_strlen( $value ) <= $max ) {
		return $value;
	}
	return mb_substr( $value, 0, $max - 1 ) . '…';
}

/**
 * @param string $href     URL.
 * @param string $base_url Base.
 * @return string
 */
function crb_extract_candidates_resolve_url( $href, $base_url ) {
	$href = trim( (string) $href );
	if ( '' === $href ) {
		return '';
	}
	if ( 0 === strpos( $href, '//' ) ) {
		return 'https:' . $href;
	}
	if ( 0 === strpos( $href, '/' ) && '' !== trim( (string) $base_url ) ) {
		$parts = wp_parse_url( $base_url );
		if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
			return $parts['scheme'] . '://' . $parts['host'] . $href;
		}
	}
	return $href;
}

/**
 * @param DOMElement $element Element.
 * @return string
 */
function crb_extract_candidates_inner_html( DOMElement $element ) {
	$doc = $element->ownerDocument;
	if ( ! $doc ) {
		return '';
	}
	$html = '';
	foreach ( $element->childNodes as $child ) {
		$html .= $doc->saveHTML( $child );
	}
	return trim( $html );
}

/**
 * @param DOMXPath   $xpath    XPath.
 * @param DOMElement $element  Element.
 * @return bool
 */
function crb_extract_candidates_should_offer_container_src( DOMXPath $xpath, DOMElement $element ) {
	$imgs = crb_xpath_query( $xpath, './/img', $element );
	if ( null === $imgs ) {
		return false;
	}
	for ( $i = 0; $i < $imgs->length; $i++ ) {
		$img = $imgs->item( $i );
		if ( ! $img instanceof DOMElement ) {
			continue;
		}
		$src = trim( (string) $img->getAttribute( 'src' ) );
		if ( '' !== $src && ! crb_is_usable_image_url( $src ) ) {
			return true;
		}
	}
	return false;
}

/**
 * @param DOMElement $node Node.
 * @return string
 */
function crb_extract_candidates_image_url_from_node( DOMElement $node ) {
	$tag = strtolower( $node->tagName );
	if ( 'source' === $tag ) {
		$srcset = trim( (string) $node->getAttribute( 'srcset' ) );
		if ( '' !== $srcset ) {
			$parts = preg_split( '/\s*,\s*/', $srcset );
			if ( is_array( $parts ) && ! empty( $parts[0] ) ) {
				$first = preg_split( '/\s+/', trim( $parts[0] ) );
				$url   = is_array( $first ) ? trim( (string) ( $first[0] ?? '' ) ) : '';
				if ( crb_is_usable_image_url( $url ) ) {
					return $url;
				}
			}
		}
	}
	foreach ( array( 'src', 'data-src', 'data-original', 'data-lazy-src' ) as $attr ) {
		$val = trim( (string) $node->getAttribute( $attr ) );
		if ( '' !== $val && crb_is_usable_image_url( $val ) ) {
			return $val;
		}
	}
	return '';
}

/**
 * @param DOMXPath   $xpath    XPath.
 * @param DOMElement $element  Element.
 * @param string     $base_url Base URL.
 * @return string
 */
function crb_extract_candidates_neighbor_image_url( DOMXPath $xpath, DOMElement $element, $base_url ) {
	$parent = $element->parentNode;
	if ( ! $parent instanceof DOMElement ) {
		return '';
	}
	$queries = array(
		'.//*[contains(@class,"thumb-container")]//source[@srcset]',
		'.//*[contains(@class,"thumb-container")]//img',
		'.//picture//source[@srcset]',
		'.//picture//img',
	);
	foreach ( $queries as $query ) {
		$nodes = crb_xpath_query( $xpath, $query, $parent );
		if ( null === $nodes ) {
			continue;
		}
		for ( $i = 0; $i < $nodes->length; $i++ ) {
			$node = $nodes->item( $i );
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			$url = crb_extract_candidates_image_url_from_node( $node );
			if ( '' !== $url ) {
				return crb_extract_candidates_resolve_url( $url, $base_url );
			}
		}
	}
	return '';
}

/**
 * @param DOMXPath   $xpath    XPath.
 * @param DOMElement $element  Element.
 * @param string     $base_url Base URL.
 * @return string
 */
function crb_extract_candidates_resolve_container_image_url( DOMXPath $xpath, DOMElement $element, $base_url ) {
	$url = crb_extract_candidates_image_url_from_node( $element );
	if ( '' !== $url ) {
		return crb_extract_candidates_resolve_url( $url, $base_url );
	}
	return crb_extract_candidates_neighbor_image_url( $xpath, $element, $base_url );
}
