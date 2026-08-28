<?php
/**
 * Temporary DLM download #695 diagnostic (delete after use).
 */
declare(strict_types=1);

header( 'Content-Type: application/json; charset=UTF-8' );

$secret = getenv( 'CRB_PROBE_SECRET' );
if ( ! is_string( $secret ) || '' === $secret ) {
	$secret = 'crb-dlm-diagnose';
}
if ( ( $_GET['secret'] ?? '' ) !== $secret ) {
	http_response_code( 403 );
	echo json_encode( array( 'error' => 'forbidden' ), JSON_UNESCAPED_UNICODE );
	exit;
}

$wp_load = __DIR__ . '/wp-load.php';
if ( ! is_file( $wp_load ) ) {
	$wp_load = dirname( __DIR__ ) . '/wp-load.php';
}
if ( ! is_file( $wp_load ) ) {
	http_response_code( 500 );
	echo json_encode( array( 'error' => 'wp-load.php not found', 'tried' => $wp_load ), JSON_UNESCAPED_UNICODE );
	exit;
}

require $wp_load;

$out = array(
	'build' => defined( 'CRB_BUILD_ID' ) ? (string) CRB_BUILD_ID : '',
	'variant' => defined( 'CRB_PACKAGE_VARIANT' ) ? (string) CRB_PACKAGE_VARIANT : '',
);

$download_id = 695;
$out['post'] = null;
$post = get_post( $download_id );
if ( $post instanceof WP_Post ) {
	$out['post'] = array(
		'ID' => (int) $post->ID,
		'post_type' => $post->post_type,
		'post_status' => $post->post_status,
		'post_name' => $post->post_name,
		'permalink' => (string) get_permalink( $post ),
	);
}

$zip_rel = 'wp-content/uploads/dlm_uploads/2026/06/custom-rss-builder-client.zip';
$zip_abs = ABSPATH . $zip_rel;
$out['zip'] = array(
	'relative' => $zip_rel,
	'absolute' => $zip_abs,
	'exists' => is_file( $zip_abs ),
	'size' => is_file( $zip_abs ) ? (int) filesize( $zip_abs ) : 0,
);

if ( is_file( $zip_abs ) ) {
	$out['zip']['sha1'] = sha1_file( $zip_abs );
}

if ( function_exists( 'download_monitor' ) ) {
	$out['dlm'] = array( 'plugin' => 'present' );
	try {
		$dlm = download_monitor();
		$out['dlm']['class'] = is_object( $dlm ) ? get_class( $dlm ) : gettype( $dlm );
	} catch ( Throwable $e ) {
		$out['dlm']['bootstrap_error'] = $e->getMessage();
	}
} else {
	$out['dlm'] = array( 'plugin' => 'missing' );
}

$out['theme'] = array(
	'template' => (string) get_option( 'template', '' ),
	'stylesheet' => (string) get_option( 'stylesheet', '' ),
);

$meta_keys = array( '_downloadable_files', '_members_only', '_featured' );
$out['post_meta'] = array();
if ( $post instanceof WP_Post ) {
	foreach ( $meta_keys as $key ) {
		$val = get_post_meta( $post->ID, $key, true );
		if ( '' !== $val && null !== $val ) {
			$out['post_meta'][ $key ] = $val;
		}
	}
}

$page_url = home_url( '/download/695/' );
if ( function_exists( 'wp_remote_get' ) ) {
	$response = wp_remote_get(
		$page_url,
		array(
			'timeout'   => 30,
			'sslverify' => false,
		)
	);
	if ( is_wp_error( $response ) ) {
		$out['page_fetch'] = array( 'error' => $response->get_error_message() );
	} else {
		$body = (string) wp_remote_retrieve_body( $response );
		$out['page_fetch'] = array(
			'url' => $page_url,
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body_len' => strlen( $body ),
			'body_head' => substr( $body, 0, 1200 ),
		);
	}
}

echo json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
