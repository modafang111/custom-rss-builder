<?php
/**
 * 正本サーバー（123789.jp）の練習用サンプル（WordPress 固定ページ）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_DEMO_SAMPLES_VERSION', '6-install-manual' );
define( 'CRB_DEMO_SAMPLES_OPTION_IDS', 'crb_demo_sample_page_ids' );
define( 'CRB_DEMO_SAMPLES_OPTION_INDEX', 'crb_demo_samples_sales_page_id' );
/** @deprecated Legacy option key (post IDs from older installs). */
define( 'CRB_DEMO_SAMPLES_OPTION_IDS_LEGACY', 'crb_demo_sample_post_ids' );
define( 'CRB_DEMO_SAMPLES_OPTION_INDEX_LEGACY', 'crb_demo_samples_index_page_id' );

/**
 * 正本サイトの URL（クライアントがサンプルページを開く先）。
 *
 * @return string
 */
function crb_demo_samples_authority_site_url() {
	if ( defined( 'CRB_LICENSE_API_BASE' ) && function_exists( 'crb_license_normalize_site_url' ) ) {
		$site = crb_license_normalize_site_url( (string) CRB_LICENSE_API_BASE );
		if ( '' !== $site ) {
			return $site;
		}
	}
	if ( function_exists( 'crb_license_is_authoritative_server' ) && crb_license_is_authoritative_server() ) {
		return crb_license_normalize_site_url( home_url( '/' ) );
	}
	return 'https://123789.jp/custom-rss-builder';
}

/**
 * @return bool
 */
function crb_demo_samples_can_manage_posts() {
	return function_exists( 'crb_license_is_authoritative_server' )
		&& crb_license_is_authoritative_server()
		&& current_user_can( 'edit_pages' );
}

/**
 * 販売・練習用の親固定ページ slug（子ページはこの下にぶら下げる）。
 *
 * @return string
 */
function crb_demo_samples_parent_slug() {
	$slug = 'crb-practice-samples';
	/**
	 * @param string $slug Parent page slug.
	 */
	return (string) apply_filters( 'crb_demo_samples_parent_slug', $slug );
}

/**
 * @return array<int, string> Demo-related slugs (parent + patterns).
 */
function crb_demo_samples_all_slugs() {
	$slugs = array( crb_demo_samples_parent_slug() );
	foreach ( crb_demo_sample_pattern_definitions() as $def ) {
		$slug = sanitize_title( (string) ( $def['slug'] ?? '' ) );
		if ( '' !== $slug ) {
			$slugs[] = $slug;
		}
	}
	return array_values( array_unique( $slugs ) );
}

/**
 * @param string $slug Page slug.
 * @return WP_Post|null
 */
function crb_demo_samples_find_page_by_slug( $slug ) {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug ) {
		return null;
	}
	$posts = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => 1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	if ( ! empty( $posts[0] ) && $posts[0] instanceof WP_Post ) {
		return $posts[0];
	}
	return null;
}

/**
 * 旧インストール（投稿 type=post やルート直下の練習ページ）を整理。
 */
function crb_demo_samples_purge_legacy_content() {
	$slugs = crb_demo_samples_all_slugs();
	foreach ( $slugs as $slug ) {
		$legacy_posts = get_posts(
			array(
				'name'           => $slug,
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => 20,
			)
		);
		foreach ( $legacy_posts as $post ) {
			if ( $post instanceof WP_Post ) {
				wp_trash_post( (int) $post->ID );
			}
		}
	}
}

/**
 * @return array<string, int> Pattern id => page ID.
 */
function crb_demo_samples_get_stored_page_ids() {
	$ids = get_option( CRB_DEMO_SAMPLES_OPTION_IDS, array() );
	if ( ! is_array( $ids ) || empty( $ids ) ) {
		$legacy = get_option( CRB_DEMO_SAMPLES_OPTION_IDS_LEGACY, array() );
		if ( is_array( $legacy ) ) {
			$ids = $legacy;
		}
	}
	return is_array( $ids ) ? $ids : array();
}

