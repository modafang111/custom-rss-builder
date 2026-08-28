<?php
/**
 * CSV からのフィード一括登録（主要設定を列で指定可）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strip UTF-8 BOM if present.
 *
 * @param string $raw Raw text.
 * @return string
 */
function crb_csv_strip_bom( $raw ) {
	$raw = (string) $raw;
	if ( 0 === strncmp( $raw, "\xEF\xBB\xBF", 3 ) ) {
		return substr( $raw, 3 );
	}
	return $raw;
}

/**
 * Canonical CSV column keys (optional except url).
 *
 * @return array<int, string>
 */
function crb_csv_known_column_keys() {
	$keys = array(
		'name',
		'url',
		'category',
		'scope_selector',
		'item_selector',
		'link_selector',
		'title_mode',
		'title_attr',
		'title_selector',
		'map_title',
		'map_link',
		'map_description',
		'map_date',
		'import_enabled',
		'import_schedule_hours',
		'import_post_status',
		'import_post_type',
		'import_post_title_template',
		'import_content_template',
		'import_author',
		'import_tag_fixed',
		'import_tag_slots',
		'ai_enabled',
		'ai_instruction',
		'ai_model',
		'ai_slots',
		'link_rewrite_rules',
	);

	if ( function_exists( 'crb_extra_slot_storage_map' ) ) {
		foreach ( crb_extra_slot_storage_map() as $meta ) {
			$config_key = (string) ( $meta['config_key'] ?? '' );
			$mode_key   = (string) ( $meta['mode_key'] ?? '' );
			if ( '' !== $config_key ) {
				$keys[] = $config_key;
			}
			if ( '' !== $mode_key ) {
				$keys[] = $mode_key;
			}
		}
	}

	return array_values( array_unique( $keys ) );
}

/**
 * Sample header row for UI / copy-paste.
 *
 * @return string
 */
function crb_csv_sample_header_line() {
	return 'url,category,name,scope_selector,item_selector,link_selector,title_mode,title_attr,title_selector,summary_selector,image_selector,import_enabled,import_schedule_hours,import_post_status,import_post_title_template,import_content_template,map_title,map_link,map_description,ai_enabled,ai_instruction,ai_slots,link_rewrite_rules';
}

/**
 * Human-readable column help for admin UI.
 *
 * @return array<int, string>
 */
function crb_csv_column_help_lines() {
	return array(
		__( '必須: url', 'custom-rss-builder' ),
		__( '基本: name / category（名前またはID）', 'custom-rss-builder' ),
		__( 'CSS: scope_selector, item_selector, link_selector, title_mode, title_attr, title_selector', 'custom-rss-builder' ),
		__( '追加スロット: summary_selector, image_selector, category_selector, review_title_selector, review_body_selector, author_selector, slot_selector_9… / slot_mode_3…', 'custom-rss-builder' ),
		__( '割当: map_title, map_link, map_description, map_date（スロット番号 0始まり。date は -1 でなし）', 'custom-rss-builder' ),
		__( '取り込み: import_enabled, import_schedule_hours, import_post_status, import_post_type, import_post_title_template, import_content_template, import_author, import_tag_fixed, import_tag_slots', 'custom-rss-builder' ),
		__( 'AI: ai_enabled, ai_instruction, ai_model, ai_slots（カンマ区切り）', 'custom-rss-builder' ),
		__( 'リンク変換: link_rewrite_rules（JSON配列。例: [{"source_prefix":"^https?://…","target_prefix":"https://…","use_regex":1}]）', 'custom-rss-builder' ),
		__( '空欄の列はデフォルトのまま。本文など改行を含む値は "..." で囲んでください。', 'custom-rss-builder' ),
	);
}

/**
 * Normalize header cell for alias lookup.
 *
 * @param string $header Header cell.
 * @return string
 */
function crb_csv_compact_header( $header ) {
	$key = strtolower( trim( (string) $header ) );
	$key = preg_replace( '/^css_?/', '', $key );
	$key = str_replace( array( ' ', '_', '-', '　' ), '', $key );
	return is_string( $key ) ? $key : '';
}

/**
 * Alias map (compact header → canonical key).
 *
 * @return array<string, string>
 */
