<?php
/**
 * CSS セレクタ → XPath 変換と抽出エントリ。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 1件プレビュー・要素調べるで表示する先頭件数 */
if ( ! defined( 'CRB_RECORD_PREVIEW_LIMIT' ) ) {
	define( 'CRB_RECORD_PREVIEW_LIMIT', 3 );
}

/** 1件あたりの抽出スロット数（{%1} … {%n}）。 */
if ( ! defined( 'CRB_RECORD_SLOT_COUNT' ) ) {
	define( 'CRB_RECORD_SLOT_COUNT', 12 );
}

/**
 * @param string $selector CSS selector.
 * @return string|WP_Error XPath (relative, starts with .//)
 */
function crb_css_to_xpath( $selector ) {
	$selector = trim( (string) $selector );
	if ( '' === $selector ) {
		return new WP_Error( 'crb_css_empty', __( 'CSS セレクタが空です。', 'custom-rss-builder' ) );
	}

	if ( ! preg_match( '#^[a-zA-Z0-9\\s\\.\\#\\[\\]\\*\\=\\"\\\'\\_\\-\\>\\:]+$#u', $selector ) ) {
		return new WP_Error(
			'crb_css_invalid',
			__( 'CSS セレクタに使えない文字が含まれています。', 'custom-rss-builder' )
		);
	}

	$parts = preg_split( '/\\s+/', $selector );
	if ( ! is_array( $parts ) || empty( $parts ) ) {
		return new WP_Error( 'crb_css_invalid', __( 'CSS セレクタの解析に失敗しました。', 'custom-rss-builder' ) );
	}

	$segments = array();
	foreach ( $parts as $part ) {
		$seg = crb_css_segment_to_xpath( $part );
		if ( is_wp_error( $seg ) ) {
			return $seg;
		}
		$segments[] = $seg;
	}

	return './/' . implode( '//', $segments );
}

/**
 * @param string $segment Single compound selector (no spaces).
 * @return string|WP_Error
 */
function crb_css_segment_to_xpath( $segment ) {
	$segment = trim( $segment );
	$tag     = '*';
	$conds   = array();
	$rest    = $segment;

	if ( preg_match( '/^([a-zA-Z][a-zA-Z0-9]*)/', $rest, $m ) ) {
		$tag  = $m[1];
		$rest = substr( $rest, strlen( $m[1] ) );
	}

	while ( '' !== $rest ) {
		if ( '.' === $rest[0] ) {
			if ( ! preg_match( '/^\\.([a-zA-Z0-9_-]+)/', $rest, $m ) ) {
				return new WP_Error( 'crb_css_invalid', __( 'クラス指定が不正です。', 'custom-rss-builder' ) );
			}
			$conds[] = 'contains(concat(" ", normalize-space(@class), " "), " ' . $m[1] . ' ")';
			$rest    = substr( $rest, strlen( $m[0] ) );
			continue;
		}
		if ( '#' === $rest[0] ) {
			if ( ! preg_match( '/^#([a-zA-Z0-9_-]+)/', $rest, $m ) ) {
				return new WP_Error( 'crb_css_invalid', __( 'ID 指定が不正です。', 'custom-rss-builder' ) );
			}
			$conds[] = '@id="' . $m[1] . '"';
			$rest    = substr( $rest, strlen( $m[0] ) );
			continue;
		}
		if ( '[' === $rest[0] ) {
			if ( ! preg_match( '/^\\[([a-zA-Z0-9_-]+)(?:(\\*?=)(?:"([^"]*)"|([^\]]+)))?\\]/', $rest, $m ) ) {
				return new WP_Error( 'crb_css_invalid', __( '属性指定が不正です。', 'custom-rss-builder' ) );
			}
			$attr = $m[1];
			if ( empty( $m[2] ) ) {
				$conds[] = '@' . $attr;
			} else {
				$val = isset( $m[3] ) && '' !== $m[3] ? $m[3] : ( $m[4] ?? '' );
				if ( '*=' === $m[2] ) {
					$conds[] = 'contains(@' . $attr . ', "' . $val . '")';
				} else {
					$conds[] = '@' . $attr . '="' . $val . '"';
				}
			}
			$rest = substr( $rest, strlen( $m[0] ) );
			continue;
		}
		return new WP_Error( 'crb_css_invalid', __( 'CSS セレクタの形式が不正です。', 'custom-rss-builder' ) );
	}

	$xpath = $tag;
	if ( ! empty( $conds ) ) {
		$xpath .= '[' . implode( ' and ', $conds ) . ']';
	}
	return $xpath;
}

/**
 * @param string               $html HTML.
 * @param array<string, mixed> $feed Feed row.
 * @return array<int, array<int|string, string>>|WP_Error
 */
