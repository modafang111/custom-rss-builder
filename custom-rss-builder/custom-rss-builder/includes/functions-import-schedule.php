<?php
/**
 * 投稿取り込みの WP-Cron 間隔（時間数入力）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 自動取り込みの最小間隔（時間） */
define( 'CRB_IMPORT_SCHEDULE_MIN_HOURS', 1 );

/** 自動取り込みの最大間隔（時間） */
define( 'CRB_IMPORT_SCHEDULE_MAX_HOURS', 168 );

/**
 * 現在プランで許可される自動取り込みの最短間隔（時間）。
 *
 * @return int
 */
function crb_import_schedule_plan_min_hours() {
	if ( function_exists( 'crb_license_import_schedule_min_hours' ) ) {
		return (int) crb_license_import_schedule_min_hours();
	}
	return (int) CRB_IMPORT_SCHEDULE_MIN_HOURS;
}

/**
 * @param string $schedule Raw or legacy schedule slug.
 * @return string Normalized slug.
 */
function crb_import_schedule_normalize( $schedule ) {
	$schedule = sanitize_key( (string) $schedule );

	$legacy = array(
		'hourly'               => 'crb_every_1_hours',
		'twicedaily'           => 'crb_every_12_hours',
		'daily'                => 'crb_every_24_hours',
		'crb_weekly'           => 'crb_every_168_hours',
		'crb_every_15_minutes' => 'off',
		'crb_every_30_minutes' => 'off',
	);

	if ( isset( $legacy[ $schedule ] ) ) {
		return $legacy[ $schedule ];
	}

	return $schedule;
}

/**
 * @param mixed $hours Hour interval from form.
 * @return int 0 = off.
 */
function crb_import_schedule_sanitize_hours( $hours ) {
	$hours = (int) $hours;
	if ( $hours < 0 ) {
		return 0;
	}
	if ( $hours > CRB_IMPORT_SCHEDULE_MAX_HOURS ) {
		return CRB_IMPORT_SCHEDULE_MAX_HOURS;
	}
	return $hours;
}

/**
 * @param int $hours Hour interval (0 = off).
 * @return string Schedule slug for storage.
 */
function crb_import_schedule_slug_from_hours( $hours ) {
	$hours    = crb_import_schedule_sanitize_hours( $hours );
	$plan_min = crb_import_schedule_plan_min_hours();
	if ( $hours < $plan_min ) {
		return 'off';
	}
	return 'crb_every_' . $hours . '_hours';
}

/**
 * @param string $schedule Stored schedule slug.
 * @return int Hours (0 = off).
 */
function crb_import_schedule_hours_from_slug( $schedule ) {
	$schedule = crb_import_schedule_normalize( $schedule );
	if ( 'off' === $schedule ) {
		return 0;
	}
	if ( preg_match( '/^crb_every_(\d+)_hours$/', $schedule, $matches ) ) {
		return crb_import_schedule_sanitize_hours( (int) $matches[1] );
	}
	return 0;
}

/**
 * @param mixed $raw Raw schedule slug.
 * @return string
 */
function crb_sanitize_import_schedule( $raw ) {
	$raw = crb_import_schedule_normalize( $raw );
	if ( 'off' === $raw ) {
		return 'off';
	}
	if ( preg_match( '/^crb_every_(\d+)_hours$/', $raw, $matches ) ) {
		$hours    = crb_import_schedule_sanitize_hours( (int) $matches[1] );
		$plan_min = crb_import_schedule_plan_min_hours();
		if ( $hours < $plan_min ) {
			return 'off';
		}
		return 'crb_every_' . $hours . '_hours';
	}
	return 'off';
}

/**
 * WP-Cron の recurrence 名（off のときは空文字）。
 *
 * @param string $schedule Schedule slug.
 * @return string
 */
function crb_import_schedule_recurrence( $schedule ) {
	$schedule = crb_sanitize_import_schedule( $schedule );
	return 'off' === $schedule ? '' : $schedule;
}

/**
 * @param string $schedule Schedule slug.
 * @return string
 */
function crb_import_schedule_label( $schedule ) {
	$hours    = crb_import_schedule_hours_from_slug( $schedule );
	$plan_min = crb_import_schedule_plan_min_hours();
	if ( $hours < $plan_min ) {
		return __( 'オフ（手動のみ）', 'custom-rss-builder' );
	}
	return sprintf(
		/* translators: %d: hours between imports */
		__( '%d時間ごと', 'custom-rss-builder' ),
		$hours
	);
}

