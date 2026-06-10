<?php
/**
 * 範囲セレクタ解決・未一致メッセージ・候補提示（③ 範囲取得 / discover / 試し読み共通）。
 * 抽出本体（crb_dom_load_html / class-css-extractor）には DOM 読み込みを変更しない。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 範囲取得用: script/style 等を除去して DOM 負荷を下げる。
 *
 * @param string $html Raw HTML.
 * @return string
 */
function crb_scope_strip_heavy_markup( $html ) {
	$html = (string) $html;
	if ( '' === $html ) {
		return $html;
	}

	$patterns = array(
		'#<script\\b[^>]*>.*?</script>#is',
		'#<style\\b[^>]*>.*?</style>#is',
		'#<svg\\b[^>]*>.*?</svg>#is',
		'#<noscript\\b[^>]*>.*?</noscript>#is',
	);
	$html = (string) preg_replace( $patterns, '', $html );
	$html = (string) preg_replace( '#<!--.*?-->#s', '', $html );

	return $html;
}

/**
 * 範囲取得専用 DOM 読み込み（軽量・script 除去済み）。
 *
 * @param string $html HTML.
 * @return DOMDocument|WP_Error
 */
function crb_scope_dom_load( $html ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return new WP_Error( 'crb_dom_unavailable', __( 'DOM 拡張が利用できません。', 'custom-rss-builder' ) );
	}

	$html = crb_scope_strip_heavy_markup( (string) $html );
	if ( '' !== $html && function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $html, 'UTF-8' ) ) {
		$html = (string) mb_convert_encoding( $html, 'UTF-8', 'auto' );
	}

	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$wrapped = '<?xml encoding="utf-8" ?><div id="crb-root">' . $html . '</div>';
	$flags   = LIBXML_NOWARNING | LIBXML_NOERROR;
	if ( defined( 'LIBXML_PARSEHUGE' ) ) {
		$flags |= LIBXML_PARSEHUGE;
	}
	$loaded = $dom->loadHTML( $wrapped, $flags );
	libxml_clear_errors();
	if ( ! $loaded ) {
		return new WP_Error( 'crb_dom_parse_failed', __( 'HTML の解析に失敗しました。', 'custom-rss-builder' ) );
	}
	return $dom;
}

/**
 * @param DOMNode $ancestor 祖先候補。
 * @param DOMNode $node     対象ノード。
 * @return bool
 */
function crb_scope_node_is_within( DOMNode $ancestor, DOMNode $node ) {
	for ( $cur = $node; $cur instanceof DOMNode; $cur = $cur->parentNode ) {
		if ( $cur->isSameNode( $ancestor ) ) {
			return true;
		}
	}
	return false;
}

/**
 * 範囲取得: 単純 CSS を DOM API で解決。
 *
 * @param DOMNode  $context  Context.
 * @param string   $selector CSS.
 * @return array<int, DOMElement>|null null = XPath へ。
 */
function crb_scope_query_css_fast( DOMNode $context, $selector ) {
	$selector = trim( (string) $selector );
	if ( '' === $selector || ! ( $context instanceof DOMElement ) ) {
		return null;
	}

	$doc = $context->ownerDocument;
	if ( ! $doc instanceof DOMDocument ) {
		return null;
	}

	$matches = array();

	if ( preg_match( '/^#([a-zA-Z][\\w-]*)$/', $selector, $m ) ) {
		$node = $doc->getElementById( $m[1] );
		if ( $node instanceof DOMElement && crb_scope_node_is_within( $context, $node ) ) {
			$matches[] = $node;
		}
		return $matches;
	}

	if ( preg_match( '/^([a-zA-Z][\\w-]*)#([a-zA-Z][\\w-]*)$/', $selector, $m ) ) {
		$node = $doc->getElementById( $m[2] );
		if ( $node instanceof DOMElement && strtolower( $node->tagName ) === strtolower( $m[1] ) && crb_scope_node_is_within( $context, $node ) ) {
			$matches[] = $node;
		}
		return $matches;
	}

	if ( preg_match( '/^([a-zA-Z][\\w-]*)$/', $selector, $m ) ) {
		$tag_want = strtolower( $m[1] );
		$stack    = array( $context );
		while ( ! empty( $stack ) ) {
			$node = array_pop( $stack );
			if ( $node instanceof DOMElement && strtolower( $node->tagName ) === $tag_want && crb_scope_node_is_within( $context, $node ) ) {
				$matches[] = $node;
			}
			if ( $node->hasChildNodes() ) {
				foreach ( $node->childNodes as $child ) {
					$stack[] = $child;
				}
			}
		}
		return $matches;
	}

	// .class / tag.class 等は PHP 標準 DOM にブラウザ API が無いため XPath へ。
	return null;
}

