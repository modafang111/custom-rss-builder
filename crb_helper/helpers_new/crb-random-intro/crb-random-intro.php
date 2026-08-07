<?php
/**
 * Plugin Name: CRB Random Intro
 * Description: Custom RSS Builder（Pro）向け汎用補助。3つの候補ボックスから行をランダム抽選し、AI 有効フィードの取り込み本文へ挿入します（特定サイト固有の文言は含みません）。
 * Version:     1.0.5
 * Author:      Auto
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: crb-random-intro
 *
 * @package CRB_Random_Intro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_RANDOM_INTRO_VERSION', '1.0.5' );
define( 'CRB_RANDOM_INTRO_FILE', __FILE__ );
define( 'CRB_RANDOM_INTRO_OPTION', 'crb_random_intro_settings' );
define( 'CRB_RANDOM_INTRO_HISTORY_OPTION', 'crb_random_intro_history' );
define( 'CRB_RANDOM_INTRO_MARKER', '[CRB_RANDOM_INTRO]' );
define( 'CRB_RANDOM_INTRO_BOX_COUNT', 3 );
define( 'CRB_RANDOM_INTRO_HISTORY_ENUM_MAX', 2000 );

/**
 * @return array{boxes:array<int,string>}
 */
function crb_random_intro_default_settings() {
	return array(
		'boxes' => array_fill( 0, CRB_RANDOM_INTRO_BOX_COUNT, '' ),
	);
}

/**
 * @return array{boxes:array<int,string>}
 */
function crb_random_intro_settings() {
	$defaults = crb_random_intro_default_settings();
	$raw      = get_option( CRB_RANDOM_INTRO_OPTION, array() );
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}

	$boxes = array();
	$src   = isset( $raw['boxes'] ) && is_array( $raw['boxes'] ) ? $raw['boxes'] : array();
	for ( $i = 0; $i < CRB_RANDOM_INTRO_BOX_COUNT; $i++ ) {
		$boxes[ $i ] = isset( $src[ $i ] ) ? (string) $src[ $i ] : '';
	}

	return array(
		'boxes' => $boxes,
	);
}

/**
 * 1 ボックス分のテキストを行候補へ。
 *
 * @param string $text Box text.
 * @return array<int,string>
 */
function crb_random_intro_lines_from_box( $text ) {
	$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
	$raw  = explode( "\n", $text );
	$out  = array();
	foreach ( $raw as $line ) {
		$line = trim( (string) $line );
		if ( '' === $line ) {
			continue;
		}
		$out[] = $line;
	}
	return $out;
}

/**
 * 空でないボックスごとの行リスト。
 *
 * @param array{boxes?:array<int,string>} $settings Settings.
 * @return array<int,array<int,string>>
 */
function crb_random_intro_box_line_sets( $settings ) {
	$boxes = isset( $settings['boxes'] ) && is_array( $settings['boxes'] ) ? $settings['boxes'] : array();
	$sets  = array();
	for ( $i = 0; $i < CRB_RANDOM_INTRO_BOX_COUNT; $i++ ) {
		$lines = crb_random_intro_lines_from_box( isset( $boxes[ $i ] ) ? $boxes[ $i ] : '' );
		if ( ! empty( $lines ) ) {
			$sets[] = array_values( $lines );
		}
	}
	return $sets;
}

/**
 * 組み合わせ総数。
 *
 * @param array<int,array<int,string>> $sets Box line sets.
 * @return int
 */
function crb_random_intro_combination_total( array $sets ) {
	if ( empty( $sets ) ) {
		return 0;
	}
	$total = 1;
	foreach ( $sets as $lines ) {
		$count = count( $lines );
		if ( $count <= 0 ) {
			return 0;
		}
		$total *= $count;
	}
	return (int) $total;
}

/**
 * 組み合わせキー。
 *
 * @param array<int,string> $picked Picked lines.
 * @return string
 */
function crb_random_intro_combination_key( array $picked ) {
	return hash( 'sha256', implode( "\n", $picked ) );
}

/**
 * ボックス内容の指紋（変更時に履歴リセット）。
 *
 * @param array{boxes?:array<int,string>} $settings Settings.
 * @return string
 */