/**
 * @param array<string, mixed> $import Import settings.
 * @return bool
 */
function crb_import_schedule_is_active( $import ) {
	if ( empty( $import['enabled'] ) ) {
		return false;
	}
	return crb_import_schedule_hours_from_slug( (string) ( $import['schedule'] ?? 'off' ) ) >= crb_import_schedule_plan_min_hours();
}

/**
 * @param array<string, mixed> $schedules WP cron schedules.
 * @return array<string, mixed>
 */
function crb_import_schedule_filter_cron_schedules( $schedules ) {
	if ( ! is_array( $schedules ) ) {
		$schedules = array();
	}

	for ( $hours = CRB_IMPORT_SCHEDULE_MIN_HOURS; $hours <= CRB_IMPORT_SCHEDULE_MAX_HOURS; $hours++ ) {
		$slug               = 'crb_every_' . $hours . '_hours';
		$schedules[ $slug ] = array(
			'interval' => $hours * HOUR_IN_SECONDS,
			'display'  => sprintf(
				/* translators: %d: hours */
				__( '%d時間ごと（Custom RSS Builder）', 'custom-rss-builder' ),
				$hours
			),
		);
	}

	return $schedules;
}

/**
 * @param int $feed_id Feed ID.
 * @return int|false Unix timestamp or false.
 */
function crb_import_schedule_next_run( $feed_id ) {
	$hook = class_exists( 'Custom_RSS_Builder_Import_Scheduler' )
		? Custom_RSS_Builder_Import_Scheduler::HOOK
		: 'crb_scheduled_feed_import';
	return wp_next_scheduled( $hook, array( (int) $feed_id ) );
}

/**
 * @param int $feed_id Feed ID.
 * @return string Human-readable next run or empty.
 */
function crb_import_schedule_next_run_label( $feed_id ) {
	$ts = crb_import_schedule_next_run( $feed_id );
	if ( ! $ts ) {
		return '';
	}
	return wp_date( 'Y-m-d H:i:s', $ts );
}

/**
 * 取り込み実行ログをフィードに保存。
 *
 * @param int                           $feed_id Feed ID.
 * @param string                        $source  manual|cron|loopback|url.
 * @param array<string,mixed>|WP_Error  $result  Import result.
 */
function crb_import_run_record( $feed_id, $source, $result ) {
	if ( ! function_exists( 'crb_plugin' ) ) {
		return;
	}

	$feed_id = (int) $feed_id;
	if ( $feed_id <= 0 ) {
		return;
	}

	$source = sanitize_key( (string) $source );
	if ( '' === $source ) {
		$source = 'manual';
	}

	$at = current_time( 'mysql' );

	if ( is_wp_error( $result ) ) {
		$run = array(
			'at'      => $at,
			'source'  => $source,
			'status'  => 'error',
			'created' => 0,
			'skipped' => 0,
			'message' => $result->get_error_message(),
		);
	} else {
		$created = (int) ( $result['created'] ?? 0 );
		$skipped = (int) ( $result['skipped'] ?? 0 );
		$errors  = isset( $result['errors'] ) && is_array( $result['errors'] ) ? $result['errors'] : array();
		$status  = 'success';
		$message = '';

		if ( ! empty( $errors ) ) {
			$status  = $created > 0 ? 'partial' : 'error';
			$message = (string) $errors[0];
		} elseif ( 0 === $created && 0 === $skipped ) {
			$message = __( '取り込み対象がありませんでした。', 'custom-rss-builder' );
		}

		$run = array(
			'at'      => $at,
			'source'  => $source,
			'status'  => $status,
			'created' => $created,
			'skipped' => $skipped,
			'message' => $message,
		);
	}

	crb_plugin()->feed_manager->touch_feed(
		$feed_id,
		array(
			'last_imported'   => $at,
			'last_import_run' => $run,
		)
	);
}

/**
 * @param string $source Source slug.
 * @return string
 */
function crb_import_run_source_label( $source ) {
	switch ( sanitize_key( (string) $source ) ) {
		case 'manual':
			return __( '手動', 'custom-rss-builder' );
		case 'cron':
			return __( 'WP-Cron', 'custom-rss-builder' );
		case 'loopback':
			return __( '自己アクセス', 'custom-rss-builder' );
		case 'url':
			return __( '実行 URL', 'custom-rss-builder' );
		default:
			return '' !== (string) $source ? (string) $source : '—';
	}
}