function crb_csv_header_alias_map() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}

	$map = array(
		'url'                       => 'url',
		'uri'                       => 'url',
		'link'                      => 'url',
		'対象url'                   => 'url',
		'フィードurl'               => 'url',
		'name'                      => 'name',
		'title'                     => 'name',
		'フィード名'                => 'name',
		'名前'                      => 'name',
		'category'                  => 'category',
		'cat'                       => 'category',
		'カテゴリー'                => 'category',
		'カテゴリ'                  => 'category',
		'importcategory'            => 'category',
		'importcategoryid'          => 'category',
		'scopeselector'             => 'scope_selector',
		'scope'                     => 'scope_selector',
		'範囲'                      => 'scope_selector',
		'itemselector'              => 'item_selector',
		'item'                      => 'item_selector',
		'1件'                       => 'item_selector',
		'linkselector'              => 'link_selector',
		'titlemode'                 => 'title_mode',
		'titleattr'                 => 'title_attr',
		'titleselector'             => 'title_selector',
		'summaryselector'           => 'summary_selector',
		'imageselector'             => 'image_selector',
		'categoryselector'          => 'category_selector',
		'reviewtitleselector'       => 'review_title_selector',
		'reviewbodyselector'        => 'review_body_selector',
		'authorselector'            => 'author_selector',
		'maptitle'                  => 'map_title',
		'maplink'                   => 'map_link',
		'mapdescription'            => 'map_description',
		'mapdate'                   => 'map_date',
		'importenabled'             => 'import_enabled',
		'取り込み'                  => 'import_enabled',
		'importschedulehours'       => 'import_schedule_hours',
		'schedulehours'             => 'import_schedule_hours',
		'間隔'                      => 'import_schedule_hours',
		'importpoststatus'          => 'import_post_status',
		'poststatus'                => 'import_post_status',
		'importposttype'            => 'import_post_type',
		'posttype'                  => 'import_post_type',
		'importposttitletemplate'   => 'import_post_title_template',
		'posttitletemplate'         => 'import_post_title_template',
		'投稿タイトル'              => 'import_post_title_template',
		'importcontenttemplate'     => 'import_content_template',
		'contenttemplate'           => 'import_content_template',
		'投稿本文'                  => 'import_content_template',
		'importauthor'              => 'import_author',
		'author'                    => 'import_author',
		'authorid'                  => 'import_author',
		'importtagfixed'            => 'import_tag_fixed',
		'tagfixed'                  => 'import_tag_fixed',
		'tags'                      => 'import_tag_fixed',
		'importtagslots'            => 'import_tag_slots',
		'tagslots'                  => 'import_tag_slots',
		'aienabled'                 => 'ai_enabled',
		'aiinstruction'             => 'ai_instruction',
		'aimodel'                   => 'ai_model',
		'aislots'                   => 'ai_slots',
		'linkrewriterules'          => 'link_rewrite_rules',
		'linkrewrite'               => 'link_rewrite_rules',
	);

	if ( function_exists( 'crb_extra_slot_storage_map' ) ) {
		foreach ( crb_extra_slot_storage_map() as $meta ) {
			$config_key = (string) ( $meta['config_key'] ?? '' );
			$mode_key   = (string) ( $meta['mode_key'] ?? '' );
			if ( '' !== $config_key ) {
				$map[ str_replace( array( '_', '-' ), '', strtolower( $config_key ) ) ] = $config_key;
			}
			if ( '' !== $mode_key ) {
				$map[ str_replace( array( '_', '-' ), '', strtolower( $mode_key ) ) ] = $mode_key;
			}
		}
	}

	return $map;
}

/**
 * Map CSV header cell to canonical column key.
 *
 * @param string $header Header cell.
 * @return string Empty if unknown.
 */
function crb_csv_normalize_header_key( $header ) {
	$trimmed = trim( (string) $header );
	if ( '' === $trimmed ) {
		return '';
	}

	$jp_exact = array(
		'URL'        => 'url',
		'Url'        => 'url',
		'対象URL'    => 'url',
		'カテゴリー' => 'category',
		'カテゴリ'   => 'category',
		'名前'       => 'name',
		'フィード名' => 'name',
		'投稿タイトル' => 'import_post_title_template',
		'投稿本文'   => 'import_content_template',
	);
	if ( isset( $jp_exact[ $trimmed ] ) ) {
		return $jp_exact[ $trimmed ];
	}

	$compact = crb_csv_compact_header( $trimmed );
	$aliases = crb_csv_header_alias_map();
	if ( isset( $aliases[ $compact ] ) ) {
		return $aliases[ $compact ];
	}

	if ( preg_match( '/^slotselector(\d+)$/', $compact, $m ) ) {
		return 'slot_selector_' . (int) $m[1];
	}
	if ( preg_match( '/^slotmode(\d+)$/', $compact, $m ) ) {
		return 'slot_mode_' . (int) $m[1];
	}

	$known = array_flip( crb_csv_known_column_keys() );
	if ( isset( $known[ $trimmed ] ) ) {
		return $trimmed;
	}
	$snake = strtolower( str_replace( array( ' ', '-' ), '_', $trimmed ) );
	if ( isset( $known[ $snake ] ) ) {
		return $snake;
	}

	return '';
}

/**
 * Build column index map from header row.
 *
 * @param array<int, string> $header_row Header cells.
 * @return array<string, int>|WP_Error
 */
function crb_csv_header_column_map( array $header_row ) {
	$map = array();
	foreach ( $header_row as $index => $cell ) {
		$key = crb_csv_normalize_header_key( $cell );
		if ( '' === $key || isset( $map[ $key ] ) ) {
			continue;
		}
		$map[ $key ] = (int) $index;
	}
	if ( ! isset( $map['url'] ) ) {
		return new WP_Error(
			'crb_csv_missing_url_header',
			__( 'CSV のヘッダーに url（または URL）列が必要です。', 'custom-rss-builder' )
		);
	}
	return $map;
}

/**
 * Parse feed CSV text into row payloads.
 *
 * @param string $raw CSV text.
 * @return array{rows: array<int, array<string, mixed>>, errors: array<int, array{line:int, message:string}>}|WP_Error
 */
