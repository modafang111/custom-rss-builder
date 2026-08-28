<?php
/**
 * Plugin Name: CRB Title Redirect
 * Description: 同一／近似タイトル（または同一作品ID）の公開投稿が重複したとき、旧投稿から新投稿へ 301 リダイレクトします（重複コンテンツ対策）。
 * Version:     1.1.1
 * Author:      Auto
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: crb-title-redirect
 *
 * @package CRB_Title_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_TITLE_REDIRECT_VERSION', '1.1.1' );
define( 'CRB_TITLE_REDIRECT_FILE', __FILE__ );
define( 'CRB_TITLE_REDIRECT_META_TO', '_crb_title_redirect_to' );
define( 'CRB_TITLE_REDIRECT_META_DISABLE', '_crb_title_redirect_disable' );
define( 'CRB_TITLE_REDIRECT_OPTION', 'crb_title_redirect_settings' );

/**
 * @return array{enabled:bool,post_types:array<int,string>,crb_only:bool,fuzzy:bool}
 */
function crb_title_redirect_settings() {
	$defaults = array(
		'enabled'    => true,
		'post_types' => array( 'post' ),
		'crb_only'   => false,
		'fuzzy'      => true,
	);
	$raw = get_option( CRB_TITLE_REDIRECT_OPTION, array() );
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}
	$settings               = array_merge( $defaults, $raw );
	$settings['enabled']    = ! empty( $settings['enabled'] );
	$settings['crb_only']   = ! empty( $settings['crb_only'] );
	$settings['fuzzy']      = ! isset( $settings['fuzzy'] ) ? true : ! empty( $settings['fuzzy'] );
	$types                  = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
		? array_values( array_filter( array_map( 'sanitize_key', $settings['post_types'] ) ) )
		: array( 'post' );
	$settings['post_types'] = ! empty( $types ) ? $types : array( 'post' );
	return $settings;
}

/**
 * 表示・比較用の軽い正規化（前後空白・実体参照）。
 *
 * @param string $title Raw title.
 * @return string
 */
function crb_title_redirect_normalize_title( $title ) {
	$title = wp_strip_all_tags( (string) $title );
	$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$title = preg_replace( '/\s+/u', ' ', $title );
	return trim( (string) $title );
}

/**
 * 重複判定用キー（DL版接尾辞・作品ID・装飾ゴミを落とす）。
 *
 * @param string $title Raw title.
 * @return string
 */
function crb_title_redirect_match_key( $title ) {
	$t = crb_title_redirect_normalize_title( $title );
	if ( '' === $t ) {
		return '';
	}

	// CRB / 取り込み由来の装飾
	$t = preg_replace( '/\|\[\{.*?\}\]\s*$/u', '', $t );
	$t = preg_replace( '/\|\[.*?\]\s*$/u', '', $t );
	$t = preg_replace( '/のレビュー結果\s*$/u', '', $t );

	// 末尾の作品ID
	$t = preg_replace( '/\s*[RVJB]J\d{5,}\s*$/iu', '', $t );

	// 「 DL版 …」以降（再投稿時のノイズ接尾辞）
	$t = preg_replace( '/\s+DL版\b.*$/iu', '', $t );

	// 末尾の | 区切りメタ（サークル名・全年齢版・PC BLゲームなど）
	for ( $i = 0; $i < 4; $i++ ) {
		$prev = $t;
		$t    = preg_replace( '/\s*\|\s*[^|]+$/u', '', $t );
		if ( $prev === $t ) {
			break;
		}
	}

	// 末尾のコミック巻号・レーベル括弧のみ（意味のある「ボイスなし版」等は残す）
	$t = preg_replace( '/\s*\(COMIC[^)]*\)\s*$/iu', '', $t );
	$t = preg_replace( '/\s*（COMIC[^）]*）\s*$/iu', '', $t );
	$t = preg_replace( '/\s*\(\s*全年齢[^)]*\)\s*$/u', '', $t );
	$t = preg_replace( '/\s*（\s*全年齢[^）]*）\s*$/u', '', $t );

	$t = preg_replace( '/\s+/u', ' ', $t );
	$t = trim( (string) $t );
	if ( function_exists( 'mb_strtolower' ) ) {
		$t = mb_strtolower( $t, 'UTF-8' );
	} else {
		$t = strtolower( $t );
	}
	return $t;
}

