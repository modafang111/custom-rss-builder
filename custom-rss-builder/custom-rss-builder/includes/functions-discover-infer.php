<?php
/**
 * 1件ブロック内からセレクタを推定（discover 候補が空／不一致のとき）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ブロック内の最良リンク候補をスコアリング（サイト非依存のヒューリスティック）。
 *
 * @param DOMElement $anchor Anchor-like element.
 */
function crb_score_anchor_in_block( DOMElement $anchor ) {
	$href  = trim( (string) $anchor->getAttribute( 'href' ) );
	$link  = trim( (string) $anchor->getAttribute( 'link' ) );
	$url   = '' !== $href ? $href : $link;
	$text  = preg_replace( '/\s+/u', ' ', (string) $anchor->textContent );
	$text  = trim( $text );
	$title = trim( (string) $anchor->getAttribute( 'title' ) );
	$label = '' !== $title ? $title : $text;

	$score = 0;
	if ( '' === $url || '#' === $url || 0 === strpos( $url, 'javascript:' ) ) {
		return -1000;
	}
	if ( mb_strlen( $label ) >= 4 ) {
		$score += min( 50, mb_strlen( $label ) );
	}
	if ( preg_match( '#^https?://#i', $url ) || 0 === strpos( $url, '/' ) ) {
		$score += 10;
	}
	if ( preg_match( '#/(cart|wishlist|login|logout|register|signup|contact|share|report)/#i', $url ) ) {
		$score -= 80;
	}
	if ( preg_match( '/^\(\d+\)$/', $text ) ) {
		$score -= 60;
	}
	return $score;
}

/**
 * 要素からブロック内で使える CSS（簡易・最大4階層）。
 *
 * @param DOMElement $element Element.
 * @param DOMElement $scope   Item block.
 */
function crb_css_selector_for_element_in_scope( DOMElement $element, DOMElement $scope ) {
	$parts = array();
	$node  = $element;
	$depth = 0;
	while ( $node instanceof DOMElement && $node !== $scope && $depth < 5 ) {
		$seg = strtolower( $node->tagName );
		if ( preg_match( '/^[a-z][a-z0-9-]*$/', $seg ) ) {
			$class = trim( (string) $node->getAttribute( 'class' ) );
			if ( '' !== $class ) {
				$tokens = preg_split( '/\s+/', $class );
				if ( is_array( $tokens ) && ! empty( $tokens[0] ) ) {
					$seg .= '.' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $tokens[0] );
				}
			}
			array_unshift( $parts, $seg );
		}
		$parent = $node->parentNode;
		$node   = ( $parent instanceof DOMElement ) ? $parent : null;
		++$depth;
	}
	if ( empty( $parts ) ) {
		return '';
	}
	$sel = implode( ' ', $parts );
	if ( 'a' !== strtolower( $element->tagName ) && $element->hasAttribute( 'link' ) ) {
		$sel .= '[link]';
	}
	return $sel;
}

/**
 * ブロック内でセレクタが1件以上一致するか。
 *
 * @param DOMXPath   $xpath    XPath.
 * @param DOMElement $context  Item block.
 * @param string     $selector CSS.
 */
function crb_context_selector_matches( DOMXPath $xpath, DOMElement $context, $selector ) {
	$selector = trim( $selector );
	if ( '' === $selector ) {
		return false;
	}
	$path = crb_css_to_xpath( $selector );
	if ( is_wp_error( $path ) ) {
		return false;
	}
	$nodes = crb_xpath_query( $xpath, $path, $context );
	return null !== $nodes && $nodes->length > 0;
}

/**
 * ブロック内の繰り返ししやすいセレクタ候補を試す。
 *
 * @param DOMXPath   $xpath    XPath.
 * @param DOMElement $context  Item block.
 * @param array<int, string> $candidates Selectors.
 */
function crb_first_matching_selector_in_context( DOMXPath $xpath, DOMElement $context, array $candidates ) {
	foreach ( $candidates as $sel ) {
		if ( crb_context_selector_matches( $xpath, $context, $sel ) ) {
			return $sel;
		}
	}
	return '';
}

