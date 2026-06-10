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
 * 現在プランで許可される最短間隔（時間）。
 *
 * @return int
 */
function crb_import_schedule_min_hours_for_plan() {
	if ( function_exists( 'crb_license_import_schedule_min_hours' ) ) {
		return (int) crb_license_import_schedule_min_hours();
	}
	return (int) CRB_IMPORT_SCHEDULE_MIN_HOURS;
}

/**
 * 保存値・実行時に適用する有効間隔（0=オフ。1〜23 は無料などで繰り上げ）。
 *
 * @param int $hours Raw hours.
 * @return int
 */
function crb_import_schedule_effective_hours( $hours ) {
	$hours = crb_import_schedule_sanitize_hours( $hours );
	if ( $hours <= 0 ) {
		return 0;
	}

	$min = crb_import_schedule_min_hours_for_plan();
	if ( $hours < $min ) {
		return $min;
	}

	return $hours;
}

/**
 * @param string $schedule Stored schedule slug.
 * @return int Effective hours (0 = off).
 */
function crb_import_schedule_effective_hours_from_slug( $schedule ) {
	return crb_import_schedule_effective_hours(
		crb_import_schedule_hours_from_slug( $schedule )
	);
}

/**
 * 無料プラン（利用可）か。
 *
 * @return bool
 */
function crb_import_schedule_is_free_usable_plan() {
	if ( ! function_exists( 'crb_license_get_state' ) ) {
		return false;
	}
	$state = crb_license_get_state();
	return ! empty( $state['usable'] ) && 'free' === sanitize_key( (string) ( $state['plan'] ?? '' ) );
}

/**
 * フィード保存 POST から自動取り込み間隔（時間）を取得。0=オフ。
 *
 * @return int
 */
function crb_import_schedule_hours_from_request() {
	if ( crb_import_schedule_is_free_usable_plan() ) {
		if ( empty( $_POST['import_schedule_auto'] ) ) {
			return 0;
		}
		return defined( 'CRB_LICENSE_FREE_IMPORT_SCHEDULE_MIN_HOURS' )
			? (int) CRB_LICENSE_FREE_IMPORT_SCHEDULE_MIN_HOURS
			: 24;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	return crb_import_schedule_sanitize_hours( wp_unslash( $_POST['import_schedule_hours'] ?? 0 ) );
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
	$hours = crb_import_schedule_effective_hours( $hours );
	$min   = crb_import_schedule_min_hours_for_plan();
	if ( $hours < $min ) {
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
		$hours = crb_import_schedule_effective_hours( (int) $matches[1] );
		$min   = crb_import_schedule_min_hours_for_plan();
		if ( $hours < $min ) {
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
	$hours = crb_import_schedule_effective_hours_from_slug( $schedule );
	$min   = crb_import_schedule_min_hours_for_plan();
	if ( $hours < $min ) {
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
	$hours = crb_import_schedule_effective_hours_from_slug( (string) ( $import['schedule'] ?? 'off' ) );
	return $hours >= crb_import_schedule_min_hours_for_plan();
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
 * 取り込み先タグ ID 一覧（保存用。UI は単一選択だが配列で保持）。
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
	$ids = array_values( array_unique( $ids ) );
	if ( count( $ids ) > 1 ) {
		$ids = array( $ids[0] );
	}
	return $ids;
}

/**
 * POST から取り込みタグ ID を取得。
 *
 * @param mixed $raw $_POST['import_tag_id'] 等。
 * @return array<int, int>
 */
function crb_import_tag_ids_from_request( $raw ) {
	return crb_sanitize_import_tag_ids( $raw );
}

/**
 * 取り込み設定用タグ選択（名前付きドロップダウン）。
 *
 * @param mixed $selected_ids Selected term IDs（先頭1件を表示）。
 */
function crb_render_import_tags_field( $selected_ids ) {
	$selected_ids = crb_sanitize_import_tag_ids( $selected_ids );
	$selected_id  = ! empty( $selected_ids ) ? (int) $selected_ids[0] : 0;

	$terms = get_terms(
		array(
			'taxonomy'   => 'post_tag',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'number'     => 0,
		)
	);

	if ( is_wp_error( $terms ) ) {
		echo '<p class="description">' . esc_html( $terms->get_error_message() ) . '</p>';
		return;
	}

	echo '<select name="import_tag_id" id="crb-import-tag-id" class="crb-import-tag-select">';
	printf(
		'<option value="0"%s>%s</option>',
		selected( 0, $selected_id, false ),
		esc_html__( '— 指定しない —', 'custom-rss-builder' )
	);

	if ( empty( $terms ) ) {
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'タグがありません。', 'custom-rss-builder' ) . '</p>';
		return;
	}

	foreach ( $terms as $term ) {
		$id = (int) $term->term_id;
		printf(
			'<option value="%d"%s>%s</option>',
			$id,
			selected( $selected_id, $id, false ),
			esc_html( $term->name )
		);
	}
	echo '</select>';
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