/**
 * @return int Sales / index page ID.
 */
function crb_demo_samples_get_sales_page_id() {
	$id = (int) get_option( CRB_DEMO_SAMPLES_OPTION_INDEX, 0 );
	if ( $id <= 0 ) {
		$id = (int) get_option( CRB_DEMO_SAMPLES_OPTION_INDEX_LEGACY, 0 );
	}
	return $id;
}

/**
 * 練習用パターンのセレクタを範囲・1件ぶん基準の相対形に揃える。
 *
 * @param array<string, string> $row Pattern row.
 * @return array<string, string>
 */
function crb_demo_normalize_pattern_selectors( array $row ) {
	if ( ! function_exists( 'crb_css_strip_selector_prefix' ) ) {
		return $row;
	}
	$scope = trim( (string) ( $row['scope_selector'] ?? '' ) );
	$item  = crb_css_strip_selector_prefix( $scope, (string) ( $row['item_selector'] ?? '' ) );
	$row['item_selector'] = $item;
	foreach ( array( 'link_selector', 'title_selector', 'summary_selector' ) as $key ) {
		$val = trim( (string) ( $row[ $key ] ?? '' ) );
		if ( '' === $val ) {
			continue;
		}
		$row[ $key ] = crb_css_strip_selector_prefix(
			$item,
			crb_css_strip_selector_prefix( $scope, $val )
		);
	}
	return $row;
}

/**
 * パターン定義（セレクタ・HTML ソースファイル名）。
 *
 * ②の「一覧の場所」内で効くよう、1件・リンク・タイトルは範囲からの相対指定にする（パターン5は tbody tr など）。
 *
 * @return array<int, array<string, string>>
 */
