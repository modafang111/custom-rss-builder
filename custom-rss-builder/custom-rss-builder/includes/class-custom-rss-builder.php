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
	public $html_parser;
	public $item_builder;
	public $rss_generator;
	public $post_importer;
	public $admin_page;
	public $rss_endpoint;
	public $import_endpoint;

	public function __construct() {
		$this->feed_manager  = new Custom_RSS_Builder_Feed_Manager();
		$this->html_fetcher  = new Custom_RSS_Builder_HTML_Fetcher();
		$this->html_parser   = new Custom_RSS_Builder_HTML_Parser();
		$this->item_builder  = new Custom_RSS_Builder_Item_Builder();
		$this->rss_generator = new Custom_RSS_Builder_RSS_Generator( $this->item_builder );
		$this->post_importer = new Custom_RSS_Builder_Post_Importer(
			$this->feed_manager,
			$this->html_fetcher,
			$this->html_parser,
			$this->item_builder
		);

		$this->rss_endpoint = new Custom_RSS_Builder_RSS_Endpoint(
			$this->feed_manager,
			$this->html_fetcher,
			$this->html_parser,
			$this->rss_generator
		);

		$this->import_endpoint = new Custom_RSS_Builder_Import_Endpoint( $this->post_importer );

		add_action( 'init', array( $this->rss_endpoint, 'register_hooks' ) );
		add_action( 'init', array( $this->import_endpoint, 'register_hooks' ) );

		if ( is_admin() ) {
			$this->admin_page = new Custom_RSS_Builder_Admin_Page(
				$this->feed_manager,
				$this->html_fetcher,
				$this->html_parser,
				$this->rss_generator,
				$this->post_importer
			);
			$this->admin_page->register_hooks();
		}
	}
}
