<?php
/**
 * CLI: end-to-end extract checks (no WordPress).
 * Run: php tools/verify_extract_flow.php
 */
define( 'ABSPATH', true );
define( 'CRB_MAX_ITEMS', 20 );
define( 'CRB_RECORD_SLOT_COUNT', 20 );
define( 'CRB_RECORD_PREVIEW_LIMIT', 3 );

$base = dirname( __DIR__ );
define( 'CRB_PLUGIN_DIR', $base . '/' );

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

require_once $base . '/includes/functions-sanitize.php';
require_once $base . '/includes/functions-css.php';
require_once $base . '/includes/class-css-extractor.php';
require_once $base . '/includes/functions-extract-candidates.php';

$fail = 0;

function crb_vfail( $label ) {
	global $fail;
	echo "FAIL {$label}\n";
	++$fail;
}

function crb_vok( $label ) {
	echo "OK   {$label}\n";
}

// nth-child xpath
$xp = crb_css_to_xpath( 'td.work_1col_thumb:nth-child(1)' );
if ( is_wp_error( $xp ) || false === strpos( (string) $xp, 'preceding-sibling' ) ) {
	crb_vfail( 'nth-child xpath' );
} else {
	crb_vok( 'nth-child xpath' );
}

if ( ! crb_is_usable_image_url( '/modpub/foo.jpg' ) ) {
	crb_vfail( 'relative image url' );
} else {
	crb_vok( 'relative image url' );
}

if ( crb_is_usable_image_url( 'data:image/gif;base64,abc' ) ) {
	crb_vfail( 'data: image rejected' );
} else {
	crb_vok( 'data: image rejected' );
}

$html = file_get_contents( $base . '/test-fixture/review-list-sample.html' );
if ( false === $html ) {
	fwrite( STDERR, "fixture missing\n" );
	exit( 1 );
}

$base_url = 'https://example.com/';
$config   = crb_sanitize_css_config(
	array(
		'scope_selector'   => '.review_inner',
		'item_selector'    => '.review_contents',
		'link_selector'    => 'dt.work_name a',
		'title_mode'       => 'attr',
		'title_attr'       => 'title',
		'title_selector'   => '',
		'image_selector'   => 'div.work_img_popover img',
		'slot_mode_4'      => 'src',
	)
);

$rows = crb_extract_items_from_html( $html, array( 'url' => $base_url, 'css' => $config ) );
if ( is_wp_error( $rows ) ) {
	crb_vfail( 'extract rows: ' . $rows->get_error_message() );
} elseif ( empty( $rows ) ) {
	crb_vfail( 'extract rows empty' );
} else {
	$slot4 = trim( (string) ( $rows[0][3] ?? '' ) );
	if ( '' === $slot4 || false === strpos( $slot4, 'cdn.example.com' ) ) {
		crb_vfail( 'slot {%4%} image src (got: ' . mb_substr( $slot4, 0, 60 ) . ')' );
	} else {
		crb_vok( 'slot {%4%} image src from user selector' );
	}
	$slot1 = trim( (string) ( $rows[0][0] ?? '' ) );
	if ( '' === $slot1 ) {
		crb_vfail( 'slot {%1%} title empty' );
	} else {
		crb_vok( 'slot {%1%} title present' );
	}
}

$user_thumb_sel = 'div.review_work.review_contents_inner table.work_1col_table td.work_1col_thumb:nth-child(1) div.work_thumb div.work_thumb_inner thumb-with-ng-filter-block';
$xp_user      = crb_css_to_xpath( $user_thumb_sel );
if ( is_wp_error( $xp_user ) || false === stripos( (string) $xp_user, 'thumb-with-ng-filter-block' ) ) {
	crb_vfail( 'xpath for hyphenated custom element tag' );
} else {
	crb_vok( 'xpath for hyphenated custom element tag' );
}

// Fragment without body tag should still parse via crb-root.
$frag_rows = crb_extract_items_from_html(
	'<div class="review_contents"><a href="https://example.com/x">T</a></div>',
	array(
		'url' => 'https://example.com/',
		'css' => crb_sanitize_css_config(
			array(
				'item_selector' => '.review_contents',
				'link_selector' => 'a',
			)
		),
	)
);
if ( is_wp_error( $frag_rows ) || empty( $frag_rows ) ) {
	crb_vfail( 'fragment extract without body' );
} else {
	crb_vok( 'fragment extract without body' );
}