function crb_demo_sample_pattern_definitions() {
	$patterns = array(
		array(
			'id'               => 'simple-div',
			'label'            => __( 'パターン1: div が直接並ぶ（箱なし）', 'custom-rss-builder' ),
			'description'      => __( '一覧用の親要素がなく、同じ class の div が並ぶだけの形です。範囲は空欄のまま、1件ぶんの区切りだけ指定します。', 'custom-rss-builder' ),
			'slug'             => 'crb-sample-simple-div',
			'page_title'       => __( 'CRB 練習用 — パターン1: div が並ぶ', 'custom-rss-builder' ),
			'file'             => '01-simple-div.html',
			'scope_selector'   => '',
			'item_selector'    => '.crb-sample-news-item',
			'link_selector'    => 'a',
			'title_selector'   => 'a',
			'title_mode'       => 'text',
			'summary_selector' => '.crb-sample-date',
		),
		array(
			'id'               => 'list-wrapper',
			'label'            => __( 'パターン2: 親の箱 + 中に記事', 'custom-rss-builder' ),
			'description'      => __( 'いちばん一般的な形です。# または . で「一覧の箱」と「1記事ぶん」を分けて指定します。', 'custom-rss-builder' ),
			'slug'             => 'crb-sample-list-wrapper',
			'page_title'       => __( 'CRB 練習用 — パターン2: 箱 + 記事', 'custom-rss-builder' ),
			'file'             => '02-list-wrapper.html',
			'scope_selector'   => '.crb-sample-list',
			'item_selector'    => '.crb-sample-item',
			'link_selector'    => '.crb-sample-title a',
			'title_selector'   => '.crb-sample-title a',
			'title_mode'       => 'text',
			'summary_selector' => '.crb-sample-lead',
		),
		array(
			'id'               => 'ul-li',
			'label'            => __( 'パターン3: ul / li リスト', 'custom-rss-builder' ),
			'description'      => __( 'ニュースサイトでよくある ul > li 構造です。', 'custom-rss-builder' ),
			'slug'             => 'crb-sample-ul-li',
			'page_title'       => __( 'CRB 練習用 — パターン3: ul / li', 'custom-rss-builder' ),
			'file'             => '03-ul-li.html',
			'scope_selector'   => 'ul.crb-sample-ul',
			'item_selector'    => 'li.crb-sample-li',
			'link_selector'    => 'a.crb-sample-link',
			'title_selector'   => 'a.crb-sample-link',
			'title_mode'       => 'text',
			'summary_selector' => '',
		),
		array(
			'id'               => 'nested-feed',
			'label'            => __( 'パターン4: 入れ子の class（ニュース一覧風）', 'custom-rss-builder' ),
			'description'      => __( '外側の箱と、li 1行ぶんを別々に指定する練習用の HTML です。', 'custom-rss-builder' ),
			'slug'             => 'crb-sample-nested-feed',
			'page_title'       => __( 'CRB 練習用 — パターン4: 入れ子', 'custom-rss-builder' ),
			'file'             => '04-nested-feed.html',
			'scope_selector'   => '.crb-sample-feed',
			'item_selector'    => '.crb-sample-feed_list_item',
			'link_selector'    => 'a.crb-sample-feed_link',
			'title_selector'   => 'a.crb-sample-feed_link',
			'title_mode'       => 'text',
			'summary_selector' => 'time.crb-sample-feed_date',
		),
		array(
			'id'               => 'table-rows',
			'label'            => __( 'パターン5: table の行', 'custom-rss-builder' ),
			'description'      => __( '表形式の一覧です。範囲は table、1件ぶんは tbody tr です。', 'custom-rss-builder' ),
			'slug'             => 'crb-sample-table-rows',
			'page_title'       => __( 'CRB 練習用 — パターン5: table', 'custom-rss-builder' ),
			'file'             => '05-table-rows.html',
			'scope_selector'   => 'table.crb-sample-table',
			'item_selector'    => 'tbody tr',
			'link_selector'    => 'a',
			'title_selector'   => 'a',
			'title_mode'       => 'text',
			'summary_selector' => '',
		),
	);

	foreach ( $patterns as $i => $row ) {
		$patterns[ $i ] = crb_demo_normalize_pattern_selectors( $row );
	}

	return $patterns;
}

/**
 * @param string $filename Sample HTML file under samples/.
 * @return string Post content HTML.
 */
function crb_demo_samples_read_body_html( $filename ) {
	$path = CRB_PLUGIN_DIR . 'samples/' . ltrim( (string) $filename, '/' );
	if ( ! is_readable( $path ) ) {
		return '';
	}
	$html = (string) file_get_contents( $path );
	if ( preg_match( '/<body[^>]*>(.*)<\/body>/is', $html, $m ) ) {
		return trim( $m[1] );
	}
	return trim( $html );
}

/**
 * @param string $slug     Page slug.
 * @param string $title    Title.
 * @param string $content  HTML content.
 * @param int    $parent_id Parent page ID.
 * @return int Post ID or 0.
 */
function crb_demo_samples_upsert_page( $slug, $title, $content, $parent_id = 0 ) {
	$slug       = sanitize_title( (string) $slug );
	$parent_id  = (int) $parent_id;
	$existing   = crb_demo_samples_find_page_by_slug( $slug );
	$postarr    = array(
		'post_title'   => (string) $title,
		'post_name'    => $slug,
		'post_content' => (string) $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_parent'  => $parent_id,
	);
	if ( $existing instanceof WP_Post ) {
		$postarr['ID'] = (int) $existing->ID;
		$result        = wp_update_post( $postarr, true );
		return is_wp_error( $result ) ? 0 : (int) $result;
	}
	$result = wp_insert_post( $postarr, true );
	return is_wp_error( $result ) ? 0 : (int) $result;
}

/**
 * 販売用親固定ページの本文（製品概要 + 練習用子ページへのリンク）。
 *
 * @param array<int, array<string, string>> $patterns Patterns with url.
 * @return string
 */
