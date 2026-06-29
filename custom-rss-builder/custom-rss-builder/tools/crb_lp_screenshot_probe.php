<?php
/**
 * LP 用スクリーンショット取得プローブ（一時アップロード専用・使用後削除）。
 *
 * @package Custom_RSS_Builder
 */

define( 'CRB_LP_PROBE_TOKEN', '__CRB_LP_PROBE_TOKEN__' );

$wp_load = '';
$dir     = __DIR__;
for ( $i = 0; $i < 8; $i++ ) {
	$candidate = $dir . '/wp-load.php';
	if ( is_readable( $candidate ) ) {
		$wp_load = $candidate;
		break;
	}
	$parent = dirname( $dir );
	if ( $parent === $dir ) {
		break;
	}
	$dir = $parent;
}
if ( '' === $wp_load ) {
	header( 'Content-Type: text/plain; charset=UTF-8', true, 500 );
	echo 'wp-load not found';
	exit;
}

require_once $wp_load;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$token = isset( $_REQUEST['token'] ) ? (string) $_REQUEST['token'] : '';
if ( $token !== CRB_LP_PROBE_TOKEN ) {
	status_header( 403 );
	header( 'Content-Type: text/plain; charset=UTF-8' );
	echo 'forbidden';
	exit;
}

/**
 * @return void
 */
function crb_lp_probe_set_admin() {
	$users = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	if ( ! empty( $users[0] ) ) {
		wp_set_current_user( (int) $users[0] );
	}
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) $_REQUEST['action'] ) : '';

switch ( $action ) {
	case 'list_feeds':
		header( 'Content-Type: application/json; charset=UTF-8' );
		if ( ! function_exists( 'crb_plugin' ) || ! crb_plugin()->feed_manager ) {
			echo wp_json_encode( array( 'ok' => false, 'error' => 'plugin missing' ) );
			exit;
		}
		$feeds = crb_plugin()->feed_manager->get_feeds();
		$list  = array();
		foreach ( $feeds as $fid => $feed ) {
			$list[] = array(
				'id'   => (int) $fid,
				'name' => (string) ( $feed['name'] ?? '' ),
				'url'  => (string) ( $feed['url'] ?? '' ),
			);
		}
		echo wp_json_encode( array( 'ok' => true, 'feeds' => $list ) );
		exit;

	case 'login_edit':
		crb_lp_probe_set_admin();
		if ( ! is_user_logged_in() ) {
			status_header( 500 );
			header( 'Content-Type: text/plain; charset=UTF-8' );
			echo 'admin user missing';
			exit;
		}
		wp_set_auth_cookie( get_current_user_id(), true, is_ssl() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$feed_id = isset( $_REQUEST['feed_id'] ) ? (int) $_REQUEST['feed_id'] : 0;
		if ( $feed_id <= 0 && function_exists( 'crb_plugin' ) && crb_plugin()->feed_manager ) {
			$feeds = crb_plugin()->feed_manager->get_feeds();
			if ( ! empty( $feeds ) ) {
				$feed_id = (int) min( array_map( 'intval', array_keys( $feeds ) ) );
			}
		}
		$target = add_query_arg(
			array(
				'page'    => 'custom-rss-builder',
				'action'  => 'edit',
				'feed_id' => $feed_id,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $target );
		exit;

	default:
		status_header( 400 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo 'unknown action';
		exit;
}