function crb_extract_items_from_html( $html, array $feed ) {
	$mode = crb_get_feed_extraction_mode( $feed );

	if ( 'css' === $mode ) {
		$extractor = new Custom_RSS_Builder_Css_Extractor();
		$config    = crb_get_feed_css_config( $feed );
		$base_url  = (string) ( $feed['url'] ?? '' );
		$result    = $extractor->extract( $html, $config, $base_url );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return crb_normalize_extract_rows_to_slots( $result );
	}

	$parser = new Custom_RSS_Builder_HTML_Parser();
	return $parser->parse_html(
		$html,
		(string) ( $feed['template'] ?? '' ),
		(string) ( $feed['scope_template'] ?? '' )
	);
}

/**
 * @param array<string, mixed> $feed Feed.
 * @return string template|css
 */
function crb_get_feed_extraction_mode( array $feed ) {
	$mode = (string) ( $feed['extraction_mode'] ?? 'template' );
	return in_array( $mode, array( 'template', 'css' ), true ) ? $mode : 'template';
}

/**
 * @param array<string, mixed> $feed Feed.
 * @return array<string, string>
 */
function crb_get_feed_css_config( array $feed ) {
	$css   = is_array( $feed['css'] ?? null ) ? $feed['css'] : array();
	$base  = array(
		'scope_selector'        => (string) ( $css['scope_selector'] ?? '' ),
		'item_selector'         => (string) ( $css['item_selector'] ?? '' ),
		'link_selector'         => (string) ( $css['link_selector'] ?? '' ),
		'title_mode'            => (string) ( $css['title_mode'] ?? 'attr' ),
		'title_attr'            => (string) ( $css['title_attr'] ?? 'title' ),
		'title_selector'        => (string) ( $css['title_selector'] ?? '' ),
		'image_selector'        => (string) ( $css['image_selector'] ?? '' ),
		'author_selector'       => (string) ( $css['author_selector'] ?? '' ),
		'review_title_selector' => (string) ( $css['review_title_selector'] ?? '' ),
		'summary_selector'      => (string) ( $css['summary_selector'] ?? '' ),
		'category_selector'     => (string) ( $css['category_selector'] ?? '' ),
		'review_body_selector'  => (string) ( $css['review_body_selector'] ?? '' ),
	);
	foreach ( crb_extra_slot_storage_map() as $meta ) {
		$key = $meta['config_key'];
		if ( ! isset( $base[ $key ] ) ) {
			$base[ $key ] = (string) ( $css[ $key ] ?? '' );
		}
		$base[ $meta['mode_key'] ] = (string) ( $css[ $meta['mode_key'] ] ?? $meta['default_mode'] );
	}
	return $base;
}

/**
 * 1件ブロック単位の名前付きレコード（正規化前）かどうか。
 *
 * @param mixed $row 抽出行。
 * @return bool
 */