function crb_parse_feed_csv( $raw ) {
	$raw = crb_csv_strip_bom( (string) $raw );
	$raw = trim( $raw );
	if ( '' === $raw ) {
		return new WP_Error( 'crb_csv_empty', __( 'CSV が空です。', 'custom-rss-builder' ) );
	}

	$stream = fopen( 'php://temp', 'r+' );
	if ( false === $stream ) {
		return new WP_Error( 'crb_csv_stream', __( 'CSV を読み込めませんでした。', 'custom-rss-builder' ) );
	}
	fwrite( $stream, $raw );
	rewind( $stream );

	$header = fgetcsv( $stream );
	if ( ! is_array( $header ) || empty( $header ) ) {
		fclose( $stream );
		return new WP_Error( 'crb_csv_header', __( 'CSV のヘッダー行を読めませんでした。', 'custom-rss-builder' ) );
	}

	$columns = crb_csv_header_column_map( $header );
	if ( is_wp_error( $columns ) ) {
		fclose( $stream );
		return $columns;
	}

	$rows   = array();
	$errors = array();
	$line   = 1;

	while ( true ) {
		$cells = fgetcsv( $stream );
		if ( false === $cells ) {
			break;
		}
		++$line;

		if ( ! is_array( $cells ) ) {
			continue;
		}

		$joined = trim( implode( '', array_map( 'strval', $cells ) ) );
		if ( '' === $joined ) {
			continue;
		}

		$row = array( 'line' => $line );
		foreach ( $columns as $key => $index ) {
			$row[ $key ] = isset( $cells[ $index ] ) ? trim( (string) $cells[ $index ] ) : '';
		}

		if ( '' === (string) ( $row['url'] ?? '' ) ) {
			$errors[] = array(
				'line'    => $line,
				'message' => __( 'URL が空です。', 'custom-rss-builder' ),
			);
			continue;
		}

		$rows[] = $row;
	}

	fclose( $stream );

	if ( empty( $rows ) && empty( $errors ) ) {
		return new WP_Error( 'crb_csv_no_rows', __( '登録対象の行がありません。', 'custom-rss-builder' ) );
	}

	return array(
		'rows'   => $rows,
		'errors' => $errors,
	);
}

/**
 * @param mixed $value Raw cell.
 * @return bool|null Null when empty / unspecified.
 */
function crb_csv_parse_optional_bool( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return null;
	}
	$lower = strtolower( $value );
	if ( in_array( $lower, array( '1', 'true', 'yes', 'y', 'on', '有効', 'はい' ), true ) ) {
		return true;
	}
	if ( in_array( $lower, array( '0', 'false', 'no', 'n', 'off', '無効', 'いいえ' ), true ) ) {
		return false;
	}
	return null;
}

/**
 * Parse comma-separated integers.
 *
 * @param string $value Raw cell.
 * @return array<int, int>
 */
function crb_csv_parse_int_list( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return array();
	}
	$parts = preg_split( '/[\s,;|]+/', $value );
	$out   = array();
	if ( ! is_array( $parts ) ) {
		return $out;
	}
	foreach ( $parts as $part ) {
		$part = trim( (string) $part );
		if ( '' === $part || ! preg_match( '/^-?\d+$/', $part ) ) {
			continue;
		}
		$out[] = (int) $part;
	}
	return array_values( array_unique( $out ) );
}

/**
 * Resolve category cell (ID or name) to term ID.
 *
 * @param string $value Raw category cell.
 * @return int|WP_Error
 */
function crb_resolve_import_category_from_csv( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 0;
	}

	if ( preg_match( '/^\d+$/', $value ) ) {
		$term_id = function_exists( 'crb_sanitize_import_category_id' )
			? crb_sanitize_import_category_id( (int) $value )
			: ( term_exists( (int) $value, 'category' ) ? (int) $value : 0 );
		if ( $term_id <= 0 ) {
			return new WP_Error(
				'crb_csv_category_id',
				sprintf(
					/* translators: %s: category id */
					__( 'カテゴリー ID「%s」が見つかりません。', 'custom-rss-builder' ),
					$value
				)
			);
		}
		return $term_id;
	}

	$term = get_term_by( 'name', $value, 'category' );
	if ( ! $term || is_wp_error( $term ) ) {
		return new WP_Error(
			'crb_csv_category_name',
			sprintf(
				/* translators: %s: category name */
				__( 'カテゴリー「%s」が見つかりません。', 'custom-rss-builder' ),
				$value
			)
		);
	}

	return (int) $term->term_id;
}

/**
 * Resolve author cell (ID or login) to user ID.
 *
 * @param string $value Raw author cell.
 * @return int|WP_Error
 */
function crb_resolve_import_author_from_csv( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 0;
	}

	if ( preg_match( '/^\d+$/', $value ) ) {
		$user = get_user_by( 'id', (int) $value );
		if ( ! $user ) {
			return new WP_Error(
				'crb_csv_author_id',
				sprintf(
					/* translators: %s: user id */
					__( '著者 ID「%s」が見つかりません。', 'custom-rss-builder' ),
					$value
				)
			);
		}
		return (int) $user->ID;
	}

	$user = get_user_by( 'login', $value );
	if ( ! $user ) {
		$user = get_user_by( 'slug', $value );
	}
	if ( ! $user ) {
		return new WP_Error(
			'crb_csv_author_login',
			sprintf(
				/* translators: %s: login */
				__( '著者「%s」が見つかりません。', 'custom-rss-builder' ),
				$value
			)
		);
	}
	return (int) $user->ID;
}

