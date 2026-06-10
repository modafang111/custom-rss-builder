<?php
/**
 * CLI: php tools/test_extract_candidates.php [fixture.html]
 */
define( 'ABSPATH', true );

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url ) {
		return parse_url( $url );
	}
}

$base = dirname( __DIR__ );
require_once $base . '/includes/functions-sanitize.php';
require_once $base . '/includes/functions-css.php';
require_once $base . '/includes/functions-extract-candidates.php';

$fixture = isset( $argv[1] ) ? $argv[1] : $base . '/test-fixture/dlsite-review-inner-fragment.html';
$html    = file_get_contents( $fixture );
if ( false === $html ) {
	fwrite( STDERR, "Cannot read: $fixture\n" );
	exit( 1 );
}

$scope = '.review_contents';
$item  = '';
if ( false !== strpos( $html, 'review_inner' ) ) {
	$scope = '.review_inner';
	$item  = '.review_contents';
}

$result = crb_build_extract_candidates( $html, $scope, $item, 'https://www.dlsite.com/maniax/' );
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, $result->get_error_message() . "\n" );
	exit( 1 );
}

echo ( $result['context_note'] ?? '' ) . "\n";
echo 'rows: ' . (int) ( $result['row_count'] ?? 0 ) . "\n\n";
foreach ( $result['rows'] ?? array() as $row ) {
	echo (int) ( $row['match_count'] ?? 0 ) . "\t" . ( $row['selector'] ?? '' ) . "\t" . ( $row['mode_label'] ?? '' ) . "\t" . mb_substr( (string) ( $row['value'] ?? '' ), 0, 60 ) . "\n";
}