/**
 * 近似キーの最低長（文字数）。短すぎると誤結合しやすい。
 *
 * @param string $key Match key.
 * @return bool
 */
function crb_title_redirect_key_long_enough( $key ) {
	$key = (string) $key;
	if ( '' === $key ) {
		return false;
	}
	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $key, 'UTF-8' ) >= 4;
	}
	return strlen( $key ) >= 8;
}

/**
 * タイトルから作品ID（RJ/BJ/VJ）を抽出。
 *
 * @param string $title Title.
 * @return array<int,string>
 */
function crb_title_redirect_extract_work_ids( $title ) {
	$ids  = array();
	$seen = array();
	if ( preg_match_all( '/\b([RVJB]J\d{5,})\b/i', (string) $title, $m ) ) {
		foreach ( $m[1] as $raw ) {
			$id = strtoupper( (string) $raw );
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$ids[]       = $id;
		}
	}
	return $ids;
}

/**
 * @param int $post_id Post ID.
 * @return bool
 */
function crb_title_redirect_is_crb_post( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return false;
	}
	$key = get_post_meta( $post_id, '_crb_item_key', true );
	return is_string( $key ) && '' !== $key;
}

/**
 * 公開投稿の ID / title / date を取得。
 *
 * @param string $post_type Post type.
 * @return array<int,array{id:int,title:string,date:string}>
 */
function crb_title_redirect_load_published( $post_type ) {
	global $wpdb;
	$post_type = sanitize_key( (string) $post_type );
	if ( '' === $post_type ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_title, post_date FROM {$wpdb->posts}
			WHERE post_status = 'publish' AND post_type = %s AND post_title <> ''
			ORDER BY post_date DESC, ID DESC",
			$post_type
		),
		ARRAY_A
	);
	$out = array();
	if ( ! is_array( $rows ) ) {
		return $out;
	}
	foreach ( $rows as $row ) {
		$id = (int) ( $row['ID'] ?? 0 );
		if ( $id <= 0 ) {
			continue;
		}
		$out[] = array(
			'id'    => $id,
			'title' => (string) ( $row['post_title'] ?? '' ),
			'date'  => (string) ( $row['post_date'] ?? '' ),
		);
	}
	return $out;
}

/**
 * 同一タイトル（完全一致）の公開投稿 ID 一覧（新しい順）。後方互換用。
 *
 * @param string $title     Title.
 * @param string $post_type Post type.
 * @param int    $exclude   Exclude ID (0 = none).
 * @return array<int,int>
 */
function crb_title_redirect_find_ids_by_title( $title, $post_type, $exclude = 0 ) {
	global $wpdb;

	$title     = trim( (string) $title );
	$post_type = sanitize_key( (string) $post_type );
	$exclude   = (int) $exclude;
	if ( '' === $title || '' === $post_type ) {
		return array();
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_status = 'publish'
				AND post_type = %s
				AND post_title = %s
			ORDER BY post_date DESC, ID DESC",
			$post_type,
			$title
		)
	);

	$out = array();
	foreach ( (array) $ids as $id ) {
		$id = (int) $id;
		if ( $id <= 0 || $id === $exclude ) {
			continue;
		}
		$out[] = $id;
	}
	return $out;
}

/**
 * 近似タイトル／作品IDを含む重複候補 ID（新しい順）。
 *
 * @param string $title     Seed title.
 * @param string $post_type Post type.
 * @param int    $exclude   Exclude ID.
 * @param bool   $fuzzy     Use normalized / work-id matching.
 * @return array<int,int>
 */