/**
 * 範囲取得: CSS セレクタに一致する要素一覧。
 *
 * @param DOMXPath $xpath    XPath.
 * @param DOMNode  $context  Context.
 * @param string   $selector CSS.
 * @return array<int, DOMElement>
 */
function crb_scope_query_elements( DOMXPath $xpath, DOMNode $context, $selector ) {
	$selector = trim( (string) $selector );
	if ( '' === $selector ) {
		return array();
	}

	$fast = crb_scope_query_css_fast( $context, $selector );
	if ( is_array( $fast ) ) {
		return $fast;
	}

	$query = crb_css_to_xpath( $selector );
	if ( is_wp_error( $query ) ) {
		return array();
	}

	$nodes = crb_xpath_query( $xpath, (string) $query, $context );
	if ( null === $nodes ) {
		return array();
	}

	$out = array();
	for ( $i = 0; $i < $nodes->length; $i++ ) {
		$node = $nodes->item( $i );
		if ( $node instanceof DOMElement ) {
			$out[] = $node;
		}
	}
	return $out;
}

/**
 * 範囲取得: 範囲要素を解決（空欄なら root）。
 *
 * @param DOMXPath   $xpath          XPath.
 * @param DOMElement $root           crb-root.
 * @param string     $scope_selector CSS.
 * @return DOMElement|null
 */
function crb_scope_resolve_element( DOMXPath $xpath, DOMElement $root, $scope_selector ) {
	$scope_selector = trim( (string) $scope_selector );
	if ( '' === $scope_selector ) {
		return $root;
	}
	$matches = crb_scope_query_elements( $xpath, $root, $scope_selector );
	return ! empty( $matches ) ? $matches[0] : null;
}

/**
 * 取得 HTML の生文字列にセレクタの痕跡があるか（#id / .class のみ）。
 *
 * @param string $html     Raw HTML.
 * @param string $selector CSS.
 * @return bool|null true=あり false=なし null=判定不能（複合セレクタ等）。
 */
function crb_scope_selector_in_raw_html( $html, $selector ) {
	$html     = (string) $html;
	$selector = trim( (string) $selector );
	if ( '' === $html || '' === $selector ) {
		return null;
	}

	if ( preg_match( '/^#([a-zA-Z][\\w-]*)$/', $selector, $m ) ) {
		$id = $m[1];
		return ( false !== strpos( $html, 'id="' . $id . '"' ) )
			|| ( false !== strpos( $html, "id='" . $id . "'" ) );
	}

	if ( preg_match( '/^\\.([a-zA-Z][\\w-]*)$/', $selector, $m ) ) {
		$cls = $m[1];
		return (bool) preg_match(
			'/class="[^"]*\\b' . preg_quote( $cls, '/' ) . '\\b[^"]*"/',
			$html
		);
	}

	return null;
}

/**
 * 範囲未一致の説明（URL・生 HTML チェック付き）。
 *
 * @param string $html           取得 HTML.
 * @param string $url            対象 URL.
 * @param string $scope_selector 範囲 CSS.
 * @return string
 */
function crb_scope_explain_miss( $html, $url, $scope_selector ) {
	$scope_selector = trim( (string) $scope_selector );
	$base           = sprintf(
		/* translators: %s: CSS selector */
		__( '範囲セレクタ「%s」に一致する要素がありません。', 'custom-rss-builder' ),
		$scope_selector
	);

	$raw_hit = crb_scope_selector_in_raw_html( $html, $scope_selector );
	if ( false === $raw_hit ) {
		$base .= ' ' . __(
			'取得した HTML 内にこの ID/class は見つかりませんでした。JavaScript で後から描画される部分（ブラウザの要素検証でだけ見えるもの）は、サーバー取得では範囲にできません。',
			'custom-rss-builder'
		);
	}

	return $base;
}