/**
 * discover 提案が使えないとき、1件ブロックから CSS 設定を推定。
 *
 * @param DOMXPath   $xpath    XPath.
 * @param DOMElement $context  1件ブロック。
 * @param array<string, string> $base_config 既存設定。
 * @return array<string, string>
 */
function crb_infer_css_config_from_context( DOMXPath $xpath, DOMElement $context, array $base_config = array() ) {
	$config = crb_sanitize_css_config( array_merge( crb_empty_css_config(), $base_config ) );

	$best_el    = null;
	$best_score = -9999;
	$nodes      = crb_xpath_query( $xpath, './/a[@href] | .//*[@link]', $context );
	if ( null !== $nodes ) {
		for ( $i = 0; $i < $nodes->length; $i++ ) {
			$node = $nodes->item( $i );
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			$score = crb_score_anchor_in_block( $node );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_el    = $node;
			}
		}
	}

	$link_sel = trim( (string) ( $config['link_selector'] ?? '' ) );
	if ( ( '' === $link_sel || ! crb_context_selector_matches( $xpath, $context, $link_sel ) ) && $best_el instanceof DOMElement ) {
		$built = crb_css_selector_for_element_in_scope( $best_el, $context );
		if ( '' !== $built && crb_context_selector_matches( $xpath, $context, $built ) ) {
			$link_sel = $built;
		} else {
			$link_sel = crb_first_matching_selector_in_context(
				$xpath,
				$context,
				array(
					'dt a[href]',
					'dl dt a[href]',
					'h2 a[href]',
					'h3 a[href]',
					'a[href]',
					'*[link]',
				)
			);
		}
		$config['link_selector'] = $link_sel;
	}

	$text_candidates = array();
	$text_nodes      = crb_xpath_query( $xpath, './/p | .//dd | .//div[@class]', $context );
	if ( null !== $text_nodes ) {
		for ( $i = 0; $i < $text_nodes->length; $i++ ) {
			$node = $text_nodes->item( $i );
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			$text = trim( preg_replace( '/\s+/u', ' ', (string) $node->textContent ) );
			if ( mb_strlen( $text ) < 12 ) {
				continue;
			}
			$sel = crb_css_selector_for_element_in_scope( $node, $context );
			if ( '' === $sel ) {
				continue;
			}
			if ( ! isset( $text_candidates[ $sel ] ) ) {
				$text_candidates[ $sel ] = mb_strlen( $text );
			} else {
				$text_candidates[ $sel ] = max( $text_candidates[ $sel ], mb_strlen( $text ) );
			}
		}
	}
	arsort( $text_candidates );
	$text_sels = array_keys( $text_candidates );

	$map = crb_extra_slot_storage_map();
	$ti  = 0;
	foreach ( array( 2, 4, 5, 6, 7 ) as $slot_idx ) {
		if ( $ti >= count( $text_sels ) ) {
			break;
		}
		$sel = $text_sels[ $ti ];
		if ( $sel === $link_sel ) {
			++$ti;
			continue;
		}
		if ( ! crb_context_selector_matches( $xpath, $context, $sel ) ) {
			continue;
		}
		$key = $map[ $slot_idx ]['config_key'];
		$mode_key = $map[ $slot_idx ]['mode_key'];
		if ( '' !== trim( (string) ( $config[ $key ] ?? '' ) ) ) {
			continue;
		}
		$config[ $key ] = $sel;
		$mode = ( 6 === $slot_idx ) ? 'html' : 'text';
		$config[ $mode_key ] = $mode;
		++$ti;
	}

	$img_sel = trim( (string) ( $config['image_selector'] ?? '' ) );
	if ( '' === $img_sel || ! crb_context_selector_matches( $xpath, $context, $img_sel ) ) {
		$img_sel = crb_first_matching_selector_in_context(
			$xpath,
			$context,
			array(
				'picture img',
				'img[src]',
			)
		);
		if ( '' !== $img_sel ) {
			$config['image_selector'] = $img_sel;
			$config['slot_mode_4']  = 'src';
		}
	}

	return crb_sanitize_css_config( $config );
}
