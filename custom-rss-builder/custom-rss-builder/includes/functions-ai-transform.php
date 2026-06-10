<?php
/**
 * Pro AI テキスト変換（フィールド単位・Gemini BYOK）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_AI_TRANSFORM_DEFAULT_MODEL', 'gemini-2.5-flash' );
define( 'CRB_AI_TRANSFORM_MAX_INSTRUCTION', 2000 );
define( 'CRB_AI_TRANSFORM_MAX_INPUT_CHARS', 12000 );
define( 'CRB_AI_TRANSFORM_PREVIEW_ROW_LIMIT', 3 );

/**
 * @param string $model Model slug.
 * @return string
 */
function crb_ai_sanitize_model_slug( $model ) {
	return strtolower( preg_replace( '/[^a-z0-9._-]/', '', (string) $model ) );
}

/**
 * @return array<string, mixed>
 */
function crb_default_ai_transform_settings() {
	return array(
		'enabled'     => false,
		'instruction' => '',
		'slots'       => array(),
		'model'       => CRB_AI_TRANSFORM_DEFAULT_MODEL,
	);
}

/**
 * @param array<string, mixed> $feed Feed row.
 * @return array<string, mixed>
 */
function crb_get_feed_ai_settings( array $feed ) {
	$defaults = crb_default_ai_transform_settings();
	$raw      = isset( $feed['ai'] ) && is_array( $feed['ai'] ) ? $feed['ai'] : array();
	return array_merge( $defaults, crb_sanitize_ai_transform_settings( $raw, false ) );
}

/**
 * @param mixed $raw     Raw settings.
 * @param bool  $enforce_license Strip enable when not licensed.
 * @return array<string, mixed>
 */
function crb_sanitize_ai_transform_settings( $raw, $enforce_license = true ) {
	$defaults = crb_default_ai_transform_settings();
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}

	$allowed_models = array_keys( crb_ai_transform_model_options() );
	$model          = crb_ai_sanitize_model_slug( (string) ( $raw['model'] ?? $defaults['model'] ) );
	$model          = crb_ai_transform_resolve_model_slug( $model );
	if ( ! in_array( $model, $allowed_models, true ) ) {
		$model = CRB_AI_TRANSFORM_DEFAULT_MODEL;
	}

	$slots = array();
	if ( ! empty( $raw['slots'] ) && is_array( $raw['slots'] ) ) {
		$max_index = function_exists( 'crb_license_get_max_slot_index' )
			? (int) crb_license_get_max_slot_index()
			: ( ( defined( 'CRB_RECORD_SLOT_COUNT' ) ? (int) CRB_RECORD_SLOT_COUNT : 20 ) - 1 );
		foreach ( $raw['slots'] as $slot ) {
			$idx = (int) $slot;
			if ( $idx < 0 || $idx > $max_index ) {
				continue;
			}
			$slots[] = $idx;
		}
		$slots = array_values( array_unique( $slots ) );
	}

	$enabled = ! empty( $raw['enabled'] );
	if ( $enforce_license && ( ! function_exists( 'crb_license_can' ) || ! crb_license_can( 'ai_transform' ) ) ) {
		$enabled = false;
	}

	$instruction = sanitize_textarea_field( (string) ( $raw['instruction'] ?? '' ) );
	if ( function_exists( 'mb_substr' ) ) {
		$instruction = mb_substr( $instruction, 0, CRB_AI_TRANSFORM_MAX_INSTRUCTION );
	} else {
		$instruction = substr( $instruction, 0, CRB_AI_TRANSFORM_MAX_INSTRUCTION );
	}

	return array(
		'enabled'     => $enabled,
		'instruction' => $instruction,
		'slots'       => $slots,
		'model'       => $model,
	);
}

/**
 * POST から AI 変換設定を収集。
 *
 * @return array<string, mixed>
 */