function crb_title_redirect_find_peer_ids( $title, $post_type, $exclude = 0, $fuzzy = true ) {
	$exclude = (int) $exclude;
	$exact   = crb_title_redirect_find_ids_by_title( $title, $post_type, 0 );
	if ( ! $fuzzy ) {
		$out = array();
		foreach ( $exact as $id ) {
			if ( $id !== $exclude ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	$seed_key = crb_title_redirect_match_key( $title );
	$seed_ids = crb_title_redirect_extract_work_ids( $title );
	$seed_set = array_fill_keys( $seed_ids, true );

	$matched = array();
	foreach ( crb_title_redirect_load_published( $post_type ) as $row ) {
		$id = (int) $row['id'];
		if ( $id <= 0 || $id === $exclude ) {
			continue;
		}
		$hit = false;
		if ( in_array( $id, $exact, true ) ) {
			$hit = true;
		}
		if ( ! $hit && '' !== $seed_key ) {
			$other_key = crb_title_redirect_match_key( $row['title'] );
			if ( '' !== $other_key && $other_key === $seed_key ) {
				// 短すぎるキーは誤爆しやすいので、完全一致タイトル以外は最低長を要求
				$seed_norm = crb_title_redirect_normalize_title( $title );
				if ( $seed_norm === crb_title_redirect_normalize_title( $row['title'] ) || crb_title_redirect_key_long_enough( $seed_key ) ) {
					$hit = true;
				}
			}
		}
		if ( ! $hit && $seed_set ) {
			foreach ( crb_title_redirect_extract_work_ids( $row['title'] ) as $wid ) {
				if ( isset( $seed_set[ $wid ] ) ) {
					$hit = true;
					break;
				}
			}
		}
		if ( $hit ) {
			$matched[] = $id;
		}
	}

	// load_published は新しい順なので matched も概ね新しい順。念のため unique。
	$out  = array();
	$seen = array();
	foreach ( $matched as $id ) {
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = true;
		$out[]       = $id;
	}
	return $out;
}

/**
 * タイトルグループの正（最新）投稿 ID。
 *
 * @param array<int,int> $ids IDs newest-first.
 * @param bool           $crb_only Only CRB-imported posts as candidates.
 * @return int
 */
function crb_title_redirect_pick_canonical( array $ids, $crb_only = false ) {
	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			continue;
		}
		if ( $crb_only && ! crb_title_redirect_is_crb_post( $id ) ) {
			continue;
		}
		if ( 'publish' !== get_post_status( $id ) ) {
			continue;
		}
		return $id;
	}
	return 0;
}

/**
 * 転送先を辿って最終 URL を決める（チェーン防止）。
 *
 * @param int $post_id Start.
 * @return int Final target ID (0 = none / stay).
 */
function crb_title_redirect_resolve_target( $post_id ) {
	$post_id = (int) $post_id;
	$seen    = array();
	$current = $post_id;
	for ( $i = 0; $i < 10; $i++ ) {
		if ( $current <= 0 || isset( $seen[ $current ] ) ) {
			return 0;
		}
		$seen[ $current ] = true;
		if ( get_post_meta( $current, CRB_TITLE_REDIRECT_META_DISABLE, true ) ) {
			return 0;
		}
		$to = (int) get_post_meta( $current, CRB_TITLE_REDIRECT_META_TO, true );
		if ( $to <= 0 || $to === $current ) {
			return $current === $post_id ? 0 : $current;
		}
		if ( 'publish' !== get_post_status( $to ) ) {
			return 0;
		}
		$current = $to;
	}
	return 0;
}

/**
 * 同一／近似タイトル群のリダイレクトメタを付け直す。
 *
 * @param int $post_id Seed post ID (used for title/type).
 * @return array{canonical:int,redirected:array<int,int>,cleared:array<int,int>}
 */
function crb_title_redirect_sync_group( $post_id ) {
	$result = array(
		'canonical'  => 0,
		'redirected' => array(),
		'cleared'    => array(),
	);
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) ) {
		return $result;
	}

	$settings = crb_title_redirect_settings();
	if ( empty( $settings['enabled'] ) ) {
		return $result;
	}
	if ( ! in_array( $post->post_type, $settings['post_types'], true ) ) {
		return $result;
	}
	if ( 'publish' !== $post->post_status ) {
		return $result;
	}

	$title = crb_title_redirect_normalize_title( $post->post_title );
	if ( '' === $title ) {
		return $result;
	}

	$fuzzy = ! empty( $settings['fuzzy'] );
	$ids   = crb_title_redirect_find_peer_ids( $post->post_title, $post->post_type, 0, $fuzzy );
	if ( count( $ids ) < 2 ) {
		if ( get_post_meta( $post_id, CRB_TITLE_REDIRECT_META_TO, true ) ) {
			delete_post_meta( $post_id, CRB_TITLE_REDIRECT_META_TO );
			$result['cleared'][] = $post_id;
		}
		return $result;
	}

	$crb_only  = ! empty( $settings['crb_only'] );
	$canonical = crb_title_redirect_pick_canonical( $ids, $crb_only );
	if ( $canonical <= 0 ) {
		return $result;
	}
	$result['canonical'] = $canonical;

	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			continue;
		}
		if ( $crb_only && ! crb_title_redirect_is_crb_post( $id ) ) {
			continue;
		}
		if ( get_post_meta( $id, CRB_TITLE_REDIRECT_META_DISABLE, true ) ) {
			continue;
		}
		if ( $id === $canonical ) {
			if ( get_post_meta( $id, CRB_TITLE_REDIRECT_META_TO, true ) ) {
				delete_post_meta( $id, CRB_TITLE_REDIRECT_META_TO );
				$result['cleared'][] = $id;
			}
			continue;
		}
		update_post_meta( $id, CRB_TITLE_REDIRECT_META_TO, $canonical );
		$result['redirected'][] = $id;
	}

	return $result;
}

