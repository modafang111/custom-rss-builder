<?php
/**
 * Plugin Name: CRB ID Split (DUGA Helper)
 * Description: Custom RSS Builder 用。作品ID（例: haisetsu-0684）を前半 {a} と後半 {b} に分割します。
 * Version:     1.0.5
 * Author:      Auto
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: crb-id-split
 *
 * @package CRB_ID_Split
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_ID_SPLIT_VERSION', '1.0.5' );
define( 'CRB_ID_SPLIT_FILE', __FILE__ );
define( 'CRB_ID_SPLIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRB_ID_SPLIT_URL', plugin_dir_url( __FILE__ ) );

/**
 * haisetsu-0684 / abnormal-0391 など → [maker, num]
 *
 * @param string $id Product id or URL containing it.
 * @return array{0:?string,1:?string}
 */
function crb_id_split_parse( $id ) {
	$id = trim( html_entity_decode( (string) $id, ENT_QUOTES, 'UTF-8' ) );
	if ( '' === $id ) {
		return array( null, null );
	}

	// ジャケットURL等: .../unsecure/{maker}/{num}/noauth/...
	if ( preg_match( '#/unsecure/([^/]+)/(\d+)/#i', $id, $m ) ) {
		return array( $m[1], $m[2] );
	}

	// /ppv/haisetsu-0684/
	if ( preg_match( '#/ppv/([a-z0-9][a-z0-9\-]*-\d+)/#i', $id, $m ) ) {
		$id = $m[1];
	}

	// クエリ id=haisetsu-0684
	if ( preg_match( '#[?&]id=([a-z0-9][a-z0-9\-]*-\d+)#i', $id, $m ) ) {
		$id = $m[1];
	}

	$id = preg_replace( '#[^a-z0-9\-]#i', '', $id );

	if ( preg_match( '#^(.+)-(\d+)$#', $id, $m ) ) {
		return array( $m[1], $m[2] );
	}

	return array( null, null );
}

/**
 * @param string $id Product id.
 * @return string
 */
function crb_id_split_flv_url( $id ) {
	list( $maker, $num ) = crb_id_split_parse( $id );
	if ( ! $maker || ! $num ) {
		return '';
	}
	return 'https://flv.duga.jp/unsecure/' . rawurlencode( $maker ) . '/' . rawurlencode( $num ) . '/noauth/temp.mp4';
}

/**
 * @param string $id Product id.
 * @return string
 */
function crb_id_split_cap_url( $id ) {
	list( $maker, $num ) = crb_id_split_parse( $id );
	if ( ! $maker || ! $num ) {
		return '';
	}
	return 'https://pic.duga.jp/unsecure/' . rawurlencode( $maker ) . '/' . rawurlencode( $num ) . '/noauth/flvcap.jpg';
}

/**
 * サンプルプレイヤー HTML。
 *
 * @param string $id Product id (haisetsu-0684).
 * @return string
 */
function crb_id_split_player_html( $id ) {
	$id = trim( (string) $id );
	list( $maker, $num ) = crb_id_split_parse( $id );
	if ( ! $maker || ! $num ) {
		return '';
	}

	// 正規化した作品ID
	$pid = $maker . '-' . $num;
	$flv = esc_url( crb_id_split_flv_url( $pid ) );
	$cap = esc_url( crb_id_split_cap_url( $pid ) );
	$dl  = esc_url(
		'https://duga.jp/prog/download/?id=' . rawurlencode( $pid )
		. '&fname=sample/sample_3000_sd.mp4&url=%2Fppv%2F' . rawurlencode( $pid ) . '%2F'
	);
	$pid_attr = esc_attr( $pid );

	return '<div class="crb-duga-player" data-crb-duga-player="1">'
		. '<a class="sample-preview" href="' . $dl . '" rel="nofollow" name="dugaplayerlink" pid="' . $pid_attr . '" filetype="mp4sdsample" viewtype="1">'
		. '<video class="play-video" src="' . $flv . '" preload="metadata" playsinline muted loop></video>'
		. '<img src="' . $cap . '" alt="">'
		. '<div class="curtain"></div>'
		. '<div class="play-icon"></div>'
		. '<div class="explanation"></div>'
		. '</a>'
		. '</div>';
}

/**
 * フロント／管理画面でプレイヤー用 CSS/JS を読み込む。
 */
function crb_id_split_enqueue_player_assets() {
	$css = CRB_ID_SPLIT_DIR . 'assets/duga-player.css';
	$js  = CRB_ID_SPLIT_DIR . 'assets/duga-player.js';
	if ( ! is_readable( $css ) || ! is_readable( $js ) ) {
		return;
	}
	wp_enqueue_style(
		'crb-id-split-duga-player',
		CRB_ID_SPLIT_URL . 'assets/duga-player.css',
		array(),
		(string) filemtime( $css )
	);
	wp_enqueue_script(
		'crb-id-split-duga-player',
		CRB_ID_SPLIT_URL . 'assets/duga-player.js',
		array(),
		(string) filemtime( $js ),
		true
	);
}