/**
 * Build tag_sources from fixed tags + slot numbers cells.
 *
 * @param string $fixed_cell Comma-separated tag names or IDs.
 * @param string $slots_cell Comma-separated slot numbers (1-based {%n%}).
 * @return array{sources: array<int, array<string, mixed>>, error: WP_Error|null}
 */
function crb_csv_build_tag_sources( $fixed_cell, $slots_cell ) {
	$sources = array();
	$fixed_cell = trim( (string) $fixed_cell );
	if ( '' !== $fixed_cell ) {
		$parts = preg_split( '/\s*,\s*/', $fixed_cell );
		if ( is_array( $parts ) ) {
			foreach ( $parts as $part ) {
				$part = trim( (string) $part );
				if ( '' === $part ) {
					continue;
				}
				if ( preg_match( '/^\d+$/', $part ) ) {
					$term_id = function_exists( 'crb_sanitize_import_tag_id' )
						? crb_sanitize_import_tag_id( (int) $part )
						: ( term_exists( (int) $part, 'post_tag' ) ? (int) $part : 0 );
					if ( $term_id <= 0 ) {
						return array(
							'sources' => array(),
							'error'   => new WP_Error(
								'crb_csv_tag_id',
								sprintf(
									/* translators: %s: tag id */
									__( 'タグ ID「%s」が見つかりません。', 'custom-rss-builder' ),
									$part
								)
							),
						);
					}
					$sources[] = array(
						'type'    => 'fixed',
						'term_id' => $term_id,
					);
					continue;
				}
				$term = get_term_by( 'name', $part, 'post_tag' );
				if ( ! $term || is_wp_error( $term ) ) {
					return array(
						'sources' => array(),
						'error'   => new WP_Error(
							'crb_csv_tag_name',
							sprintf(
								/* translators: %s: tag name */
								__( 'タグ「%s」が見つかりません。', 'custom-rss-builder' ),
								$part
							)
						),
					);
				}
				$sources[] = array(
					'type'    => 'fixed',
					'term_id' => (int) $term->term_id,
				);
			}
		}
	}

	foreach ( crb_csv_parse_int_list( $slots_cell ) as $slot ) {
		if ( $slot <= 0 ) {
			continue;
		}
		$sources[] = array(
			'type' => 'slot',
			'slot' => $slot,
		);
	}

	return array(
		'sources' => $sources,
		'error'   => null,
	);
}

/**
 * Parse link_rewrite_rules JSON cell.
 *
 * @param string $raw JSON text.
 * @return array<string, mixed>|WP_Error
 */
function crb_csv_parse_link_rewrite_rules( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return function_exists( 'crb_default_link_rewrite_settings' )
			? crb_default_link_rewrite_settings()
			: array();
	}

	$decoded = json_decode( $raw, true );
	if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
		return new WP_Error(
			'crb_csv_link_rewrite_json',
			__( 'link_rewrite_rules の JSON が不正です。', 'custom-rss-builder' )
		);
	}

	if ( isset( $decoded['rules'] ) && is_array( $decoded ) ) {
		$settings = $decoded;
	} elseif ( is_array( $decoded ) ) {
		$settings = array(
			'rules' => $decoded,
		);
	} else {
		return new WP_Error(
			'crb_csv_link_rewrite_shape',
			__( 'link_rewrite_rules はルール配列または {rules:[…]} 形式の JSON にしてください。', 'custom-rss-builder' )
		);
	}

	if ( function_exists( 'crb_sanitize_link_rewrite_settings' ) ) {
		$settings = crb_sanitize_link_rewrite_settings( $settings );
	}

	$rules = isset( $settings['rules'] ) && is_array( $settings['rules'] ) ? $settings['rules'] : array();
	if ( ! empty( $rules ) ) {
		$settings['enabled'] = true;
		if ( ! isset( $settings['all_slots'] ) ) {
			$settings['all_slots'] = true;
		}
	}

	return $settings;
}

/**
 * Auto feed name from URL host (fallback path / generic).
 *
 * @param string $url Source URL.
 * @return string
 */
function crb_csv_auto_feed_name( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( is_string( $host ) && '' !== $host ) {
		return $host;
	}
	$path = wp_parse_url( $url, PHP_URL_PATH );
	if ( is_string( $path ) && '' !== trim( $path, '/' ) ) {
		return trim( $path, '/' );
	}
	return __( 'CSV登録フィード', 'custom-rss-builder' );
}

/**
 * Build CSS config from CSV row fields.
 *
 * @param array<string, mixed> $row Parsed row.
 * @return array<string, string>
 */
