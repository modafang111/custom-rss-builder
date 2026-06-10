<?php
/**
 * Feed43 風スロット定義・抽出・要素調べる連携。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ライセンスに応じた利用可能スロット数（{%1%} から連番）。
 *
 * @return int
 */
function crb_get_effective_slot_count() {
	if ( function_exists( 'crb_license_get_record_slot_count' ) ) {
		return max( 1, (int) crb_license_get_record_slot_count() );
	}
	return (int) CRB_RECORD_SLOT_COUNT;
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
	$map = array(
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

	for ( $i = 12; $i < (int) CRB_RECORD_SLOT_COUNT; $i++ ) {
		$slot_num        = $i + 1;
		$map[ $i ]       = array(
			'config_key'   => 'slot_selector_' . $slot_num,
			'mode_key'     => 'slot_mode_' . $slot_num,
			'default_mode' => 'text',
		);
	}

	return $map;
}

/**
 * ④追加スロットのフォーム name / id（{%3%} 以降）。
 *
 * @return array<int, array{input_id: string, input_name: string}>
 */
function crb_extra_slot_form_rows() {
	$rows = array();
	foreach ( crb_extra_slot_storage_map() as $index => $meta ) {
		$config_key            = (string) ( $meta['config_key'] ?? '' );
		$rows[ (int) $index ] = array(
			'input_id'   => 'crb-css-' . str_replace( '_', '-', $config_key ),
			'input_name' => 'css_' . $config_key,
		);
	}
	return $rows;
}

/**
 * POST から追加スロット欄を収集（crb_sanitize_css_config 用の生配列）。
 *
 * @return array<string, string>
 */
function crb_collect_extra_slot_fields_from_post() {
	$out = array();
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST ) || ! is_array( $_POST ) ) {
		return $out;
	}
	foreach ( crb_extra_slot_storage_map() as $meta ) {
		$config_key = (string) ( $meta['config_key'] ?? '' );
		$mode_key   = (string) ( $meta['mode_key'] ?? '' );
		if ( '' !== $config_key ) {
			$post_key            = 'css_' . $config_key;
			$out[ $config_key ] = isset( $_POST[ $post_key ] ) ? wp_unslash( $_POST[ $post_key ] ) : '';
		}
		if ( '' !== $mode_key ) {
			$post_mode          = 'css_' . $mode_key;
			$out[ $mode_key ] = isset( $_POST[ $post_mode ] ) ? wp_unslash( $_POST[ $post_mode ] ) : '';
		}
	}
	return $out;
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
 * ③候補行の mode を⑤プレビューと同じスロット抽出ルールへ（admin.js mapCandidateModeToSlotMode と同等）。
 *
 * @param string $candidate_mode 候補表の mode（text, href, attr:src など）。
 * @return array{mode: string, attr?: string}
 */
function crb_candidate_mode_to_slot_rule( $candidate_mode ) {
	$mode = trim( (string) $candidate_mode );
	if ( 'html' === $mode ) {
		return array( 'mode' => 'html' );
	}
	if ( 'href' === $mode || 'attr:href' === $mode ) {
		return array( 'mode' => 'href' );
	}
	if ( 'text' === $mode ) {
		return array( 'mode' => 'text' );
	}
	if ( 'src' === $mode ) {
		return array( 'mode' => 'src' );
	}
	if ( 0 === strpos( $mode, 'attr:' ) ) {
		$attr = strtolower( substr( $mode, 5 ) );
		if ( 'href' === $attr ) {
			return array( 'mode' => 'href' );
		}
		if (
			false !== strpos( $attr, 'src' )
			|| false !== strpos( $attr, 'lazy' )
		) {
			return array( 'mode' => 'src' );
		}
		return array(
			'mode' => 'attr',
			'attr' => crb_sanitize_css_attr_name( $attr ),
		);
	}
	return array( 'mode' => 'text' );
}

/**
 * 1件ブロック内でセレクタ＋取り方で値を抽出（③候補・⑤プレビュー・RSS 共通）。
 *
 * @param DOMXPath                            $xpath          XPath.
 * @param DOMElement                          $context        1件ブロック（または範囲）。
 * @param string                              $selector       CSS セレクタ。
 * @param string                              $candidate_mode 候補表の mode。
 * @param string                              $base_url       Base URL.
 * @param Custom_RSS_Builder_Css_Extractor|null $extractor    Extractor（省略時は新規）。
 * @return string
 */
function crb_extract_slot_value( DOMXPath $xpath, DOMElement $context, $selector, $candidate_mode, $base_url = '', $extractor = null ) {
	$selector = trim( (string) $selector );
	if ( '' === $selector ) {
		return '';
	}
	if ( ! ( $extractor instanceof Custom_RSS_Builder_Css_Extractor ) ) {
		$extractor = new Custom_RSS_Builder_Css_Extractor();
	}
	$rule = crb_candidate_mode_to_slot_rule( $candidate_mode );
	$rule['selector'] = $selector;
	return $extractor->probe_slot_in_context( $xpath, $context, $rule, $base_url );
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
	$limit = crb_get_effective_slot_count();
	while ( count( $schema ) < $limit ) {
		$schema[] = array( 'is_url' => false, 'is_html' => false );
	}
	return array_slice( $schema, 0, $limit );
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
	// レガシー自動注入の判定・テスト用のみ。管理画面からは参照しないこと。
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
