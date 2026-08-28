<?php
/**
 * 一時プローブ: 正本 LP / インストール手順を強制再生成。
 * 実行後は必ず FTP から削除すること。
 *
 * Usage: https://123789.jp/custom-rss-builder/wp-content/plugins/custom-rss-builder/tools/crb_force_lp_reinstall.php?key=...
 */
define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';

header( 'Content-Type: application/json; charset=utf-8' );

$expected = getenv( 'CRB_PROBE_KEY' );
$key      = isset( $_GET['key'] ) ? (string) $_GET['key'] : '';
if ( '' === $expected || ! hash_equals( $expected, $key ) ) {
	status_header( 403 );
	echo wp_json_encode( array( 'ok' => false, 'error' => 'forbidden' ) );
	exit;
}

$out = array( 'ok' => true, 'results' => array() );

if ( function_exists( 'crb_sales_lp_install' ) ) {
	$out['results']['sales_lp'] = crb_sales_lp_install( true );
}
if ( function_exists( 'crb_install_manual_install' ) ) {
	$out['results']['install_manual'] = crb_install_manual_install( true );
}

echo wp_json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