/**
 * 公開保存時に同期。
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 * @param bool    $update  Update.
 */
function crb_title_redirect_on_save( $post_id, $post, $update ) {
	unset( $update );
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	crb_title_redirect_sync_group( (int) $post_id );
}
add_action( 'save_post', 'crb_title_redirect_on_save', 30, 3 );

/**
 * フロントで 301。
 */
function crb_title_redirect_template_redirect() {
	$settings = crb_title_redirect_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview() ) {
		return;
	}
	if ( ! is_singular() ) {
		return;
	}

	$post_id = (int) get_queried_object_id();
	if ( $post_id <= 0 ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}
	if ( ! in_array( $post->post_type, $settings['post_types'], true ) ) {
		return;
	}

	$target = crb_title_redirect_resolve_target( $post_id );
	if ( $target <= 0 || $target === $post_id ) {
		return;
	}

	$url = get_permalink( $target );
	if ( ! is_string( $url ) || '' === $url ) {
		return;
	}

	/**
	 * @param string $url     Destination.
	 * @param int    $from_id Old post.
	 * @param int    $to_id   New post.
	 */
	$url = (string) apply_filters( 'crb_title_redirect_destination', $url, $post_id, $target );

	nocache_headers();
	wp_safe_redirect( $url, 301 );
	exit;
}
add_action( 'template_redirect', 'crb_title_redirect_template_redirect', 1 );

/**
 * Union-Find で重複グループを構築して一括同期。
 *
 * @param int $limit Max groups to process (0 = all).
 * @return array{groups:int,redirected:int,fuzzy:bool}
 */