// ③候補の値と⑤プレビュー（同一 extract）が一致すること。
$cand = crb_build_extract_candidates(
	$html,
	'.review_inner',
	'.review_contents',
	$base_url
);
if ( is_wp_error( $cand ) || empty( $cand['rows'] ) ) {
	crb_vfail( 'candidate rows for review-list fixture' );
} else {
	$dom      = crb_dom_load_html( $html );
	$root     = crb_dom_parse_root( $dom );
	$xpath    = new DOMXPath( $dom );
	$scope    = crb_resolve_scope_element( $xpath, $root, '.review_inner' );
	$context  = $scope instanceof DOMElement ? $scope : $root;
	$item_xp  = crb_css_to_xpath( '.review_contents' );
	$item_nodes = $xpath->query( (string) $item_xp, $context );
	if ( false !== $item_nodes && $item_nodes->length > 0 && $item_nodes->item( 0 ) instanceof DOMElement ) {
		$context = $item_nodes->item( 0 );
	}
	$extractor = new Custom_RSS_Builder_Css_Extractor();
	$mismatch    = 0;
	foreach ( array_slice( $cand['rows'], 0, 40 ) as $row ) {
		$sel  = (string) ( $row['selector'] ?? '' );
		$mode = (string) ( $row['mode'] ?? '' );
		$val  = (string) ( $row['value'] ?? '' );
		if ( '' === $sel || '' === $mode ) {
			continue;
		}
		$rule  = crb_candidate_mode_to_slot_rule( $mode );
		$rule['selector'] = $sel;
		$probe = $extractor->probe_slot_in_context( $xpath, $context, $rule, $base_url );
		if ( 'html' !== ( $rule['mode'] ?? '' ) ) {
			$probe = preg_replace( '/\s+/u', ' ', trim( (string) $probe ) );
			$val   = preg_replace( '/\s+/u', ' ', trim( $val ) );
		}
		$probe_short = mb_strlen( $probe ) > 800 ? mb_substr( $probe, 0, 799 ) . '…' : $probe;
		if ( $probe_short !== $val ) {
			++$mismatch;
			if ( $mismatch <= 2 ) {
				crb_vfail( "candidate vs probe mismatch {$mode} {$sel}" );
			}
		}
	}
	if ( 0 === $mismatch ) {
		crb_vok( 'candidate row values match probe_slot_in_context' );
	}
}

$demo_defs = $base . '/includes/functions-demo-samples.php';
if ( is_readable( $demo_defs ) ) {
	require_once $demo_defs;
	if ( function_exists( 'crb_demo_sample_pattern_definitions' ) ) {
		foreach ( crb_demo_sample_pattern_definitions() as $pattern ) {
			$file = $base . '/samples/' . (string) ( $pattern['file'] ?? '' );
			$id   = (string) ( $pattern['id'] ?? '?' );
			if ( ! is_readable( $file ) ) {
				crb_vfail( "demo sample file missing: {$id}" );
				continue;
			}
			$html = file_get_contents( $file );
			if ( false === $html ) {
				crb_vfail( "demo sample read failed: {$id}" );
				continue;
			}
			$config = crb_sanitize_css_config(
				array(
					'scope_selector'   => (string) ( $pattern['scope_selector'] ?? '' ),
					'item_selector'    => (string) ( $pattern['item_selector'] ?? '' ),
					'link_selector'    => (string) ( $pattern['link_selector'] ?? '' ),
					'title_mode'       => (string) ( $pattern['title_mode'] ?? 'text' ),
					'title_selector'   => (string) ( $pattern['title_selector'] ?? '' ),
					'summary_selector' => (string) ( $pattern['summary_selector'] ?? '' ),
				)
			);
			$rows = crb_extract_items_from_html(
				$html,
				array(
					'url' => 'https://example.com/',
					'css' => $config,
				)
			);
			if ( is_wp_error( $rows ) ) {
				crb_vfail( "demo {$id}: " . $rows->get_error_message() );
			} elseif ( empty( $rows ) ) {
				crb_vfail( "demo {$id}: no rows" );
			} else {
				$title = trim( (string) ( $rows[0][0] ?? '' ) );
				$link  = trim( (string) ( $rows[0][1] ?? '' ) );
				if ( '' === $title || '' === $link ) {
					crb_vfail( "demo {$id}: empty title/link" );
				} else {
					crb_vok( "demo pattern {$id} (" . count( $rows ) . ' rows)' );
				}
			}
		}
	}
}

exit( $fail > 0 ? 1 : 0 );
