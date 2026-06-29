<?php
/**
 * Feed43風テンプレートによるHTML抽出。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_HTML_Parser {

	/**
	 * @param string $html_content   Raw HTML.
	 * @param string $template       Item extraction template.
	 * @param string $scope_template Optional global pattern (Feed43 全体パターン). Limits item extraction to {%} capture.
	 * @return array<int, array<int, string>>|WP_Error
	 */
	public function parse_html( $html_content, $template, $scope_template = '' ) {
		$template = trim( (string) $template );
		if ( '' === $template ) {
			return new WP_Error( 'crb_empty_template', __( '抽出テンプレートが空です。', 'custom-rss-builder' ) );
		}

		if ( false === strpos( $template, '{%}' ) ) {
			return new WP_Error(
				'crb_invalid_template',
				__( '抽出テンプレートに {%} が含まれていません。', 'custom-rss-builder' )
			);
		}

		$regex = $this->template_to_regex( $this->normalize_template( $template ) );
		if ( is_wp_error( $regex ) ) {
			return $regex;
		}

		$html_content = $this->normalize_html( $html_content );

		$scope_template = trim( (string) $scope_template );
		if ( '' !== $scope_template ) {
			$scoped = $this->apply_scope( $html_content, $scope_template );
			if ( is_wp_error( $scoped ) ) {
				return $scoped;
			}
			$html_content = $scoped;
		}
		$results      = array();
		$offset       = 0;
		$length       = strlen( $html_content );

		while ( $offset < $length && count( $results ) < CRB_MAX_ITEMS ) {
			$match = array();
			if ( ! preg_match( $regex, $html_content, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
				break;
			}

			if ( empty( $match[0][0] ) ) {
				++$offset;
				continue;
			}

			$row = array();
			for ( $i = 1, $count = count( $match ); $i < $count; $i++ ) {
				if ( ! isset( $match[ $i ][0] ) ) {
					continue;
				}
				$row[] = $this->clean_value( (string) $match[ $i ][0] );
			}

			if ( $this->has_meaningful_values( $row ) ) {
				$results[] = $row;
			}

			$match_end = $match[0][1] + strlen( $match[0][0] );
			if ( $match_end <= $offset ) {
				++$offset;
			} else {
				$offset = $match_end;
			}
		}

		if ( empty( $results ) ) {
			return new WP_Error(
				'crb_no_results',
				__( '抽出パターンに問題があるか、結果が0件でした。', 'custom-rss-builder' )
			);
		}

		return $results;
	}

	/**
	 * @param string $html_content   Normalized HTML.
	 * @param string $scope_template Global pattern with {%}.
	 * @return string|WP_Error Scoped HTML substring.
	 */
	public function apply_scope( $html_content, $scope_template ) {
		$scope_template = trim( (string) $scope_template );
		if ( '' === $scope_template ) {
			return (string) $html_content;
		}

		if ( false === strpos( $scope_template, '{%}' ) ) {
			return new WP_Error(
				'crb_invalid_scope',
				__( '範囲テンプレートに {%} が含まれていません。', 'custom-rss-builder' )
			);
		}

		// 範囲は {%} を貪欲に（子要素の </div> で切れないようにする）。
		$regex = $this->template_to_regex( $this->normalize_template( $scope_template ), true );
		if ( is_wp_error( $regex ) ) {
			return $regex;
		}

		$match = array();
		if ( ! preg_match( $regex, (string) $html_content, $match ) ) {
			return new WP_Error(
				'crb_scope_no_match',
				__( '範囲テンプレートに一致する箇所が見つかりませんでした。', 'custom-rss-builder' )
			);
		}

		$scoped = $this->extract_captured_groups( $match );
		if ( '' === $scoped ) {
			return new WP_Error(
				'crb_scope_empty',
				__( '範囲テンプレートは一致しましたが、{%} の部分が空でした。', 'custom-rss-builder' )
			);
		}

		return $scoped;
	}

	/**
	 * @param array<int, string> $match preg_match result.
	 */
	private function extract_captured_groups( array $match ) {
		$parts = array();
		for ( $i = 1, $count = count( $match ); $i < $count; $i++ ) {
			if ( isset( $match[ $i ] ) && '' !== (string) $match[ $i ] ) {
				$parts[] = (string) $match[ $i ];
			}
		}
		if ( empty( $parts ) ) {
			return '';
		}
		return 1 === count( $parts ) ? $parts[0] : implode( '', $parts );
	}

	private function normalize_template( $template ) {
		return (string) preg_replace( '/>\s+</', '><', $template );
	}

	/**
	 * @param string $template        Normalized template.
	 * @param bool   $greedy_capture  true = {%} uses greedy match (scope template).
	 */
	private function template_to_regex( $template, $greedy_capture = false ) {
		$parts   = preg_split( '/(\{%\}|\{\*\})/', $template, -1, PREG_SPLIT_DELIM_CAPTURE );
		$regex   = '';
		$has_var = false;

		if ( ! is_array( $parts ) ) {
			return new WP_Error( 'crb_invalid_template', __( '抽出テンプレートの解析に失敗しました。', 'custom-rss-builder' ) );
		}

		foreach ( $parts as $part ) {
			if ( '{%}' === $part ) {
				$regex  .= $greedy_capture ? '([\s\S]*)' : '([\s\S]*?)';
				$has_var = true;
				continue;
			}
			if ( '{*}' === $part ) {
				$regex .= '[\s\S]*?';
				continue;
			}
			if ( '' !== $part ) {
				$regex .= preg_quote( $part, '/' );
			}
		}

		if ( ! $has_var ) {
			return new WP_Error( 'crb_invalid_template', __( '抽出テンプレートに {%} が含まれていません。', 'custom-rss-builder' ) );
		}

		return '/' . $regex . '/';
	}

	private function normalize_html( $html ) {
		$html = str_replace( array( "\r\n", "\r" ), "\n", $html );
		$html = preg_replace( '/>\s+</', '><', $html );
		return (string) $html;
	}

	private function clean_value( $value ) {
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = wp_strip_all_tags( $value );
		$value = preg_replace( '/\s+/u', ' ', $value );
		return trim( (string) $value );
	}

	private function has_meaningful_values( array $row ) {
		foreach ( $row as $value ) {
			if ( '' !== trim( $value ) ) {
				return true;
			}
		}
		return false;
	}
}
