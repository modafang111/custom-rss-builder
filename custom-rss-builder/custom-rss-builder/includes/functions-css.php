<?php
/**
 * CSS / 抽出モジュールのローダー（実装は includes/functions-css-*.php に分割）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CRB_PLUGIN_DIR' ) ) {
	define( 'CRB_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

/** 1件プレビュー・要素調べるで表示する先頭件数 */
if ( ! defined( 'CRB_RECORD_PREVIEW_LIMIT' ) ) {
	define( 'CRB_RECORD_PREVIEW_LIMIT', 3 );
}

/** 1件あたりの抽出スロット数（{%1} … {%n}）。Pro プラン上限。 */
if ( ! defined( 'CRB_RECORD_SLOT_COUNT' ) ) {
	define( 'CRB_RECORD_SLOT_COUNT', 20 );
}

/** ③試し読みで表示するスロット数（{%1}…{%n}） */
if ( ! defined( 'CRB_DISCOVER_PREVIEW_SLOTS' ) ) {
	define( 'CRB_DISCOVER_PREVIEW_SLOTS', CRB_RECORD_SLOT_COUNT );
}

$crb_css_modules = array(
	'functions-css-xpath.php',
	'functions-dom-core.php',
	'functions-css-slots.php',
	'functions-css-feed-config.php',
	'functions-css-discover-preview.php',
);

foreach ( $crb_css_modules as $crb_css_module ) {
	$crb_css_path = CRB_PLUGIN_DIR . 'includes/' . $crb_css_module;
	if ( is_readable( $crb_css_path ) ) {
		require_once $crb_css_path;
	}
}