function crb_demo_samples_build_sales_page_content( array $patterns ) {
	$lines = array(
		'<div class="crb-sales-page">',
		'<h2>' . esc_html__( 'Custom RSS Builder', 'custom-rss-builder' ) . '</h2>',
		'<p>' . esc_html__( '任意の Web ページから RSS フィードを作成し、WordPress へ取り込めるプラグインです。', 'custom-rss-builder' ) . '</p>',
		'<p>' . esc_html__( '下の練習用ページで HTML の構造（class 名）を確認しながら、お手持ちの WordPress（クライアントサイト）のフィード編集画面で設定を試せます。', 'custom-rss-builder' ) . '</p>',
		'<h3>' . esc_html__( '練習用サンプル（固定ページ）', 'custom-rss-builder' ) . '</h3>',
		'<ul class="crb-sample-index-list">',
	);
	foreach ( $patterns as $row ) {
		$url   = (string) ( $row['url'] ?? '' );
		$label = (string) ( $row['label'] ?? '' );
		if ( '' === $url ) {
			continue;
		}
		$scope = '' !== trim( (string) ( $row['scope_selector'] ?? '' ) )
			? (string) $row['scope_selector']
			: esc_html__( '（空欄）', 'custom-rss-builder' );
		$item  = (string) ( $row['item_selector'] ?? '' );
		$lines[] = sprintf(
			'<li><a href="%s">%s</a> — %s / %s <code>%s</code> + <code>%s</code></li>',
			esc_url( $url ),
			esc_html( $label ),
			esc_html__( '範囲', 'custom-rss-builder' ),
			esc_html__( '1件', 'custom-rss-builder' ),
			esc_html( $scope ),
			esc_html( $item )
		);
	}
	$lines[] = '</ul>';
	$lines[] = '<h3>' . esc_html__( 'サポート・手順', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ul class="crb-manual-index-list">';
	if ( function_exists( 'crb_install_manual_page_url' ) ) {
		$install_url = crb_install_manual_page_url();
		if ( '' !== $install_url ) {
			$lines[] = sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( $install_url ),
				esc_html__( 'Custom RSS Builder インストール手順', 'custom-rss-builder' )
			);
		}
	}
	if ( function_exists( 'crb_ai_manual_page_url' ) ) {
		$manual_url = crb_ai_manual_page_url();
		if ( '' !== $manual_url ) {
			$lines[] = sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( $manual_url ),
				esc_html__( 'Gemini API キー設定手順（Pro AI 変換）', 'custom-rss-builder' )
			);
		}
	}
	$lines[] = '</ul>';
	$lines[] = '<p class="crb-sales-note"><small>' . esc_html__( '※ ライセンスの購入・お問い合わせは、この固定ページの内容を編集して追記してください。', 'custom-rss-builder' ) . '</small></p>';
	$lines[] = '</div>';
	return implode( "\n", $lines );
}

/**
 * 正本サーバーに練習用固定ページを作成・更新。
 *
 * @param bool $force Force reinstall.
 * @return array{ok:bool,message:string,index_url:string}
 */