function crb_collect_ai_transform_from_request() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$slots = isset( $_POST['ai_transform_slots'] ) && is_array( $_POST['ai_transform_slots'] )
		? array_map( 'intval', wp_unslash( $_POST['ai_transform_slots'] ) )
		: array();

	return crb_sanitize_ai_transform_settings(
		array(
			'enabled'     => ! empty( $_POST['ai_transform_enabled'] ),
			'instruction' => isset( $_POST['ai_transform_instruction'] ) ? wp_unslash( $_POST['ai_transform_instruction'] ) : '',
			'slots'       => $slots,
			'model'       => isset( $_POST['ai_transform_model'] ) ? wp_unslash( $_POST['ai_transform_model'] ) : CRB_AI_TRANSFORM_DEFAULT_MODEL,
		)
	);
}

/**
 * @return bool
 */
function crb_ai_transform_is_configured_globally() {
	return function_exists( 'crb_license_can' )
		&& crb_license_can( 'ai_transform' )
		&& function_exists( 'crb_gemini_api_key_configured' )
		&& crb_gemini_api_key_configured();
}

/**
 * @param array<string, mixed> $feed    Feed row.
 * @param string               $context preview|rss|import.
 * @return bool
 */
function crb_ai_transform_should_apply( array $feed, $context ) {
	if ( ! crb_ai_transform_is_configured_globally() ) {
		return false;
	}
	$ai = crb_get_feed_ai_settings( $feed );
	if ( empty( $ai['enabled'] ) || '' === trim( (string) ( $ai['instruction'] ?? '' ) ) ) {
		return false;
	}
	if ( empty( $ai['slots'] ) ) {
		return false;
	}
	switch ( sanitize_key( (string) $context ) ) {
		case 'rss':
			return false;
		case 'import':
		case 'preview':
		default:
			return true;
	}
}

/**
 * @param array<string, mixed> $feed       Feed row.
 * @param int                  $slot_index Slot index.
 * @return bool
 */
function crb_ai_transform_slot_is_link( array $feed, $slot_index ) {
	$slot_index = (int) $slot_index;
	if ( 1 === $slot_index ) {
		return true;
	}
	if ( ! function_exists( 'crb_get_feed_css_config' ) ) {
		return false;
	}
	$css = crb_get_feed_css_config( $feed );
	if ( 0 === $slot_index ) {
		return 'href' === sanitize_key( (string) ( $css['title_mode'] ?? '' ) );
	}
	$map = function_exists( 'crb_extra_slot_storage_map' ) ? crb_extra_slot_storage_map() : array();
	if ( ! isset( $map[ $slot_index ]['mode_key'] ) ) {
		return false;
	}
	$mode_key = (string) $map[ $slot_index ]['mode_key'];
	$mode     = sanitize_key( (string) ( $css[ $mode_key ] ?? '' ) );
	return in_array( $mode, array( 'href', 'src' ), true );
}

/**
 * @param string $model Model slug.
 * @return string
 */
function crb_ai_transform_model_label( $model ) {
	$options = crb_ai_transform_model_options();
	$model   = crb_ai_sanitize_model_slug( $model );
	return isset( $options[ $model ] ) ? (string) $options[ $model ] : (string) $model;
}

/**
 * テキスト出力モデル（generateContent 用）。TTS / Imagen / Embedding 等は含めない。
 *
 * @return array<string, string> slug => 表示名
 */
function crb_ai_transform_text_model_catalog() {
	return array(
		'gemini-2.5-flash'      => 'Gemini 2.5 Flash（推奨・無料枠対応）',
		'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
		'gemini-2.0-flash'      => 'Gemini 2 Flash',
		'gemini-2.0-flash-lite' => 'Gemini 2 Flash Lite',
		'gemini-3.5-flash'      => 'Gemini 3.5 Flash',
		'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash Lite',
		'gemini-3.1-pro-preview' => 'Gemini 3.1 Pro',
		'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite',
	);
}

/**
 * 保存済みフィードの旧モデル slug を現行 catalog へ寄せる。
 *
 * @param string $model Model slug.
 * @return string
 */