function crb_random_intro_boxes_fingerprint( $settings ) {
	$boxes = isset( $settings['boxes'] ) && is_array( $settings['boxes'] ) ? $settings['boxes'] : array();
	return hash( 'sha256', wp_json_encode( array_values( $boxes ) ) );
}

/**
 * @return array{fingerprint:string,used:array<int,string>}
 */
function crb_random_intro_history_get() {
	$raw = get_option( CRB_RANDOM_INTRO_HISTORY_OPTION, array() );
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}
	$used = isset( $raw['used'] ) && is_array( $raw['used'] ) ? array_values( array_filter( array_map( 'strval', $raw['used'] ) ) ) : array();
	return array(
		'fingerprint' => isset( $raw['fingerprint'] ) ? (string) $raw['fingerprint'] : '',
		'used'        => $used,
	);
}

/**
 * @param array{fingerprint:string,used:array<int,string>} $history History.
 */
function crb_random_intro_history_save( array $history ) {
	update_option(
		CRB_RANDOM_INTRO_HISTORY_OPTION,
		array(
			'fingerprint' => (string) ( $history['fingerprint'] ?? '' ),
			'used'        => isset( $history['used'] ) && is_array( $history['used'] ) ? array_values( $history['used'] ) : array(),
		),
		false
	);
}

/**
 * 履歴クリア。
 */
function crb_random_intro_history_reset() {
	delete_option( CRB_RANDOM_INTRO_HISTORY_OPTION );
}

/**
 * 全組み合わせ（件数が少ないときのみ）。
 *
 * @param array<int,array<int,string>> $sets Box line sets.
 * @return array<int,array<int,string>>
 */
function crb_random_intro_enumerate_combinations( array $sets ) {
	if ( empty( $sets ) ) {
		return array();
	}
	$combos = array( array() );
	foreach ( $sets as $lines ) {
		$next = array();
		foreach ( $combos as $prefix ) {
			foreach ( $lines as $line ) {
				$next[] = array_merge( $prefix, array( $line ) );
			}
		}
		$combos = $next;
	}
	return $combos;
}

/**
 * セットからランダムに 1 組み合わせ。
 *
 * @param array<int,array<int,string>> $sets Box line sets.
 * @return array<int,string>
 */
function crb_random_intro_pick_raw( array $sets ) {
	$picked = array();
	foreach ( $sets as $lines ) {
		$picked[] = $lines[ random_int( 0, count( $lines ) - 1 ) ];
	}
	return $picked;
}

/**
 * 履歴を避けて抽選。
 *
 * @param array{boxes?:array<int,string>}|null $settings Optional settings.
 * @param array{record?:bool}                  $args     record=false で履歴に残さない（設定プレビュー等）。
 * @return string
 */
