<?php
/**
 * CLI: php tools/simulate_scope_preview.php
 * Simulates discover scope preview ({%1}…{%8}) for DLsite fixture.
 */
define( 'ABSPATH', true );
define( 'CRB_MAX_ITEMS', 20 );
define( 'CRB_RECORD_SLOT_COUNT', 12 );
define( 'CRB_RECORD_PREVIEW_LIMIT', 3 );

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

$base = dirname( __DIR__ );
require_once $base . '/includes/functions-css.php';
require_once $base . '/includes/class-css-extractor.php';
require_once $base . '/includes/class-element-discovery.php';

$html_file = $argv[1] ?? ( $base . '/test-fixture/dlsite-review-snippet.html' );
$html      = file_get_contents( $html_file );
if ( false === $html ) {
	fwrite( STDERR, "Cannot read: $html_file\n" );
	exit( 1 );
}

$discovery = new Custom_RSS_Builder_Element_Discovery();
$cases     = array(
	array(
		'label'  => 'work_1col_table + item=.review_contents (fallback)',
		'scope'  => 'table.work_1col_table',
		'item'   => '.review_contents',
	),
	array(
		'label'  => 'work_1col_table + item=empty',
		'scope'  => 'table.work_1col_table',
		'item'   => '',
	),
	array(
		'label'  => '#review_list + item=.review_contents',
		'scope'  => '#review_list',
		'item'   => '.review_contents',
	),
);

foreach ( $cases as $case ) {
	echo "=== {$case['label']} ===\n";
	$result = $discovery->discover( $html, $case['scope'], $case['item'] );
	if ( $result instanceof WP_Error ) {
		echo 'DISCOVER ERROR: ' . $result->get_error_message() . "\n\n";
		continue;
	}
	$preview = crb_build_discover_scope_preview(
		$html,
		$case['scope'],
		$case['item'],
		$result['groups'] ?? array(),
		'https://www.dlsite.com/maniax/'
	);
	echo 'Note: ' . ( $preview['context_note'] ?? '' ) . "\n";
	$rows = $preview['rows'] ?? array();
	if ( empty( $rows ) ) {
		echo "(no rows)\n\n";
		continue;
	}
	foreach ( $rows as $row ) {
		$val = (string) ( $row['value'] ?? '' );
		if ( strlen( $val ) > 72 ) {
			$val = substr( $val, 0, 69 ) . '...';
		}
		printf(
			"%-6s %-28s %-18s %s\n",
			(string) ( $row['token'] ?? '' ),
			(string) ( $row['selector'] ?? '—' ),
			(string) ( $row['mode_label'] ?? '' ),
			'' !== $val ? $val : '(empty)'
		);
	}
	echo "\n";
}