function crb_is_named_record( $row ) {
	if ( ! is_array( $row ) ) {
		return false;
	}
	if ( isset( $row['link'] ) || isset( $row['title'] ) || isset( $row['image_url'] ) || isset( $row['review_body'] ) ) {
		return true;
	}
	foreach ( $row as $key => $val ) {
		if ( is_string( $key ) && 0 === strpos( $key, 'slot_' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Feed43 風スロット行（0 始まりの連番キー）かどうか。
 *
 * @param mixed $row 抽出行。
 * @return bool
 */
function crb_is_slot_indexed_row( $row ) {
	return is_array( $row ) && array_key_exists( 0, $row ) && ! crb_is_named_record( $row );
}

/** @deprecated Use crb_is_named_record() or crb_is_slot_indexed_row(). */
function crb_is_extracted_record( $row ) {
	return crb_is_named_record( $row ) || crb_is_slot_indexed_row( $row );
}

/**
 * 抽出器内部用の連想配列キー（順序＝{%1}…{%n}）。UI・プレビューでは使わない。
 *
 * @return array<int, string>
 */
function crb_record_internal_field_keys() {
	$keys = array( 'title', 'link' );
	$extra = (int) CRB_RECORD_SLOT_COUNT - 2;
	for ( $i = 0; $i < $extra; $i++ ) {
		$keys[] = 'slot_' . ( $i + 3 );
	}
	return $keys;
}

/**
 * {%3} 以降のスロット index（0 始まり）一覧。
 *
 * @return array<int, int>
 */
function crb_extra_slot_indices() {
	$out = array();
	$max = (int) CRB_RECORD_SLOT_COUNT;
	for ( $i = 2; $i < $max; $i++ ) {
		$out[] = $i;
	}
	return $out;
}

/**
 * 追加スロットの POST / 保存キー（レガシー名互換）。
 *
 * @return array<int, array{config_key: string, mode_key: string, default_mode: string}>
 */
function crb_extra_slot_storage_map() {
	return array(
		2  => array(
			'config_key'    => 'summary_selector',
			'mode_key'      => 'slot_mode_3',
			'default_mode'  => 'text',
		),
		3  => array(
			'config_key'    => 'image_selector',
			'mode_key'      => 'slot_mode_4',
			'default_mode'  => 'src',
		),
		4  => array(
			'config_key'    => 'category_selector',
			'mode_key'      => 'slot_mode_5',
			'default_mode'  => 'text',
		),
		5  => array(
			'config_key'    => 'review_title_selector',
			'mode_key'      => 'slot_mode_6',
			'default_mode'  => 'text',
		),
		6  => array(
			'config_key'    => 'review_body_selector',
			'mode_key'      => 'slot_mode_7',
			'default_mode'  => 'html',
		),
		7  => array(
			'config_key'    => 'author_selector',
			'mode_key'      => 'slot_mode_8',
			'default_mode'  => 'text',
		),
		8  => array(
			'config_key'    => 'slot_selector_9',
			'mode_key'      => 'slot_mode_9',
			'default_mode'  => 'text',
		),
		9  => array(
			'config_key'    => 'slot_selector_10',
			'mode_key'      => 'slot_mode_10',
			'default_mode'  => 'text',
		),
		10 => array(
			'config_key'    => 'slot_selector_11',
			'mode_key'      => 'slot_mode_11',
			'default_mode'  => 'text',
		),
		11 => array(
			'config_key'    => 'slot_selector_12',
			'mode_key'      => 'slot_mode_12',
			'default_mode'  => 'text',
		),
	);
}

/**
 * @param string $mode Raw mode.
 * @return string text|html|src|href|attr
 */
function crb_sanitize_slot_extract_mode( $mode ) {
	$mode = sanitize_key( (string) $mode );
	$allowed = array( 'text', 'html', 'src', 'href', 'attr' );
	return in_array( $mode, $allowed, true ) ? $mode : 'text';
}

/**
 * @param string $mode Extract mode.
 * @return array{is_url: bool, is_html: bool}
 */
function crb_slot_flags_for_mode( $mode ) {
	$mode = crb_sanitize_slot_extract_mode( $mode );
	return array(
		'is_url'  => in_array( $mode, array( 'src', 'href' ), true ),
		'is_html' => 'html' === $mode,
	);
}

/**
 * @param array<string, string> $config CSS 設定。
 * @return array<int, array{selector: string, mode: string, attr: string}>
 */
function crb_get_feed_extra_slot_rules( array $config ) {
	$rules = array();
	foreach ( crb_extra_slot_storage_map() as $index => $meta ) {
		$selector = trim( (string) ( $config[ $meta['config_key'] ] ?? '' ) );
		$mode_key = $meta['mode_key'];
		$mode     = crb_sanitize_slot_extract_mode(
			(string) ( $config[ $mode_key ] ?? $meta['default_mode'] )
		);
		$attr = 'title';
		if ( 'attr' === $mode ) {
			$attr_key = 'slot_attr_' . ( $index + 1 );
			$attr     = crb_sanitize_css_attr_name( (string) ( $config[ $attr_key ] ?? 'title' ) );
		}
		$rules[ $index ] = array(
			'selector' => $selector,
			'mode'     => $mode,
			'attr'     => $attr,
		);
	}
	return $rules;
}

/**
 * Feed43 風スロット定義（{%1}=index 0）。表示・テンプレートは番号のみ。
 *
 * @return array<int, array<string, mixed>>
 */
function crb_get_record_slot_schema( array $config = array() ) {
	$schema = array(
		array( 'is_url' => false, 'is_html' => false ),
		array( 'is_url' => true, 'is_html' => false ),
	);
	$rules  = crb_get_feed_extra_slot_rules( $config );
	foreach ( crb_extra_slot_indices() as $index ) {
		$mode   = (string) ( $rules[ $index ]['mode'] ?? 'text' );
		$flags  = crb_slot_flags_for_mode( $mode );
		$schema[] = $flags;
	}
	while ( count( $schema ) < (int) CRB_RECORD_SLOT_COUNT ) {
		$schema[] = array( 'is_url' => false, 'is_html' => false );
	}
	return array_slice( $schema, 0, (int) CRB_RECORD_SLOT_COUNT );
}

/**
 * AJAX / JS 向け（{%n} と URL/HTML フラグのみ。意味名は送らない）。
 *
 * @return array<int, array<string, mixed>>
 */
function crb_get_record_slot_schema_for_json( array $config = array() ) {
	$out = array();
	foreach ( crb_get_slot_rules_for_json( $config ) as $rule ) {
		$out[] = array(
			'index'      => (int) $rule['index'],
			'token'      => (string) $rule['token'],
			'selector'   => (string) $rule['selector'],
			'mode'       => (string) $rule['mode'],
			'mode_label' => (string) $rule['mode_label'],
			'is_html'    => ! empty( $rule['is_html'] ),
			'is_url'     => ! empty( $rule['is_url'] ),
		);
	}
	return $out;
}

/**
 * スロットごとのセレクタ・取り方（③プレビュー・JSON 用）。
 *
 * @param array<string, string> $config CSS 設定。
 * @return array<int, array<string, mixed>>
 */
function crb_get_slot_rules_for_json( array $config = array() ) {
	$title_opts = crb_title_slot_mode_options();
	$extra_opts = crb_extra_slot_mode_options();
	$title_mode = (string) ( $config['title_mode'] ?? 'attr' );
	$title_sel  = trim( (string) ( $config['title_selector'] ?? '' ) );
	$link_sel   = trim( (string) ( $config['link_selector'] ?? '' ) );

	if ( in_array( $title_mode, array( 'attr', 'text' ), true ) ) {
		$display_sel = '' !== $link_sel ? $link_sel : '—';
	} else {
		$display_sel = $title_sel;
	}

	$title_label = $title_opts[ $title_mode ] ?? $title_mode;
	if ( 'attr' === $title_mode ) {
		$attr = trim( (string) ( $config['title_attr'] ?? 'title' ) );
		if ( '' === $attr ) {
			$attr = 'title';
		}
		$title_label .= ' (' . $attr . ')';
	}

	$rules   = array();
	$rules[] = array(
		'index'      => 0,
		'token'      => crb_slot_token( 0 ),
		'selector'   => $display_sel,
		'mode'       => $title_mode,
		'mode_label' => $title_label,
		'is_html'    => false,
		'is_url'     => false,
	);
	$rules[] = array(
		'index'      => 1,
		'token'      => crb_slot_token( 1 ),
		'selector'   => $link_sel,
		'mode'       => 'href',
		'mode_label' => __( 'リンクURL (href)', 'custom-rss-builder' ),
		'is_html'    => false,
		'is_url'     => true,
	);

	foreach ( crb_get_feed_extra_slot_rules( $config ) as $index => $rule ) {
		$mode = (string) ( $rule['mode'] ?? 'text' );
		$flags = crb_slot_flags_for_mode( $mode );
		$label = $extra_opts[ $mode ] ?? $mode;
		if ( 'attr' === $mode ) {
			$label .= ' (' . (string) ( $rule['attr'] ?? 'title' ) . ')';
		}
		$sel = trim( (string) ( $rule['selector'] ?? '' ) );
		$rules[] = array(
			'index'       => (int) $index,
			'token'       => crb_slot_token( (int) $index ),
			'selector'    => $sel,
			'mode'        => $mode,
			'mode_label'  => $label,
			'is_html'     => ! empty( $flags['is_html'] ),
			'is_url'      => ! empty( $flags['is_url'] ),
			'auto_detect' => ( 3 === (int) $index && '' === trim( (string) ( $rule['selector'] ?? '' ) ) ),
		);
	}

	return $rules;
}

/**
 * @deprecated Feed43 では crb_feed43_discover_suggest_slot_rules() を使用。
 * @return array<string, int>
 */
function crb_discover_role_slot_indexes() {
	return array(
		'primary_link' => 1,
		'link'         => 1,
		'eyecatch'     => 3,
		'image'        => 3,
		'text'         => 2,
	);
}

/**
 * 要素調べる結果から Feed43 風にスロットへセレクタを提案。
 *
 * @param array<int, array<string, mixed>> $groups Discovery groups.
 * @return array<string, mixed>
 */
function crb_discover_suggest_slot_rules( array $groups ) {
	return crb_feed43_discover_suggest_slot_rules( $groups );
}

/**
 * 発見グループ1件を範囲コンテキストで試し読み。
 *
 * @param Custom_RSS_Builder_Css_Extractor $extractor Extractor.
 * @param DOMXPath                       $xpath     XPath.
 * @param DOMElement                     $context   Context.
 * @param array<string, mixed>           $group     Discovery group.
 * @param string                         $base_url  Base URL.
 * @return string
 */
function crb_probe_discover_group_value( Custom_RSS_Builder_Css_Extractor $extractor, DOMXPath $xpath, DOMElement $context, array $group, $base_url = '' ) {
	$selector = trim( (string) ( $group['selector'] ?? '' ) );
	if ( '' === $selector ) {
		return '';
	}

	$kind = (string) ( $group['kind'] ?? '' );
	$role = (string) ( $group['role'] ?? '' );
	$mode = trim( (string) ( $group['extract_mode'] ?? '' ) );
	if ( '' === $mode ) {
		if ( 'image' === $kind || 'eyecatch' === $role ) {
			$mode = 'src';
		} elseif ( 'link' === $kind ) {
			$mode = 'href';
		} else {
			$mode = 'text';
		}
	}
	$mode = crb_sanitize_slot_extract_mode( $mode );

	$rule = array(
		'selector' => $selector,
		'mode'     => $mode,
	);
	if ( 'attr' === $mode ) {
		$rule['attr'] = 'title';
	}

	$value = $extractor->probe_slot_in_context( $xpath, $context, $rule, $base_url );

	if ( '' === $value && ! empty( $group['samples'] ) && is_array( $group['samples'] ) ) {
		$sample = $group['samples'][0];
		if ( is_array( $sample ) ) {
			if ( ! empty( $sample['src'] ) ) {
				$value = (string) $sample['src'];
			} elseif ( ! empty( $sample['href'] ) ) {
				$value = (string) $sample['href'];
			} elseif ( ! empty( $sample['text'] ) ) {
				$value = (string) $sample['text'];
			} elseif ( ! empty( $sample['title'] ) ) {
				$value = (string) $sample['title'];
			}
		}
	}

	if ( '' !== $value && in_array( $mode, array( 'href', 'src' ), true ) && 0 === strpos( $value, '//' ) ) {
		$value = 'https:' . $value;
	}

	return $value;
}

/**
 * 要素調べる: 画像グループから {%4} 用セレクタを選ぶ（recommended 以外も含む）。
 *
 * @param array<int, array<string, mixed>> $groups Groups.
 * @return string
 */
function crb_discover_pick_image_selector( array $groups ) {
	$best_sel = '';
	$best_pri = -1;
	foreach ( $groups as $group ) {
		if ( 'image' !== (string) ( $group['kind'] ?? '' ) ) {
			continue;
		}
		$selector = trim( (string) ( $group['selector'] ?? '' ) );
		if ( '' === $selector ) {
			continue;
		}
		$pri = (int) ( $group['priority'] ?? 0 );
		if ( $pri > $best_pri ) {
			$best_pri = $pri;
			$best_sel = $selector;
		}
	}
	return $best_sel;
}

/**
 * {%4} 用のデフォルト画像セレクタ（プリセットと同じ）。
 *
 * @return string
 */
function crb_default_image_selector() {
	$preset = crb_preset_review_list_example();
	return trim( (string) ( $preset['image_selector'] ?? '' ) );
}

/**
 * 名前付きレコード（抽出器内部）→ スロット行。
 *
 * @param array<string, string> $record Named record.
 * @return array<int, string>
 */
function crb_record_to_slot_row( array $record ) {
	$row = array();
	foreach ( crb_record_internal_field_keys() as $index => $key ) {
		$row[ $index ] = trim( (string) ( $record[ $key ] ?? '' ) );
	}
	return $row;
}

/**
 * @param array<int, array<string, string>>|array<int, array<int, string>> $rows Rows.
 * @return array<int, array<int, string>>
 */
function crb_normalize_extract_rows_to_slots( array $rows ) {
	if ( empty( $rows ) ) {
		return $rows;
	}
	$first = $rows[0];
	if ( ! crb_is_named_record( $first ) ) {
		return $rows;
	}
	$out = array();
	foreach ( $rows as $record ) {
		if ( ! is_array( $record ) ) {
			continue;
		}
		$out[] = crb_record_to_slot_row( $record );
	}
	return $out;
}

/**
 * スロット番号（1 始まり）の表示用トークン。
 *
 * @param int $index 0 始まりインデックス。
 * @return string
 */
function crb_slot_token( $index ) {
	return '{%' . ( (int) $index + 1 ) . '}';
}

/**
 * タイトル欄に URL や複数 href が連結されたとき、表示用テキストだけ残す。
 *
 * @param string $title Raw title.
 * @return string
 */
function crb_sanitize_extracted_title( $title ) {
	$title = trim( preg_replace( '/\s+/u', ' ', (string) $title ) );
	if ( '' === $title ) {
		return '';
	}
	if ( preg_match_all( '#https?://[^\s<>"\']+#i', $title, $matches ) && ! empty( $matches[0] ) ) {
		$stripped = trim( preg_replace( '#https?://[^\s<>"\']+#i', ' ', $title ) );
		$stripped = preg_replace( '/\s+/u', ' ', $stripped );
		$stripped = preg_replace( '/[0-9]+-(?:No|Yes|[A-Za-z]+)/u', ' ', $stripped );
		$stripped = trim( preg_replace( '/\s+/u', ' ', $stripped ) );
		if ( strlen( $stripped ) >= 2 ) {
			return $stripped;
		}
	}
	return $title;
}

/**
 * レコード抽出用のフィールド定義（セレクタが空の項目は除外）。
 *
 * @param array<string, string> $config CSS 設定。
 * @return array<string, array<string, mixed>>
 */
/** @deprecated Use crb_get_feed_extra_slot_rules(). */
function crb_css_record_field_definitions( array $config ) {
	$keys = crb_record_internal_field_keys();
	$out  = array();
	foreach ( crb_get_feed_extra_slot_rules( $config ) as $index => $rule ) {
		if ( '' === trim( (string) ( $rule['selector'] ?? '' ) ) ) {
			continue;
		}
		$key = $keys[ $index ] ?? 'slot_' . ( $index + 1 );
		$mode = (string) ( $rule['mode'] ?? 'text' );
		$def  = array(
			'selector' => (string) $rule['selector'],
			'type'     => 'text',
		);
		if ( 'html' === $mode ) {
			$def['type'] = 'html';
		} elseif ( 'src' === $mode ) {
			$def['type']   = 'attr';
			$def['attr']   = 'src';
			$def['is_url'] = true;
		} elseif ( 'href' === $mode ) {
			$def['type']   = 'attr';
			$def['attr']   = 'href';
			$def['is_url'] = true;
		} elseif ( 'attr' === $mode ) {
			$def['type'] = 'attr';
			$def['attr'] = (string) ( $rule['attr'] ?? 'title' );
		}
		$out[ $key ] = $def;
	}
	return $out;
}

/**
 * レビュー一覧向けの投稿本文テンプレート例。
 *
 * @return string
 */
/**
 * {%1} 用の取得方法（select options）。
 *
 * @return array<string, string> value => label
 */
function crb_title_slot_mode_options() {
	return array(
		'attr'      => __( 'リンクの属性', 'custom-rss-builder' ),
		'text'      => __( 'リンクの表示テキスト', 'custom-rss-builder' ),
		'selector'  => __( '別セレクタ（テキスト）', 'custom-rss-builder' ),
		'el_text'   => __( 'テキスト', 'custom-rss-builder' ),
		'el_html'   => __( 'HTML', 'custom-rss-builder' ),
		'el_src'    => __( '画像URL (src)', 'custom-rss-builder' ),
		'el_href'   => __( 'リンクURL (href)', 'custom-rss-builder' ),
		'el_attr'   => __( '属性', 'custom-rss-builder' ),
	);
}

/**
 * 追加スロット用の取得方法。
 *
 * @return array<string, string>
 */
function crb_extra_slot_mode_options() {
	return array(
		'text'  => __( 'テキスト', 'custom-rss-builder' ),
		'html'  => __( 'HTML', 'custom-rss-builder' ),
		'src'   => __( '画像URL (src)', 'custom-rss-builder' ),
		'href'  => __( 'リンクURL (href)', 'custom-rss-builder' ),
		'attr'  => __( '属性', 'custom-rss-builder' ),
	);
}

function crb_preset_review_import_template() {
	return '<p>{%1}</p>' . "\n"
		. '<p><a href="{%2}">{%2}</a></p>' . "\n"
		. '<p>{%3}</p>' . "\n"
		. '<p>{%4}</p>' . "\n"
		. '<p>{%5}</p>' . "\n"
		. '<p>{%6}</p>' . "\n"
		. '{%7}';
}

/**
 * @param mixed $raw POST / feed data.
 * @return array<string, string>
 */
function crb_sanitize_css_config( $raw ) {
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}
	$title_mode = sanitize_key( (string) ( $raw['title_mode'] ?? 'attr' ) );
	$title_modes = array( 'attr', 'text', 'selector', 'el_text', 'el_html', 'el_src', 'el_href', 'el_attr' );
	if ( ! in_array( $title_mode, $title_modes, true ) ) {
		$title_mode = 'attr';
	}
	$out = array(
		'scope_selector'        => crb_sanitize_css_selector( (string) ( $raw['scope_selector'] ?? '' ) ),
		'item_selector'         => crb_sanitize_css_selector( (string) ( $raw['item_selector'] ?? '' ) ),
		'link_selector'         => crb_sanitize_css_selector( (string) ( $raw['link_selector'] ?? '' ) ),
		'title_mode'            => $title_mode,
		'title_attr'            => crb_sanitize_css_attr_name( (string) ( $raw['title_attr'] ?? 'title' ) ),
		'title_selector'        => crb_sanitize_css_selector( (string) ( $raw['title_selector'] ?? '' ) ),
		'image_selector'        => crb_sanitize_css_selector( (string) ( $raw['image_selector'] ?? '' ) ),
		'author_selector'       => crb_sanitize_css_selector( (string) ( $raw['author_selector'] ?? '' ) ),
		'review_title_selector' => crb_sanitize_css_selector( (string) ( $raw['review_title_selector'] ?? '' ) ),
		'summary_selector'      => crb_sanitize_css_selector( (string) ( $raw['summary_selector'] ?? '' ) ),
		'category_selector'     => crb_sanitize_css_selector( (string) ( $raw['category_selector'] ?? '' ) ),
		'review_body_selector'  => crb_sanitize_css_selector( (string) ( $raw['review_body_selector'] ?? '' ) ),
	);
	foreach ( crb_extra_slot_storage_map() as $index => $meta ) {
		$key = $meta['config_key'];
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = crb_sanitize_css_selector( (string) ( $raw[ $key ] ?? '' ) );
		}
		$out[ $meta['mode_key'] ] = crb_sanitize_slot_extract_mode(
			(string) ( $raw[ $meta['mode_key'] ] ?? $meta['default_mode'] )
		);
		if ( 'attr' === $out[ $meta['mode_key'] ] ) {
			$attr_key = 'slot_attr_' . ( $index + 1 );
			$out[ $attr_key ] = crb_sanitize_css_attr_name( (string) ( $raw[ $attr_key ] ?? 'title' ) );
		}
	}
	return $out;
}

/**
 * @param string $selector CSS selector.
 * @return string
 */
function crb_sanitize_css_selector( $selector ) {
	$selector = trim( (string) $selector );
	if ( '' === $selector ) {
		return '';
	}
	$selector = preg_replace( '/[^a-zA-Z0-9\\s\\.\\#\\[\\]\\*\\=\\"\\\'\\_\\-\\>\\:]/u', '', $selector );
	return trim( (string) $selector );
}

/**
 * @param string $name Attribute name.
 * @return string
 */
function crb_sanitize_css_attr_name( $name ) {
	$name = sanitize_key( (string) $name );
	return '' !== $name ? $name : 'title';
}

/**
 * レビュー／記事リスト型ページ向けの入力例（サイト非依存の汎用値）。
 *
 * @return array<string, string>
 */
/**
 * 新規フィード用の空の CSS 設定（プリセットは入力例ボタンのみ）。
 *
 * @return array<string, string>
 */
function crb_empty_css_config() {
	$out = array(
		'scope_selector'        => '',
		'item_selector'         => '',
		'link_selector'         => '',
		'title_mode'            => 'attr',
		'title_attr'            => 'title',
		'title_selector'        => '',
		'image_selector'        => '',
		'author_selector'       => '',
		'review_title_selector' => '',
		'summary_selector'      => '',
		'category_selector'     => '',
		'review_body_selector'  => '',
	);
	foreach ( crb_extra_slot_storage_map() as $index => $meta ) {
		$out[ $meta['config_key'] ] = '';
		$out[ $meta['mode_key'] ]   = $meta['default_mode'];
	}
	return $out;
}

/**
 * 要素調べる: 発見結果だけから CSS 設定を組み立てる（フォーム値は使わない）。
 *
 * @param string                            $scope_selector Scope.
 * @param string                            $item_selector  Item block.
 * @param array<string, string|array>       $suggested      crb_discover_suggest_slot_rules() の戻り値。
 * @return array<string, string>
 */
function crb_css_config_from_discover_suggested( $scope_selector, $item_selector, array $suggested ) {
	$raw = crb_empty_css_config();
	$raw['scope_selector'] = (string) $scope_selector;
	$raw['item_selector']  = (string) $item_selector;
	if ( ! empty( $suggested['link_selector'] ) ) {
		$raw['link_selector'] = (string) $suggested['link_selector'];
	}
	foreach ( crb_extra_slot_storage_map() as $index => $meta ) {
		$key = $meta['config_key'];
		if ( isset( $suggested[ $key ] ) && is_string( $suggested[ $key ] ) ) {
			$raw[ $key ] = (string) $suggested[ $key ];
		}
		$mode_key = $meta['mode_key'];
		if ( isset( $suggested[ $mode_key ] ) && is_array( $suggested[ $mode_key ] ) ) {
			$raw[ $mode_key ] = (string) ( $suggested[ $mode_key ]['mode'] ?? $meta['default_mode'] );
		}
	}
	return crb_sanitize_css_config( $raw );
}

/**
 * 範囲内の試し読みプレビュー行（{%n}・セレクタ・取り方・取れた値）。
 *
 * @param string                         $html           HTML.
 * @param string                         $scope_selector 範囲 CSS。
 * @param string                         $item_selector  1件ブロック CSS。
 * @param array<int, array<string,mixed>> $groups        discover groups.
 * @param string                         $base_url       Base URL.
 * @return array{context_note: string, item_count: int, rows: array<int, array<string,mixed>>}
 */
function crb_build_discover_scope_preview( $html, $scope_selector, $item_selector, array $groups, $base_url = '' ) {
	$empty = array(
		'context_note' => '',
		'item_count'   => 0,
		'rows'         => array(),
	);

	if ( ! class_exists( 'DOMDocument' ) ) {
		$empty['context_note'] = __( 'DOM 拡張が利用できません。', 'custom-rss-builder' );
		return $empty;
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
		$empty['context_note'] = __( 'HTML の解析に失敗しました。', 'custom-rss-builder' );
		return $empty;
	}

	$xpath = new DOMXPath( $dom );
	$root  = $dom->getElementById( 'crb-root' );
	if ( ! $root instanceof DOMElement ) {
		return $empty;
	}

	$scope_el = $root;
	$scope_selector = trim( (string) $scope_selector );
	if ( '' !== $scope_selector ) {
		$scope_xpath = crb_css_to_xpath( $scope_selector );
		if ( is_wp_error( $scope_xpath ) ) {
			$empty['context_note'] = __( '範囲セレクタが不正です。', 'custom-rss-builder' );
			return $empty;
		}
		$scoped = $xpath->query( $scope_xpath, $root );
		if ( false === $scoped || 0 === $scoped->length || ! ( $scoped->item( 0 ) instanceof DOMElement ) ) {
			$empty['context_note'] = __( '範囲セレクタに一致する要素がありません。', 'custom-rss-builder' );
			return $empty;
		}
		$scope_el = $scoped->item( 0 );
	}

	$item_selector  = trim( (string) $item_selector );
	$context        = $scope_el;
	$item_count     = 1;
	$item_fallback  = false;
	if ( '' !== $item_selector ) {
		$item_xpath = crb_css_to_xpath( $item_selector );
		if ( is_wp_error( $item_xpath ) ) {
			$empty['context_note'] = __( '1件ブロックのセレクタが不正です。', 'custom-rss-builder' );
			return $empty;
		}
		$items = $xpath->query( $item_xpath, $scope_el );
		if ( false !== $items && $items->length > 0 && $items->item( 0 ) instanceof DOMElement ) {
			$context    = $items->item( 0 );
			$item_count = (int) $items->length;
		} else {
			$context       = $scope_el;
			$item_count    = 1;
			$item_fallback = true;
		}
	}

	$suggested = crb_discover_suggest_slot_rules( $groups );
	$config    = crb_css_config_from_discover_suggested( $scope_selector, $item_selector, $suggested );
	$extractor = new Custom_RSS_Builder_Css_Extractor();
	$rows      = $extractor->probe_record_slots_in_context( $xpath, $context, $config, $base_url, 8 );

	$note = '';
	if ( '' !== $scope_selector ) {
		$note = $scope_selector;
	} else {
		$note = __( 'ページ全体', 'custom-rss-builder' );
	}
	if ( '' !== $item_selector ) {
		$note .= ' / ' . $item_selector;
		if ( $item_fallback ) {
			$note .= ' (' . __( '範囲内に1件ブロックなし→範囲を1件として試読', 'custom-rss-builder' ) . ')';
		} else {
			$note .= ' (' . sprintf(
				/* translators: %d: item count */
				__( '%d件', 'custom-rss-builder' ),
				$item_count
			) . ')';
		}
	} else {
		$note .= ' / ' . __( '範囲を1件として試読', 'custom-rss-builder' );
	}

	return array(
		'context_note' => $note,
		'item_count'   => $item_count,
		'rows'         => $rows,
	);
}

function crb_preset_review_list_example() {
	return array(
		'scope_selector'        => '#review_list',
		'item_selector'         => '.review_contents',
		'link_selector'         => 'dt.work_name a[href*="product_id"]',
		'title_mode'            => 'attr',
		'title_attr'            => 'title',
		'title_selector'        => '',
		'image_selector'        => '.review_work .work_img_popover img',
		'author_selector'       => 'dd.maker_name span.author a',
		'review_title_selector' => '.reveiw_title a[href*="reviewlist"]',
		'summary_selector'      => 'dd.work_text',
		'category_selector'     => '.review_work .work_category a',
		'review_body_selector'  => '.review_main p.review_desc',
	);
}

/** @deprecated Use crb_preset_review_list_example() */
function crb_preset_dlsite_review_list() {
	return crb_preset_review_list_example();
}
