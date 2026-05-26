<?php
/**
 * 投稿本文・タイトル用テンプレート（Feed43 の Item テンプレート相当）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Content_Template {

	/**
	 * @param string                      $template HTMLテンプレート。
	 * @param array<string, string>       $item     マッピング済み項目。
	 * @param array<int|string, string>   $row      抽出行。
	 * @param array<string, int>          $mapping  割り当て。
	 * @return string
	 */
	public function render( $template, array $item, array $row, array $mapping ) {
		$template = (string) $template;
		if ( '' === trim( $template ) ) {
			return '';
		}

		$replacements = $this->build_replacements( $item, $row, $mapping );
		$keys         = array_keys( $replacements );
		usort(
			$keys,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		$output = $template;
		foreach ( $keys as $key ) {
			$output = str_replace( $key, $replacements[ $key ], $output );
		}

		return wp_kses_post( $output );
	}

	/**
	 * 投稿タイトル用（URL 化しない。{%1}=index0 … をそのままテキストで差し替え）。
	 *
	 * @param string                      $template Template.
	 * @param array<int|string, string>   $row      Slot row.
	 * @return string
	 */
	public function render_plain_slot_template( $template, array $row ) {
		$template = (string) $template;
		if ( '' === trim( $template ) ) {
			return '';
		}
		if ( crb_is_named_record( $row ) ) {
			$row = crb_record_to_slot_row( $row );
		}
		if ( ! crb_is_slot_indexed_row( $row ) ) {
			return '';
		}

		$output = $template;
		foreach ( $row as $index => $value ) {
			if ( ! is_int( $index ) && ! ctype_digit( (string) $index ) ) {
				continue;
			}
			$idx  = (int) $index;
			$safe = esc_html( trim( (string) $value ) );
			$n    = $idx + 1;
			$output = str_replace(
				array( '{%' . $n . '}', '%' . $n, '{{' . $idx . '}}' ),
				$safe,
				$output
			);
		}
		return $output;
	}

	/**
	 * @param array<string, string>      $item    Mapped item.
	 * @param array<int|string, string>  $row     Raw row.
	 * @param array<string, int>         $mapping Field mapping.
	 * @return array<string, string>
	 */
	private function build_replacements( array $item, array $row, array $mapping ) {
		$map    = array();
		$schema = crb_get_record_slot_schema();
		// {%1}=index0（タイトル）、{%2}=index1（リンク）は固定。RSS 用 map_link/map_title とは別。
		$link_idx = crb_is_slot_indexed_row( $row ) || crb_is_named_record( $row )
			? 1
			: ( isset( $mapping['link'] ) ? (int) $mapping['link'] : 1 );

		if ( crb_is_slot_indexed_row( $row ) ) {
			foreach ( $schema as $index => $slot ) {
				if ( ! array_key_exists( $index, $row ) ) {
					continue;
				}
				$value = trim( (string) $row[ $index ] );
				$safe  = $this->escape_slot_value( $slot, $value, (int) $index === $link_idx );
				$n     = $index + 1;
				$map[ '{%' . $n . '}' ]  = $safe;
				$map[ '%' . $n ]         = $safe;
				$map[ '{{' . $index . '}}' ] = $safe;
			}
		} elseif ( crb_is_named_record( $row ) ) {
			$indexed = crb_record_to_slot_row( $row );
			foreach ( $schema as $index => $slot ) {
				$value = trim( (string) ( $indexed[ $index ] ?? '' ) );
				$safe  = $this->escape_slot_value( $slot, $value, (int) $index === $link_idx );
				$n     = $index + 1;
				$map[ '{%' . $n . '}' ]  = $safe;
				$map[ '%' . $n ]         = $safe;
				$map[ '{{' . $index . '}}' ] = $safe;
			}
		} else {
			foreach ( $row as $index => $value ) {
				if ( ! is_int( $index ) && ! ctype_digit( (string) $index ) ) {
					continue;
				}
				$value = trim( (string) $value );
				$safe  = $this->escape_placeholder_value( $value, (int) $index === $link_idx );
				$idx   = (int) $index;
				$map[ '{{' . $idx . '}}' ] = $safe;
				$map[ '%' . ( $idx + 1 ) ]  = $safe;
				$map[ '{%' . ( $idx + 1 ) . '}' ] = $safe;
			}
		}

		$map['{{link}}']        = esc_url( (string) ( $item['link'] ?? '' ) );
		$map['{{title}}']       = esc_html( (string) ( $item['title'] ?? '' ) );
		$map['{{description}}'] = esc_html( (string) ( $item['description'] ?? '' ) );
		$map['{{date}}']        = esc_html( (string) ( $item['date'] ?? '' ) );

		return $map;
	}

	/**
	 * @param array<string, mixed> $slot    Slot schema row.
	 * @param string               $value   Raw value.
	 * @param bool                 $is_link Force URL escape.
	 * @return string
	 */
	private function escape_slot_value( array $slot, $value, $is_link ) {
		if ( '' === $value ) {
			return '';
		}
		if ( ! empty( $slot['is_html'] ) ) {
			return wp_kses_post( $value );
		}
		// {%1}（index 0）はタイトル専用。中に URL が含まれてもリンク扱いしない。
		if ( $is_link || ( ! empty( $slot['is_url'] ) && preg_match( '#^https?://#i', $value ) ) || 0 === strpos( $value, '//' ) ) {
			return esc_url( crb_normalize_link( $value ) );
		}
		return esc_html( $value );
	}

	/**
	 * @param string $value   Raw value.
	 * @param bool   $is_link Treat as URL.
	 * @return string
	 */
	private function escape_placeholder_value( $value, $is_link ) {
		if ( '' === $value ) {
			return '';
		}
		if ( $is_link || preg_match( '#^https?://#i', $value ) ) {
			return esc_url( crb_normalize_link( $value ) );
		}
		return esc_html( $value );
	}
}