function crb_demo_samples_install( $force = false ) {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return array(
			'ok'        => false,
			'message'   => 'not authority',
			'index_url' => '',
		);
	}

	$stored_ver = (string) get_option( 'crb_demo_samples_install_version', '' );
	if ( ! $force && CRB_DEMO_SAMPLES_VERSION === $stored_ver ) {
		$sales_id = crb_demo_samples_get_sales_page_id();
		return array(
			'ok'        => true,
			'message'   => 'already installed',
			'index_url' => $sales_id > 0 ? (string) get_permalink( $sales_id ) : crb_demo_samples_index_url(),
		);
	}

	crb_demo_samples_purge_legacy_content();

	$definitions = crb_demo_sample_pattern_definitions();
	$post_ids    = array();
	$with_urls   = array();

	$parent_slug = crb_demo_samples_parent_slug();
	$parent_id   = crb_demo_samples_upsert_page(
		$parent_slug,
		__( 'Custom RSS Builder（製品・練習用）', 'custom-rss-builder' ),
		'<p>' . esc_html__( 'インストール処理中…', 'custom-rss-builder' ) . '</p>',
		0
	);
	if ( $parent_id <= 0 ) {
		return array(
			'ok'        => false,
			'message'   => 'parent page failed',
			'index_url' => '',
		);
	}

	foreach ( $definitions as $def ) {
		$content = crb_demo_samples_read_body_html( (string) ( $def['file'] ?? '' ) );
		if ( '' === $content ) {
			continue;
		}
		$post_id = crb_demo_samples_upsert_page(
			(string) ( $def['slug'] ?? '' ),
			(string) ( $def['page_title'] ?? '' ),
			$content,
			$parent_id
		);
		if ( $post_id <= 0 ) {
			continue;
		}
		$post_ids[ (string) ( $def['id'] ?? '' ) ] = $post_id;
		$def['url']                              = crb_demo_samples_pattern_public_url( $def, $post_id );
		$with_urls[]                             = $def;
	}

	if ( function_exists( 'crb_ai_manual_install' ) ) {
		crb_ai_manual_install( true );
	}
	if ( function_exists( 'crb_install_manual_install' ) ) {
		crb_install_manual_install( true );
	}

	$sales_content = crb_demo_samples_build_sales_page_content( $with_urls );
	$sales_id      = crb_demo_samples_upsert_page(
		$parent_slug,
		__( 'Custom RSS Builder（製品・練習用）', 'custom-rss-builder' ),
		$sales_content,
		0
	);
	if ( $sales_id <= 0 ) {
		$sales_id = $parent_id;
	}

	update_option( CRB_DEMO_SAMPLES_OPTION_IDS, $post_ids, false );
	update_option( CRB_DEMO_SAMPLES_OPTION_INDEX, (int) $sales_id, false );
	update_option( 'crb_demo_samples_install_version', CRB_DEMO_SAMPLES_VERSION, false );

	return array(
		'ok'        => ! empty( $post_ids ),
		'message'   => sprintf( 'installed %d patterns', count( $post_ids ) ),
		'index_url' => $sales_id > 0 ? (string) get_permalink( $sales_id ) : '',
	);
}

/**
 * init / activate 用。
 */
function crb_demo_samples_maybe_install() {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return;
	}
	crb_demo_samples_install( false );
}

/**
 * サンプル一覧ページ URL。
 *
 * @return string
 */
function crb_demo_samples_index_url() {
	$sales_id = crb_demo_samples_get_sales_page_id();
	if ( $sales_id > 0 ) {
		$url = get_permalink( $sales_id );
		if ( $url ) {
			return $url;
		}
	}
	return trailingslashit( crb_demo_samples_authority_site_url() ) . crb_demo_samples_parent_slug() . '/';
}

/**
 * クライアント向け: 正本サイト上の練習用固定ページ URL（親子階層を反映）。
 *
 * @param array<string, string> $pattern Pattern row (slug 必須).
 * @param int                  $page_id 正本に保存済みのページ ID（0 なら推測 URL）。
 * @return string
 */
function crb_demo_samples_pattern_public_url( array $pattern, $page_id = 0 ) {
	$page_id = (int) $page_id;
	if ( $page_id > 0 ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return $url;
		}
	}
	$slug = sanitize_title( (string) ( $pattern['slug'] ?? '' ) );
	if ( '' === $slug ) {
		return crb_demo_samples_authority_site_url();
	}
	$parent = crb_demo_samples_parent_slug();
	return trailingslashit( crb_demo_samples_authority_site_url() ) . $parent . '/' . $slug . '/';
}

/**
 * @param string $slug Page slug on authority site.
 * @return string
 */