/**
 * @param string $content Post content.
 * @return bool
 */
function crb_id_split_content_has_player( $content ) {
	return false !== strpos( (string) $content, 'crb-duga-player' )
		|| false !== strpos( (string) $content, 'sample-preview' );
}

/**
 * 投稿表示時のみアセットを読み込む。
 */
function crb_id_split_maybe_enqueue_frontend_assets() {
	if ( is_admin() ) {
		return;
	}
	if ( ! is_singular() ) {
		return;
	}
	$post = get_queried_object();
	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}
	if ( ! crb_id_split_content_has_player( (string) $post->post_content ) ) {
		return;
	}
	crb_id_split_enqueue_player_assets();
}
add_action( 'wp_enqueue_scripts', 'crb_id_split_maybe_enqueue_frontend_assets' );

/**
 * CRB プレビュー等の管理画面でも再生できるようにする。
 */
function crb_id_split_maybe_enqueue_admin_assets( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$screen_id = $screen && ! empty( $screen->id ) ? (string) $screen->id : (string) $hook;
	if ( false === strpos( $screen_id, 'custom-rss-builder' ) ) {
		return;
	}
	crb_id_split_enqueue_player_assets();
}
add_action( 'admin_enqueue_scripts', 'crb_id_split_maybe_enqueue_admin_assets' );

/**
 * 分割失敗時の見えるメッセージ（黙って消さない）。
 *
 * @param string $raw Raw input.
 * @return string
 */
function crb_id_split_fail_message( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return '<span class="crb-id-split-miss" style="color:#b32d2e;font-size:12px;">[ID Split] スロットが空です。先に {%番号} 単体を置いて中身を確認してください。</span>';
	}
	$short = function_exists( 'mb_substr' ) ? mb_substr( $raw, 0, 40 ) : substr( $raw, 0, 40 );
	return '<span class="crb-id-split-miss" style="color:#b32d2e;font-size:12px;">[ID Split] 分割できません: '
		. esc_html( $short )
		. '（例: haisetsu-0684 またはジャケットURL）</span>';
}

/**
 * テンプレート内の分割トークンを展開する。
 *
 * 書き方:
 *   {{a:{%4}}}  → 前半（例: haisetsu）
 *   {{b:{%4}}}  → 後半（例: 0684）
 *   {{split:{%4}}} のあと {a} {b}
 *
 * @param string $content Content.
 * @return string
 */
function crb_id_split_expand( $content ) {
	$content = (string) $content;
	if ( '' === $content ) {
		return $content;
	}

	$has_token = (
		false !== strpos( $content, '{{a:' )
		|| false !== strpos( $content, '{{b:' )
		|| false !== strpos( $content, '{{split:' )
		|| false !== strpos( $content, '{{player:' )
		|| false !== strpos( $content, '{{duga_' )
		|| false !== strpos( $content, '{a}' )
		|| false !== strpos( $content, '{b}' )
		|| false !== strpos( $content, '{{a}}' )
		|| false !== strpos( $content, '{{b}}' )
	);
	if ( ! $has_token ) {
		return $content;
	}

	$maker = null;
	$num   = null;

	$token_list = 'a|b|split|player|flv|cap|duga_player|duga_flv|duga_cap|duga_m|duga_n|duga_maker|duga_num';

	// ※ {%n} 置換後の値には通常 } が無い。未置換の {{a:{%5}}} はスキップして壊さない。
	$content = preg_replace_callback(
		'/\{\{(' . $token_list . '):(\{%(\d+)\}|[^}]+)\}\}/i',
		static function ( $m ) use ( &$maker, &$num ) {
			$kind_token = strtolower( $m[1] );
			$id         = trim( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) );

			// まだ {%5} のまま → CRB が置換していないので触らない
			if ( preg_match( '/^\{\%\d+\}$/', $id ) ) {
				return $m[0];
			}

			$map = array(
				'a'           => 'm',
				'b'           => 'n',
				'duga_m'      => 'm',
				'duga_n'      => 'n',
				'duga_maker'  => 'm',
				'duga_num'    => 'n',
				'split'       => 'split',
				'player'      => 'player',
				'duga_player' => 'player',
				'flv'         => 'flv',
				'duga_flv'    => 'flv',
				'cap'         => 'cap',
				'duga_cap'    => 'cap',
			);
			$kind = isset( $map[ $kind_token ] ) ? $map[ $kind_token ] : '';

			list( $mkr, $n ) = crb_id_split_parse( $id );
			if ( ! $mkr || ! $n ) {
				if ( 'split' === $kind ) {
					return crb_id_split_fail_message( $id );
				}
				return crb_id_split_fail_message( $id );
			}

			$maker = $mkr;
			$num   = $n;

			switch ( $kind ) {
				case 'split':
					return '';
				case 'player':
					return crb_id_split_player_html( $mkr . '-' . $n );
				case 'flv':
					return esc_url( crb_id_split_flv_url( $mkr . '-' . $n ) );
				case 'cap':
					return esc_url( crb_id_split_cap_url( $mkr . '-' . $n ) );
				case 'm':
					return esc_html( $mkr );
				case 'n':
					return esc_html( $n );
			}
			return '';
		},
		$content
	);

	if ( $maker && $num ) {
		$pairs = array(
			'{{a}}' => $maker,
			'{{b}}' => $num,
			'{a}'   => $maker,
			'{b}'   => $num,
		);
		$content = str_replace( array_keys( $pairs ), array_values( $pairs ), $content );
	}

	return $content;
}

