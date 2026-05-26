<?php
/**
 * CSS セレクタによる HTML 抽出（1件ブロック単位のレコード対応）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Css_Extractor {

	/**
	 * @param string               $html     HTML.
	 * @param array<string, string> $config  CSS 設定。
	 * @param string               $base_url 相対 URL 解決用。
	 * @return array<int, array<int, string>>|WP_Error
	 */
	public function extract( $html, array $config, $base_url = '' ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return new WP_Error( 'crb_css_empty_html', __( 'HTML が空です。', 'custom-rss-builder' ) );
		}

		$link_selector = trim( (string) ( $config['link_selector'] ?? '' ) );
		$item_selector = trim( (string) ( $config['item_selector'] ?? '' ) );
		if ( '' === $link_selector && '' === $item_selector ) {
			return new WP_Error(
				'crb_css_no_scope',
				__( '1件ブロックまたはリンク用 CSS セレクタのどちらかを指定してください。', 'custom-rss-builder' )
			);
		}

		$dom = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( ! $loaded ) {
			return new WP_Error( 'crb_css_parse', __( 'HTML の解析に失敗しました。', 'custom-rss-builder' ) );
		}

		$xpath = new DOMXPath( $dom );
		$scope = $xpath->query( '//body' )->item( 0 );
		if ( ! $scope instanceof DOMElement ) {
			return new WP_Error( 'crb_css_no_body', __( 'HTML 本文が見つかりません。', 'custom-rss-builder' ) );
		}

		$scope_selector = trim( (string) ( $config['scope_selector'] ?? '' ) );
		if ( '' !== $scope_selector ) {
			$scope_xpath = crb_css_to_xpath( $scope_selector );
			if ( is_wp_error( $scope_xpath ) ) {
				return $scope_xpath;
			}
			$scoped = $xpath->query( $scope_xpath, $scope );
			if ( false === $scoped || 0 === $scoped->length ) {
				return new WP_Error(
					'crb_css_scope_miss',
					sprintf(
						/* translators: %s: CSS selector */
						__( '範囲セレクタに一致する要素がありません: %s', 'custom-rss-builder' ),
						$scope_selector
					)
				);
			}
			$scope = $scoped->item( 0 );
			if ( ! $scope instanceof DOMElement ) {
				return new WP_Error( 'crb_css_scope_miss', __( '範囲要素が不正です。', 'custom-rss-builder' ) );
			}
		}

		$item_selector = trim( (string) ( $config['item_selector'] ?? '' ) );
		if ( '' !== $item_selector ) {
			return $this->extract_records( $xpath, $scope, $config, $base_url );
		}

		return $this->extract_legacy_rows( $xpath, $scope, $config, $link_selector, $base_url );
	}

	/**
	 * 1件ブロック（.review_contents 等）ごとに構造化レコードを返す。
	 *
	 * @param DOMXPath             $xpath    XPath.
	 * @param DOMElement           $scope    範囲要素。
	 * @param array<string, string> $config   設定。
	 * @param string               $base_url Base URL.
	 * @return array<int, array<int, string>>|WP_Error
	 */
	private function extract_records( DOMXPath $xpath, DOMElement $scope, array $config, $base_url ) {
		$item_selector = trim( (string) ( $config['item_selector'] ?? '' ) );
		$item_xpath    = crb_css_to_xpath( $item_selector );
		if ( is_wp_error( $item_xpath ) ) {
			return $item_xpath;
		}

		$containers = $xpath->query( $item_xpath, $scope );
		if ( false === $containers || 0 === $containers->length ) {
			return new WP_Error(
				'crb_css_item_miss',
				sprintf(
					/* translators: %s: CSS selector */
					__( '1件ブロックのセレクタに一致する要素がありません: %s', 'custom-rss-builder' ),
					$item_selector
				)
			);
		}

		$records = array();
		foreach ( $containers as $container ) {
			if ( ! $container instanceof DOMElement ) {
				continue;
			}
			$record = $this->extract_record_from_container( $xpath, $container, $config, $base_url );
			if ( null === $record ) {
				continue;
			}
			$records[] = $record;
		}

		if ( empty( $records ) ) {
			return new WP_Error( 'crb_css_no_items', __( '有効なレコードが1件も抽出できませんでした。', 'custom-rss-builder' ) );
		}

		return $records;
	}

	/**
	 * 要素調べる用: 範囲内の先頭ブロックだけ試し読み（最大 $max 件）。
	 *
	 * @param string               $html     HTML.
	 * @param array<string, string> $config  CSS 設定（scope / item 必須推奨）。
	 * @param string               $base_url Base URL.
	 * @param int                  $max      件数上限。
	 * @return array<int, array<int, string>>|WP_Error
	 */
	public function extract_preview_in_scope( $html, array $config, $base_url = '', $max = 3 ) {
		$max = max( 1, (int) $max );
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return new WP_Error( 'crb_css_empty_html', __( 'HTML が空です。', 'custom-rss-builder' ) );
		}

		$dom = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		if ( ! $loaded ) {
			return new WP_Error( 'crb_css_parse', __( 'HTML の解析に失敗しました。', 'custom-rss-builder' ) );
		}

		$xpath = new DOMXPath( $dom );
		$scope = $xpath->query( '//body' )->item( 0 );
		if ( ! $scope instanceof DOMElement ) {
			return new WP_Error( 'crb_css_no_body', __( 'HTML 本文が見つかりません。', 'custom-rss-builder' ) );
		}

		$scope_selector = trim( (string) ( $config['scope_selector'] ?? '' ) );
		if ( '' !== $scope_selector ) {
			$scope_xpath = crb_css_to_xpath( $scope_selector );
			if ( is_wp_error( $scope_xpath ) ) {
				return $scope_xpath;
			}
			$scoped = $xpath->query( $scope_xpath, $scope );
			if ( false === $scoped || 0 === $scoped->length || ! ( $scoped->item( 0 ) instanceof DOMElement ) ) {
				return new WP_Error(
					'crb_css_scope_miss',
					sprintf(
						/* translators: %s: CSS selector */
						__( '範囲セレクタに一致する要素がありません: %s', 'custom-rss-builder' ),
						$scope_selector
					)
				);
			}
			$scope = $scoped->item( 0 );
		}

		$item_selector = trim( (string) ( $config['item_selector'] ?? '' ) );
		$containers    = array();
		if ( '' !== $item_selector ) {
			$item_xpath = crb_css_to_xpath( $item_selector );
			if ( is_wp_error( $item_xpath ) ) {
				return $item_xpath;
			}
			$nodes = $xpath->query( $item_xpath, $scope );
			if ( false !== $nodes ) {
				for ( $i = 0; $i < $nodes->length && count( $containers ) < $max; $i++ ) {
					$node = $nodes->item( $i );
					if ( $node instanceof DOMElement ) {
						$containers[] = $node;
					}
				}
			}
		}

		if ( empty( $containers ) ) {
			$containers[] = $scope;
		}

		$records = array();
		foreach ( $containers as $container ) {
			$record = $this->extract_record_from_container( $xpath, $container, $config, $base_url );
			if ( null !== $record ) {
				$records[] = $record;
			}
			if ( count( $records ) >= $max ) {
				break;
			}
		}

		if ( empty( $records ) ) {
			return new WP_Error( 'crb_css_no_items', __( 'この範囲では試し読みできる項目がありませんでした。', 'custom-rss-builder' ) );
		}

		return $records;
	}

	/**
	 * 1件ブロック内でセレクタを試し読み。
	 *
	 * @param DOMXPath                            $xpath     XPath.
	 * @param DOMElement                          $context   起点要素。
	 * @param array{selector: string, mode: string, attr?: string} $rule Rule.
	 * @param string                              $base_url  Base URL.
	 * @return string
	 */
	public function probe_slot_in_context( DOMXPath $xpath, DOMElement $context, array $rule, $base_url = '' ) {
		return $this->read_slot_rule( $xpath, $context, $rule, $base_url );
	}

	/**
	 * 要素調べる: 提案セレクタで {%1}… を範囲内1件コンテキストに試し読み。
	 *
	 * @param DOMXPath             $xpath     XPath.
	 * @param DOMElement           $context   1件ブロック（または範囲そのもの）。
	 * @param array<string, mixed> $config    CSS 設定（discover 提案で組み立てたもの）。
	 * @param string               $base_url  Base URL.
	 * @param int                  $max_slots 表示するスロット数（既定 8 = {%1}…{%8}）。
	 * @return array<int, array<string, mixed>>
	 */
	public function probe_record_slots_in_context( DOMXPath $xpath, DOMElement $context, array $config, $base_url = '', $max_slots = 8 ) {
		$max_slots = max( 1, min( (int) CRB_RECORD_SLOT_COUNT, (int) $max_slots ) );
		$record    = $this->extract_record_from_container( $xpath, $context, $config, $base_url );
		$rows      = array();

		foreach ( crb_get_slot_rules_for_json( $config ) as $rule ) {
			$idx = (int) ( $rule['index'] ?? -1 );
			if ( $idx < 0 || $idx >= $max_slots ) {
				if ( $idx >= $max_slots ) {
					break;
				}
				continue;
			}
			$value = '';
			if ( is_array( $record ) && isset( $record[ $idx ] ) ) {
				$value = (string) $record[ $idx ];
			}
			$rows[] = array(
				'index'      => $idx,
				'token'      => (string) ( $rule['token'] ?? crb_slot_token( $idx ) ),
				'selector'   => (string) ( $rule['selector'] ?? '' ),
				'mode'       => (string) ( $rule['mode'] ?? '' ),
				'mode_label' => (string) ( $rule['mode_label'] ?? '' ),
				'value'      => $value,
				'is_html'    => ! empty( $rule['is_html'] ),
				'is_url'     => ! empty( $rule['is_url'] ),
				'in_scope'   => '' !== trim( $value ),
			);
		}

		return $rows;
	}

	/**
	 * @param DOMXPath             $xpath     XPath.
	 * @param DOMElement           $container 1件ブロック。
	 * @param array<string, string> $config    設定。
	 * @param string               $base_url  Base URL.
	 * @return array<int, string>|null
	 */
	private function extract_record_from_container( DOMXPath $xpath, DOMElement $container, array $config, $base_url ) {
		$link_selector = trim( (string) ( $config['link_selector'] ?? '' ) );
		$keys          = crb_record_internal_field_keys();
		$record        = array_fill_keys( $keys, '' );
		$anchor        = null;

		if ( '' !== $link_selector ) {
			$link_xpath = crb_css_to_xpath( $link_selector );
			if ( is_wp_error( $link_xpath ) ) {
				return null;
			}
			$links = $xpath->query( $link_xpath, $container );
			if ( false !== $links && $links->length > 0 ) {
				$candidate = $links->item( 0 );
				if ( $candidate instanceof DOMElement ) {
					$href = trim( (string) $candidate->getAttribute( 'href' ) );
					if ( '' !== $href ) {
						$anchor           = $candidate;
						$record['link'] = crb_normalize_link( $this->resolve_url( $href, $base_url ) );
					}
				}
			}
		}

		$record['title'] = crb_sanitize_extracted_title(
			$this->read_title_field( $xpath, $container, $config, $anchor, $base_url )
		);

		foreach ( crb_get_feed_extra_slot_rules( $config ) as $index => $rule ) {
			$key = $keys[ $index ] ?? 'slot_' . ( $index + 1 );
			if ( 3 === (int) $index && 'src' === ( $rule['mode'] ?? '' ) && '' === trim( (string) ( $rule['selector'] ?? '' ) ) ) {
				$record[ $key ] = $this->read_image_url( $xpath, $container, '', $base_url );
				continue;
			}
			if ( 3 === (int) $index && 'src' === ( $rule['mode'] ?? '' ) ) {
				$record[ $key ] = $this->read_image_url( $xpath, $container, (string) ( $rule['selector'] ?? '' ), $base_url );
				continue;
			}
			$record[ $key ] = $this->read_slot_rule( $xpath, $container, $rule, $base_url );
		}

		$has_extra = false;
		foreach ( crb_get_feed_extra_slot_rules( $config ) as $rule ) {
			if ( '' !== trim( (string) ( $rule['selector'] ?? '' ) ) ) {
				$has_extra = true;
				break;
			}
		}
		if ( '' === $record['title'] && '' === $record['link'] && ! $has_extra ) {
			return null;
		}

		return crb_record_to_slot_row( $record );
	}

	/**
	 * {%1} タイトル欄の取得。
	 *
	 * @param DOMXPath             $xpath     XPath.
	 * @param DOMElement           $container 1件ブロック。
	 * @param array<string, string> $config    設定。
	 * @param DOMElement|null      $anchor    リンク要素（{%2}）。
	 * @param string               $base_url  Base URL.
	 * @return string
	 */
	private function read_title_field( DOMXPath $xpath, DOMElement $container, array $config, $anchor, $base_url ) {
		$title_mode = (string) ( $config['title_mode'] ?? 'attr' );
		if ( 0 === strpos( $title_mode, 'el_' ) ) {
			$selector = trim( (string) ( $config['title_selector'] ?? '' ) );
			if ( '' === $selector ) {
				return '';
			}
			$mode = substr( $title_mode, 3 );
			$attr = 'title';
			if ( 'attr' === $mode ) {
				$attr = trim( (string) ( $config['title_attr'] ?? 'title' ) );
				if ( '' === $attr ) {
					$attr = 'title';
				}
			}
			return $this->read_slot_rule(
				$xpath,
				$container,
				array(
					'selector' => $selector,
					'mode'     => $mode,
					'attr'     => $attr,
				),
				$base_url
			);
		}
		if ( $anchor instanceof DOMElement ) {
			return $this->read_title_from_anchor( $anchor, $xpath, $container, $config );
		}
		return '';
	}

	/**
	 * レコードモード未使用時: リンクごとに [link, title] の数値行。
	 *
	 * @param DOMXPath             $xpath         XPath.
	 * @param DOMElement           $scope         範囲。
	 * @param array<string, string> $config        設定。
	 * @param string               $link_selector リンクセレクタ。
	 * @param string               $base_url      Base URL.
	 * @return array<int, array<int, string>>|WP_Error
	 */
	private function extract_legacy_rows( DOMXPath $xpath, DOMElement $scope, array $config, $link_selector, $base_url ) {
		$link_xpath = crb_css_to_xpath( $link_selector );
		if ( is_wp_error( $link_xpath ) ) {
			return $link_xpath;
		}

		$nodes = $xpath->query( $link_xpath, $scope );
		if ( false === $nodes || 0 === $nodes->length ) {
			return new WP_Error(
				'crb_css_link_miss',
				sprintf(
					/* translators: %s: CSS selector */
					__( 'リンク用セレクタに一致する要素がありません: %s', 'custom-rss-builder' ),
					$link_selector
				)
			);
		}

		$items = array();
		foreach ( $nodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			$row = $this->read_legacy_link_row( $node, $xpath, $scope, $config, $base_url );
			if ( null === $row ) {
				continue;
			}
			$items[] = $row;
		}

		if ( empty( $items ) ) {
			return new WP_Error( 'crb_css_no_items', __( '有効なリンクが1件も抽出できませんでした。', 'custom-rss-builder' ) );
		}

		return $items;
	}

	/**
	 * @param DOMElement           $anchor    リンク要素。
	 * @param DOMXPath             $xpath     XPath.
	 * @param DOMElement           $container 1件ブロック。
	 * @param array<string, string> $config    設定。
	 * @return string
	 */
	private function read_title_from_anchor( DOMElement $anchor, DOMXPath $xpath, DOMElement $container, array $config ) {
		$title_mode = (string) ( $config['title_mode'] ?? 'attr' );

		if ( 'selector' === $title_mode ) {
			$title_selector = trim( (string) ( $config['title_selector'] ?? '' ) );
			if ( '' !== $title_selector ) {
				$title_xpath = crb_css_to_xpath( $title_selector );
				if ( ! is_wp_error( $title_xpath ) ) {
					$title_nodes = $xpath->query( $title_xpath, $container );
					if ( false !== $title_nodes && $title_nodes->length > 0 ) {
						$title_node = $title_nodes->item( 0 );
						if ( $title_node instanceof DOMElement ) {
							return crb_sanitize_extracted_title( $this->clean_text( $title_node->textContent ) );
						}
					}
				}
			}
		}

		if ( 'text' === $title_mode ) {
			return crb_sanitize_extracted_title( $this->clean_text( $anchor->textContent ) );
		}

		$attr = trim( (string) ( $config['title_attr'] ?? 'title' ) );
		if ( '' === $attr ) {
			$attr = 'title';
		}
		$from_attr = trim( (string) $anchor->getAttribute( $attr ) );
		if ( '' !== $from_attr ) {
			return crb_sanitize_extracted_title( $this->clean_text( $from_attr ) );
		}

		return crb_sanitize_extracted_title( $this->clean_text( $anchor->textContent ) );
	}

	/**
	 * 画像 URL（data: プレースホルダを避け srcset / ポップオーバーへフォールバック）。
	 *
	 * @param DOMXPath   $xpath     XPath.
	 * @param DOMElement $context   1件ブロック。
	 * @param string     $selector  CSS セレクタ。
	 * @param string     $base_url  Base URL.
	 * @return string
	 */
	private function read_image_url( DOMXPath $xpath, DOMElement $context, $selector, $base_url ) {
		$candidates = array();
		$selector   = trim( (string) $selector );

		if ( '' !== $selector ) {
			$this->collect_image_urls_from_xpath( $xpath, $context, crb_css_to_xpath( $selector ), $candidates );
		}

		$fallbacks = array(
			'.//*[contains(@class,"work_img_popover")]//img',
			'.//*[contains(@class,"work_img_popover")]//source[@srcset]',
			'.//picture//source[@srcset]',
			'.//picture//img',
			'.//*[contains(@class,"thumb-container")]//img',
			'.//*[contains(@class,"work_thumb")]//img',
		);
		foreach ( $fallbacks as $fb ) {
			$this->collect_image_urls_from_xpath( $xpath, $context, $fb, $candidates );
		}

		$best = $this->pick_best_image_url( $candidates );
		if ( '' === $best ) {
			return '';
		}
		return crb_normalize_link( $this->resolve_url( $best, $base_url ) );
	}

	/**
	 * @param DOMXPath            $xpath      XPath.
	 * @param DOMElement          $context    1件ブロック。
	 * @param string|WP_Error     $path       XPath（相対）。
	 * @param array<int, string>  $candidates URL 候補（参照渡し）。
	 */
	private function collect_image_urls_from_xpath( DOMXPath $xpath, DOMElement $context, $path, array &$candidates ) {
		if ( is_wp_error( $path ) || '' === trim( (string) $path ) ) {
			return;
		}
		$nodes = $xpath->query( (string) $path, $context );
		if ( false === $nodes ) {
			return;
		}
		for ( $i = 0; $i < $nodes->length; $i++ ) {
			$node = $nodes->item( $i );
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			$url = $this->pick_usable_image_src( $xpath, $node );
			if ( '' !== $url ) {
				$candidates[] = $url;
			}
		}
	}

	/**
	 * DLsite 系: popover の JPG を webp サムネより優先。
	 *
	 * @param array<int, string> $candidates URLs.
	 * @return string
	 */
	private function pick_best_image_url( array $candidates ) {
		$best_url  = '';
		$best_score = -1;
		foreach ( $candidates as $url ) {
			$url = trim( (string) $url );
			if ( ! $this->is_usable_image_url( $url ) ) {
				continue;
			}
			$score = 10;
			if ( false !== strpos( $url, '/modpub/' ) || false !== strpos( $url, '_img_main.jpg' ) ) {
				$score += 50;
			}
			if ( preg_match( '/\.jpe?g(\?|$)/i', $url ) ) {
				$score += 30;
			}
			if ( false !== strpos( $url, '/resize/' ) || preg_match( '/\.webp(\?|$)/i', $url ) ) {
				$score += 5;
			}
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_url   = $url;
			}
		}
		return $best_url;
	}

	/**
	 * @param DOMXPath   $xpath XPath.
	 * @param DOMElement $node  img または source。
	 * @return string
	 */
	private function pick_usable_image_src( DOMXPath $xpath, DOMElement $node ) {
		$tag = strtolower( $node->tagName );

		if ( 'source' === $tag ) {
			$srcset = trim( (string) $node->getAttribute( 'srcset' ) );
			$url    = $this->first_url_from_srcset( $srcset );
			if ( $this->is_usable_image_url( $url ) ) {
				return $url;
			}
		}

		foreach ( array( 'src', 'data-src', 'data-original', 'data-lazy-src' ) as $attr ) {
			$src = trim( (string) $node->getAttribute( $attr ) );
			if ( $this->is_usable_image_url( $src ) ) {
				return $src;
			}
		}

		if ( 'picture' === $tag ) {
			$sources = $xpath->query( './/source[@srcset]', $node );
			if ( false !== $sources ) {
				for ( $i = 0; $i < $sources->length; $i++ ) {
					$src_node = $sources->item( $i );
					if ( $src_node instanceof DOMElement ) {
						$url = $this->first_url_from_srcset( trim( (string) $src_node->getAttribute( 'srcset' ) ) );
						if ( $this->is_usable_image_url( $url ) ) {
							return $url;
						}
					}
				}
			}
			$imgs = $xpath->query( './/img', $node );
			if ( false !== $imgs && $imgs->length > 0 && $imgs->item( 0 ) instanceof DOMElement ) {
				$src = trim( (string) $imgs->item( 0 )->getAttribute( 'src' ) );
				if ( $this->is_usable_image_url( $src ) ) {
					return $src;
				}
			}
		}

		return '';
	}

	/**
	 * @param string $srcset srcset attribute.
	 * @return string
	 */
	private function first_url_from_srcset( $srcset ) {
		$srcset = trim( (string) $srcset );
		if ( '' === $srcset ) {
			return '';
		}
		$parts = preg_split( '/\s*,\s*/', $srcset );
		if ( ! is_array( $parts ) || empty( $parts ) ) {
			return '';
		}
		$first = trim( preg_split( '/\s+/', trim( $parts[0] ), 2 )[0] ?? '' );
		return $first;
	}

	/**
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_usable_image_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return false;
		}
		if ( 0 === stripos( $url, 'data:' ) ) {
			return false;
		}
		return (bool) preg_match( '#^(https?:)?//#i', $url );
	}

	/**
	 * 汎用スロットルール（text / html / src / href / attr）で値を取得。
	 *
	 * @param DOMXPath                            $xpath     XPath.
	 * @param DOMElement                          $context   1件ブロック。
	 * @param array{selector: string, mode: string, attr?: string} $rule Rule.
	 * @param string                              $base_url  Base URL.
	 * @return string
	 */
	private function read_slot_rule( DOMXPath $xpath, DOMElement $context, array $rule, $base_url ) {
		$selector = trim( (string) ( $rule['selector'] ?? '' ) );
		$mode     = crb_sanitize_slot_extract_mode( (string) ( $rule['mode'] ?? 'text' ) );
		if ( '' === $selector ) {
			return '';
		}
		if ( 'src' === $mode ) {
			return $this->read_image_url( $xpath, $context, $selector, $base_url );
		}
		$field_def = array(
			'selector' => $selector,
			'type'     => 'text',
		);
		if ( 'html' === $mode ) {
			$field_def['type'] = 'html';
		} elseif ( 'href' === $mode ) {
			$field_def['type']   = 'attr';
			$field_def['attr']   = 'href';
			$field_def['is_url'] = true;
		} elseif ( 'attr' === $mode ) {
			$field_def['type'] = 'attr';
			$field_def['attr'] = (string) ( $rule['attr'] ?? 'title' );
		}
		return $this->read_field_in_context( $xpath, $context, $field_def, $base_url );
	}

	/**
	 * @param DOMXPath             $xpath     XPath.
	 * @param DOMElement           $context   検索起点。
	 * @param array<string, mixed> $field_def フィールド定義。
	 * @param string               $base_url  Base URL.
	 * @return string
	 */
	private function read_field_in_context( DOMXPath $xpath, DOMElement $context, array $field_def, $base_url ) {
		$selector = trim( (string) ( $field_def['selector'] ?? '' ) );
		if ( '' === $selector ) {
			return '';
		}

		$field_xpath = crb_css_to_xpath( $selector );
		if ( is_wp_error( $field_xpath ) ) {
			return '';
		}

		$nodes = $xpath->query( $field_xpath, $context );
		if ( false === $nodes || 0 === $nodes->length ) {
			return '';
		}

		$node = $nodes->item( 0 );
		if ( ! $node instanceof DOMElement ) {
			return '';
		}

		$type = (string) ( $field_def['type'] ?? 'text' );

		if ( 'attr' === $type ) {
			$attr = trim( (string) ( $field_def['attr'] ?? 'href' ) );
			$val  = trim( (string) $node->getAttribute( $attr ) );
			if ( '' === $val && 'src' === $attr && $node->tagName === 'picture' ) {
				$imgs = $xpath->query( './/img', $node );
				if ( false !== $imgs && $imgs->length > 0 && $imgs->item( 0 ) instanceof DOMElement ) {
					$val = trim( (string) $imgs->item( 0 )->getAttribute( 'src' ) );
				}
			}
			if ( ! empty( $field_def['is_url'] ) ) {
				return crb_normalize_link( $this->resolve_url( $val, $base_url ) );
			}
			return $this->clean_text( $val );
		}

		if ( 'html' === $type ) {
			return $this->read_inner_html( $node );
		}

		return $this->clean_text( $node->textContent );
	}

	/**
	 * @param DOMElement $node Element.
	 * @return string
	 */
	private function read_inner_html( DOMElement $node ) {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}
		$html = trim( $html );
		if ( '' === $html ) {
			$html = $this->clean_text( $node->textContent );
		}
		return $html;
	}

	/**
	 * @param DOMElement           $anchor   Link.
	 * @param DOMXPath             $xpath    XPath.
	 * @param DOMElement           $scope    Scope.
	 * @param array<string, string> $config   Config.
	 * @param string               $base_url Base.
	 * @return array<int, string>|null
	 */
	private function read_legacy_link_row( DOMElement $anchor, DOMXPath $xpath, DOMElement $scope, array $config, $base_url ) {
		$href = trim( (string) $anchor->getAttribute( 'href' ) );
		if ( '' === $href ) {
			return null;
		}

		$link  = crb_normalize_link( $this->resolve_url( $href, $base_url ) );
		$title = $this->read_title_from_anchor( $anchor, $xpath, $scope, $config );

		// {%1}=index0（タイトル）、{%2}=index1（リンク）に合わせる。
		return array( crb_sanitize_extracted_title( $title ), $link );
	}

	/**
	 * @param string $text Raw text.
	 * @return string
	 */
	private function clean_text( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\\s+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * @param string $relative Relative URL.
	 * @param string $base     Base URL.
	 * @return string
	 */
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