function crb_csv_build_css_from_row( array $row ) {
	$css = function_exists( 'crb_empty_css_config' ) ? crb_empty_css_config() : array();

	$simple = array(
		'scope_selector',
		'item_selector',
		'link_selector',
		'title_mode',
		'title_attr',
		'title_selector',
	);
	foreach ( $simple as $key ) {
		if ( isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
			$css[ $key ] = (string) $row[ $key ];
		}
	}

	if ( function_exists( 'crb_extra_slot_storage_map' ) ) {
		foreach ( crb_extra_slot_storage_map() as $meta ) {
			$config_key = (string) ( $meta['config_key'] ?? '' );
			$mode_key   = (string) ( $meta['mode_key'] ?? '' );
			if ( '' !== $config_key && isset( $row[ $config_key ] ) && '' !== trim( (string) $row[ $config_key ] ) ) {
				$css[ $config_key ] = (string) $row[ $config_key ];
			}
			if ( '' !== $mode_key && isset( $row[ $mode_key ] ) && '' !== trim( (string) $row[ $mode_key ] ) ) {
				$css[ $mode_key ] = (string) $row[ $mode_key ];
			}
		}
	}

	return function_exists( 'crb_sanitize_css_config' ) ? crb_sanitize_css_config( $css ) : $css;
}

/**
 * Create one feed from a parsed CSV row (full settings supported).
 *
 * @param array<string, mixed> $row Parsed row.
 * @return int|WP_Error New feed ID.
 */
function crb_create_feed_from_csv_row( array $row ) {
	$url_raw = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
	$url     = esc_url_raw( $url_raw );
	if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
		return new WP_Error(
			'crb_csv_invalid_url',
			sprintf(
				/* translators: %s: url */
				__( '無効な URL です: %s', 'custom-rss-builder' ),
				$url_raw
			)
		);
	}

	$category = crb_resolve_import_category_from_csv( isset( $row['category'] ) ? (string) $row['category'] : '' );
	if ( is_wp_error( $category ) ) {
		return $category;
	}

	$author = 0;
	if ( isset( $row['import_author'] ) && '' !== trim( (string) $row['import_author'] ) ) {
		$author = crb_resolve_import_author_from_csv( (string) $row['import_author'] );
		if ( is_wp_error( $author ) ) {
			return $author;
		}
	}

	$name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';
	if ( '' === $name ) {
		$name = crb_csv_auto_feed_name( $url );
	}
	$name = sanitize_text_field( $name );
	if ( '' === $name ) {
		$name = crb_csv_auto_feed_name( $url );
	}

	if ( ! function_exists( 'crb_plugin' ) ) {
		return new WP_Error( 'crb_csv_no_plugin', __( 'プラグインが初期化されていません。', 'custom-rss-builder' ) );
	}

	$plugin = crb_plugin();
	if ( ! is_object( $plugin ) || ! isset( $plugin->feed_manager ) ) {
		return new WP_Error( 'crb_csv_no_manager', __( 'フィード管理を利用できません。', 'custom-rss-builder' ) );
	}

	/** @var Custom_RSS_Builder_Feed_Manager $manager */
	$manager  = $plugin->feed_manager;
	$defaults = $manager->default_import_settings();
	$mapping  = function_exists( 'crb_default_rss_mapping' ) ? crb_default_rss_mapping() : array(
		'title'       => 0,
		'link'        => 1,
		'description' => 2,
		'date'        => -1,
	);

	foreach ( array( 'title' => 'map_title', 'link' => 'map_link', 'description' => 'map_description', 'date' => 'map_date' ) as $mk => $ck ) {
		if ( isset( $row[ $ck ] ) && '' !== trim( (string) $row[ $ck ] ) ) {
			$mapping[ $mk ] = (int) $row[ $ck ];
		}
	}

	$import = $defaults;
	$import['category_id'] = (int) $category;
	$import['author_id']   = (int) $author;

	$enabled = crb_csv_parse_optional_bool( $row['import_enabled'] ?? '' );
	if ( null !== $enabled ) {
		$import['enabled'] = $enabled;
	}

	if ( isset( $row['import_schedule_hours'] ) && '' !== trim( (string) $row['import_schedule_hours'] ) ) {
		$hours = (int) $row['import_schedule_hours'];
		$import['schedule'] = function_exists( 'crb_import_schedule_slug_from_hours' )
			? crb_import_schedule_slug_from_hours( $hours )
			: ( $hours > 0 ? 'hours_' . $hours : 'off' );
		if ( $hours > 0 ) {
			$import['enabled'] = true;
		}
	}
	if ( empty( $import['enabled'] ) ) {
		$import['schedule'] = 'off';
	}

	if ( isset( $row['import_post_status'] ) && '' !== trim( (string) $row['import_post_status'] ) ) {
		$import['post_status'] = sanitize_key( (string) $row['import_post_status'] );
	}
	if ( isset( $row['import_post_type'] ) && '' !== trim( (string) $row['import_post_type'] ) ) {
		$import['post_type'] = sanitize_key( (string) $row['import_post_type'] );
	}
	if ( isset( $row['import_post_title_template'] ) ) {
		$import['post_title_template'] = function_exists( 'crb_sanitize_template' )
			? crb_sanitize_template( (string) $row['import_post_title_template'] )
			: (string) $row['import_post_title_template'];
	}
	if ( isset( $row['import_content_template'] ) ) {
		$import['content_template'] = function_exists( 'crb_sanitize_import_content_template' )
			? crb_sanitize_import_content_template( (string) $row['import_content_template'] )
			: (string) $row['import_content_template'];
	}

	$tag_pack = crb_csv_build_tag_sources(
		isset( $row['import_tag_fixed'] ) ? (string) $row['import_tag_fixed'] : '',
		isset( $row['import_tag_slots'] ) ? (string) $row['import_tag_slots'] : ''
	);
	if ( $tag_pack['error'] instanceof WP_Error ) {
		return $tag_pack['error'];
	}
	if ( ! empty( $tag_pack['sources'] ) ) {
		$import['tag_sources'] = function_exists( 'crb_sanitize_import_tag_sources' )
			? crb_sanitize_import_tag_sources( $tag_pack['sources'], true )
			: $tag_pack['sources'];
	}

	$ai = function_exists( 'crb_default_ai_transform_settings' )
		? crb_default_ai_transform_settings()
		: array();
	$ai_enabled = crb_csv_parse_optional_bool( $row['ai_enabled'] ?? '' );
	if ( null !== $ai_enabled ) {
		$ai['enabled'] = $ai_enabled;
	}
	if ( isset( $row['ai_instruction'] ) && '' !== trim( (string) $row['ai_instruction'] ) ) {
		$ai['instruction'] = (string) $row['ai_instruction'];
		if ( null === $ai_enabled ) {
			$ai['enabled'] = true;
		}
	}
	if ( isset( $row['ai_model'] ) && '' !== trim( (string) $row['ai_model'] ) ) {
		$ai['model'] = sanitize_key( (string) $row['ai_model'] );
	}
	$ai_slots = crb_csv_parse_int_list( isset( $row['ai_slots'] ) ? (string) $row['ai_slots'] : '' );
	if ( ! empty( $ai_slots ) ) {
		$ai['slots'] = $ai_slots;
	}
	if ( function_exists( 'crb_sanitize_ai_transform_settings' ) ) {
		$ai = crb_sanitize_ai_transform_settings( $ai, true );
	}

	$link_rewrite = function_exists( 'crb_default_link_rewrite_settings' )
		? crb_default_link_rewrite_settings()
		: array();
	if ( isset( $row['link_rewrite_rules'] ) && '' !== trim( (string) $row['link_rewrite_rules'] ) ) {
		$parsed_lr = crb_csv_parse_link_rewrite_rules( (string) $row['link_rewrite_rules'] );
		if ( is_wp_error( $parsed_lr ) ) {
			return $parsed_lr;
		}
		$link_rewrite = $parsed_lr;
	}

	$payload = array(
		'id'              => 0,
		'name'            => $name,
		'url'             => $url,
		'extraction_mode' => 'css',
		'css'             => crb_csv_build_css_from_row( $row ),
		'mapping'         => $mapping,
		'import'          => $import,
		'ai'              => $ai,
		'link_rewrite'    => $link_rewrite,
	);

	if ( function_exists( 'crb_license_apply_feed_limits' ) ) {
		$payload = crb_license_apply_feed_limits( $payload );
	}

	$feed_id = $manager->save_feed( $payload );
	if ( $feed_id <= 0 ) {
		return new WP_Error( 'crb_csv_save_failed', __( 'フィードの保存に失敗しました。', 'custom-rss-builder' ) );
	}

	return (int) $feed_id;
}