function crb_title_redirect_rebuild_all( $limit = 0 ) {
	$settings   = crb_title_redirect_settings();
	$post_types = $settings['post_types'];
	$fuzzy      = ! empty( $settings['fuzzy'] );
	$stats      = array(
		'groups'     => 0,
		'redirected' => 0,
		'fuzzy'      => $fuzzy,
	);
	if ( empty( $post_types ) ) {
		return $stats;
	}

	$parent = array();
	$rank   = array();

	$find = static function ( $x ) use ( &$parent, &$find ) {
		if ( ! isset( $parent[ $x ] ) ) {
			$parent[ $x ] = $x;
			return $x;
		}
		if ( $parent[ $x ] !== $x ) {
			$parent[ $x ] = $find( $parent[ $x ] );
		}
		return $parent[ $x ];
	};
	$union = static function ( $a, $b ) use ( &$parent, &$rank, $find ) {
		$ra = $find( $a );
		$rb = $find( $b );
		if ( $ra === $rb ) {
			return;
		}
		$ra_rank = $rank[ $ra ] ?? 0;
		$rb_rank = $rank[ $rb ] ?? 0;
		if ( $ra_rank < $rb_rank ) {
			$parent[ $ra ] = $rb;
		} elseif ( $ra_rank > $rb_rank ) {
			$parent[ $rb ] = $ra;
		} else {
			$parent[ $rb ] = $ra;
			$rank[ $ra ]   = $ra_rank + 1;
		}
	};

	foreach ( $post_types as $post_type ) {
		$rows = crb_title_redirect_load_published( $post_type );
		if ( count( $rows ) < 2 ) {
			continue;
		}

		$by_exact = array();
		$by_key   = array();
		$by_work  = array();

		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			$parent[ $id ] = $id;
			$exact = crb_title_redirect_normalize_title( $row['title'] );
			if ( '' !== $exact ) {
				$by_exact[ $exact ][] = $id;
			}
			if ( $fuzzy ) {
				$key = crb_title_redirect_match_key( $row['title'] );
				// 短すぎるキーは誤結合しやすいので近似バケットは最低長を要求。
				if ( crb_title_redirect_key_long_enough( $key ) ) {
					$by_key[ $key ][] = $id;
				}
				foreach ( crb_title_redirect_extract_work_ids( $row['title'] ) as $wid ) {
					$by_work[ $wid ][] = $id;
				}
			}
		}

		foreach ( array( $by_exact, $by_key, $by_work ) as $buckets ) {
			foreach ( $buckets as $ids ) {
				if ( count( $ids ) < 2 ) {
					continue;
				}
				$first = (int) $ids[0];
				foreach ( $ids as $id ) {
					$union( $first, (int) $id );
				}
			}
		}
	}

	$groups = array();
	foreach ( $parent as $id => $_ ) {
		$root = $find( (int) $id );
		$groups[ $root ][] = (int) $id;
	}

	$n = 0;
	foreach ( $groups as $ids ) {
		if ( count( $ids ) < 2 ) {
			continue;
		}
		if ( $limit > 0 && $n >= $limit ) {
			break;
		}
		// 新しい順に並べ替えて seed = 最新
		usort(
			$ids,
			static function ( $a, $b ) {
				$da = get_post_field( 'post_date', $a );
				$db = get_post_field( 'post_date', $b );
				if ( $da === $db ) {
					return (int) $b - (int) $a;
				}
				return strcmp( (string) $db, (string) $da );
			}
		);
		$seed = (int) $ids[0];
		$res  = crb_title_redirect_sync_group( $seed );
		++$stats['groups'];
		$stats['redirected'] += count( $res['redirected'] );
		++$n;
	}
	return $stats;
}

/**
 * REST: 一括スキャン。
 */
function crb_title_redirect_register_rest() {
	register_rest_route(
		'crb-title-redirect/v1',
		'/rebuild',
		array(
			'methods'             => 'POST',
			'permission_callback' => static function () {
				return current_user_can( 'manage_options' );
			},
			'callback'            => static function () {
				$stats = crb_title_redirect_rebuild_all( 0 );
				return rest_ensure_response(
					array(
						'ok'      => true,
						'version' => CRB_TITLE_REDIRECT_VERSION,
						'stats'   => $stats,
					)
				);
			},
		)
	);
	register_rest_route(
		'crb-title-redirect/v1',
		'/status',
		array(
			'methods'             => 'GET',
			'permission_callback' => static function () {
				return current_user_can( 'manage_options' );
			},
			'callback'            => static function () {
				return rest_ensure_response(
					array(
						'ok'       => true,
						'version'  => CRB_TITLE_REDIRECT_VERSION,
						'settings' => crb_title_redirect_settings(),
					)
				);
			},
		)
	);
}
add_action( 'rest_api_init', 'crb_title_redirect_register_rest' );

