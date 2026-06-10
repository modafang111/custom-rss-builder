<?php
/**
 * プラグイン本体。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder {

	public $feed_manager;
	public $html_fetcher;
	public $item_builder;
	public $rss_generator;
	public $post_importer;
	public $admin_page;
	public $rss_endpoint;
	public $import_endpoint;
	public $import_scheduler;
	public $import_loopback;

	public function __construct() {
		$this->feed_manager  = new Custom_RSS_Builder_Feed_Manager();
		$this->html_fetcher  = new Custom_RSS_Builder_HTML_Fetcher();
		$this->item_builder  = new Custom_RSS_Builder_Item_Builder();
		$this->rss_generator = new Custom_RSS_Builder_RSS_Generator( $this->item_builder );
		$content_template    = new Custom_RSS_Builder_Content_Template();
		$this->post_importer = new Custom_RSS_Builder_Post_Importer(
			$this->feed_manager,
			$this->html_fetcher,
			$this->item_builder,
			$content_template
		);

		$this->rss_endpoint = new Custom_RSS_Builder_RSS_Endpoint(
			$this->feed_manager,
			$this->html_fetcher,
			$this->rss_generator
		);

		$this->import_endpoint  = new Custom_RSS_Builder_Import_Endpoint( $this->post_importer );
		$this->import_scheduler = new Custom_RSS_Builder_Import_Scheduler( $this->feed_manager, $this->post_importer );
		$this->import_loopback  = new Custom_RSS_Builder_Import_Loopback( $this->feed_manager, $this->post_importer );

		// 取り込みは init 優先度 5 で抜けるため、同一リクエスト内に登録する（init@10 から登録すると1回遅れる）。
		$this->import_endpoint->register_hooks();
		$this->import_scheduler->register_hooks();
		$this->import_loopback->register_hooks();

		add_action( 'init', array( $this->rss_endpoint, 'register_hooks' ) );

		if ( is_admin() ) {
			$this->admin_page = new Custom_RSS_Builder_Admin_Page(
				$this->feed_manager,
				$this->html_fetcher,
				$this->rss_generator,
				$this->post_importer
			);
			$this->admin_page->register_hooks();

			$license_admin = new Custom_RSS_Builder_Admin_License();
			$license_admin->register_hooks();
		}

		if ( crb_is_license_server_app_enabled() ) {
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
}