function crb_random_intro_pick( $settings = null, $args = array() ) {
	if ( null === $settings ) {
		$settings = crb_random_intro_settings();
	}
	$record = ! isset( $args['record'] ) || ! empty( $args['record'] );
	$sets   = crb_random_intro_box_line_sets( $settings );
	if ( empty( $sets ) ) {
		return (string) apply_filters( 'crb_random_intro_pick', '', array(), $settings );
	}

	$total       = crb_random_intro_combination_total( $sets );
	$fingerprint = crb_random_intro_boxes_fingerprint( $settings );
	$history     = crb_random_intro_history_get();
	$used_map    = array();

	if ( $record ) {
		if ( $history['fingerprint'] !== $fingerprint ) {
			$history = array(
				'fingerprint' => $fingerprint,
				'used'        => array(),
			);
		}
		foreach ( $history['used'] as $key ) {
			$used_map[ $key ] = true;
		}
		if ( $total > 0 && count( $used_map ) >= $total ) {
			$used_map = array();
			$history  = array(
				'fingerprint' => $fingerprint,
				'used'        => array(),
			);
		}
	}

	$picked = null;

	if ( $record && $total > 0 && $total <= CRB_RANDOM_INTRO_HISTORY_ENUM_MAX ) {
		$unused = array();
		foreach ( crb_random_intro_enumerate_combinations( $sets ) as $combo ) {
			$key = crb_random_intro_combination_key( $combo );
			if ( empty( $used_map[ $key ] ) ) {
				$unused[] = $combo;
			}
		}
		if ( ! empty( $unused ) ) {
			$picked = $unused[ random_int( 0, count( $unused ) - 1 ) ];
		}
	}

	if ( null === $picked ) {
		$attempts = max( 8, min( 64, $total > 0 ? $total : 8 ) );
		for ( $i = 0; $i < $attempts; $i++ ) {
			$candidate = crb_random_intro_pick_raw( $sets );
			$key       = crb_random_intro_combination_key( $candidate );
			if ( ! $record || empty( $used_map[ $key ] ) ) {
				$picked = $candidate;
				break;
			}
		}
		if ( null === $picked ) {
			// ほぼ使い切り: 履歴を捨てて新規。
			if ( $record ) {
				$used_map = array();
				$history  = array(
					'fingerprint' => $fingerprint,
					'used'        => array(),
				);
			}
			$picked = crb_random_intro_pick_raw( $sets );
		}
	}

	if ( $record ) {
		$key = crb_random_intro_combination_key( $picked );
		if ( empty( $used_map[ $key ] ) ) {
			$history['fingerprint'] = $fingerprint;
			$history['used'][]      = $key;
			crb_random_intro_history_save( $history );
		}
	}

	$block = implode( "\n", $picked );

	/**
	 * 抽選結果（空文字あり得る）。
	 *
	 * @param string                             $block    Joined lines.
	 * @param array<int,string>                  $picked   Lines.
	 * @param array{boxes:array<int,string>}     $settings Settings.
	 */
	return (string) apply_filters( 'crb_random_intro_pick', $block, $picked, $settings );
}

/**
 * 履歴の利用状況。
 *
 * @param array{boxes?:array<int,string>}|null $settings Settings.
 * @return array{used:int,total:int,fingerprint_ok:bool}
 */
function crb_random_intro_history_stats( $settings = null ) {
	if ( null === $settings ) {
		$settings = crb_random_intro_settings();
	}
	$sets        = crb_random_intro_box_line_sets( $settings );
	$total       = crb_random_intro_combination_total( $sets );
	$fingerprint = crb_random_intro_boxes_fingerprint( $settings );
	$history     = crb_random_intro_history_get();
	$ok          = ( $history['fingerprint'] === $fingerprint );
	$used        = $ok ? count( $history['used'] ) : 0;
	if ( $total > 0 && $used > $total ) {
		$used = $total;
	}
	return array(
		'used'           => $used,
		'total'          => $total,
		'fingerprint_ok' => $ok,
	);
}

/**
 * 素材が 1 行でもあるか。
 *
 * @return bool
 */