/**
 * 設定メニュー。
 */
function crb_title_redirect_admin_menu() {
	add_options_page(
		__( 'CRB Title Redirect', 'crb-title-redirect' ),
		__( 'CRB Title Redirect', 'crb-title-redirect' ),
		'manage_options',
		'crb-title-redirect',
		'crb_title_redirect_render_settings'
	);
}
add_action( 'admin_menu', 'crb_title_redirect_admin_menu' );

/**
 * 設定保存・スキャン。
 */
function crb_title_redirect_admin_post() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'crb-title-redirect' ) );
	}
	check_admin_referer( 'crb_title_redirect_save' );

	$action = isset( $_POST['crb_tr_action'] ) ? sanitize_key( wp_unslash( $_POST['crb_tr_action'] ) ) : 'save';

	if ( 'rebuild' === $action ) {
		$stats = crb_title_redirect_rebuild_all( 0 );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'crb-title-redirect',
					'crb_tr_rebuilt'    => '1',
					'crb_tr_groups'     => (int) $stats['groups'],
					'crb_tr_redirected' => (int) $stats['redirected'],
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	$types_raw = isset( $_POST['post_types'] ) ? (string) wp_unslash( $_POST['post_types'] ) : 'post';
	$types     = array_values(
		array_filter(
			array_map(
				'sanitize_key',
				preg_split( '/[\s,]+/', $types_raw ) ?: array()
			)
		)
	);
	if ( empty( $types ) ) {
		$types = array( 'post' );
	}

	update_option(
		CRB_TITLE_REDIRECT_OPTION,
		array(
			'enabled'    => ! empty( $_POST['enabled'] ),
			'crb_only'   => ! empty( $_POST['crb_only'] ),
			'fuzzy'      => ! empty( $_POST['fuzzy'] ),
			'post_types' => $types,
		),
		false
	);

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'         => 'crb-title-redirect',
				'crb_tr_saved' => '1',
			),
			admin_url( 'options-general.php' )
		)
	);
	exit;
}
add_action( 'admin_post_crb_title_redirect_save', 'crb_title_redirect_admin_post' );

/**
 * 設定画面。
 */