/**
 * 範囲未一致時に試すセレクタ一覧（URL 別の追加候補を含む）。
 *
 * @param string $url 対象 URL（空可）。
 * @return array<int, string>
 */
function crb_scope_default_suggestion_selectors( $url = '' ) {
	$candidates = array(
		'#main',
		'main',
		'[role="main"]',
		'#content',
		'article',
		'.newsFeed_list',
		'.newsFeed',
		'ol',
		'ul',
	);

	return $candidates;
}

/**
 * 候補配列を説明文用テキストに整形。
 *
 * @param array<int, array{selector: string, count: int}> $suggestions Suggestions.
 * @return string
 */
function crb_scope_format_suggestions_text( array $suggestions ) {
	if ( empty( $suggestions ) ) {
		return ' ' . __(
			'③「取れる値を一覧表示」で候補を選ぶか、ブラウザの要素検証で class 名を調べてください。',
			'custom-rss-builder'
		);
	}

	$parts = array();
	foreach ( $suggestions as $row ) {
		$parts[] = $row['selector'] . ' (' . (int) $row['count'] . ')';
	}

	return ' ' . sprintf(
		/* translators: %s: comma-separated selectors */
		__( '取得した HTML では次が一致しました: %s', 'custom-rss-builder' ),
		implode( ', ', $parts )
	);
}

/**
 * 範囲未一致時の候補（取得 HTML 内で実際に一致するもののみ）。
 *
 * @param string     $url   対象 URL.
 * @param DOMXPath   $xpath XPath.
 * @param DOMElement $root  crb-root.
 * @return array<int, array{selector: string, count: int}>
 */
function crb_scope_suggestions_for_url( $url, DOMXPath $xpath, DOMElement $root ) {
	$out  = array();
	$seen = array();
	foreach ( crb_scope_default_suggestion_selectors( $url ) as $sel ) {
		if ( isset( $seen[ $sel ] ) ) {
			continue;
		}
		$seen[ $sel ] = true;
		$matches      = crb_scope_query_elements( $xpath, $root, $sel );
		$count        = count( $matches );
		if ( $count > 0 ) {
			$out[] = array(
				'selector' => $sel,
				'count'    => $count,
			);
		}
	}
	return $out;
}

/**
 * 後方互換: URL なし候補（discover 等）。
 *
 * @param DOMXPath   $xpath XPath.
 * @param DOMElement $root  crb-root.
 * @return array<int, array{selector: string, count: int}>
 */
function crb_scope_suggestions( DOMXPath $xpath, DOMElement $root ) {
	return crb_scope_suggestions_for_url( '', $xpath, $root );
}

/**
 * 範囲セレクタ未一致の説明（候補セレクタ付き・モジュール共通入口）。
 *
 * @param string          $html           取得 HTML.
 * @param string          $url            対象 URL.
 * @param string          $scope_selector 範囲 CSS.
 * @param DOMXPath|null   $xpath          既に parse 済みなら渡す（再 parse 省略）。
 * @param DOMElement|null $root           crb-root（$xpath とセット）。
 * @return string
 */
function crb_scope_miss_message( $html, $url, $scope_selector, $xpath = null, $root = null ) {
	$message = crb_scope_explain_miss( $html, $url, $scope_selector );

	if ( ! ( $xpath instanceof DOMXPath ) || ! ( $root instanceof DOMElement ) ) {
		$dom = crb_scope_dom_load( $html );
		if ( is_wp_error( $dom ) ) {
			return $message . crb_scope_format_suggestions_text( array() );
		}
		$root = crb_dom_parse_root( $dom );
		if ( is_wp_error( $root ) ) {
			return $message . crb_scope_format_suggestions_text( array() );
		}
		$xpath = new DOMXPath( $dom );
	}

	$suggestions = crb_scope_suggestions_for_url( $url, $xpath, $root );
	return $message . crb_scope_format_suggestions_text( $suggestions );
}