function crb_ai_transform_resolve_model_slug( $model ) {
	$model   = crb_ai_sanitize_model_slug( $model );
	$allowed = array_keys( crb_ai_transform_text_model_catalog() );
	if ( in_array( $model, $allowed, true ) ) {
		return $model;
	}

	$legacy = array(
		'gpt-4o-mini'                    => CRB_AI_TRANSFORM_DEFAULT_MODEL,
		'gpt-4o'                         => CRB_AI_TRANSFORM_DEFAULT_MODEL,
		'gpt-35-turbo'                   => CRB_AI_TRANSFORM_DEFAULT_MODEL,
		'gpt-3.5-turbo'                  => CRB_AI_TRANSFORM_DEFAULT_MODEL,
		'gemini-1.5-flash'               => 'gemini-3.1-flash-lite',
		'gemini-1.5-pro'                 => 'gemini-2.5-pro',
		'gemini-3.1-pro'                 => 'gemini-3.1-pro-preview',
		'gemini-3-flash-preview'         => 'gemini-3.5-flash',
		'gemini-2.5-flash-preview-04-17' => 'gemini-2.5-flash',
	);

	if ( isset( $legacy[ $model ] ) ) {
		$mapped = crb_ai_sanitize_model_slug( (string) $legacy[ $model ] );
		if ( in_array( $mapped, $allowed, true ) ) {
			return $mapped;
		}
	}

	return CRB_AI_TRANSFORM_DEFAULT_MODEL;
}

/**
 * @return array<string, string>
 */
function crb_ai_transform_model_options() {
	return crb_ai_transform_text_model_catalog();
}

/**
 * Gemini API エラーを管理画面向けに補足説明付きで返す。
 *
 * @param string $api_message Raw API error message.
 * @return string
 */
function crb_gemini_api_error_user_message( $api_message ) {
	$api_message = trim( (string) $api_message );
	if ( '' === $api_message ) {
		return __( 'API がエラーを返しました。', 'custom-rss-builder' );
	}

	$lower = strtolower( $api_message );
	if (
		false !== strpos( $lower, 'high demand' )
		|| false !== strpos( $lower, 'try again later' )
	) {
		return $api_message . ' '
			. __(
				'→ Gemini 側の一時的な混雑です。数分待って再試行するか、フィード編集で別モデル（例: Gemini 2.5 Flash Lite）に切り替えてください。',
				'custom-rss-builder'
			);
	}

	if (
		false !== strpos( $lower, 'free_tier' )
		|| false !== strpos( $lower, 'exceeded your current quota' )
	) {
		return $api_message . ' '
			. __(
				'→ 無料枠の 1 日あたり上限（モデルごと）に達した可能性があります。Google AI Studio の利用状況を確認し、翌日まで待つか、別モデル（Gemini 2.5 Flash Lite / 3.1 Flash Lite 等）を検討してください。プレビューは先頭 3 件×選択スロット数ぶん API を呼び出します。',
				'custom-rss-builder'
			);
	}

	if (
		false !== strpos( $lower, 'quota' )
		|| false !== strpos( $lower, 'resource_exhausted' )
		|| false !== strpos( $lower, 'billing' )
		|| false !== strpos( $lower, 'limit' )
	) {
		return $api_message . ' '
			. __(
				'→ Google AI / Gemini 側の利用上限・課金の問題です。aistudio.google.com または Google Cloud Console で API 有効化と課金設定を確認してください。',
				'custom-rss-builder'
			);
	}

	if (
		false !== strpos( $lower, 'api key' )
		|| false !== strpos( $lower, 'api_key' )
		|| false !== strpos( $lower, 'permission denied' )
	) {
		return $api_message . ' '
			. __( '→ API キーが無効か、Generative Language API が有効になっていません。Google AI Studio で新しいキーを発行してください。', 'custom-rss-builder' );
	}

	return $api_message;
}

/**
 * Gemini generateContent API。
 *
 * @param array<int, array{role:string, content:string}> $messages Messages.
 * @param string                                         $model    Model.
 * @return string|WP_Error
 */