function crb_demo_sample_url_by_slug( $slug ) {
	$slug     = sanitize_title( (string) $slug );
	$post_ids = crb_demo_samples_get_stored_page_ids();
	foreach ( crb_demo_sample_pattern_definitions() as $def ) {
		if ( sanitize_title( (string) ( $def['slug'] ?? '' ) ) !== $slug ) {
			continue;
		}
		$pid = (int) ( $post_ids[ (string) ( $def['id'] ?? '' ) ] ?? 0 );
		return crb_demo_samples_pattern_public_url( $def, $pid );
	}
	$pattern = array( 'slug' => $slug );
	return crb_demo_samples_pattern_public_url( $pattern, 0 );
}

/**
 * 練習用サンプル一覧（クライアント UI・リモート URL 用）。
 *
 * @return array<int, array<string, string>>
 */
function crb_get_demo_sample_patterns() {
	$patterns = crb_demo_sample_pattern_definitions();
	$post_ids = crb_demo_samples_get_stored_page_ids();
	$on_auth  = function_exists( 'crb_license_is_authoritative_server' ) && crb_license_is_authoritative_server();

	foreach ( $patterns as $i => $row ) {
		$id  = (string) ( $row['id'] ?? '' );
		$pid = (int) ( $post_ids[ $id ] ?? 0 );
		$patterns[ $i ]['url']      = crb_demo_samples_pattern_public_url( $row, $on_auth ? $pid : 0 );
		$patterns[ $i ]['index_url'] = crb_demo_samples_index_url();
		if ( $pid > 0 && $on_auth ) {
			$patterns[ $i ]['page_id'] = (string) $pid;
		}
	}

	/**
	 * @param array<int, array<string, string>> $patterns Patterns.
	 */
	return apply_filters( 'crb_demo_sample_patterns', $patterns );
}

/**
 * @deprecated Use crb_demo_samples_index_url().
 * @return string
 */
function crb_demo_samples_base_url() {
	return crb_demo_samples_index_url();
}

/**
 * @param string $id Pattern id.
 * @return array<string, string>|null
 */
function crb_get_demo_sample_pattern( $id ) {
	$id = sanitize_key( (string) $id );
	foreach ( crb_get_demo_sample_patterns() as $row ) {
		if ( ( $row['id'] ?? '' ) === $id ) {
			return $row;
		}
	}
	return null;
}

/**
 * ライセンスサーバー設定画面用: 再インストール POST。
 */
function crb_demo_samples_handle_admin_reinstall() {
	if ( ! crb_demo_samples_can_manage_posts() ) {
		wp_die( esc_html__( '権限がありません。', 'custom-rss-builder' ) );
	}
	check_admin_referer( 'crb_demo_samples_reinstall' );
	$result = crb_demo_samples_install( true );
	$redirect = add_query_arg(
		array(
			'page'                  => 'crb-license-server-settings',
			'crb-samples-reinstalled' => $result['ok'] ? '1' : '0',
		),
		admin_url( 'admin.php' )
	);
	wp_safe_redirect( $redirect );
	exit;
}

/**
 * クライアントが正本の練習用 URL 一覧を取得（公開 GET）。
 *
 * @return WP_REST_Response
 */
function crb_rest_demo_samples() {
	return new WP_REST_Response(
		array(
			'version'   => CRB_DEMO_SAMPLES_VERSION,
			'index_url' => crb_demo_samples_index_url(),
			'patterns'  => crb_get_demo_sample_patterns(),
		),
		200
	);
}

/**
 * @return void
 */
function crb_demo_samples_register_rest_route() {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return;
	}
	register_rest_route(
		'crb-license/v1',
		'/demo-samples',
		array(
			'methods'             => 'GET',
			'callback'            => 'crb_rest_demo_samples',
			'permission_callback' => '__return_true',
		)
	);
}

add_action( 'init', 'crb_demo_samples_maybe_install', 20 );
add_action( 'rest_api_init', 'crb_demo_samples_register_rest_route' );
add_action( 'admin_post_crb_demo_samples_reinstall', 'crb_demo_samples_handle_admin_reinstall' );
