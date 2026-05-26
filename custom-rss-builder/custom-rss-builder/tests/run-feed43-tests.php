<?php
/**
 * Feed43 スロット提案の単体テスト（CLI: php tests/run-feed43-tests.php）
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
require_once $base . '/includes/functions-feed43.php';
require_once $base . '/includes/class-css-extractor.php';
require_once $base . '/includes/class-element-discovery.php';

$failures = 0;

/**
 * @param bool   $ok      Pass.
 * @param string $name    Test name.
 * @param string $detail  Detail.
 */
function crb_assert( $ok, $name, $detail = '' ) {
	global $failures;
	if ( $ok ) {
		echo "OK   {$name}\n";
		return;
	}
	++$failures;
	echo "FAIL {$name}";
	if ( '' !== $detail ) {
		echo " — {$detail}";
	}
	echo "\n";
}

/**
 * @param string $fixture Relative to test-fixture/.
 * @param string $scope   Scope selector.
 * @param string $item    Item selector.
 * @return array{suggested: array<string, mixed>, rows: array<int, array<string, mixed>>}
 */
function crb_test_discover_preview( $fixture, $scope, $item ) {
	$path = dirname( __DIR__ ) . '/test-fixture/' . $fixture;
	$html = file_get_contents( $path );
	if ( false === $html ) {
		throw new RuntimeException( "Cannot read {$path}" );
	}
	$discovery = new Custom_RSS_Builder_Element_Discovery();
	$result    = $discovery->discover( $html, $scope, $item );
	if ( $result instanceof WP_Error ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	$suggested = crb_feed43_discover_suggest_slot_rules( $result['groups'] ?? array() );
	$preview   = crb_build_discover_scope_preview( $html, $scope, $item, $result['groups'] ?? array(), 'https://example.com/' );
	$rows      = $preview['rows'] ?? array();
	return array(
		'suggested' => $suggested,
		'rows'      => $rows,
	);
}

function crb_row_value( array $rows, $index ) {
	foreach ( $rows as $row ) {
		if ( (int) ( $row['index'] ?? -1 ) === $index ) {
			return trim( (string) ( $row['value'] ?? '' ) );
		}
	}
	return '';
}

// --- ニュース一覧（Feed43 基本） ---
$r = crb_test_discover_preview( 'sample-news.html', '', '.news-item' );
crb_assert(
	strpos( (string) ( $r['suggested']['link_selector'] ?? '' ), 'news-item' ) !== false,
	'news: primary link selector repeats per item',
	$r['suggested']['link_selector'] ?? ''
);
crb_assert(
	strpos( crb_row_value( $r['rows'], 0 ), '初めての物件見学' ) !== false,
	'news: {%1} = link text (not site-specific field)',
	crb_row_value( $r['rows'], 0 )
);
crb_assert(
	strpos( crb_row_value( $r['rows'], 1 ), 'first-item' ) !== false,
	'news: {%2} = article href',
	crb_row_value( $r['rows'], 1 )
);

// --- Yahoo 風 ---
$r = crb_test_discover_preview( 'yahoo-style-list.html', '.newsFeed', '.newsFeed_list_item' );
crb_assert(
	strpos( (string) ( $r['suggested']['link_selector'] ?? '' ), 'newsFeed' ) !== false,
	'yahoo: list item link pattern',
	$r['suggested']['link_selector'] ?? ''
);
crb_assert(
	strpos( crb_row_value( $r['rows'], 0 ), '政府' ) !== false,
	'yahoo: {%1} headline text',
	crb_row_value( $r['rows'], 0 )
);

// --- Livedoor 風 ---
$r = crb_test_discover_preview( 'livedoor-style-list.html', '.articleList', '.articleList-article' );
crb_assert(
	strpos( crb_row_value( $r['rows'], 0 ), '記事タイトル第一号' ) !== false,
	'livedoor: {%1} from h2 link',
	crb_row_value( $r['rows'], 0 )
);
crb_assert(
	strpos( crb_row_value( $r['rows'], 1 ), 'livedoor.com' ) !== false,
	'livedoor: {%2} article URL',
	crb_row_value( $r['rows'], 1 )
);

// --- DLsite（検証用）: 1件ブロックで {%1}{%2} が取れること ---
$r = crb_test_discover_preview( 'dlsite-review-snippet.html', '#review_list', '.review_contents' );
crb_assert( '' !== trim( (string) ( $r['suggested']['link_selector'] ?? '' ) ), 'dlsite: primary link selector present', $r['suggested']['link_selector'] ?? '' );
crb_assert( '' !== crb_row_value( $r['rows'], 0 ), 'dlsite: {%1} title', crb_row_value( $r['rows'], 0 ) );
crb_assert( '' !== crb_row_value( $r['rows'], 1 ), 'dlsite: {%2} href', crb_row_value( $r['rows'], 1 ) );
// 最長テキストは {%3} 以降に入る（レビュー本文が作品紹介より長ければ review_desc 側）
$summary = crb_row_value( $r['rows'], 2 );
crb_assert( '' !== $summary, 'dlsite: {%3} has longest text field', $summary );

echo "\n";
if ( $failures > 0 ) {
	echo "{$failures} test(s) failed.\n";
	exit( 1 );
}
echo "All Feed43 tests passed.\n";
exit( 0 );
