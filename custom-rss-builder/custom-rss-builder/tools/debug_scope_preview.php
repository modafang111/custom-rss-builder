<?php
/**
 * CLI: php tools/debug_scope_preview.php
 * Debug scope preview against test-fixture/review-list-sample.html
 */
define( 'ABSPATH', true );
define( 'CRB_MAX_ITEMS', 20 );
define( 'CRB_RECORD_SLOT_COUNT', 20 );
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
require_once $base . '/includes/functions-discover-infer.php';
require_once $base . '/includes/functions-feed43.php';
require_once $base . '/includes/class-css-extractor.php';
require_once $base . '/includes/class-element-discovery.php';

$html  = file_get_contents( $base . '/test-fixture/review-list-sample.html' );
$scope = '#review_list';
$item  = '.review_contents';

$discovery = new Custom_RSS_Builder_Element_Discovery();
$result    = $discovery->discover( $html, $scope, $item );
if ( $result instanceof WP_Error ) {
	fwrite( STDERR, $result->get_error_message() . "\n" );
	exit( 1 );
}

echo 'groups: ' . count( $result['groups'] ?? array() ) . "\n";
echo 'item_match_count: ' . (int) ( $result['item_match_count'] ?? 0 ) . "\n";
echo 'probe_anchors: ' . (int) ( $result['probe_anchor_count'] ?? 0 ) . "\n";

$suggested = crb_discover_suggest_slot_rules( $result['groups'] ?? array() );
echo 'link_selector: ' . ( $suggested['link_selector'] ?? '(empty)' ) . "\n";
echo 'image_selector: ' . ( $suggested['image_selector'] ?? '(empty)' ) . "\n";

$preview = crb_build_discover_scope_preview( $html, $scope, $item, $result['groups'] ?? array(), 'https://example.com/' );
echo 'context_note: ' . ( $preview['context_note'] ?? '' ) . "\n";
echo "rows:\n";
foreach ( $preview['rows'] ?? array() as $row ) {
	$idx = (int) ( $row['index'] ?? 0 );
	$val = (string) ( $row['value'] ?? '' );
	echo sprintf(
		"  {%d} sel=%s mode=%s val=%s\n",
		$idx + 1,
		$row['selector'] ?? '—',
		$row['mode_label'] ?? '',
		mb_substr( $val, 0, 60 ) . ( mb_strlen( $val ) > 60 ? '…' : '' )
	);
}

$fail = 0;
if ( '' === crb_row_val( $preview['rows'] ?? array(), 0 ) ) {
	++$fail;
}
if ( '' === crb_row_val( $preview['rows'] ?? array(), 1 ) ) {
	++$fail;
}
exit( $fail > 0 ? 1 : 0 );

function crb_row_val( array $rows, $index ) {
	foreach ( $rows as $row ) {
		if ( (int) ( $row['index'] ?? -1 ) === $index ) {
			return trim( (string) ( $row['value'] ?? '' ) );
		}
	}
	return '';
}