/**
 * Import multiple CSV rows into feeds.
 *
 * @param array<int, array<string, mixed>> $rows Parsed rows.
 * @return array{
 *   created:int,
 *   skipped:int,
 *   license_stopped:int,
 *   created_ids:array<int,int>,
 *   errors:array<int, array{line:int, message:string}>
 * }
 */
function crb_import_feeds_from_csv_rows( array $rows ) {
	$result = array(
		'created'         => 0,
		'skipped'         => 0,
		'license_stopped' => 0,
		'created_ids'     => array(),
		'errors'          => array(),
	);

	$total = count( $rows );
	for ( $i = 0; $i < $total; $i++ ) {
		$row  = $rows[ $i ];
		$line = isset( $row['line'] ) ? (int) $row['line'] : ( $i + 2 );

		if ( function_exists( 'crb_license_can' ) && ! crb_license_can( 'create_feed' ) ) {
			$result['license_stopped'] = $total - $i;
			$result['errors'][]        = array(
				'line'    => $line,
				'message' => function_exists( 'crb_license_denied_message' )
					? crb_license_denied_message( 'create_feed' )
					: __( 'フィード作成上限に達したため、残りをスキップしました。', 'custom-rss-builder' ),
			);
			break;
		}

		$created = crb_create_feed_from_csv_row( $row );
		if ( is_wp_error( $created ) ) {
			++$result['skipped'];
			$result['errors'][] = array(
				'line'    => $line,
				'message' => $created->get_error_message(),
			);
			continue;
		}

		++$result['created'];
		$result['created_ids'][] = (int) $created;
	}

	return $result;
}

/**
 * Current feed quota snapshot for the active license.
 *
 * @return array{current:int, limit:int, remaining:int, usable:bool, plan:string}
 */
