<?php
/**
 * ライセンス正本サーバー専用ブートストラップ（フィード/RSS/取り込みは登録しない）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Authority {

	public function __construct() {
		add_action(
			'plugins_loaded',
			static function () {
				if ( function_exists( 'crb_ls_register_hooks' ) ) {
					crb_ls_register_hooks();
				}
			},
			6
		);

	}
}