function crb_title_redirect_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = crb_title_redirect_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CRB Title Redirect', 'crb-title-redirect' ); ?></h1>
		<p><?php esc_html_e( 'タイトルが同じ／近似の公開投稿、または同一作品ID（RJ/BJ/VJ）があるとき、古い投稿の URL から新しい投稿へ 301 リダイレクトします。', 'crb-title-redirect' ); ?></p>
		<p><code>v<?php echo esc_html( CRB_TITLE_REDIRECT_VERSION ); ?></code></p>

		<?php if ( ! empty( $_GET['crb_tr_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '設定を保存しました。', 'crb-title-redirect' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['crb_tr_rebuilt'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				printf(
					/* translators: 1: groups, 2: redirected count */
					esc_html__( 'スキャン完了: 重複グループ %1$d 件 / リダイレクト設定 %2$d 件', 'crb-title-redirect' ),
					isset( $_GET['crb_tr_groups'] ) ? (int) $_GET['crb_tr_groups'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					isset( $_GET['crb_tr_redirected'] ) ? (int) $_GET['crb_tr_redirected'] : 0 // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				);
				?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'crb_title_redirect_save' ); ?>
			<input type="hidden" name="action" value="crb_title_redirect_save">
			<input type="hidden" name="crb_tr_action" value="save">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( '有効', 'crb-title-redirect' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
							<?php esc_html_e( '旧→新リダイレクトを有効にする', 'crb-title-redirect' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '投稿タイプ', 'crb-title-redirect' ); ?></th>
					<td>
						<input type="text" class="regular-text" name="post_types" value="<?php echo esc_attr( implode( ', ', $settings['post_types'] ) ); ?>">
						<p class="description"><?php esc_html_e( 'カンマ区切り。例: post, page', 'crb-title-redirect' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '近似マッチ', 'crb-title-redirect' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="fuzzy" value="1" <?php checked( ! empty( $settings['fuzzy'] ) ); ?>>
							<?php esc_html_e( 'DL版・作品ID・装飾接尾辞などを除いた近似タイトル／同一RJ・BJでも重複とみなす', 'crb-title-redirect' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'CRB 取り込みのみ', 'crb-title-redirect' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="crb_only" value="1" <?php checked( ! empty( $settings['crb_only'] ) ); ?>>
							<?php esc_html_e( 'Custom RSS Builder が付けたメタ（_crb_item_key）がある投稿だけ対象にする', 'crb-title-redirect' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( '設定を保存', 'crb-title-redirect' ) ); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( '既存投稿の一括スキャン', 'crb-title-redirect' ); ?></h2>
		<p><?php esc_html_e( 'すでに公開済みの同一／近似タイトルを洗い出し、旧→新のリダイレクトを付け直します。', 'crb-title-redirect' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('既存の同一／近似タイトルをスキャンしてリダイレクトを付け直します。よろしいですか？');">
			<?php wp_nonce_field( 'crb_title_redirect_save' ); ?>
			<input type="hidden" name="action" value="crb_title_redirect_save">
			<input type="hidden" name="crb_tr_action" value="rebuild">
			<?php submit_button( __( '今すぐスキャン', 'crb-title-redirect' ), 'secondary' ); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'メモ', 'crb-title-redirect' ); ?></h2>
		<ul style="list-style:disc;margin-left:1.4em;">
			<li><?php esc_html_e( '完全一致に加え、近似マッチ（既定オン）では DL版接尾辞・末尾 RJ/BJ・|[{…}] などを除いて比較します。', 'crb-title-redirect' ); ?></li>
			<li><?php esc_html_e( '新しい投稿（投稿日が新しい／同時刻なら ID が大きい）を残し、旧 URL は 301 します。', 'crb-title-redirect' ); ?></li>
			<li><?php echo wp_kses_post( __( '特定投稿だけ転送したくない場合は、その投稿にカスタムフィールド <code>_crb_title_redirect_disable</code> = <code>1</code> を付けてください。', 'crb-title-redirect' ) ); ?></li>
		</ul>
	</div>
	<?php
}

/**
 * 投稿編集画面に状態表示。
 *
 * @param WP_Post $post Post.
 */
function crb_title_redirect_metabox( $post ) {
	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}
	$to       = (int) get_post_meta( $post->ID, CRB_TITLE_REDIRECT_META_TO, true );
	$disabled = (bool) get_post_meta( $post->ID, CRB_TITLE_REDIRECT_META_DISABLE, true );
	echo '<p>';
	if ( $disabled ) {
		esc_html_e( 'この投稿はリダイレクト無効（_crb_title_redirect_disable）です。', 'crb-title-redirect' );
	} elseif ( $to > 0 ) {
		$url = get_edit_post_link( $to );
		printf(
			/* translators: %d: target post ID */
			esc_html__( 'この投稿は #%d へ 301 リダイレクトされます。', 'crb-title-redirect' ),
			$to
		);
		if ( $url ) {
			echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( '転送先を編集', 'crb-title-redirect' ) . '</a>';
		}
	} else {
		esc_html_e( 'この投稿からのタイトル転送は設定されていません（正側、または重複なし）。', 'crb-title-redirect' );
	}
	echo '</p>';
	$key = crb_title_redirect_match_key( $post->post_title );
	if ( '' !== $key ) {
		echo '<p class="description">match_key: <code>' . esc_html( $key ) . '</code></p>';
	}
}

/**
 * @param string  $post_type Post type.
 * @param WP_Post $post      Post.
 */
function crb_title_redirect_add_metabox( $post_type, $post ) {
	$settings = crb_title_redirect_settings();
	if ( ! in_array( $post_type, $settings['post_types'], true ) ) {
		return;
	}
	add_meta_box(
		'crb-title-redirect',
		__( 'CRB Title Redirect', 'crb-title-redirect' ),
		'crb_title_redirect_metabox',
		$post_type,
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'crb_title_redirect_add_metabox', 10, 2 );
