<?php
/**
 * 他プラグインとの互換（当プラグイン側のワークアラウンド）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Download Monitor: WP 6.9.1+ で dlm-reports-upsells が未登録の dlm-reports-app に依存して Notice が出る。
 * 正本サイト等で DLM を併用する場合、空のスタブを先に register して警告を防ぐ。
 */
function crb_compat_register_dlm_reports_app_stub() {
	if ( wp_script_is( 'dlm-reports-app', 'registered' ) ) {
		return;
	}

	wp_register_script( 'dlm-reports-app', false, array(), null, true );
}

add_action( 'admin_enqueue_scripts', 'crb_compat_register_dlm_reports_app_stub', 0 );
add_action( 'wp_enqueue_scripts', 'crb_compat_register_dlm_reports_app_stub', 0 );