/**
 * @param string $status Status slug.
 * @return string
 */
function crb_import_run_status_label( $status ) {
	switch ( sanitize_key( (string) $status ) ) {
		case 'success':
			return __( '成功', 'custom-rss-builder' );
		case 'partial':
			return __( '一部成功', 'custom-rss-builder' );
		case 'error':
			return __( '失敗', 'custom-rss-builder' );
		default:
			return '' !== (string) $status ? (string) $status : '—';
	}
}

/**
 * @param array<string, mixed> $run Stored run log.
 * @return string
 */
function crb_import_run_summary( array $run ) {
	$created = (int) ( $run['created'] ?? 0 );
	$skipped = (int) ( $run['skipped'] ?? 0 );
	$summary = sprintf(
		/* translators: 1: created count, 2: skipped count */
		__( '新規 %1$d 件 / スキップ %2$d 件', 'custom-rss-builder' ),
		$created,
		$skipped
	);
	$message = trim( (string) ( $run['message'] ?? '' ) );
	if ( '' !== $message ) {
		$summary .= ' — ' . $message;
	}
	return $summary;
}

/**
 * 取り込み先カテゴリー ID（存在しない場合は 0）。
 *
 * @param mixed $category_id Raw value.
 * @return int
 */
function crb_sanitize_import_category_id( $category_id ) {
	$category_id = max( 0, (int) $category_id );
	if ( $category_id > 0 && ! term_exists( $category_id, 'category' ) ) {
		return 0;
	}
	return $category_id;
}

/**
 * 取り込み設定用カテゴリー選択（名前付きドロップダウン）。
 *
 * @param int $selected_id Selected term ID (0 = none).
 */
function crb_render_import_category_dropdown( $selected_id ) {
	$selected_id = max( 0, (int) $selected_id );

	if ( ! function_exists( 'wp_dropdown_categories' ) ) {
		printf(
			'<input name="import_category_id" id="crb-import-category-id" type="number" min="0" class="small-text" value="%s">',
			esc_attr( (string) $selected_id )
		);
		return;
	}

	wp_dropdown_categories(
		array(
			'show_option_none'  => __( '— 指定しない —', 'custom-rss-builder' ),
			'option_none_value' => '0',
			'selected'          => $selected_id,
			'name'              => 'import_category_id',
			'id'                => 'crb-import-category-id',
			'hide_empty'        => 0,
			'hierarchical'      => 1,
			'orderby'           => 'name',
			'class'             => 'crb-import-category-select',
		)
	);
}

/**
 * 取り込み先タグ ID（存在しない場合は 0）。
 *
 * @param mixed $tag_id Raw value.
 * @return int
 */
function crb_sanitize_import_tag_id( $tag_id ) {
	$tag_id = max( 0, (int) $tag_id );
	if ( $tag_id > 0 && ! term_exists( $tag_id, 'post_tag' ) ) {
		return 0;
	}
	return $tag_id;
}

/**
 * 取り込み先タグ ID 一覧（レガシー移行用）。
 *
 * @param mixed $raw Raw value (array or scalar).
 * @return array<int, int>
 */