function crb_csv_feed_quota() {
	$current   = function_exists( 'crb_license_feed_count' ) ? (int) crb_license_feed_count() : 0;
	$usable    = true;
	$plan      = '';
	$limit     = 0;
	$unlimited = false;

	if ( function_exists( 'crb_license_get_state' ) ) {
		$state  = crb_license_get_state();
		$usable = ! empty( $state['usable'] );
		$plan   = isset( $state['plan'] ) ? (string) $state['plan'] : '';
	}
	if ( function_exists( 'crb_license_feed_limit_is_unlimited' ) ) {
		$unlimited = crb_license_feed_limit_is_unlimited( $plan );
	}
	if ( function_exists( 'crb_license_feed_limit_for_plan' ) ) {
		$limit = (int) crb_license_feed_limit_for_plan( $plan );
	}

	return array(
		'current'   => $current,
		'limit'     => $limit,
		'remaining' => $unlimited ? PHP_INT_MAX : max( 0, $limit - $current ),
		'unlimited' => $unlimited,
		'usable'    => (bool) $usable,
		'plan'      => $plan,
	);
}

/**
 * Ordered columns for CSV export (id は参照用。再インポート時は無視されます).
 *
 * @return array<int, string>
 */
function crb_csv_export_column_keys() {
	$keys = array_merge( array( 'id' ), crb_csv_known_column_keys() );
	return array_values( array_unique( $keys ) );
}

/**
 * Category cell for export (prefer term name).
 *
 * @param int $category_id Term ID.
 * @return string
 */
function crb_csv_export_category_cell( $category_id ) {
	$category_id = (int) $category_id;
	if ( $category_id <= 0 ) {
		return '';
	}
	$term = get_term( $category_id, 'category' );
	if ( $term && ! is_wp_error( $term ) && isset( $term->name ) ) {
		return (string) $term->name;
	}
	return (string) $category_id;
}

/**
 * Author cell for export (prefer login).
 *
 * @param int $author_id User ID.
 * @return string
 */
function crb_csv_export_author_cell( $author_id ) {
	$author_id = (int) $author_id;
	if ( $author_id <= 0 ) {
		return '';
	}
	$user = get_user_by( 'id', $author_id );
	if ( $user && ! empty( $user->user_login ) ) {
		return (string) $user->user_login;
	}
	return (string) $author_id;
}

/**
 * Split tag_sources into fixed names and slot numbers for CSV cells.
 *
 * @param array<int, array<string, mixed>> $sources Tag sources.
 * @return array{fixed:string, slots:string}
 */
function crb_csv_export_tag_source_cells( array $sources ) {
	$fixed = array();
	$slots = array();
	if ( function_exists( 'crb_sanitize_import_tag_sources' ) ) {
		$sources = crb_sanitize_import_tag_sources( $sources, false );
	}
	foreach ( $sources as $source ) {
		if ( ! is_array( $source ) ) {
			continue;
		}
		$type = sanitize_key( (string) ( $source['type'] ?? '' ) );
		if ( 'fixed' === $type ) {
			$term_id = (int) ( $source['term_id'] ?? 0 );
			if ( $term_id <= 0 ) {
				continue;
			}
			$term = get_term( $term_id, 'post_tag' );
			if ( $term && ! is_wp_error( $term ) && isset( $term->name ) ) {
				$fixed[] = (string) $term->name;
			} else {
				$fixed[] = (string) $term_id;
			}
			continue;
		}
		if ( 'slot' === $type ) {
			$slot = (int) ( $source['slot'] ?? 0 );
			if ( $slot > 0 ) {
				$slots[] = (string) $slot;
			}
		}
	}
	return array(
		'fixed' => implode( ',', $fixed ),
		'slots' => implode( ',', $slots ),
	);
}

/**
 * Convert one feed to a CSV associative row (import-compatible keys + id).
 *
 * @param array<string, mixed> $feed Feed row.
 * @return array<string, string>
 */
