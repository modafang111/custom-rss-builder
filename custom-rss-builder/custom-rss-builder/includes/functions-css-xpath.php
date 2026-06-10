<?php
/**
 * CSS セレクタ → XPath 変換。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $selector CSS selector.
 * @return string|WP_Error XPath (relative, starts with .//)
 */
function crb_css_to_xpath( $selector ) {
	$selector = trim( (string) $selector );
	if ( '' === $selector ) {
		return new WP_Error( 'crb_css_empty', __( 'CSS セレクタが空です。', 'custom-rss-builder' ) );
	}

	// 入力ミス *:tag → tag（*:li など）。
	if ( preg_match( '/^\*:(\w[\w-]*)$/u', $selector, $m ) ) {
		$selector = $m[1];
	}

	if ( ! preg_match( '#^[a-zA-Z0-9\\s\\.\\#\\[\\]\\*\\=\\"\\\'\\_\\-\\>\\:\\(\\),+~|]+$#u', $selector ) ) {
		return new WP_Error(
			'crb_css_invalid',
			__( 'CSS セレクタに使えない文字が含まれています。', 'custom-rss-builder' )
		);
	}

	$normalized = preg_replace( '/\s*>\s*/u', ' > ', $selector );
	$parts      = preg_split( '/\\s+/u', (string) $normalized, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $parts ) || empty( $parts ) ) {
		return new WP_Error( 'crb_css_invalid', __( 'CSS セレクタの解析に失敗しました。', 'custom-rss-builder' ) );
	}

	$segments   = array();
	$relations  = array();
	$relation   = 'descendant';
	foreach ( $parts as $part ) {
		if ( '>' === $part ) {
			if ( 'child' === $relation ) {
				return new WP_Error( 'crb_css_invalid', __( 'CSS セレクタの形式が不正です。', 'custom-rss-builder' ) );
			}
			$relation = 'child';
			continue;
		}
		$seg = crb_css_segment_to_xpath( $part );
		if ( is_wp_error( $seg ) ) {
			return $seg;
		}
		$segments[] = $seg;
		$relations[] = $relation;
		$relation    = 'descendant';
	}

	if ( empty( $segments ) || 'child' === $relation ) {
		return new WP_Error( 'crb_css_invalid', __( 'CSS セレクタの形式が不正です。', 'custom-rss-builder' ) );
	}

	$xpath = '.';
	foreach ( $segments as $idx => $segment ) {
		$rel   = $relations[ $idx ] ?? 'descendant';
		$xpath .= ( 'child' === $rel ) ? '/' : '//';
		$xpath .= $segment;
	}

	return $xpath;
}

/**
 * @param string $segment Single compound selector (no spaces).
 * @return string|WP_Error
 */
function crb_css_segment_to_xpath( $segment ) {
	$segment = trim( $segment );
	$tag     = '*';
	$conds   = array();
	$rest    = $segment;

	// thumb-with-ng-filter-block などハイフン付きタグ名（カスタム要素）に対応。
	if ( preg_match( '/^([a-zA-Z][a-zA-Z0-9-]*)/', $rest, $m ) ) {
		$tag  = $m[1];
		$rest = substr( $rest, strlen( $m[1] ) );
	}

	while ( '' !== $rest ) {
		if ( '.' === $rest[0] ) {
			if ( ! preg_match( '/^\\.([a-zA-Z0-9_-]+)/', $rest, $m ) ) {
				return new WP_Error( 'crb_css_invalid', __( 'クラス指定が不正です。', 'custom-rss-builder' ) );
			}
			$conds[] = 'contains(concat(" ", normalize-space(@class), " "), " ' . $m[1] . ' ")';
			$rest    = substr( $rest, strlen( $m[0] ) );
			continue;
		}
		if ( '#' === $rest[0] ) {
			if ( ! preg_match( '/^#([a-zA-Z0-9_-]+)/', $rest, $m ) ) {
				return new WP_Error( 'crb_css_invalid', __( 'ID 指定が不正です。', 'custom-rss-builder' ) );
			}
			$conds[] = '@id="' . $m[1] . '"';
			$rest    = substr( $rest, strlen( $m[0] ) );
			continue;
		}
		if ( '[' === $rest[0] ) {
			if ( ! preg_match( '/^\\[([a-zA-Z0-9_-]+)(?:(\\*?=)(?:"([^"]*)"|([^\]]+)))?\\]/', $rest, $m ) ) {
				return new WP_Error( 'crb_css_invalid', __( '属性指定が不正です。', 'custom-rss-builder' ) );
			}
			$attr = $m[1];
			if ( empty( $m[2] ) ) {
				$conds[] = '@' . $attr;
			} else {
				$val = isset( $m[3] ) && '' !== $m[3] ? $m[3] : ( $m[4] ?? '' );
				if ( '*=' === $m[2] ) {
					$conds[] = 'contains(@' . $attr . ', "' . $val . '")';
				} else {
					$conds[] = '@' . $attr . '="' . $val . '"';
				}
			}
			$rest = substr( $rest, strlen( $m[0] ) );
			continue;
		}
		if ( ':' === $rest[0] ) {
			if ( preg_match( '/^:nth-child\\((\\d+)\\)/', $rest, $m ) ) {
				$conds[] = 'count(preceding-sibling::*) + 1 = ' . (int) $m[1];
				$rest    = substr( $rest, strlen( $m[0] ) );
				continue;
			}
			if ( preg_match( '/^:nth-of-type\\((\\d+)\\)/', $rest, $m ) ) {
				$conds[] = 'count(preceding-sibling::*[name()=name()]) + 1 = ' . (int) $m[1];
				$rest    = substr( $rest, strlen( $m[0] ) );
				continue;
			}
		}
		return new WP_Error( 'crb_css_invalid', __( 'CSS セレクタの形式が不正です。', 'custom-rss-builder' ) );
	}

	$xpath = crb_css_xpath_tag_expr( $tag );
	if ( ! empty( $conds ) ) {
		$xpath .= '[' . implode( ' and ', $conds ) . ']';
	}
	return $xpath;
}

/**
 * HTML DOM ではタグ名が大文字（DIV 等）のため、CSS の小文字タグと一致させる。
 *
 * @param string $tag Tag or *.
 * @return string XPath node test.
 */
function crb_css_xpath_tag_expr( $tag ) {
	$tag = trim( (string) $tag );
	if ( '' === $tag || '*' === $tag ) {
		return '*';
	}
	$lower = strtolower( $tag );
	return "*[translate(name(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='" . $lower . "']";
}
