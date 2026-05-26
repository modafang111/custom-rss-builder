<?php
/**
 * RSSフィード配信エンドポイント。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_RSS_Endpoint {

	private $feed_manager;
	private $html_fetcher;
	private $html_parser;
	private $rss_generator;

	public function __construct( $feed_manager, $html_fetcher, $html_parser, $rss_generator ) {
		$this->feed_manager  = $feed_manager;
		$this->html_fetcher  = $html_fetcher;
		$this->html_parser   = $html_parser;
		$this->rss_generator = $rss_generator;
	}

	public function register_hooks() {
		self::register_rewrite_rules();
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_feed' ) );
	}

	public static function register_rewrite_rules() {
		add_rewrite_rule(
			'^feed/custom-rss/([0-9]+)/?$',
			'index.php?custom_rss_builder_feed_id=$matches[1]',
			'top'
		);
	}

	public function register_query_vars( $vars ) {
		$vars[] = 'custom_rss_builder_feed_id';
		return $vars;
	}

	public function maybe_serve_feed() {
		$feed_id = (int) get_query_var( 'custom_rss_builder_feed_id' );
		if ( $feed_id <= 0 ) {
			return;
		}

		$feed = $this->feed_manager->get_feed( $feed_id );
		if ( null === $feed ) {
			status_header( 404 );
			nocache_headers();
			echo esc_html__( 'RSSフィードが見つかりません。', 'custom-rss-builder' );
			exit;
		}

		$html = $this->html_fetcher->fetch_html( $feed['url'] );
		if ( is_wp_error( $html ) ) {
			status_header( 502 );
			nocache_headers();
			echo esc_html( $html->get_error_message() );
			exit;
		}

		$parsed = crb_extract_items_from_html( $html, $feed );
		if ( is_wp_error( $parsed ) ) {
			status_header( 502 );
			nocache_headers();
			echo esc_html( $parsed->get_error_message() );
			exit;
		}

		$xml = $this->rss_generator->generate_rss( $feed, $parsed );
		if ( is_wp_error( $xml ) ) {
			status_header( 500 );
			nocache_headers();
			echo esc_html( $xml->get_error_message() );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/rss+xml; charset=UTF-8' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