function crb_sanitize_import_tag_ids( $raw ) {
	if ( ! is_array( $raw ) ) {
		$id = crb_sanitize_import_tag_id( $raw );
		return $id > 0 ? array( $id ) : array();
	}
	$ids = array();
	foreach ( $raw as $id ) {
		$id = crb_sanitize_import_tag_id( $id );
		if ( $id > 0 ) {
			$ids[] = $id;
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * 取り込みタグ（tag_sources）が Pro で利用可能か。
 *
 * @return bool
 */
function crb_import_tag_sources_can_use() {
	return function_exists( 'crb_license_can' ) && crb_license_can( 'import_tag_sources' );
}

/**
 * 旧 tag_ids / tag_slot から tag_sources へ移行。
 *
 * @param array<string, mixed> $import Import settings.
 * @return array<int, array<string, int|string>>
 */
function crb_import_tag_sources_from_legacy( array $import ) {
	if ( ! empty( $import['tag_sources'] ) && is_array( $import['tag_sources'] ) ) {
		return $import['tag_sources'];
	}

	$sources = array();
	$tag_ids = isset( $import['tag_ids'] ) ? crb_sanitize_import_tag_ids( $import['tag_ids'] ) : array();
	foreach ( $tag_ids as $term_id ) {
		$sources[] = array(
			'type'    => 'fixed',
			'term_id' => (int) $term_id,
		);
	}

	$slot = isset( $import['tag_slot'] ) ? crb_sanitize_import_tag_slot( $import['tag_slot'] ) : 0;
	if ( $slot > 0 ) {
		$sources[] = array(
			'type' => 'slot',
			'slot' => (int) $slot,
		);
	}

	return $sources;
}

/**
 * 取り込み時にタグへ変換するスロット番号（0=無効、1={%1%}…）。
 *
 * @param mixed $raw Raw value.
 * @return int
 */
function crb_sanitize_import_tag_slot( $raw ) {
	$slot = max( 0, (int) $raw );
	if ( $slot <= 0 ) {
		return 0;
	}
	$max_slot = function_exists( 'crb_get_effective_slot_count' )
		? (int) crb_get_effective_slot_count()
		: (int) CRB_RECORD_SLOT_COUNT;
	return min( $slot, max( 1, $max_slot ) );
}

/**
 * tag_sources 配列を正規化。
 *
 * @param mixed $raw              Raw sources or legacy import row.
 * @param bool  $enforce_license  false のとき表示用にライセンスチェックを省略。
 * @return array<int, array{type:string,term_id?:int,slot?:int}>
 */
function crb_sanitize_import_tag_sources( $raw, $enforce_license = true ) {
	if ( $enforce_license && ! crb_import_tag_sources_can_use() ) {
		return array();
	}

	if ( is_array( $raw ) && isset( $raw['tag_ids'] ) ) {
		$raw = crb_import_tag_sources_from_legacy( $raw );
	} elseif ( ! is_array( $raw ) ) {
		$raw = array();
	}

	$sources    = array();
	$seen_fixed = array();
	$seen_slots = array();

	foreach ( $raw as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$type = sanitize_key( (string) ( $item['type'] ?? '' ) );
		if ( 'fixed' === $type ) {
			$term_id = crb_sanitize_import_tag_id( $item['term_id'] ?? 0 );
			if ( $term_id > 0 && ! isset( $seen_fixed[ $term_id ] ) ) {
				$sources[]                 = array(
					'type'    => 'fixed',
					'term_id' => $term_id,
				);
				$seen_fixed[ $term_id ] = true;
			}
			continue;
		}
		if ( 'slot' === $type ) {
			$slot = crb_sanitize_import_tag_slot( $item['slot'] ?? 0 );
			if ( $slot > 0 && ! isset( $seen_slots[ $slot ] ) ) {
				$sources[]               = array(
					'type' => 'slot',
					'slot' => $slot,
				);
				$seen_slots[ $slot ] = true;
			}
		}
	}

	return $sources;
}

/**
 * POST から tag_sources を取得。
 *
 * @param mixed $fixed_raw Fixed term IDs.
 * @param mixed $slot_raw  1-based slot numbers.
 * @return array<int, array{type:string,term_id?:int,slot?:int}>
 */
function crb_import_tag_sources_from_request( $fixed_raw, $slot_raw ) {
	if ( ! crb_import_tag_sources_can_use() ) {
		return array();
	}

	$sources = array();
	$fixed   = is_array( $fixed_raw ) ? $fixed_raw : array();
	foreach ( $fixed as $term_id ) {
		$term_id = crb_sanitize_import_tag_id( $term_id );
		if ( $term_id > 0 ) {
			$sources[] = array(
				'type'    => 'fixed',
				'term_id' => $term_id,
			);
		}
	}

	$slots = is_array( $slot_raw ) ? $slot_raw : array();
	foreach ( $slots as $slot ) {
		$slot = crb_sanitize_import_tag_slot( $slot );
		if ( $slot > 0 ) {
			$sources[] = array(
				'type' => 'slot',
				'slot' => $slot,
			);
		}
	}

	return crb_sanitize_import_tag_sources( $sources, true );
}

/**
 * POST からスロット→タグ番号を取得（レガシー互換）。
 *
 * @param mixed $raw $_POST['import_tag_slot'] 等。
 * @return int
 */
function crb_import_tag_slot_from_request( $raw ) {
	return crb_sanitize_import_tag_slot( $raw );
}

/**
 * 抽出行の指定スロットからタグ名候補を取得。
 *
 * @param array<int|string, string> $row         Slot row.
 * @param int                       $slot_number 1-based slot ({%8%}=8).
 * @return array<int, string>
 */
function crb_import_tag_names_from_row( array $row, $slot_number ) {
	$slot_number = crb_sanitize_import_tag_slot( $slot_number );
	if ( $slot_number <= 0 ) {
		return array();
	}
	if ( function_exists( 'crb_is_named_record' ) && crb_is_named_record( $row ) ) {
		$row = function_exists( 'crb_record_to_slot_row' ) ? crb_record_to_slot_row( $row ) : $row;
	}
	if ( ! function_exists( 'crb_is_slot_indexed_row' ) || ! crb_is_slot_indexed_row( $row ) ) {
		return array();
	}

	$index = $slot_number - 1;
	if ( ! isset( $row[ $index ] ) ) {
		return array();
	}

	$raw = trim( wp_strip_all_tags( (string) $row[ $index ] ) );
	if ( '' === $raw ) {
		return array();
	}

	// 中黒（・）は DLsite 作品形式名の一部になりうるため、区切りに含めない。
	$parts = preg_split( '/[,、|\/]+/u', $raw );
	if ( ! is_array( $parts ) ) {
		$parts = array( $raw );
	}

	$names = array();
	foreach ( $parts as $part ) {
		$name = sanitize_text_field( trim( (string) $part ) );
		if ( '' === $name ) {
			continue;
		}
		if ( function_exists( 'mb_substr' ) ) {
			$name = mb_substr( $name, 0, 200 );
		} else {
			$name = substr( $name, 0, 200 );
		}
		$names[] = $name;
	}

	return array_values( array_unique( $names ) );
}

/**
 * tag_sources から取り込み時に付与するタグ名一覧を生成。
 *
 * @param array<int, array{type:string,term_id?:int,slot?:int}> $sources tag_sources.
 * @param array<int, string>                                   $row     Extracted row.
 * @return array<int, string>
 */
function crb_import_tag_names_from_sources( array $sources, array $row ) {
	$tag_names = array();

	foreach ( crb_sanitize_import_tag_sources( $sources, false ) as $source ) {
		$type = (string) ( $source['type'] ?? '' );
		if ( 'fixed' === $type ) {
			$term = get_term( (int) ( $source['term_id'] ?? 0 ), 'post_tag' );
			if ( $term && ! is_wp_error( $term ) && '' !== trim( (string) $term->name ) ) {
				$tag_names[] = (string) $term->name;
			}
			continue;
		}
		if ( 'slot' === $type ) {
			$tag_names = array_merge(
				$tag_names,
				crb_import_tag_names_from_row( $row, (int) ( $source['slot'] ?? 0 ) )
			);
		}
	}

	return array_values( array_unique( array_filter( $tag_names ) ) );
}

/**
 * tag_sources を投稿に付与（Pro 専用）。
 *
 * @param int                  $post_id Post ID.
 * @param array<string, mixed> $import  Import settings.
 * @param array<int, string>   $row     Extracted row.
 */
function crb_apply_import_tags_to_post( $post_id, array $import, array $row ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || ! crb_import_tag_sources_can_use() ) {
		return;
	}

	$sources = isset( $import['tag_sources'] ) && is_array( $import['tag_sources'] )
		? crb_sanitize_import_tag_sources( $import['tag_sources'], true )
		: crb_sanitize_import_tag_sources(
			crb_import_tag_sources_from_legacy( $import ),
			true
		);

	$tag_names = crb_import_tag_names_from_sources( $sources, $row );
	if ( empty( $tag_names ) ) {
		return;
	}

	wp_set_post_tags( $post_id, $tag_names, false );
}

/**
 * 取り込みタグ設定 UI（固定タグ＋スロット由来、Pro 専用）。
 *
 * @param array<int, array{type:string,term_id?:int,slot?:int}> $tag_sources Selected sources.
 * @param array<string, mixed>                                 $feed_values Feed form values (css 等).
 */
function crb_render_import_tag_sources_field( array $tag_sources, array $feed_values ) {
	$can         = crb_import_tag_sources_can_use();
	$tag_sources = crb_sanitize_import_tag_sources( $tag_sources, false );
	$fixed_ids   = array();
	$slot_nums   = array();

	foreach ( $tag_sources as $source ) {
		if ( 'fixed' === (string) ( $source['type'] ?? '' ) ) {
			$fixed_ids[] = (int) ( $source['term_id'] ?? 0 );
		} elseif ( 'slot' === (string) ( $source['type'] ?? '' ) ) {
			$slot_nums[] = (int) ( $source['slot'] ?? 0 );
		}
	}

	$max_index = function_exists( 'crb_license_get_max_slot_index' )
		? (int) crb_license_get_max_slot_index()
		: ( ( defined( 'CRB_RECORD_SLOT_COUNT' ) ? (int) CRB_RECORD_SLOT_COUNT : 20 ) - 1 );

	if ( ! $can ) {
		echo '<div class="crb-import-tag-sources crb-import-tag-sources--locked">';
		echo '<p class="description">';
		echo esc_html(
			function_exists( 'crb_license_denied_message' )
				? crb_license_denied_message( 'import_tag_sources' )
				: __( '取り込みタグ（固定・スロット由来）は Pro プラン専用です。', 'custom-rss-builder' )
		);
		echo '</p>';
		echo '</div>';
		return;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'post_tag',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'number'     => 0,
		)
	);

	echo '<div class="crb-import-tag-sources">';

	echo '<fieldset class="crb-import-tag-sources__group">';
	echo '<legend>' . esc_html__( '固定タグ（常に付与）', 'custom-rss-builder' ) . '</legend>';
	if ( is_wp_error( $terms ) ) {
		echo '<p class="description">' . esc_html( $terms->get_error_message() ) . '</p>';
	} elseif ( empty( $terms ) ) {
		echo '<p class="description">' . esc_html__( 'タグがありません。', 'custom-rss-builder' ) . '</p>';
	} else {
		echo '<div class="crb-import-tag-sources__choices">';
		foreach ( $terms as $term ) {
			$term_id = (int) $term->term_id;
			printf(
				'<label class="crb-import-tag-sources__choice"><input type="checkbox" name="import_tag_sources_fixed[]" value="%1$d"%2$s> %3$s</label>',
				$term_id,
				checked( in_array( $term_id, $fixed_ids, true ), true, false ),
				esc_html( (string) $term->name )
			);
		}
		echo '</div>';
	}
	echo '</fieldset>';

	echo '<fieldset class="crb-import-tag-sources__group">';
	echo '<legend>' . esc_html__( 'スロットから生成（記事ごと）', 'custom-rss-builder' ) . '</legend>';
	echo '<div class="crb-import-tag-sources__choices">';
	for ( $slot_index = 0; $slot_index <= $max_index; $slot_index++ ) {
		$slot_number = $slot_index + 1;
		$token       = function_exists( 'crb_slot_token' ) ? crb_slot_token( $slot_index ) : '{%' . $slot_number . '}';
		printf(
			'<label class="crb-import-tag-sources__choice"><input type="checkbox" name="import_tag_sources_slot[]" value="%1$d"%2$s> <code>%3$s</code></label>',
			$slot_number,
			checked( in_array( $slot_number, $slot_nums, true ), true, false ),
			esc_html( $token )
		);
	}
	echo '</div>';
	echo '<p class="description">' . esc_html__( '選択したスロットの値をタグ化します。カンマ・読点・|・/ で複数値が入っている場合のみ分割します（中黒は作品名の一部として1タグにします）。', 'custom-rss-builder' ) . '</p>';
	echo '</fieldset>';

	echo '</div>';
}

/**
 * 取り込み投稿者 ID（存在しない場合は 0）。
 *
 * @param mixed $author_id Raw value.
 * @return int
 */
function crb_sanitize_import_author_id( $author_id ) {
	$author_id = max( 0, (int) $author_id );
	if ( $author_id > 0 && ! get_userdata( $author_id ) ) {
		return 0;
	}
	return $author_id;
}

/**
 * 取り込み設定用投稿者選択（表示名ドロップダウン）。
 *
 * @param int $selected_id Selected user ID (0 = plugin default on import).
 */
function crb_render_import_author_dropdown( $selected_id ) {
	$selected_id = crb_sanitize_import_author_id( $selected_id );

	if ( ! function_exists( 'wp_dropdown_users' ) ) {
		printf(
			'<input name="import_author_id" id="crb-import-author-id" type="number" min="0" class="small-text" value="%s">',
			esc_attr( (string) $selected_id )
		);
		return;
	}

	wp_dropdown_users(
		array(
			'show_option_none'  => __( '— 既定 —', 'custom-rss-builder' ),
			'option_none_value' => '0',
			'name'              => 'import_author_id',
			'id'                => 'crb-import-author-id',
			'selected'          => $selected_id,
			'class'             => 'crb-import-author-select',
			'who'               => 'authors',
			'orderby'           => 'display_name',
		)
	);
}