/**
 * CRB Content_Template::render 後フィルタ。
 *
 * @param string $content Rendered.
 * @return string
 */
function crb_id_split_filter_rendered( $content ) {
	return crb_id_split_expand( $content );
}
add_filter( 'crb_content_template_rendered', 'crb_id_split_filter_rendered', 10, 1 );

/**
 * 投稿保存時にも展開（フィルタ未適用環境の保険）。
 *
 * @param array<string,mixed> $data Post data.
 * @return array<string,mixed>
 */
function crb_id_split_filter_insert( $data ) {
	if ( isset( $data['post_content'] ) ) {
		$data['post_content'] = crb_id_split_expand( $data['post_content'] );
	}
	if ( isset( $data['post_title'] ) ) {
		$data['post_title'] = crb_id_split_expand( $data['post_title'] );
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'crb_id_split_filter_insert', 20, 1 );

/**
 * 表示時保険。
 *
 * @param string $content Content.
 * @return string
 */
function crb_id_split_filter_the_content( $content ) {
	return crb_id_split_expand( $content );
}
add_filter( 'the_content', 'crb_id_split_filter_the_content', 8 );

/**
 * @param array<string,string> $atts Atts.
 * @return string
 */
function crb_id_split_shortcode_player( $atts ) {
	$atts = shortcode_atts(
		array(
			'id' => '',
		),
		$atts,
		'duga_player'
	);
	return crb_id_split_player_html( $atts['id'] );
}
add_shortcode( 'duga_player', 'crb_id_split_shortcode_player' );

/**
 * wp_kses でプレイヤー HTML が落ちないよう許可。
 *
 * @param array<string,array<string,bool>> $tags Tags.
 * @return array<string,array<string,bool>>
 */
function crb_id_split_allow_kses( $tags ) {
	$tags['video'] = array(
		'class'    => true,
		'src'      => true,
		'preload'  => true,
		'muted'    => true,
		'loop'     => true,
		'autoplay' => true,
		'playsinline' => true,
		'controls' => true,
		'width'    => true,
		'height'   => true,
	);
	$tags['source'] = array(
		'src'  => true,
		'type' => true,
	);
	if ( isset( $tags['a'] ) ) {
		$tags['a']['pid']      = true;
		$tags['a']['filetype'] = true;
		$tags['a']['viewtype'] = true;
		$tags['a']['name']     = true;
		$tags['a']['fileno']   = true;
	}
	$tags['div']['class']                  = true;
	$tags['div']['data-crb-duga-player']   = true;
	return $tags;
}
add_filter( 'wp_kses_allowed_html', 'crb_id_split_allow_kses', 10, 1 );

/**
 * 管理画面に初めてでも分かる手順を表示。
 */
function crb_id_split_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || empty( $screen->id ) ) {
		return;
	}
	if ( false === strpos( (string) $screen->id, 'custom-rss-builder' ) ) {
		return;
	}
	?>
	<div class="notice notice-info" style="padding:12px 14px;">
		<p style="margin:0 0 8px;"><strong>CRB ID Split</strong> — IDを前半 <code>{a}</code> と後半 <code>{b}</code> に分けるだけです。</p>
		<p style="margin:0 0 8px;"><strong>よくある失敗:</strong> 番号だけ指定しても、そのスロットが<strong>空</strong>だと何も出ません（例: `{%5}` にセレクタが無い）。</p>
		<ol style="margin:0 0 8px 1.2em; padding:0; line-height:1.7;">
			<li>本文に <code>{%4}</code> のように<strong>スロットだけ</strong>置いてプレビュー → <strong>中身が見える番号</strong>を使う。</li>
			<li>中身が <code>haisetsu-0684</code> やジャケットURLなら、その番号で分割する:<br>
				<code>{{a:{%4}}}</code>　<code>{{b:{%4}}}</code><br>
				または <code>{{split:{%4}}}</code> のあとで <code>{a}</code> <code>{b}</code></li>
			<li>赤い <code>[ID Split]</code> メッセージが出たら、スロット空か形式違いです。</li>
		</ol>
	</div>
	<?php
}
add_action( 'admin_notices', 'crb_id_split_admin_notice' );
