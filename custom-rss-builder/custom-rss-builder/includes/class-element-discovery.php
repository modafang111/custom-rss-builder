<?php
/**
 * 範囲内の DOM 要素を走査し、CSS セレクタ候補を提案。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Element_Discovery {

	const MAX_GROUPS       = 50;
	const MAX_SAMPLES      = 2;
	const MIN_BLOCK_REPEAT = 2;
	const MIN_LINK_SCORE   = 10;
	const MIN_IMAGE_SCORE  = 15;

	/** @var int テキスト／ブロック候補の最低一致件数（要素調べるでは 1）。 */
	private $min_block_repeat = 1;

	/**
	 * @param string $html           HTML.
	 * @param string $scope_selector Optional CSS scope.
	 * @param string $item_selector  Optional 1-item block CSS (1件ブロック内の候補を追加)。
	 * @param int    $min_block_repeat 候補に必要な最低一致件数（既定 1＝1件範囲でも取得）。
	 * @return array<string, mixed>|WP_Error
	 */
	public function discover( $html, $scope_selector = '', $item_selector = '', $min_block_repeat = 1 ) {
		$this->min_block_repeat = max( 1, (int) $min_block_repeat );
		$dom = $this->load_dom( $html );
		if ( is_wp_error( $dom ) ) {
			return $dom;
		}

		$xpath = new DOMXPath( $dom );
		$root  = $dom->documentElement;
		$scope = $this->query_first( $xpath, $scope_selector, $root );
		if ( '' !== trim( $scope_selector ) && ! $scope ) {
			return new WP_Error(
				'crb_discover_scope',
				__( '範囲の CSS セレクタに一致する要素が見つかりませんでした。', 'custom-rss-builder' )
			);
		}

		$context = $scope ? $scope : $root;

		$groups = array_merge(
			$this->collect_link_groups( $xpath, $context ),
			$this->collect_image_groups( $xpath, $context ),
			$this->collect_attr_media_groups( $xpath, $context ),
			$this->collect_text_groups( $xpath, $context ),
			$this->collect_block_groups( $xpath, $context )
		);

		$item_selector = trim( (string) $item_selector );
		if ( '' !== $item_selector ) {
			$groups = array_merge( $groups, $this->collect_item_inner_groups( $xpath, $context, $item_selector ) );
		}

		$groups = array_map( array( $this, 'attach_group_metadata' ), $groups );
		foreach ( $groups as $idx => $group ) {
			$groups[ $idx ] = $this->fill_group_samples( $xpath, $context, $group );
		}
		usort(
			$groups,
			static function ( $a, $b ) {
				$oa = (int) ( $a['sort_order'] ?? 0 );
				$ob = (int) ( $b['sort_order'] ?? 0 );
				if ( $oa !== $ob ) {
					return $ob <=> $oa;
				}
				$pa = (int) ( $a['priority'] ?? 0 );
				$pb = (int) ( $b['priority'] ?? 0 );
				if ( $pa !== $pb ) {
					return $pb <=> $pa;
				}
				return (int) ( $b['count'] ?? 0 ) <=> (int) ( $a['count'] ?? 0 );
			}
		);

		$groups = array_slice( $groups, 0, self::MAX_GROUPS );
		$groups = $this->mark_primary_link_group( $groups );

		return array(
			'scope_selector' => trim( (string) $scope_selector ),
			'scope_label'    => '' !== trim( $scope_selector ) ? trim( $scope_selector ) : __( 'ページ全体', 'custom-rss-builder' ),
			'groups'         => $groups,
			'field_guide'    => $this->build_field_guide( $groups ),
		);
	}

	/**
	 * @param string $html HTML.
	 * @return DOMDocument|WP_Error
	 */
	private function load_dom( $html ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error( 'crb_dom_missing', __( 'DOM 拡張が利用できません。', 'custom-rss-builder' ) );
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$wrapped = '<?xml encoding="utf-8" ?><div id="crb-root">' . (string) $html . '</div>';
		$loaded  = $dom->loadHTML(
			mb_convert_encoding( $wrapped, 'HTML-ENTITIES', 'UTF-8' ),
			LIBXML_NOWARNING | LIBXML_NOERROR
		);
		libxml_clear_errors();

		if ( ! $loaded || ! $dom->getElementById( 'crb-root' ) ) {
			return new WP_Error( 'crb_dom_parse', __( 'HTML の解析に失敗しました。', 'custom-rss-builder' ) );
		}

		return $dom;
	}

	/**
	 * @param DOMXPath $xpath    XPath.
	 * @param string   $selector CSS.
	 * @param DOMNode  $context  Context.
	 * @return DOMElement|null
	 */
	private function query_first( DOMXPath $xpath, $selector, DOMNode $context ) {
		$selector = trim( $selector );
		if ( '' === $selector ) {
			return null;
		}
		$query = crb_css_to_xpath( $selector );
		if ( is_wp_error( $query ) ) {
			return null;
		}
		$nodes = $xpath->query( $query, $context );
		if ( false === $nodes || 0 === $nodes->length ) {
			return null;
		}
		$node = $nodes->item( 0 );
		return ( $node instanceof DOMElement ) ? $node : null;
	}

	/**
	 * @param DOMXPath   $xpath    XPath.
	 * @param DOMElement $context  Context.
	 * @param string     $selector CSS.
	 */
	private function count_selector_matches( DOMXPath $xpath, DOMElement $context, $selector ) {
		$selector = trim( $selector );
		if ( '' === $selector ) {
			return 0;
		}
		$query = crb_css_to_xpath( $selector );
		if ( is_wp_error( $query ) ) {
			return 0;
		}
		$nodes = $xpath->query( $query, $context );
		return ( false === $nodes ) ? 0 : (int) $nodes->length;
	}

	/**
	 * @param DOMXPath   $xpath   XPath.
	 * @param DOMElement $context Context.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_link_groups( DOMXPath $xpath, DOMElement $context ) {
		$nodes   = $xpath->query( './/a[@href]', $context );
		$buckets = array();
		if ( false === $nodes ) {
			return array();
		}

		for ( $i = 0; $i < $nodes->length; $i++ ) {
			$node = $nodes->item( $i );
			if ( ! ( $node instanceof DOMElement ) ) {
				continue;
			}

			$href = trim( (string) $node->getAttribute( 'href' ) );
			if ( '' === $href || '#' === $href || 0 === strpos( $href, 'javascript:' ) ) {
				continue;
			}

			$role     = $this->detect_link_role( $node, $href );
			$selector = $this->suggest_link_selector( $xpath, $node, $context );
			$score    = $this->score_link_element( $node, $role );
			if ( $score < self::MIN_LINK_SCORE ) {
				continue;
			}

			$bucket_key = $role . '|' . $selector;
			if ( ! isset( $buckets[ $bucket_key ] ) ) {
				$buckets[ $bucket_key ] = array(
					'kind'        => 'link',
					'role'        => $role,
					'selector'    => $selector,
					'count'       => 0,
					'priority'    => $score,
					'recommended' => false,
					'samples'     => array(),
				);
			} else {
				$buckets[ $bucket_key ]['priority'] = max( (int) $buckets[ $bucket_key ]['priority'], $score );
			}

			if ( count( $buckets[ $bucket_key ]['samples'] ) < self::MAX_SAMPLES ) {
				$buckets[ $bucket_key ]['samples'][] = array(
					'href'  => $this->truncate( $href, 120 ),
					'text'  => $this->truncate( $this->node_text( $node ), 80 ),
					'title' => $this->truncate( (string) $node->getAttribute( 'title' ), 80 ),
				);
			}
		}

		$out = array();
		foreach ( $buckets as $bucket ) {
			$match_count = $this->count_selector_matches( $xpath, $context, $bucket['selector'] );
			if ( $match_count < 1 ) {
				continue;
			}
			$bucket['count'] = $match_count;
			$out[]           = $bucket;
		}

		return $out;
	}

	/**
	 * リンク群のうち priority 最大を primary_link（{%1}{%2}）にする。
	 *
	 * @param array<int, array<string, mixed>> $groups All groups.
	 * @return array<int, array<string, mixed>>
	 */
	private function mark_primary_link_group( array $groups ) {
		$best_idx = null;
		$best_pri = -1;
		foreach ( $groups as $idx => $group ) {
			if ( 'link' !== ( $group['kind'] ?? '' ) ) {
				continue;
			}
			$pri = (int) ( $group['priority'] ?? 0 );
			if ( $pri > $best_pri ) {
				$best_pri = $pri;
				$best_idx = $idx;
			}
		}
		if ( null !== $best_idx ) {
			$groups[ $best_idx ]['role']        = 'primary_link';
			$groups[ $best_idx ]['recommended'] = true;
		}
		return $groups;
	}

	/**
	 * @param DOMXPath   $xpath   XPath.
	 * @param DOMElement $context Context.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_image_groups( DOMXPath $xpath, DOMElement $context ) {
		$nodes   = $xpath->query( './/img[@src or @data-src or @data-original or @data-lazy-src] | .//picture//img | .//picture//source[@srcset]', $context );
		$buckets = array();
		if ( false === $nodes ) {
			return array();
		}

		for ( $i = 0; $i < $nodes->length; $i++ ) {
			$node = $nodes->item( $i );
			if ( ! ( $node instanceof DOMElement ) ) {
				continue;
			}
			$src = $this->pick_image_sample_src( $node );
			if ( '' === $src ) {
				continue;
			}

			$score = $this->score_image_element( $node );
			if ( $score < self::MIN_IMAGE_SCORE ) {
				continue;
			}

			$selector = $this->suggest_image_selector( $node, $context );
			if ( ! isset( $buckets[ $selector ] ) ) {
				$buckets[ $selector ] = array(
					'kind'        => 'image',
					'role'        => 'eyecatch',
					'selector'    => $selector,
					'count'       => 0,
					'priority'    => $score,
					'recommended' => false,
					'samples'     => array(),
				);
			} else {
				$buckets[ $selector ]['priority'] = max( (int) $buckets[ $selector ]['priority'], $score );
			}

			if ( count( $buckets[ $selector ]['samples'] ) < self::MAX_SAMPLES ) {
				$buckets[ $selector ]['samples'][] = array(
					'src' => $this->truncate( $src, 120 ),
					'alt' => $this->truncate( (string) $node->getAttribute( 'alt' ), 80 ),
				);
			}
		}

		$out = array();
		foreach ( $buckets as $bucket ) {
			$match_count = $this->count_selector_matches( $xpath, $context, $bucket['selector'] );
			if ( $match_count < 1 ) {
				continue;
			}
			$bucket['count'] = $match_count;
			$out[] = $bucket;
		}

		return $this->mark_recommended_images( $out );
	}

	/**
	 * @param array<int, array<string, mixed>> $groups Image groups.
	 * @return array<int, array<string, mixed>>
	 */
	private function mark_recommended_images( array $groups ) {
		if ( empty( $groups ) ) {
			return $groups;
		}
		$best_idx = 0;
		$best_pri = (int) ( $groups[0]['priority'] ?? 0 );
		foreach ( $groups as $idx => $group ) {
			$pri = (int) ( $group['priority'] ?? 0 );
			if ( $pri > $best_pri ) {
				$best_pri = $pri;
				$best_idx = $idx;
			}
		}
		$groups[ $best_idx ]['recommended'] = true;
		return $groups;
	}

	/**
	 * テキスト要素（p / dd / span 等）の繰り返しパターン。
	 *
	 * @param DOMXPath   $xpath   XPath.
	 * @param DOMElement $context Context.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_text_groups( DOMXPath $xpath, DOMElement $context ) {
		$tags    = array( 'p', 'dd', 'dt', 'span', 'h2', 'h3', 'h4', 'li' );
		$buckets = array();

		foreach ( $tags as $tag ) {
			$nodes = $xpath->query( './/' . $tag, $context );
			if ( false === $nodes ) {
				continue;
			}
			for ( $i = 0; $i < $nodes->length; $i++ ) {
				$node = $nodes->item( $i );
				if ( ! ( $node instanceof DOMElement ) ) {
					continue;
				}
				$text = $this->node_text( $node );
				if ( mb_strlen( $text ) < 4 ) {
					continue;
				}
				$selector = $this->suggest_text_selector( $node, $context, $tag );
				if ( '' === $selector ) {
					continue;
				}
				if ( ! isset( $buckets[ $selector ] ) ) {
					$buckets[ $selector ] = array(
						'kind'        => 'text',
						'role'        => 'text',
						'selector'    => $selector,
						'count'       => 0,
						'priority'    => 20,
						'recommended' => false,
						'samples'     => array(),
					);
				}
				if ( count( $buckets[ $selector ]['samples'] ) < self::MAX_SAMPLES ) {
					$buckets[ $selector ]['samples'][] = array(
						'text' => $this->truncate( $text, 120 ),
					);
				}
			}
		}

		$out = array();
		foreach ( $buckets as $bucket ) {
			$match_count = $this->count_selector_matches( $xpath, $context, $bucket['selector'] );
			if ( $match_count < $this->min_block_repeat ) {
				continue;
			}
			$bucket['count'] = $match_count;
			$out[]           = $bucket;
		}
		return $out;
	}

	/**
	 * link / thumb-candidates など HTML 属性由来の候補（Vue コンポーネント向け）。
	 *
	 * @param DOMXPath   $xpath   XPath.
	 * @param DOMElement $context Context.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_attr_media_groups( DOMXPath $xpath, DOMElement $context ) {
		$groups = array();
		$nodes  = $xpath->query( './/*', $context );
		if ( false === $nodes ) {
			return array();
		}

		for ( $i = 0; $i < $nodes->length && count( $groups ) < 20; $i++ ) {
			$node = $nodes->item( $i );
			if ( ! ( $node instanceof DOMElement ) ) {
				continue;
			}

			$link = trim( (string) $node->getAttribute( 'link' ) );
			if ( '' !== $link && preg_match( '#^(https?:)?//#i', $link ) ) {
				$selector = $this->suggest_generic_selector( $node, $context ) . '[link]';
				$key      = 'link_attr|' . $selector;
				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array(
						'kind'        => 'link',
						'role'        => 'link',
						'selector'    => $selector,
						'count'       => 1,
						'priority'    => 75,
						'recommended' => false,
						'samples'     => array(
							array(
								'href' => $this->truncate( $link, 120 ),
								'text' => $this->truncate( (string) $node->getAttribute( 'alt' ), 80 ),
							),
						),
					);
				}
			}

			if ( $node->hasAttributes() ) {
				foreach ( $node->attributes as $attr ) {
					$name = (string) $attr->name;
					if ( false === stripos( $name, 'thumb-candidates' ) && false === stripos( $name, 'thumb_candidates' ) ) {
						continue;
					}
					$urls = $this->urls_from_attribute_value( (string) $attr->value );
					if ( empty( $urls ) ) {
						continue;
					}
					$selector = $this->suggest_generic_selector( $node, $context );
					if ( '' !== $selector ) {
						$selector .= '[' . preg_replace( '/[^a-zA-Z0-9_\-:]/', '', $name ) . ']';
					} else {
						$selector = '*[' . preg_replace( '/[^a-zA-Z0-9_\-:]/', '', $name ) . ']';
					}
					$key = 'thumb_attr|' . $selector;
					if ( ! isset( $groups[ $key ] ) ) {
						$samples = array();
						foreach ( array_slice( $urls, 0, self::MAX_SAMPLES ) as $url ) {
							$samples[] = array(
								'src' => $this->truncate( $url, 120 ),
								'alt' => $this->truncate( (string) $node->getAttribute( 'alt' ), 80 ),
							);
						}
						$groups[ $key ] = array(
							'kind'        => 'image',
							'role'        => 'eyecatch',
							'selector'    => $selector,
							'count'       => 1,
							'priority'    => 70,
							'recommended' => false,
							'samples'     => $samples,
						);
					}
				}
			}

			if ( 'img' === strtolower( $node->tagName ) ) {
				$vue_src = $this->url_from_vue_bind_attribute( $node );
				if ( '' !== $vue_src ) {
					$selector = $this->suggest_image_selector( $node, $context );
					$key      = 'vue_src|' . $selector;
					if ( ! isset( $groups[ $key ] ) ) {
						$groups[ $key ] = array(
							'kind'        => 'image',
							'role'        => 'eyecatch',
							'selector'    => $selector,
							'count'       => 1,
							'priority'    => 65,
							'recommended' => false,
							'samples'     => array(
								array(
									'src' => $this->truncate( $vue_src, 120 ),
									'alt' => $this->truncate( (string) $node->getAttribute( 'alt' ), 80 ),
								),
							),
						);
					}
				}
			}
		}

		return array_values( $groups );
	}

	/**
	 * @param string $value Attribute value.
	 * @return array<int, string>
	 */
	private function urls_from_attribute_value( $value ) {
		$value = html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( ! preg_match_all( '#(?:https?:)?//[^\s\'"\\]]+\.(?:jpe?g|webp|png|gif)(?:\?[^\s\'"\\]]*)?#i', $value, $matches ) ) {
			return array();
		}
		$urls = array();
		foreach ( $matches[0] as $url ) {
			$url = trim( $url );
			if ( '' !== $url && 0 !== stripos( $url, 'data:' ) ) {
				$urls[] = $url;
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * @param DOMElement $node img 等。
	 * @return string
	 */
	private function url_from_vue_bind_attribute( DOMElement $node ) {
		if ( ! $node->hasAttributes() ) {
			return '';
		}
		foreach ( $node->attributes as $attr ) {
			$name  = (string) $attr->name;
			$value = (string) $attr->value;
			if ( false === stripos( $name, 'src' ) ) {
				continue;
			}
			$urls = $this->urls_from_attribute_value( $value );
			if ( ! empty( $urls ) ) {
				return $urls[0];
			}
		}
		return '';
	}

	/**
	 * 1件ブロック内の img / a / テキスト候補（先頭ブロックを走査）。
	 *
	 * @param DOMXPath   $xpath         XPath.
	 * @param DOMElement $context       Scope.
	 * @param string     $item_selector Item CSS.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_item_inner_groups( DOMXPath $xpath, DOMElement $context, $item_selector ) {
		$query = crb_css_to_xpath( $item_selector );
		if ( is_wp_error( $query ) ) {
			return array();
		}
		$items = $xpath->query( $query, $context );
		if ( false === $items || 0 === $items->length ) {
			return array();
		}
		$item = $items->item( 0 );
		if ( ! ( $item instanceof DOMElement ) ) {
			return array();
		}

		$groups = array();
		$seen   = array();

		$img_nodes = $xpath->query( './/img[@src or @data-src or @data-original] | .//picture//img', $item );
		if ( false !== $img_nodes ) {
			for ( $i = 0; $i < $img_nodes->length && count( $groups ) < 15; $i++ ) {
				$node = $img_nodes->item( $i );
				if ( ! ( $node instanceof DOMElement ) ) {
					continue;
				}
				$selector = $this->suggest_image_selector( $node, $item );
				if ( isset( $seen[ $selector ] ) ) {
					continue;
				}
				$seen[ $selector ] = true;
				$src               = $this->pick_image_sample_src( $node );
				$groups[]          = array(
					'kind'        => 'image',
					'role'        => 'eyecatch',
					'selector'    => $selector,
					'count'       => 1,
					'priority'    => 60,
					'recommended' => false,
					'samples'     => array( array( 'src' => $this->truncate( $src, 120 ) ) ),
				);
			}
		}

		$link_nodes = $xpath->query( './/a[@href]', $item );
		if ( false !== $link_nodes ) {
			for ( $i = 0; $i < $link_nodes->length && count( $groups ) < 25; $i++ ) {
				$node = $link_nodes->item( $i );
				if ( ! ( $node instanceof DOMElement ) ) {
					continue;
				}
				$href = trim( (string) $node->getAttribute( 'href' ) );
				if ( '' === $href || '#' === $href ) {
					continue;
				}
				$selector = $this->suggest_generic_selector( $node, $item );
				if ( isset( $seen[ $selector ] ) ) {
					continue;
				}
				$seen[ $selector ] = true;
				$groups[]        = array(
					'kind'        => 'link',
					'role'        => 'link',
					'selector'    => $selector,
					'count'       => 1,
					'priority'    => 30,
					'recommended' => false,
					'samples'     => array(
						array(
							'href' => $this->truncate( $href, 120 ),
							'text' => $this->truncate( $this->node_text( $node ), 80 ),
						),
					),
				);
			}
		}

		$text_nodes = $xpath->query( './/p | .//dd | .//span | .//div[@class]', $item );
		if ( false !== $text_nodes ) {
			for ( $i = 0; $i < $text_nodes->length && count( $groups ) < 40; $i++ ) {
				$node = $text_nodes->item( $i );
				if ( ! ( $node instanceof DOMElement ) ) {
					continue;
				}
				$text = $this->node_text( $node );
				if ( mb_strlen( $text ) < 6 ) {
					continue;
				}
				$tag      = strtolower( $node->tagName );
				$selector = $this->suggest_text_selector( $node, $item, $tag );
				if ( isset( $seen[ $selector ] ) ) {
					continue;
				}
				$seen[ $selector ] = true;
				$groups[]          = array(
					'kind'        => 'text',
					'role'        => 'text',
					'selector'    => $selector,
					'count'       => 1,
					'priority'    => 15,
					'recommended' => false,
					'samples'     => array( array( 'text' => $this->truncate( $text, 120 ) ) ),
				);
			}
		}

		return $groups;
	}

	/**
	 * @param DOMElement $node img / source.
	 * @return string
	 */
	private function pick_image_sample_src( DOMElement $node ) {
		$tag = strtolower( $node->tagName );
		if ( 'source' === $tag ) {
			$srcset = trim( (string) $node->getAttribute( 'srcset' ) );
			if ( '' !== $srcset ) {
				$parts = preg_split( '/\s*,\s*/', $srcset );
				if ( is_array( $parts ) && ! empty( $parts[0] ) ) {
					$first = trim( preg_split( '/\s+/', trim( $parts[0] ), 2 )[0] ?? '' );
					if ( '' !== $first && 0 !== strpos( $first, 'data:' ) ) {
						return $first;
					}
				}
			}
		}
		foreach ( array( 'src', 'data-src', 'data-original', 'data-lazy-src' ) as $attr ) {
			$val = trim( (string) $node->getAttribute( $attr ) );
			if ( '' !== $val && 0 !== strpos( $val, 'data:' ) ) {
				return $val;
			}
		}
		return '';
	}

	/**
	 * @param DOMElement $element Element.
	 * @param DOMElement $scope   Scope.
	 * @param string     $tag     Tag name.
	 * @return string
	 */
	private function suggest_text_selector( DOMElement $element, DOMElement $scope, $tag ) {
		$cls = array_slice( $this->get_class_tokens( $element ), 0, 2 );
		if ( ! empty( $cls ) ) {
			return $tag . '.' . implode( '.', $cls );
		}
		return $this->suggest_generic_selector( $element, $scope );
	}

	/**
	 * @param DOMXPath   $xpath   XPath.
	 * @param DOMElement $context Context.
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_block_groups( DOMXPath $xpath, DOMElement $context ) {
		$class_counts = array();
		$nodes        = $xpath->query( './/*[@class]', $context );
		if ( false === $nodes ) {
			return array();
		}

		for ( $i = 0; $i < $nodes->length; $i++ ) {
			$node = $nodes->item( $i );
			if ( ! ( $node instanceof DOMElement ) ) {
				continue;
			}
			foreach ( $this->get_class_tokens( $node ) as $token ) {
				if ( strlen( $token ) < 4 ) {
					continue;
				}
				if ( ! isset( $class_counts[ $token ] ) ) {
					$class_counts[ $token ] = 0;
				}
				++$class_counts[ $token ];
			}
		}

		$out = array();
		foreach ( $class_counts as $token => $raw_count ) {
			if ( $raw_count < $this->min_block_repeat ) {
				continue;
			}
			if ( $this->is_noisy_block_class( $token, $class_counts ) ) {
				continue;
			}

			$selector    = '.' . $token;
			$match_count = $this->count_selector_matches( $xpath, $context, $selector );
			if ( $match_count < $this->min_block_repeat ) {
				continue;
			}

			$priority = $match_count * 2;

			$out[] = array(
				'kind'        => 'block',
				'role'        => 'item_block',
				'selector'    => $selector,
				'count'       => $match_count,
				'priority'    => $priority,
				'recommended' => false,
				'samples'     => array(),
			);
		}

		return $this->mark_recommended_blocks( $out );
	}

	/**
	 * より具体的なクラス（review_contents_inner 等）をブロック候補から除外。
	 *
	 * @param string               $token        Class token.
	 * @param array<string, int>   $class_counts Counts by token.
	 */
	private function is_noisy_block_class( $token, array $class_counts ) {
		// より長い派生クラス（review_contents_inner 等）だけ除外し、親（review_contents）は残す。
		foreach ( array_keys( $class_counts ) as $other ) {
			if ( $other === $token ) {
				continue;
			}
			if ( strlen( $token ) > strlen( $other ) && false !== strpos( $token, $other ) ) {
				return true;
			}
		}
		$noise_tokens = array( 'clear', 'hide', 'hidden', 'inner', 'wrap', 'separator' );
		if ( in_array( $token, $noise_tokens, true ) ) {
			return true;
		}
		$noise_prefixes = array( 'type_', 'icon_', 'btn_', 'star_', 'work_btn', 'ga4_' );
		foreach ( $noise_prefixes as $prefix ) {
			if ( 0 === strpos( $token, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<int, array<string, mixed>> $groups Block groups.
	 * @return array<int, array<string, mixed>>
	 */
	private function mark_recommended_blocks( array $groups ) {
		if ( empty( $groups ) ) {
			return $groups;
		}
		$best_idx = 0;
		$best_pri = (int) ( $groups[0]['priority'] ?? 0 );
		foreach ( $groups as $idx => $group ) {
			$pri = (int) ( $group['priority'] ?? 0 );
			if ( $pri > $best_pri ) {
				$best_pri = $pri;
				$best_idx = $idx;
			}
		}
		$groups[ $best_idx ]['recommended'] = true;
		return $groups;
	}

	/**
	 * Feed43: サイト固有ロールは使わず link のみ（優先度で主リンクを決める）。
	 *
	 * @param DOMElement $anchor Link.
	 * @param string     $href   href.
	 * @return string link
	 */
	private function detect_link_role( DOMElement $anchor, $href ) {
		unset( $anchor, $href );
		return 'link';
	}

	/**
	 * 1件ブロック内で繰り返すパターンに寄せたリンクセレクタ。
	 *
	 * @param DOMXPath   $xpath   XPath.
	 * @param DOMElement $anchor  Link.
	 * @param DOMElement $context Scope.
	 * @return string
	 */
	private function suggest_link_selector( DOMXPath $xpath, DOMElement $anchor, DOMElement $context ) {
		$node  = $anchor;
		$depth = 0;
		while ( $node instanceof DOMElement && $node !== $context && $depth < 6 ) {
			foreach ( $this->get_class_tokens( $node ) as $token ) {
				if ( strlen( $token ) < 3 ) {
					continue;
				}
				$candidate = '.' . $token . ' a[href]';
				if ( $this->count_selector_matches( $xpath, $context, $candidate ) >= $this->min_block_repeat ) {
					return $candidate;
				}
			}
			$parent = $node->parentNode;
			$node   = ( $parent instanceof DOMElement ) ? $parent : null;
			++$depth;
		}
		return $this->suggest_generic_selector( $anchor, $context );
	}

	/**
	 * @param DOMElement $img   Image.
	 * @param DOMElement $scope Scope.
	 * @return string
	 */
	private function suggest_image_selector( DOMElement $img, DOMElement $scope ) {
		return $this->suggest_generic_selector( $img, $scope );
	}

	/**
	 * @param DOMElement $img Image.
	 */
	private function score_image_element( DOMElement $img ) {
		$src   = (string) $img->getAttribute( 'src' );
		$score = 0;
		if ( 0 === strpos( $src, 'data:' ) ) {
			return -100;
		}
		if ( preg_match( '#\.(jpe?g|webp|png|gif)(\?|$)#i', $src ) ) {
			$score += 25;
		}
		if ( mb_strlen( (string) $img->getAttribute( 'alt' ) ) >= 4 ) {
			$score += 10;
		}
		if ( preg_match( '#/(icon|logo|pixel|spacer|1x1)#i', $src ) ) {
			$score -= 40;
		}
		return $score;
	}

	/**
	 * @param DOMElement $element Element.
	 * @param string     $role    Link role.
	 */
	private function score_link_element( DOMElement $element, $role = 'link' ) {
		unset( $role );
		$href  = (string) $element->getAttribute( 'href' );
		$text  = $this->node_text( $element );
		$title = trim( (string) $element->getAttribute( 'title' ) );
		$score = 0;

		$label = $title !== '' ? $title : $text;
		if ( mb_strlen( $label ) >= 4 ) {
			$score += min( 50, mb_strlen( $label ) );
		}
		if ( mb_strlen( $label ) >= 12 ) {
			$score += 15;
		}
		if ( preg_match( '#^https?://#i', $href ) || 0 === strpos( $href, '/' ) ) {
			$score += 10;
		}

		if ( preg_match( '#/(cart|wishlist|login|logout|register|signup|contact|share|report|genre|fsr|reviewlist|reviewer|keyword_creater|circle/profile)/#i', $href ) ) {
			$score -= 80;
		}
		if ( preg_match( '#/(cart|wishlist)/#i', $href ) || false !== strpos( $href, 'btn_' ) ) {
			$score -= 50;
		}
		if ( preg_match( '/^\(\d+\)$/', $text ) ) {
			$score -= 60;
		}
		if ( in_array( $text, array( 'カートに入れる', 'お気に入りに追加', '無料サンプル', '報告する', 'カートに追加' ), true ) ) {
			$score -= 60;
		}
		if ( $this->ancestor_has_class_token( $element, 'search_tag' ) ) {
			$score -= 30;
		}

		return $score;
	}

	/**
	 * @param DOMElement $element Element.
	 * @param DOMElement $scope   Scope.
	 * @return string
	 */
	private function suggest_generic_selector( DOMElement $element, DOMElement $scope ) {
		$parts = array();
		$node  = $element;
		$depth = 0;
		while ( $node instanceof DOMElement && $node !== $scope && $depth < 4 ) {
			$seg = strtolower( $node->tagName );
			$cls = array_slice( $this->get_class_tokens( $node ), 0, 2 );
			if ( ! empty( $cls ) ) {
				$seg .= '.' . implode( '.', $cls );
			}
			array_unshift( $parts, $seg );
			$parent = $node->parentNode;
			$node   = ( $parent instanceof DOMElement ) ? $parent : null;
			++$depth;
		}

		return implode( ' ', $parts );
	}

	/**
	 * @param DOMElement $element Element.
	 * @param string     $token   Class token.
	 */
	private function ancestor_has_class_token( DOMElement $element, $token ) {
		$node = $element;
		while ( $node instanceof DOMElement ) {
			if ( $this->has_class_token( $node, $token ) ) {
				return true;
			}
			$parent = $node->parentNode;
			$node   = ( $parent instanceof DOMElement ) ? $parent : null;
		}
		return false;
	}

	/**
	 * @param DOMElement $element Element.
	 * @param string     $token   Class name.
	 */
	private function has_class_token( DOMElement $element, $token ) {
		return in_array( $token, $this->get_class_tokens( $element ), true );
	}

	/**
	 * @param DOMElement $element Element.
	 * @return array<int, string>
	 */
	private function get_class_tokens( DOMElement $element ) {
		$raw = trim( (string) $element->getAttribute( 'class' ) );
		if ( '' === $raw ) {
			return array();
		}
		$parts = preg_split( '/\s+/', $raw );
		return is_array( $parts ) ? array_values( array_filter( $parts ) ) : array();
	}

	/**
	 * @param DOMElement $node Node.
	 */
	private function node_text( DOMElement $node ) {
		$text = preg_replace( '/\s+/u', ' ', (string) $node->textContent );
		return trim( (string) $text );
	}

	/**
	 * @param string $text Text.
	 * @param int    $max  Max length.
	 */
	private function truncate( $text, $max ) {
		$text = trim( (string) $text );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		return mb_substr( $text, 0, $max - 1 ) . '…';
	}

	/**
	 * @param array<string, mixed> $group Discovery row.
	 * @return array<string, mixed>
	 */
	private function attach_group_metadata( array $group ) {
		$role = (string) ( $group['role'] ?? '' );
		if ( '' === $role ) {
			$role = 'block' === ( $group['kind'] ?? '' ) ? 'item_block' : (string) ( $group['kind'] ?? 'link' );
		}
		$kind = (string) ( $group['kind'] ?? '' );
		$group['role']         = $role;
		$group['label']        = $this->role_label( $role );
		$group['value_hint']   = $this->role_value_hint( $role, $kind );
		$group['extract_mode'] = $this->group_extract_mode( $role, $kind );
		$group['sort_order']   = $this->role_sort_order( $role );
		if ( empty( $group['sample'] ) ) {
			$group['sample'] = $this->format_sample_line( $group );
		}
		return $group;
	}

	/**
	 * @param string $role Role id.
	 */
	private function role_label( $role ) {
		if ( 'item_block' === $role ) {
			return __( '1件ブロック', 'custom-rss-builder' );
		}
		if ( 'product_link' === $role || 'link' === $role ) {
			return __( 'リンク', 'custom-rss-builder' );
		}
		if ( 'eyecatch' === $role || 'image' === $role ) {
			return __( '画像', 'custom-rss-builder' );
		}
		if ( 'author' === $role || 'review_title' === $role || 'text' === $role ) {
			return __( 'テキスト', 'custom-rss-builder' );
		}
		return '';
	}

	/**
	 * @param string $role Role.
	 * @param string $kind Kind.
	 * @return string
	 */
	private function group_extract_mode( $role, $kind ) {
		if ( 'image' === $kind || 'eyecatch' === $role ) {
			return 'src';
		}
		if ( 'link' === $kind || 'product_link' === $role ) {
			return 'href';
		}
		if ( 'text' === $role || 'text' === $kind ) {
			return 'text';
		}
		if ( 'item_block' === $role ) {
			return '';
		}
		return 'text';
	}

	/**
	 * @param string $role Role.
	 * @param string $kind kind.
	 */
	private function role_value_hint( $role, $kind ) {
		if ( 'eyecatch' === $role || 'image' === $kind ) {
			return __( 'src 属性（画像URL）', 'custom-rss-builder' );
		}
		if ( in_array( $role, array( 'review_title', 'author' ), true ) ) {
			return __( 'リンクの表示テキスト', 'custom-rss-builder' );
		}
		if ( 'product_link' === $role ) {
			return __( 'href + title 属性', 'custom-rss-builder' );
		}
		return '';
	}

	/**
	 * @param string $role Role.
	 */
	private function role_sort_order( $role ) {
		$order = array(
			'item_block'   => 1000,
			'product_link' => 950,
			'review_title' => 940,
			'author'       => 930,
			'eyecatch'     => 920,
			'link'         => 100,
			'image'        => 90,
			'block'        => 80,
		);
		return (int) ( $order[ $role ] ?? 50 );
	}

	/**
	 * @param array<int, array<string, mixed>> $groups Groups.
	 * @return array<int, array<string, string>>
	 */
	private function build_field_guide( array $groups ) {
		$want   = array( 'item_block', 'product_link', 'review_title', 'author', 'eyecatch' );
		$guide  = array();
		$by_role = array();
		foreach ( $groups as $group ) {
			$role = (string) ( $group['role'] ?? '' );
			if ( '' === $role || isset( $by_role[ $role ] ) ) {
				continue;
			}
			$by_role[ $role ] = $group;
		}
		foreach ( $want as $role ) {
			if ( ! isset( $by_role[ $role ] ) ) {
				continue;
			}
			$g = $by_role[ $role ];
			$guide[] = array(
				'role'       => $role,
				'label'      => (string) ( $g['label'] ?? '' ),
				'selector'   => (string) ( $g['selector'] ?? '' ),
				'value_hint' => (string) ( $g['value_hint'] ?? '' ),
				'count'      => (string) ( $g['count'] ?? '0' ),
				'sample'     => $this->format_sample_line( $g ),
			);
		}
		return $guide;
	}

	/**
	 * セレクタ一致の先頭要素からサンプルを補完（一覧で必ずプレビューできるようにする）。
	 *
	 * @param DOMXPath              $xpath   XPath.
	 * @param DOMElement            $context Scope.
	 * @param array<string, mixed>  $group   Group row.
	 * @return array<string, mixed>
	 */
	private function fill_group_samples( DOMXPath $xpath, DOMElement $context, array $group ) {
		$selector = trim( (string) ( $group['selector'] ?? '' ) );
		if ( '' === $selector ) {
			return $group;
		}

		$query = crb_css_to_xpath( $selector );
		if ( is_wp_error( $query ) ) {
			return $group;
		}

		$nodes = $xpath->query( $query, $context );
		if ( false === $nodes || 0 === $nodes->length ) {
			return $group;
		}

		$kind    = (string) ( $group['kind'] ?? '' );
		$role    = (string) ( $group['role'] ?? '' );
		$samples = array();

		for ( $i = 0; $i < $nodes->length && count( $samples ) < self::MAX_SAMPLES; $i++ ) {
			$node = $nodes->item( $i );
			if ( ! ( $node instanceof DOMElement ) ) {
				continue;
			}

			if ( 'image' === $kind || 'eyecatch' === $role ) {
				$src = $this->pick_image_sample_src( $node );
				if ( '' === $src ) {
					continue;
				}
				$samples[] = array(
					'src' => $this->truncate( $src, 120 ),
					'alt' => $this->truncate( (string) $node->getAttribute( 'alt' ), 80 ),
				);
				continue;
			}

			if ( 'text' === $kind || 'text' === $role ) {
				$text = $this->node_text( $node );
				if ( '' === $text ) {
					continue;
				}
				$samples[] = array( 'text' => $this->truncate( $text, 120 ) );
				continue;
			}

			if ( 'block' === $kind || 'item_block' === $role ) {
				$text = $this->truncate( $this->node_text( $node ), 100 );
				if ( '' !== $text ) {
					$samples[] = array( 'text' => $text );
				}
				continue;
			}

			$href = trim( (string) $node->getAttribute( 'href' ) );
			if ( '' === $href || '#' === $href ) {
				continue;
			}
			$samples[] = array(
				'href'  => $this->truncate( $href, 120 ),
				'text'  => $this->truncate( $this->node_text( $node ), 80 ),
				'title' => $this->truncate( (string) $node->getAttribute( 'title' ), 80 ),
			);
		}

		if ( ! empty( $samples ) ) {
			$group['samples'] = $samples;
		}
		$group['sample'] = $this->format_sample_line( $group );

		return $group;
	}

	/**
	 * @param array<string, mixed> $group Group with samples.
	 * @return string
	 */
	private function format_sample_line( array $group ) {
		if ( empty( $group['samples'] ) || ! is_array( $group['samples'] ) ) {
			return '—';
		}

		$lines = array();
		foreach ( $group['samples'] as $sample ) {
			if ( ! is_array( $sample ) ) {
				continue;
			}
			if ( isset( $sample['src'] ) ) {
				$line = '';
				if ( ! empty( $sample['alt'] ) ) {
					$line .= $sample['alt'] . ' — ';
				}
				$line .= (string) $sample['src'];
				$lines[] = $line;
				continue;
			}
			if ( isset( $sample['text'] ) && ! isset( $sample['href'] ) ) {
				$lines[] = (string) $sample['text'];
				continue;
			}
			$parts = array();
			if ( ! empty( $sample['text'] ) ) {
				$parts[] = (string) $sample['text'];
			}
			if ( ! empty( $sample['title'] ) && (string) $sample['title'] !== (string) ( $sample['text'] ?? '' ) ) {
				$parts[] = (string) $sample['title'];
			}
			if ( ! empty( $sample['href'] ) ) {
				$parts[] = (string) $sample['href'];
			}
			if ( ! empty( $parts ) ) {
				$lines[] = implode( ' / ', $parts );
			}
		}

		return empty( $lines ) ? '—' : implode( "\n", $lines );
	}
}
