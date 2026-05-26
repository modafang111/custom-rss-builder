<?php
/**
 * RSS 2.0 XML生成。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_RSS_Generator {

	/** @var Custom_RSS_Builder_Item_Builder */
	private $item_builder;

	public function __construct( $item_builder = null ) {
		$this->item_builder = $item_builder ? $item_builder : new Custom_RSS_Builder_Item_Builder();
	}

	public function generate_rss( $feed_settings, $extracted_data ) {
		$items = $this->item_builder->build_items( $feed_settings, $extracted_data );
		if ( empty( $items ) ) {
			return new WP_Error( 'crb_empty_items', __( 'RSSフィードの生成に失敗しました。', 'custom-rss-builder' ) );
		}

		$feed_name = (string) ( $feed_settings['name'] ?? 'Custom RSS Feed' );
		$feed_url  = (string) ( $feed_settings['url'] ?? home_url( '/' ) );
		$self_url  = ( new Custom_RSS_Builder_Feed_Manager() )->get_feed_url( (int) ( $feed_settings['id'] ?? 0 ) );

		$dom               = new DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = true;

		$rss = $dom->createElement( 'rss' );
		$rss->setAttribute( 'version', '2.0' );
		$dom->appendChild( $rss );

		$channel = $dom->createElement( 'channel' );
		$rss->appendChild( $channel );

		$this->append_text_element( $dom, $channel, 'title', $feed_name );
		$this->append_text_element( $dom, $channel, 'link', $feed_url );
		$this->append_text_element( $dom, $channel, 'description', $feed_name );
		$this->append_text_element( $dom, $channel, 'generator', 'Custom RSS Builder ' . CRB_VERSION );
		$this->append_text_element( $dom, $channel, 'lastBuildDate', $this->format_pub_date( '' ) );

		foreach ( $items as $item ) {
			$title = (string) ( $item['title'] ?? '' );
			$link  = (string) ( $item['link'] ?? '' );
			$desc  = (string) ( $item['description'] ?? '' );
			$date  = (string) ( $item['date'] ?? '' );

			if ( '' === $title ) {
				$title = $feed_name;
			}

			$element = $dom->createElement( 'item' );
			$this->append_text_element( $dom, $element, 'title', $title );
			if ( '' !== $link ) {
				$this->append_text_element( $dom, $element, 'link', $link );
				$this->append_text_element( $dom, $element, 'guid', $link );
			} else {
				$this->append_text_element( $dom, $element, 'guid', $self_url . '#item-' . md5( $title . $desc ) );
			}
			$this->append_text_element( $dom, $element, 'description', $desc );
			$this->append_text_element( $dom, $element, 'pubDate', $this->format_pub_date( $date ) );
			$channel->appendChild( $element );
		}

		return $dom->saveXML();
	}

	private function format_pub_date( $date ) {
		$date = trim( (string) $date );
		$time = $date ? strtotime( $date ) : false;
		if ( false === $time ) {
			$time = time();
		}
		return gmdate( 'D, d M Y H:i:s', $time ) . ' GMT';
	}

	private function append_text_element( DOMDocument $dom, DOMElement $parent, $tag, $value ) {
		$element = $dom->createElement( $tag );
		$element->appendChild( $dom->createTextNode( (string) $value ) );
		$parent->appendChild( $element );
	}
}
