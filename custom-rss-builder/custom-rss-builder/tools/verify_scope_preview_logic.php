<?php
/**
 * CLI: scope preview on review-list fixture (no WordPress).
 * Run: php tools/verify_scope_preview_logic.php
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

$preview = crb_build_discover_scope_preview( $html, $scope, $item, $result['groups'] ?? array(), 'https://example.com/' );

function crb_vrow( array $rows, $index ) {
	foreach ( $rows as $row ) {
		if ( (int) ( $row['index'] ?? -1 ) === $index ) {
			return $row;
		}
	}
	return array();
}

$checks = array(
	array( 0, '作品タイトル第一号', '{%1} title' ),
	array( 1, 'example.com/work', '{%2} link' ),
	array( 2, 'レビュー本文', '{%3} body' ),
	array( 3, 'AAA001_img_main', '{%4} image' ),
);

$fail = 0;
echo 'version_check: functions-discover-infer loaded=' . ( function_exists( 'crb_infer_css_config_from_context' ) ? 'yes' : 'no' ) . "\n";
echo 'context_note: ' . ( $preview['context_note'] ?? '' ) . "\n";
echo 'anchor_count: ' . (int) ( $preview['anchor_count'] ?? 0 ) . "\n";
echo "rows:\n";

foreach ( $checks as $c ) {
	list( $idx, $needle, $label ) = $c;
	$row = crb_vrow( $preview['rows'] ?? array(), $idx );
	$val = trim( (string) ( $row['value'] ?? '' ) );
	$sel = (string) ( $row['selector'] ?? '—' );
	$ok  = '' !== $val && false !== strpos( $val, $needle );
	echo ( $ok ? 'OK  ' : 'FAIL' ) . " {$label} val=" . mb_substr( $val, 0, 50 ) . " sel={$sel}\n";
	if ( ! $ok ) {
		++$fail;
	}
}

$max_index = -1;
foreach ( $preview['rows'] ?? array() as $row ) {
	$max_index = max( $max_index, (int) ( $row['index'] ?? -1 ) );
}
$item_previews = $preview['item_previews'] ?? array();
echo 'item_previews: ' . count( $item_previews ) . ' preview_shown: ' . (int) ( $preview['preview_shown'] ?? 0 ) . "\n";
if ( count( $item_previews ) < 2 ) {
	echo "FAIL expected at least 2 item previews in fixture\n";
	++$fail;
} else {
	echo "OK   multiple item previews (" . count( $item_previews ) . ")\n";
}

echo 'row_count: ' . count( $preview['rows'] ?? array() ) . " max_index: {$max_index}\n";
if ( (int) CRB_DISCOVER_PREVIEW_SLOTS !== count( $preview['rows'] ?? array() ) ) {
	echo 'FAIL preview row count expected ' . (int) CRB_DISCOVER_PREVIEW_SLOTS . "\n";
	++$fail;
} else {
	echo 'OK   preview shows ' . (int) CRB_DISCOVER_PREVIEW_SLOTS . " slots\n";
}
if ( $max_index !== (int) CRB_DISCOVER_PREVIEW_SLOTS - 1 ) {
	echo "FAIL max slot index expected " . ( (int) CRB_DISCOVER_PREVIEW_SLOTS - 1 ) . "\n";
	++$fail;
} else {
	echo "OK   max slot index {%" . ( $max_index + 1 ) . "}\n";
}

if ( ! crb_is_usable_image_url( '/modpub/AAA001_img_main.jpg' ) ) {
	echo "FAIL relative image path usable\n";
	++$fail;
} else {
	echo "OK   relative image path usable\n";
}

// XPath hyphen
$xp = crb_css_to_xpath( 'thumb-with-ng-filter-block[link]' );
if ( is_wp_error( $xp ) || false === strpos( (string) $xp, 'thumb-with-ng-filter-block' ) ) {
	echo "FAIL hyphen xpath\n";
	++$fail;
} else {
	echo "OK   hyphen xpath\n";
}

exit( $fail > 0 ? 1 : 0 );
