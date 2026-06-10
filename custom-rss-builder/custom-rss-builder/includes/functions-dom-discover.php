<?php
/**
 * 要素探索（ajax_discover_elements / Element_Discovery）専用 DOM 処理。
 * RSS 抽出（crb_dom_load_html / class-css-extractor）には影響しない。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 要素探索: class 走査ノード上限（巨大ページ対策）。 */
if ( ! defined( 'CRB_DISCOVER_CLASS_SCAN_LIMIT' ) ) {
	define( 'CRB_DISCOVER_CLASS_SCAN_LIMIT', 4000 );
}

/**
 * 要素探索 AJAX の実行時間上限を延長。
 *
 * @return void
 */
function crb_discover_prepare_ajax() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 );
	}
}

/**
 * 要素探索専用 DOM 読み込み（script/style 除去・軽量 parse）。
 *
 * @param string $html HTML.
 * @return DOMDocument|WP_Error
 */
function crb_discover_dom_load( $html ) {
	if ( function_exists( 'crb_scope_dom_load' ) ) {
		return crb_scope_dom_load( $html );
	}
	if ( function_exists( 'crb_dom_load_html' ) ) {
		return crb_dom_load_html( $html );
	}
	return new WP_Error( 'crb_dom_unavailable', __( 'DOM 拡張が利用できません。', 'custom-rss-builder' ) );
}

/**
 * 要素探索: 範囲要素を解決。
 *
 * @param DOMXPath   $xpath          XPath.
 * @param DOMElement $root           crb-root.
 * @param string     $scope_selector CSS.
 * @return DOMElement|null
 */
function crb_discover_resolve_scope( DOMXPath $xpath, DOMElement $root, $scope_selector ) {
	if ( function_exists( 'crb_scope_resolve_element' ) ) {
		return crb_scope_resolve_element( $xpath, $root, $scope_selector );
	}
	return crb_resolve_scope_element( $xpath, $root, $scope_selector );
}

/**
 * @return int
 */
function crb_discover_class_scan_limit() {
	return max( 500, (int) CRB_DISCOVER_CLASS_SCAN_LIMIT );
}

/**
 * 要素探索フロー向け HTML → DOM（discover モード時のみ軽量経路）。
 *
 * @param string $html             HTML.
 * @param bool   $use_discover_dom 要素探索専用 DOM を使う。
 * @return DOMDocument|WP_Error
 */
function crb_discover_dom_load_for_flow( $html, $use_discover_dom = false ) {
	if ( $use_discover_dom ) {
		return crb_discover_dom_load( $html );
	}
	return crb_dom_load_html( $html );
}

/**
 * 要素探索フロー向け範囲解決。
 *
 * @param DOMXPath   $xpath             XPath.
 * @param DOMElement $root              crb-root.
 * @param string     $scope_selector    CSS.
 * @param bool       $use_discover_dom    要素探索専用 DOM を使う。
 * @return DOMElement|null
 */
function crb_discover_resolve_scope_for_flow( DOMXPath $xpath, DOMElement $root, $scope_selector, $use_discover_dom = false ) {
	if ( $use_discover_dom ) {
		return crb_discover_resolve_scope( $xpath, $root, $scope_selector );
	}
	return crb_resolve_scope_element( $xpath, $root, $scope_selector );
}