function crb_feed_to_csv_row( array $feed ) {
	$columns = crb_csv_export_column_keys();
	$row     = array();
	foreach ( $columns as $key ) {
		$row[ $key ] = '';
	}

	$row['id']   = isset( $feed['id'] ) ? (string) (int) $feed['id'] : '';
	$row['name'] = isset( $feed['name'] ) ? (string) $feed['name'] : '';
	$row['url']  = isset( $feed['url'] ) ? (string) $feed['url'] : '';

	$css = isset( $feed['css'] ) && is_array( $feed['css'] ) ? $feed['css'] : array();
	if ( empty( $css ) && function_exists( 'crb_empty_css_config' ) ) {
		$css = crb_empty_css_config();
	}
	foreach ( $css as $css_key => $css_val ) {
		$key = (string) $css_key;
		if ( array_key_exists( $key, $row ) ) {
			$row[ $key ] = (string) $css_val;
		}
	}

	$mapping = isset( $feed['mapping'] ) && is_array( $feed['mapping'] ) ? $feed['mapping'] : array();
	$row['map_title']       = isset( $mapping['title'] ) ? (string) (int) $mapping['title'] : '';
	$row['map_link']        = isset( $mapping['link'] ) ? (string) (int) $mapping['link'] : '';
	$row['map_description'] = isset( $mapping['description'] ) ? (string) (int) $mapping['description'] : '';
	$row['map_date']        = isset( $mapping['date'] ) ? (string) (int) $mapping['date'] : '';

	$import = array();
	if ( function_exists( 'crb_plugin' ) ) {
		$plugin = crb_plugin();
		if ( is_object( $plugin ) && isset( $plugin->feed_manager ) ) {
			$import = $plugin->feed_manager->get_import_settings( $feed );
		}
	}
	if ( empty( $import ) && isset( $feed['import'] ) && is_array( $feed['import'] ) ) {
		$import = $feed['import'];
	}

	$row['category'] = crb_csv_export_category_cell( (int) ( $import['category_id'] ?? 0 ) );
	$row['import_enabled'] = ! empty( $import['enabled'] ) ? '1' : '0';
	$hours = 0;
	if ( function_exists( 'crb_import_schedule_hours_from_slug' ) ) {
		$hours = (int) crb_import_schedule_hours_from_slug( $import['schedule'] ?? 'off' );
	}
	$row['import_schedule_hours']        = (string) $hours;
	$row['import_post_status']           = (string) ( $import['post_status'] ?? '' );
	$row['import_post_type']             = (string) ( $import['post_type'] ?? '' );
	$row['import_post_title_template']   = (string) ( $import['post_title_template'] ?? '' );
	$row['import_content_template']      = (string) ( $import['content_template'] ?? '' );
	$row['import_author']                = crb_csv_export_author_cell( (int) ( $import['author_id'] ?? 0 ) );

	$tag_cells = crb_csv_export_tag_source_cells(
		isset( $import['tag_sources'] ) && is_array( $import['tag_sources'] ) ? $import['tag_sources'] : array()
	);
	$row['import_tag_fixed'] = $tag_cells['fixed'];
	$row['import_tag_slots'] = $tag_cells['slots'];

	$ai = function_exists( 'crb_get_feed_ai_settings' )
		? crb_get_feed_ai_settings( $feed )
		: ( isset( $feed['ai'] ) && is_array( $feed['ai'] ) ? $feed['ai'] : array() );
	$row['ai_enabled']     = ! empty( $ai['enabled'] ) ? '1' : '0';
	$row['ai_instruction'] = (string) ( $ai['instruction'] ?? '' );
	$row['ai_model']       = (string) ( $ai['model'] ?? '' );
	$ai_slots              = isset( $ai['slots'] ) && is_array( $ai['slots'] ) ? $ai['slots'] : array();
	$row['ai_slots']       = implode( ',', array_map( 'strval', array_map( 'intval', $ai_slots ) ) );

	$link_rewrite = isset( $feed['link_rewrite'] ) && is_array( $feed['link_rewrite'] ) ? $feed['link_rewrite'] : array();
	if ( function_exists( 'crb_sanitize_link_rewrite_settings' ) ) {
		$link_rewrite = crb_sanitize_link_rewrite_settings( $link_rewrite );
	}
	$rules = isset( $link_rewrite['rules'] ) && is_array( $link_rewrite['rules'] ) ? $link_rewrite['rules'] : array();
	if ( ! empty( $rules ) ) {
		$encoded = wp_json_encode( $rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$row['link_rewrite_rules'] = is_string( $encoded ) ? $encoded : '';
	}

	return $row;
}

/**
 * Build CSV text for all feeds (UTF-8 with BOM).
 *
 * @param array<int, array<string, mixed>>|null $feeds Feeds or null to load all.
 * @return string
 */
function crb_export_feeds_csv( $feeds = null ) {
	if ( null === $feeds ) {
		$feeds = array();
		if ( function_exists( 'crb_plugin' ) ) {
			$plugin = crb_plugin();
			if ( is_object( $plugin ) && isset( $plugin->feed_manager ) ) {
				$loaded = $plugin->feed_manager->get_feeds();
				$feeds  = is_array( $loaded ) ? $loaded : array();
			}
		}
	}

	$columns = crb_csv_export_column_keys();
	$stream  = fopen( 'php://temp', 'r+' );
	if ( false === $stream ) {
		return '';
	}

	fputcsv( $stream, $columns );
	foreach ( $feeds as $feed ) {
		if ( ! is_array( $feed ) ) {
			continue;
		}
		$data = crb_feed_to_csv_row( $feed );
		$line = array();
		foreach ( $columns as $key ) {
			$line[] = isset( $data[ $key ] ) ? (string) $data[ $key ] : '';
		}
		fputcsv( $stream, $line );
	}

	rewind( $stream );
	$csv = stream_get_contents( $stream );
	fclose( $stream );

	if ( ! is_string( $csv ) ) {
		$csv = '';
	}

	return "\xEF\xBB\xBF" . $csv;
}

/**
 * Admin URL that downloads the feeds CSV.
 *
 * @return string
 */
function crb_csv_export_download_url() {
	return wp_nonce_url(
		add_query_arg(
			array(
				'page'           => 'custom-rss-builder',
				'crb_csv_export' => '1',
			),
			admin_url( 'admin.php' )
		),
		'crb_csv_export'
	);
}

