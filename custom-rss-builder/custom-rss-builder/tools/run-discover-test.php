<?php
/**
 * CLI: php tools/run-discover-test.php [path-to-html]
 */
define( 'ABSPATH', true );
define( 'CRB_MAX_ITEMS', 20 );

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $message;
		public function __construct( $code, $message ) {
			$this->message = $message;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}

$base = dirname( __DIR__ );
require_once $base . '/includes/functions-css.php';
require_once $base . '/includes/class-element-discovery.php';

$html_file = $argv[1] ?? ( $base . '/test-fixture/dlsite-review-snippet.html' );
$html      = file_get_contents( $html_file );
if ( false === $html ) {
	fwrite( STDERR, "Cannot read: $html_file\n" );
	exit( 1 );
}

$discovery = new Custom_RSS_Builder_Element_Discovery();
$result    = $discovery->discover( $html, '#review_list' );

if ( $result instanceof WP_Error ) {
	fwrite( STDERR, $result->get_error_message() . "\n" );
	exit( 1 );
}

echo 'Scope: ' . $result['scope_label'] . "\n\n";
foreach ( $result['groups'] as $g ) {
	$rec = ! empty( $g['recommended'] ) ? ' [recommended]' : '';
	echo sprintf(
		"%-8s pri=%3d cnt=%3d%s\n  %s\n",
		$g['kind'],
		(int) ( $g['priority'] ?? 0 ),
		(int) ( $g['count'] ?? 0 ),
		$rec,
		$g['selector']
	);
	if ( ! empty( $g['samples'][0] ) ) {
		$s = $g['samples'][0];
		if ( isset( $s['href'] ) ) {
			echo '  sample: ' . ( $s['title'] ?: $s['text'] ) . ' | ' . $s['href'] . "\n";
		}
	}
	echo "\n";
}