function crb_gemini_generate_content( array $messages, $model = '' ) {
	if ( ! function_exists( 'crb_gemini_api_key' ) ) {
		return new WP_Error( 'crb_ai_missing', __( 'AI 設定が読み込まれていません。', 'custom-rss-builder' ) );
	}

	$key = crb_gemini_api_key();
	if ( '' === $key ) {
		return new WP_Error( 'crb_ai_no_key', __( 'Gemini API キーが未設定です。ライセンス画面で設定してください。', 'custom-rss-builder' ) );
	}

	$model = crb_ai_sanitize_model_slug( $model );
	if ( '' === $model ) {
		$model = CRB_AI_TRANSFORM_DEFAULT_MODEL;
	}

	$system   = '';
	$contents = array();
	foreach ( $messages as $message ) {
		if ( ! is_array( $message ) ) {
			continue;
		}
		$role    = sanitize_key( (string) ( $message['role'] ?? 'user' ) );
		$content = trim( (string) ( $message['content'] ?? '' ) );
		if ( '' === $content ) {
			continue;
		}
		if ( 'system' === $role ) {
			$system = $content;
			continue;
		}
		$gemini_role = ( 'assistant' === $role ) ? 'model' : 'user';
		$contents[]  = array(
			'role'  => $gemini_role,
			'parts' => array(
				array( 'text' => $content ),
			),
		);
	}

	if ( empty( $contents ) ) {
		return new WP_Error( 'crb_ai_empty_request', __( 'AI へのリクエスト内容が空です。', 'custom-rss-builder' ) );
	}

	$payload = array(
		'contents'         => $contents,
		'generationConfig' => array(
			'temperature' => 0.3,
		),
	);
	if ( '' !== $system ) {
		$payload['systemInstruction'] = array(
			'parts' => array(
				array( 'text' => $system ),
			),
		);
	}

	$url = sprintf(
		'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
		rawurlencode( $model )
	);

	$response = wp_remote_post(
		$url,
		array(
			'timeout' => 60,
			'headers' => array(
				'Content-Type'   => 'application/json',
				'x-goog-api-key' => $key,
			),
			'body'    => wp_json_encode( $payload ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'crb_ai_http',
			sprintf(
				/* translators: %s: error message */
				__( 'Gemini への接続に失敗しました: %s', 'custom-rss-builder' ),
				$response->get_error_message()
			)
		);
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 ) {
		$message = '';
		if ( is_array( $body ) && isset( $body['error']['message'] ) ) {
			$message = (string) $body['error']['message'];
		}
		if ( '' === $message ) {
			$message = __( 'API がエラーを返しました。', 'custom-rss-builder' );
		}
		return new WP_Error( 'crb_ai_api', crb_gemini_api_error_user_message( $message ) );
	}

	$content = '';
	if ( is_array( $body ) && ! empty( $body['candidates'][0]['content']['parts'] ) && is_array( $body['candidates'][0]['content']['parts'] ) ) {
		foreach ( $body['candidates'][0]['content']['parts'] as $part ) {
			if ( is_array( $part ) && ! empty( $part['text'] ) ) {
				$content .= (string) $part['text'];
			}
		}
		$content = trim( $content );
	}
	if ( '' === $content ) {
		return new WP_Error( 'crb_ai_empty', __( 'Gemini から空の応答が返されました。', 'custom-rss-builder' ) );
	}

	return $content;
}

/**
 * プレビュー等向け: AI 変換の適用サマリー文。
 *
 * @param array<string, mixed> $stats stats from crb_ai_transform_rows_result.
 * @return string
 */
function crb_ai_transform_stats_summary( array $stats ) {
	$attempted = (int) ( $stats['attempted'] ?? 0 );
	$succeeded = (int) ( $stats['succeeded'] ?? 0 );
	if ( $attempted <= 0 ) {
		return '';
	}
	$model = (string) ( $stats['model'] ?? CRB_AI_TRANSFORM_DEFAULT_MODEL );
	$label = function_exists( 'crb_ai_transform_model_label' ) ? crb_ai_transform_model_label( $model ) : $model;
	if ( 'preview' === (string) ( $stats['context'] ?? '' ) ) {
		return sprintf(
			/* translators: 1: succeeded count, 2: attempted count, 3: model slug, 4: model label, 5: preview row limit */
			__( 'AI テキスト変換: %1$d / %2$d 回成功（モデル %3$s — %4$s）。プレビューは先頭 %5$d 件×選択スロットごとに 1 回 API を呼び出します。', 'custom-rss-builder' ),
			$succeeded,
			$attempted,
			$model,
			$label,
			(int) ( $stats['row_limit'] ?? CRB_AI_TRANSFORM_PREVIEW_ROW_LIMIT )
		);
	}
	return sprintf(
		/* translators: 1: succeeded count, 2: attempted count, 3: model slug, 4: model label */
		__( 'AI テキスト変換: %1$d / %2$d 回成功（モデル %3$s — %4$s）。', 'custom-rss-builder' ),
		$succeeded,
		$attempted,
		$model,
		$label
	);
}

/**
 * @param string $text        Source text.
 * @param string $instruction User instruction.
 * @param string $model       Model.
 * @return string|WP_Error
 */
function crb_ai_transform_text( $text, $instruction, $model = '' ) {
	$text        = trim( (string) $text );
	$instruction = trim( (string) $instruction );
	if ( '' === $text || '' === $instruction ) {
		return $text;
	}

	if ( function_exists( 'mb_substr' ) ) {
		$text = mb_substr( $text, 0, CRB_AI_TRANSFORM_MAX_INPUT_CHARS );
	} else {
		$text = substr( $text, 0, CRB_AI_TRANSFORM_MAX_INPUT_CHARS );
	}

	$system = 'You transform text according to the user instruction. Return only the transformed result without explanations or markdown fences. If the input contains HTML tags, preserve meaningful structure unless the instruction says otherwise.';

	return crb_gemini_generate_content(
		array(
			array(
				'role'    => 'system',
				'content' => $system,
			),
			array(
				'role'    => 'user',
				'content' => "Instruction:\n" . $instruction . "\n\nText:\n" . $text,
			),
		),
		$model
	);
}

/**
 * 疎通テスト用の短いリクエスト。
 *
 * @return true|WP_Error
 */
function crb_gemini_test_connection() {
	if ( ! function_exists( 'crb_license_can' ) || ! crb_license_can( 'ai_transform' ) ) {
		return new WP_Error( 'crb_ai_license', __( 'Pro ライセンスが必要です。', 'custom-rss-builder' ) );
	}

	$result = crb_gemini_generate_content(
		array(
			array(
				'role'    => 'user',
				'content' => 'Reply with exactly: OK',
			),
		),
		CRB_AI_TRANSFORM_DEFAULT_MODEL
	);
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( false === stripos( (string) $result, 'ok' ) ) {
		return new WP_Error( 'crb_ai_test_unexpected', __( '接続はできましたが、応答内容を確認できませんでした。', 'custom-rss-builder' ) );
	}
	return true;
}

/**
 * 抽出行に AI 変換を適用。
 *
 * @param array<string, mixed>             $feed    Feed settings.
 * @param array<int, array<int, string>>   $rows    Extracted rows.
 * @param string                           $context preview|rss|import.
 * @return array{rows: array<int, array<int, string>>, applied: bool, errors: array<int, string>, warnings: array<int, string>}
 */
function crb_ai_transform_rows_result( array $feed, array $rows, $context = 'preview' ) {
	$out = array(
		'rows'     => $rows,
		'applied'  => false,
		'errors'   => array(),
		'warnings' => array(),
		'stats'    => array(
			'attempted' => 0,
			'succeeded' => 0,
			'model'     => '',
			'context'   => sanitize_key( (string) $context ),
			'row_limit' => 0,
		),
	);

	if ( ! crb_ai_transform_should_apply( $feed, $context ) ) {
		return $out;
	}

	$ai          = crb_get_feed_ai_settings( $feed );
	$instruction = (string) ( $ai['instruction'] ?? '' );
	$model       = (string) ( $ai['model'] ?? CRB_AI_TRANSFORM_DEFAULT_MODEL );
	$slots       = array_map( 'intval', (array) ( $ai['slots'] ?? array() ) );
	$limit       = 'preview' === sanitize_key( (string) $context ) ? CRB_AI_TRANSFORM_PREVIEW_ROW_LIMIT : count( $rows );
	$out['stats']['model']     = $model;
	$out['stats']['row_limit'] = $limit;

	foreach ( $rows as $row_index => $row ) {
		if ( (int) $row_index >= $limit ) {
			break;
		}
		if ( ! is_array( $row ) ) {
			continue;
		}

		$slot_row = $row;
		if ( function_exists( 'crb_is_named_record' ) && crb_is_named_record( $row ) && function_exists( 'crb_record_to_slot_row' ) ) {
			$slot_row = crb_record_to_slot_row( $row );
		}
		if ( ! function_exists( 'crb_is_slot_indexed_row' ) || ! crb_is_slot_indexed_row( $slot_row ) ) {
			continue;
		}

		foreach ( $slots as $slot_index ) {
			if ( crb_ai_transform_slot_is_link( $feed, $slot_index ) ) {
				continue;
			}
			if ( ! isset( $slot_row[ $slot_index ] ) ) {
				continue;
			}
			$value = trim( (string) $slot_row[ $slot_index ] );
			if ( '' === $value ) {
				continue;
			}

			$out['stats']['attempted']++;
			$transformed = crb_ai_transform_text( $value, $instruction, $model );
			if ( is_wp_error( $transformed ) ) {
				$out['errors'][] = sprintf(
					/* translators: 1: row number, 2: slot token, 3: error message */
					__( '%1$d件目 %2$s: %3$s', 'custom-rss-builder' ),
					(int) $row_index + 1,
					function_exists( 'crb_slot_token' ) ? crb_slot_token( $slot_index ) : (string) $slot_index,
					$transformed->get_error_message()
				);
				continue;
			}

			$slot_row[ $slot_index ] = (string) $transformed;
			$out['applied']          = true;
			$out['stats']['succeeded']++;
		}

		$rows[ $row_index ] = $slot_row;
	}

	$out['rows'] = $rows;
	if ( 'preview' === sanitize_key( (string) $context ) && count( $rows ) > $limit ) {
		$out['warnings'][] = sprintf(
			/* translators: %d: preview row limit */
			__( 'プレビューでは先頭 %d 件だけ AI 変換しています。RSS・取り込みでは全件が対象です。', 'custom-rss-builder' ),
			$limit
		);
	}

	return $out;
}

/**
 * @param array<string, mixed>           $feed    Feed settings.
 * @param array<int, array<int, string>> $rows    Extracted rows.
 * @param string                         $context preview|rss|import.
 * @return array<int, array<int, string>>
 */
function crb_ai_transform_rows( array $feed, array $rows, $context = 'preview' ) {
	$result = crb_ai_transform_rows_result( $feed, $rows, $context );
	return is_array( $result['rows'] ?? null ) ? $result['rows'] : $rows;
}

/**
 * AJAX: Gemini 接続テスト。
 */
function crb_ajax_test_gemini_api() {
	check_ajax_referer( 'crb_admin_action', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( '権限がありません。', 'custom-rss-builder' ) ) );
	}
	$test = crb_gemini_test_connection();
	if ( is_wp_error( $test ) ) {
		wp_send_json_error( array( 'message' => $test->get_error_message() ) );
	}
	wp_send_json_success( array( 'message' => __( 'Gemini API への接続に成功しました。', 'custom-rss-builder' ) ) );
}

add_action( 'wp_ajax_crb_test_gemini_api', 'crb_ajax_test_gemini_api' );
