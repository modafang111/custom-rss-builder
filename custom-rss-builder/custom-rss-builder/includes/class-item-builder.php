<?php
/**
 * 抽出行を RSS / 投稿用の項目に変換。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Item_Builder {

	public function build_items( $feed_settings, $extracted_data ) {
		if ( empty( $extracted_data ) || ! is_array( $extracted_data ) ) {
			return array();
		}

		$feed_url = (string) ( $feed_settings['url'] ?? home_url( '/' ) );
		$mapping  = is_array( $feed_settings['mapping'] ?? null ) ? $feed_settings['mapping'] : array();
		$items    = array();

		foreach ( $extracted_data as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( crb_is_slot_indexed_row( $row ) ) {
				$built = $this->build_from_slot_row( $row, $mapping, $feed_url );
			} elseif ( crb_is_named_record( $row ) ) {
				$built = $this->build_from_record( $row, $feed_url );
			} else {
				$built = $this->build_from_numeric_row( $row, $mapping, $feed_url );
			}

			if ( null === $built ) {
				continue;
			}
			$items[] = $built;
		}

		return $items;
	}

	/**
	 * スロット行: index 0 = {%1}, 1 = {%2}, …
	 *
	 * @param array<int, string> $row      Slot row.
	 * @param array<string, int> $mapping  RSS mapping.
	 * @param string             $feed_url Feed URL.
	 * @return array<string, string>|null
	 */
	private function build_from_slot_row( array $row, array $mapping, $feed_url ) {
		$title = $this->mapped_value( $row, $mapping, 'title' );
		$link  = $this->mapped_value( $row, $mapping, 'link' );
		$desc  = $this->mapped_value( $row, $mapping, 'description' );
		$date  = $this->mapped_value( $row, $mapping, 'date' );

		if ( '' === $title && isset( $row[0] ) ) {
			$title = trim( (string) $row[0] );
		}
		if ( '' === $link && isset( $row[1] ) ) {
			$link = trim( (string) $row[1] );
		}
		if ( '' === $desc && isset( $row[2] ) ) {
			$desc = trim( (string) $row[2] );
		}
		if ( '' === $desc && isset( $row[6] ) ) {
			$desc = wp_trim_words( wp_strip_all_tags( (string) $row[6] ), 40, '...' );
		}

		if ( '' === $title && '' !== $desc ) {
			$title = wp_trim_words( $desc, 12, '...' );
		}

		$link = crb_normalize_link( $this->resolve_url( $link, $feed_url ) );
		if ( '' === $title && '' === $link ) {
			return null;
		}

		return array(
			'title'       => $title,
			'link'        => $link,
			'description' => $desc,
			'date'        => $date,
			'slots'       => $row,
		);
	}

	/**
	 * @param array<string, string> $row      構造化レコード。
	 * @param string                $feed_url Feed URL.
	 * @return array<string, string>|null
	 */
	private function build_from_record( array $row, $feed_url ) {
		return $this->build_from_slot_row( crb_record_to_slot_row( $row ), array(), $feed_url );
	}

	/**
	 * @param array<int, string>   $row      数値インデックス行。
	 * @param array<string, int>   $mapping  割り当て。
	 * @param string               $feed_url Feed URL.
	 * @return array<string, string>|null
	 */
	private function build_from_numeric_row( array $row, array $mapping, $feed_url ) {
		$title = $this->mapped_value( $row, $mapping, 'title' );
		$link  = $this->mapped_value( $row, $mapping, 'link' );
		$desc  = $this->mapped_value( $row, $mapping, 'description' );
		$date  = $this->mapped_value( $row, $mapping, 'date' );

		if ( '' === $title && '' !== $desc ) {
			$title = wp_trim_words( $desc, 12, '...' );
		}

		$link = crb_normalize_link( $this->resolve_url( $link, $feed_url ) );
		if ( '' === $title && '' === $link ) {
			return null;
		}

		return array(
			'title'       => $title,
			'link'        => $link,
			'description' => $desc,
			'date'        => $date,
		);
	}

	private function mapped_value( array $row, array $mapping, $field ) {
		$index = isset( $mapping[ $field ] ) ? (int) $mapping[ $field ] : -1;
		return ( $index >= 0 && isset( $row[ $index ] ) ) ? trim( (string) $row[ $index ] ) : '';
	}

	private function resolve_url( $relative, $base ) {
		$relative = trim( (string) $relative );
		if ( '' === $relative ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $relative ) ) {
			return $relative;
		}
		if ( 0 === strpos( $relative, '//' ) ) {
			$parsed = wp_parse_url( $base );
			$scheme = $parsed['scheme'] ?? 'https';
			return $scheme . ':' . $relative;
		}

		$parsed = wp_parse_url( $base );
		if ( empty( $parsed['host'] ) ) {
			return $relative;
		}

		$scheme = $parsed['scheme'] ?? 'https';
		$host   = $parsed['host'];
		$port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';

		if ( 0 === strpos( $relative, '/' ) ) {
			return $scheme . '://' . $host . $port . $relative;
		}

		$base_path = isset( $parsed['path'] ) ? $parsed['path'] : '/';
		$dir       = trailingslashit( dirname( $base_path ) );
		return $scheme . '://' . $host . $port . $dir . $relative;
	}
}