function crb_random_intro_has_candidates() {
	$settings = crb_random_intro_settings();
	foreach ( $settings['boxes'] as $box ) {
		if ( ! empty( crb_random_intro_lines_from_box( $box ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * AI 指示文へ追記するブロック。
 *
 * @param string $block Picked lines.
 * @return string
 */
function crb_random_intro_instruction_appendix( $block ) {
	$block = trim( (string) $block );
	if ( '' === $block ) {
		return '';
	}

	$appendix = CRB_RANDOM_INTRO_MARKER . "\n"
		. "追加素材（この変換用にランダム抽出。自然な導入・言い回しとして織り込むこと。素材の単なる羅列は避ける）:\n"
		. $block;

	/**
	 * @param string $appendix Full appendix including marker.
	 * @param string $block    Raw picked lines.
	 */
	return (string) apply_filters( 'crb_random_intro_instruction_appendix', $appendix, $block );
}

/**
 * CRB の Gemini 変換ペイロードかどうか。
 *
 * @param string $user_text User message content.
 * @return bool
 */
function crb_random_intro_is_crb_transform_user_text( $user_text ) {
	$user_text = (string) $user_text;
	if ( false !== strpos( $user_text, CRB_RANDOM_INTRO_MARKER ) ) {
		return false;
	}
	return (bool) preg_match( '/^Instruction:\n.*\n\nText:\n/s', $user_text );
}

/**
 * Instruction 部へ素材を追記。
 *
 * @param string $user_text Original user content.
 * @param string $appendix  Appendix text.
 * @return string
 */
function crb_random_intro_inject_into_user_text( $user_text, $appendix ) {
	$appendix = trim( (string) $appendix );
	if ( '' === $appendix ) {
		return (string) $user_text;
	}

	if ( ! preg_match( '/^(Instruction:\n)(.*?)(\n\nText:\n)(.*)$/s', (string) $user_text, $m ) ) {
		return (string) $user_text;
	}

	$instruction = rtrim( (string) $m[2] );
	$injected    = $instruction . "\n\n" . $appendix;

	return $m[1] . $injected . $m[3] . $m[4];
}

/**
 * Gemini generateContent の JSON body を書き換える。
 *
 * CRB 本体に AI 用フィルタが無いため、送信直前の HTTP 引数のみを補助側で差し込む。
 * 接続テスト（Instruction/Text 形式以外）には触れない。
 *
 * @param array  $args Request args.
 * @param string $url  URL.
 * @return array
 */
function crb_random_intro_http_request_args( $args, $url ) {
	$url = (string) $url;
	if ( false === strpos( $url, 'generativelanguage.googleapis.com' ) ) {
		return $args;
	}
	if ( false === strpos( $url, ':generateContent' ) ) {
		return $args;
	}
	if ( ! crb_random_intro_has_candidates() ) {
		return $args;
	}
	if ( ! is_array( $args ) || empty( $args['body'] ) || ! is_string( $args['body'] ) ) {
		return $args;
	}

	$payload = json_decode( $args['body'], true );
	if ( ! is_array( $payload ) || empty( $payload['contents'] ) || ! is_array( $payload['contents'] ) ) {
		return $args;
	}

	$changed = false;
	foreach ( $payload['contents'] as $c_idx => $content ) {
		if ( ! is_array( $content ) || empty( $content['parts'] ) || ! is_array( $content['parts'] ) ) {
			continue;
		}
		foreach ( $content['parts'] as $p_idx => $part ) {
			if ( ! is_array( $part ) || ! isset( $part['text'] ) ) {
				continue;
			}
			$text = (string) $part['text'];
			if ( ! crb_random_intro_is_crb_transform_user_text( $text ) ) {
				continue;
			}

			$block    = crb_random_intro_pick( null, array( 'record' => false ) );
			$appendix = crb_random_intro_instruction_appendix( $block );
			if ( '' === $appendix ) {
				continue;
			}

			$payload['contents'][ $c_idx ]['parts'][ $p_idx ]['text'] = crb_random_intro_inject_into_user_text( $text, $appendix );
			$changed = true;
		}
	}

	if ( ! $changed ) {
		return $args;
	}

	$encoded = wp_json_encode( $payload );
	if ( ! is_string( $encoded ) || '' === $encoded ) {
		return $args;
	}

	$args['body'] = $encoded;
	return $args;
}
add_filter( 'http_request_args', 'crb_random_intro_http_request_args', 10, 2 );

/**
 * バックトレースから現在のフィード配列を探す。
 *
 * @return array<string, mixed>|null
 */
function crb_random_intro_current_feed_from_backtrace() {
	$trace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 24 );
	foreach ( $trace as $frame ) {
		if ( ! is_array( $frame ) ) {
			continue;
		}
		$fn = (string) ( $frame['function'] ?? '' );
		if ( ! in_array( $fn, array( 'import_single_item', 'preview_import_items', 'crb_ai_transform_rows', 'crb_ai_transform_rows_result' ), true ) ) {
			continue;
		}
		if ( empty( $frame['args'][0] ) || ! is_array( $frame['args'][0] ) ) {
			continue;
		}
		return $frame['args'][0];
	}
	return null;
}

/**
 * タイトル用テンプレート展開中か。
 *
 * @return bool
 */
function crb_random_intro_is_title_render_context() {
	$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 16 );
	foreach ( $trace as $frame ) {
		if ( ! is_array( $frame ) ) {
			continue;
		}
		if ( 'render_plain_slot_template' === (string) ( $frame['function'] ?? '' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * フィードの AI 設定（正規化済み優先）。
 *
 * @param array<string, mixed> $feed Feed.
 * @return array<string, mixed>
 */
function crb_random_intro_feed_ai_settings( array $feed ) {
	if ( function_exists( 'crb_get_feed_ai_settings' ) ) {
		return crb_get_feed_ai_settings( $feed );
	}
	$raw = isset( $feed['ai'] ) && is_array( $feed['ai'] ) ? $feed['ai'] : array();
	return array_merge(
		array(
			'enabled'     => false,
			'instruction' => '',
			'slots'       => array(),
			'model'       => '',
		),
		$raw
	);
}

/**
 * 本文へ抽選文を入れる条件（スロット選択は不要）。
 *
 * フィードで「AI テキスト変換」がオンなら実行する。
 * CRB 本体の Gemini 適用条件（指示・スロット・API キー）とは独立。
 *
 * @return bool
 */
function crb_random_intro_feed_ai_should_apply() {
	if ( function_exists( 'crb_license_can' ) && ! crb_license_can( 'ai_transform' ) ) {
		return false;
	}

	$feed = crb_random_intro_current_feed_from_backtrace();
	if ( null === $feed ) {
		return false;
	}

	$ai = crb_random_intro_feed_ai_settings( $feed );
	return ! empty( $ai['enabled'] );
}

/**
 * 抽選結果を HTML 断片へ。
 *
 * @param string $block Picked lines.
 * @return string
 */
function crb_random_intro_block_to_html( $block ) {
	$block = trim( (string) $block );
	if ( '' === $block ) {
		return '';
	}
	$lines = preg_split( '/\R/u', $block );
	if ( ! is_array( $lines ) ) {
		$lines = array( $block );
	}
	$parts = array();
	foreach ( $lines as $line ) {
		$line = trim( (string) $line );
		if ( '' === $line ) {
			continue;
		}
		$parts[] = '<p>' . esc_html( $line ) . '</p>';
	}
	return implode( "\n", $parts );
}

/**
 * 取り込み本文テンプレート展開後に抽選文を先頭へ固定挿入。
 *
 * AI 指示への追記だけでは本文に残らないため、保存される HTML にも書く。
 *
 * @param string $output Rendered content.
 * @return string
 */
function crb_random_intro_filter_content_template( $output ) {
	$output = (string) $output;
	if ( '' === trim( $output ) ) {
		return $output;
	}
	if ( crb_random_intro_is_title_render_context() ) {
		return $output;
	}
	if ( ! crb_random_intro_has_candidates() ) {
		return $output;
	}
	if ( ! crb_random_intro_feed_ai_should_apply() ) {
		return $output;
	}
	if ( false !== strpos( $output, 'crb-random-intro-block' ) ) {
		return $output;
	}

	$block = crb_random_intro_pick();
	$html  = crb_random_intro_block_to_html( $block );
	if ( '' === $html ) {
		return $output;
	}

	$wrapped = '<div class="crb-random-intro-block">' . $html . '</div>';

	/**
	 * @param string $wrapped Prefixed HTML.
	 * @param string $output  Original rendered content.
	 * @param string $block   Raw picked lines.
	 */
	return (string) apply_filters( 'crb_random_intro_prefixed_content', $wrapped . "\n" . $output, $output, $block );
}
add_filter( 'crb_content_template_rendered', 'crb_random_intro_filter_content_template', 20 );

/**
 * 設定画面。
 */
function crb_random_intro_admin_menu() {
	add_options_page(
		__( 'CRB Random Intro', 'crb-random-intro' ),
		__( 'CRB Random Intro', 'crb-random-intro' ),
		'manage_options',
		'crb-random-intro',
		'crb_random_intro_render_settings_page'
	);
}
add_action( 'admin_menu', 'crb_random_intro_admin_menu' );

/**
 * 設定保存・履歴リセット。
 */
function crb_random_intro_handle_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['crb_random_intro_reset_history'] ) ) {
		check_admin_referer( 'crb_random_intro_save' );
		crb_random_intro_history_reset();
		add_settings_error(
			'crb_random_intro',
			'crb_random_intro_history_reset',
			__( '組み合わせ履歴をリセットしました。', 'crb-random-intro' ),
			'success'
		);
		return;
	}

	if ( ! isset( $_POST['crb_random_intro_save'] ) ) {
		return;
	}
	check_admin_referer( 'crb_random_intro_save' );

	$boxes = array();
	for ( $i = 0; $i < CRB_RANDOM_INTRO_BOX_COUNT; $i++ ) {
		$key = 'crb_random_intro_box_' . $i;
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		$boxes[ $i ] = sanitize_textarea_field( (string) $raw );
	}

	update_option(
		CRB_RANDOM_INTRO_OPTION,
		array(
			'boxes' => $boxes,
		),
		false
	);

	// ボックス変更時は組み合わせ空間が変わるため履歴を捨てる。
	crb_random_intro_history_reset();

	add_settings_error(
		'crb_random_intro',
		'crb_random_intro_saved',
		__( '設定を保存しました（組み合わせ履歴もリセットしました）。', 'crb-random-intro' ),
		'success'
	);
}
add_action( 'admin_init', 'crb_random_intro_handle_save' );

/**
 * 設定画面向け: 動作前提の診断。
 *
 * @return array{
 *   crb_on:bool,
 *   license_ai:bool|null,
 *   gemini_ok:bool|null,
 *   ready_feeds:array<int,string>,
 *   blocked_feeds:array<int,array{name:string,reasons:array<int,string>}>
 * }
 */
function crb_random_intro_admin_diagnostics() {
	$crb_on = defined( 'CRB_VERSION' ) || class_exists( 'Custom_RSS_Builder', false );

	$license_ai = null;
	if ( function_exists( 'crb_license_can' ) ) {
		$license_ai = (bool) crb_license_can( 'ai_transform' );
	}

	$gemini_ok = null;
	if ( function_exists( 'crb_ai_transform_is_configured_globally' ) ) {
		$gemini_ok = (bool) crb_ai_transform_is_configured_globally();
	} elseif ( function_exists( 'crb_gemini_api_key_configured' ) ) {
		$gemini_ok = (bool) crb_gemini_api_key_configured();
	}

	$ready   = array();
	$blocked = array();

	if ( defined( 'CRB_OPTION_FEEDS' ) ) {
		$feeds = get_option( CRB_OPTION_FEEDS, array() );
		if ( is_array( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				if ( ! is_array( $feed ) ) {
					continue;
				}
				$ai   = isset( $feed['ai'] ) && is_array( $feed['ai'] ) ? $feed['ai'] : array();
				$name = (string) ( $feed['name'] ?? '' );
				if ( '' === $name ) {
					$name = 'ID ' . (string) ( $feed['id'] ?? '?' );
				}
				if ( empty( $ai['enabled'] ) ) {
					continue;
				}

				$reasons = array();
				if ( null === $license_ai ) {
					// 判定不可時はブロックしない。
				} elseif ( ! $license_ai ) {
					$reasons[] = __( 'Pro AI ライセンス不可', 'crb-random-intro' );
				}

				if ( empty( $reasons ) ) {
					$ready[] = $name;
				} else {
					$blocked[] = array(
						'name'    => $name,
						'reasons' => $reasons,
					);
				}
			}
		}
	}

	return array(
		'crb_on'        => $crb_on,
		'license_ai'    => $license_ai,
		'gemini_ok'     => $gemini_ok,
		'ready_feeds'   => $ready,
		'blocked_feeds' => $blocked,
	);
}

/**
 * 設定ページ描画。
 */
function crb_random_intro_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = crb_random_intro_settings();
	$sample   = crb_random_intro_pick( $settings, array( 'record' => false ) );
	$hist     = crb_random_intro_history_stats( $settings );
	$diag     = crb_random_intro_admin_diagnostics();
	$license_url = admin_url( 'admin.php?page=custom-rss-builder-license' );
	$feeds_url   = admin_url( 'admin.php?page=custom-rss-builder' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'CRB Random Intro', 'crb-random-intro' ); ?></h1>
		<?php settings_errors( 'crb_random_intro' ); ?>

		<div class="notice notice-warning" style="border-left-color:#dba617;padding:12px 16px;">
			<p style="margin:0 0 8px;font-size:14px;font-weight:600;">
				<?php esc_html_e( '動かす条件（対象スロットは不要です）', 'crb-random-intro' ); ?>
			</p>
			<p style="margin:0 0 8px;">
				<?php esc_html_e( 'フィードで「AI テキスト変換」がオンのとき、取り込み／プレビュー本文の先頭に抽選文を入れます。投稿の編集画面では実行されません。既存投稿は再取り込みが必要です。', 'crb-random-intro' ); ?>
			</p>
			<ol style="margin:0 0 0 1.25em;padding:0;">
				<li>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: feeds admin URL */
							__( '<a href="%s">フィード編集</a> の「AI テキスト変換」を <strong>有効</strong>にする', 'crb-random-intro' ),
							esc_url( $feeds_url )
						)
					);
					?>
				</li>
				<li><?php esc_html_e( '下のボックスに候補を入れる（1 行＝1 候補）', 'crb-random-intro' ); ?></li>
				<li><?php esc_html_e( '取り込み（またはプレビュー）を実行する', 'crb-random-intro' ); ?></li>
			</ol>
			<p style="margin:10px 0 0;color:#646970;">
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: license settings URL */
						__( '※ Gemini への自動付与は、CRB 側で変換指示・スロット・<a href="%s">API キー</a>が揃っているときだけ追加で動きます。抽選文の本文挿入自体にスロット選択は不要です。', 'crb-random-intro' ),
						esc_url( $license_url )
					)
				);
				?>
			</p>
		</div>

		<div class="notice notice-info" style="padding:12px 16px;">
			<p style="margin:0 0 8px;font-weight:600;"><?php esc_html_e( 'いまのサイト状態', 'crb-random-intro' ); ?></p>
			<ul style="margin:0;list-style:disc;padding-left:1.25em;">
				<li>
					<?php
					echo $diag['crb_on']
						? esc_html__( 'Custom RSS Builder: 有効', 'crb-random-intro' )
						: esc_html__( 'Custom RSS Builder: 無効（必須）', 'crb-random-intro' );
					?>
				</li>
				<li>
					<?php
					if ( null === $diag['license_ai'] ) {
						esc_html_e( 'Pro AI ライセンス: 判定不可', 'crb-random-intro' );
					} else {
						echo $diag['license_ai']
							? esc_html__( 'Pro AI ライセンス: 利用可', 'crb-random-intro' )
							: esc_html__( 'Pro AI ライセンス: 不可', 'crb-random-intro' );
					}
					?>
				</li>
				<li>
					<?php
					if ( null === $diag['gemini_ok'] ) {
						esc_html_e( 'Gemini API キー: 判定不可（本文挿入には不要）', 'crb-random-intro' );
					} else {
						echo $diag['gemini_ok']
							? esc_html__( 'Gemini API キー: 設定済み（CRB AI 変換用・任意）', 'crb-random-intro' )
							: esc_html__( 'Gemini API キー: 未設定（本文への抽選挿入には不要。CRB の Gemini 変換だけ動かない）', 'crb-random-intro' );
					}
					?>
				</li>
				<?php if ( ! empty( $diag['ready_feeds'] ) ) : ?>
					<li>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: feed names */
								__( 'AI 実行可能なフィード: %s', 'crb-random-intro' ),
								implode( ' / ', $diag['ready_feeds'] )
							)
						);
						?>
					</li>
				<?php endif; ?>
				<?php foreach ( $diag['blocked_feeds'] as $blocked ) : ?>
					<li style="color:#b32d2e;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: feed name, 2: reasons */
								__( '要対応フィード「%1$s」: %2$s', 'crb-random-intro' ),
								$blocked['name'],
								implode( '・', $blocked['reasons'] )
							)
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<p>
			<?php esc_html_e( '下の 3 ボックスに候補を入れます（1 行＝1 候補）。タグ指定は不要です。AI が動いた取り込み時に自動で使われます。', 'crb-random-intro' ); ?>
		</p>

		<?php if ( ! $diag['crb_on'] ) : ?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Custom RSS Builder が有効ではありません。この補助プラグイン単体では AI 変換は動きません。', 'crb-random-intro' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'crb_random_intro_save' ); ?>
			<table class="form-table" role="presentation">
				<?php for ( $i = 0; $i < CRB_RANDOM_INTRO_BOX_COUNT; $i++ ) : ?>
					<tr>
						<th scope="row">
							<label for="crb-random-intro-box-<?php echo esc_attr( (string) $i ); ?>">
								<?php
								printf(
									/* translators: %d: box number */
									esc_html__( 'ボックス %d', 'crb-random-intro' ),
									(int) ( $i + 1 )
								);
								?>
							</label>
						</th>
						<td>
							<textarea
								name="crb_random_intro_box_<?php echo esc_attr( (string) $i ); ?>"
								id="crb-random-intro-box-<?php echo esc_attr( (string) $i ); ?>"
								class="large-text code"
								rows="6"
								placeholder="<?php esc_attr_e( "こんにちは。\nおはようございます。\nお久しぶりです。", 'crb-random-intro' ); ?>"
							><?php echo esc_textarea( (string) $settings['boxes'][ $i ] ); ?></textarea>
							<p class="description"><?php esc_html_e( '1 行＝1 候補。空行は無視します。空のボックスは抽選対象外です。', 'crb-random-intro' ); ?></p>
						</td>
					</tr>
				<?php endfor; ?>
				<tr>
					<th scope="row"><?php esc_html_e( '抽選プレビュー', 'crb-random-intro' ); ?></th>
					<td>
						<?php if ( '' === trim( (string) $sample ) ) : ?>
							<p class="description"><?php esc_html_e( '候補がありません。', 'crb-random-intro' ); ?></p>
						<?php else : ?>
							<pre style="white-space:pre-wrap;background:#f6f7f7;padding:12px;border:1px solid #dcdcde;max-width:40rem;"><?php echo esc_html( $sample ); ?></pre>
							<p class="description"><?php esc_html_e( 'プレビューは履歴に残りません。ページを開くたびに変わります。', 'crb-random-intro' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '組み合わせ履歴', 'crb-random-intro' ); ?></th>
					<td>
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: used count, 2: total combinations */
									__( '本文取り込みで使用済み: %1$d / %2$d 通り', 'crb-random-intro' ),
									(int) $hist['used'],
									(int) $hist['total']
								)
							);
							?>
						</p>
						<p class="description">
							<?php esc_html_e( '同じ組み合わせは使い切るまで出ません。使い切ると履歴をクリアして最初から再抽選します。ボックス保存時も履歴はリセットされます。', 'crb-random-intro' ); ?>
						</p>
						<?php submit_button( __( '履歴だけリセット', 'crb-random-intro' ), 'secondary', 'crb_random_intro_reset_history', false ); ?>
					</td>
				</tr>
			</table>
			<?php submit_button( __( '設定を保存', 'crb-random-intro' ), 'primary', 'crb_random_intro_save' ); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( '動作', 'crb-random-intro' ); ?></h2>
		<ul style="list-style:disc;padding-left:1.5em;">
			<li><?php esc_html_e( 'フィードで「AI テキスト変換」がオンなら、取り込み本文先頭に抽選文を保存します（対象スロットは不要）。', 'crb-random-intro' ); ?></li>
			<li><?php esc_html_e( '本文へ入れた組み合わせは履歴に残り、重複しないよう抽選します（使い切り後はリセット）。', 'crb-random-intro' ); ?></li>
			<li><?php esc_html_e( 'CRB が Gemini を呼ぶ設定のときは、あわせて変換指示へ素材を自動追記します。', 'crb-random-intro' ); ?></li>
			<li><?php esc_html_e( '公開 HTML 用のタグや、表示のたびの差し替えは行いません。', 'crb-random-intro' ); ?></li>
			<li><?php esc_html_e( 'Custom RSS Builder 本体のファイルは変更しません。', 'crb-random-intro' ); ?></li>
		</ul>
	</div>
	<?php
}

/**
 * プラグイン一覧に設定リンク。
 *
 * @param array<int,string> $links Links.
 * @return array<int,string>
 */
function crb_random_intro_plugin_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=crb-random-intro' );
	array_unshift(
		$links,
		'<a href="' . esc_url( $url ) . '">' . esc_html__( '設定', 'crb-random-intro' ) . '</a>'
	);
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( CRB_RANDOM_INTRO_FILE ), 'crb_random_intro_plugin_action_links' );
