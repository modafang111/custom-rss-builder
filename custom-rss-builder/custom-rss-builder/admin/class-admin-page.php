<?php
/**
 * 管理画面。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Admin_Page {

	const MENU_SLUG = 'custom-rss-builder';

	/** @var Custom_RSS_Builder_Feed_Manager */
	private $feed_manager;

	/** @var Custom_RSS_Builder_HTML_Fetcher */
	private $html_fetcher;

	/** @var Custom_RSS_Builder_HTML_Parser */
	private $html_parser;

	/** @var Custom_RSS_Builder_RSS_Generator */
	private $rss_generator;

	/** @var Custom_RSS_Builder_Post_Importer */
	private $post_importer;

	/** @var array<string, mixed> */
	private $preview_data = array();

	public function __construct( $feed_manager, $html_fetcher, $html_parser, $rss_generator, $post_importer ) {
		$this->feed_manager  = $feed_manager;
		$this->html_fetcher  = $html_fetcher;
		$this->html_parser   = $html_parser;
		$this->rss_generator = $rss_generator;
		$this->post_importer = $post_importer;
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_crb_discover_elements', array( $this, 'ajax_discover_elements' ) );
		add_action( 'wp_ajax_crb_discover_scope_html', array( $this, 'ajax_discover_scope_html' ) );
	}

	public function add_admin_menu() {
		add_menu_page(
			__( 'Custom RSS Builder', 'custom-rss-builder' ),
			__( 'Custom RSS Builder', 'custom-rss-builder' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-rss',
			58
		);
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}
		$css_path = CRB_PLUGIN_DIR . 'assets/css/admin.css';
		$js_path  = CRB_PLUGIN_DIR . 'assets/js/admin.js';
		$build    = defined( 'CRB_BUILD_ID' ) ? CRB_BUILD_ID : CRB_VERSION;
		$ver      = CRB_VERSION . '.' . $build . '.' . (string) filemtime( $css_path );
		$ver_js   = CRB_VERSION . '.' . $build . '.' . (string) filemtime( $js_path );

		// 管理画面は変更確認が最優先なので、当面は毎回キャッシュを破棄する。
		$nocache_suffix = '.' . (string) time();
		$ver           .= $nocache_suffix;
		$ver_js        .= $nocache_suffix;

		wp_enqueue_style(
			'custom-rss-builder-admin',
			CRB_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$ver
		);
		wp_enqueue_script(
			'custom-rss-builder-admin',
			CRB_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			$ver_js,
			true
		);
		wp_localize_script(
			'custom-rss-builder-admin',
			'crbAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'crb_discover_elements' ),
				'i18n'    => array(
					'discovering'  => __( '調べています…', 'custom-rss-builder' ),
					'discoverFail' => __( '要素の取得に失敗しました。', 'custom-rss-builder' ),
					'needUrl'      => __( '対象 URL を入力してください。', 'custom-rss-builder' ),
					'empty'        => __( '一致する要素がありませんでした。範囲セレクタを空にするか見直してください。', 'custom-rss-builder' ),
					'roleSlotIndex' => crb_discover_role_slot_indexes(),
					'sampleRecordsLead' => __( '抽出プレビュー（先頭%d件）', 'custom-rss-builder' ),
					'scopePreviewLead'  => __( 'この範囲での試し読み', 'custom-rss-builder' ),
					'colSlot'           => __( 'スロット', 'custom-rss-builder' ),
					'colExtract'        => __( '取り方', 'custom-rss-builder' ),
					'colValue'          => __( '取れた値', 'custom-rss-builder' ),
					'discoverHint'      => __( '④の設定とは別です。④へ反映する場合は「おすすめを一括入力」を使ってください。', 'custom-rss-builder' ),
					'noScopePreview'    => __( 'この範囲では取れる候補がありませんでした。', 'custom-rss-builder' ),
					'noValueInScope'    => __( '（範囲内で値なし）', 'custom-rss-builder' ),
					'imageAutoDetect'   => __( '（1件ブロック内の画像を自動検出）', 'custom-rss-builder' ),
					'recordSuffix' => __( '件目', 'custom-rss-builder' ),
					'applySuggested' => __( 'おすすめを一括入力', 'custom-rss-builder' ),
					'extractText'    => __( 'テキスト', 'custom-rss-builder' ),
					'extractHtml'    => __( 'HTML', 'custom-rss-builder' ),
					'extractSrc'     => __( '画像URL', 'custom-rss-builder' ),
					'extractHref'    => __( 'リンクURL', 'custom-rss-builder' ),
					'valueHint'    => __( '取得する値', 'custom-rss-builder' ),
				),
			)
		);
	}

	/**
	 * AJAX: 範囲内の要素候補一覧。
	 */
	public function ajax_discover_elements() {
		check_ajax_referer( 'crb_discover_elements', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'custom-rss-builder' ) ), 403 );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( '対象 URL を入力してください。', 'custom-rss-builder' ) ) );
		}

		$scope = isset( $_POST['scope_selector'] ) ? crb_sanitize_css_selector( wp_unslash( $_POST['scope_selector'] ) ) : '';

		$html = $this->html_fetcher->fetch_html( $url, true );
		if ( is_wp_error( $html ) ) {
			wp_send_json_error( array( 'message' => $html->get_error_message() ) );
		}

		$item_sel  = isset( $_POST['item_selector'] ) ? crb_sanitize_css_selector( wp_unslash( $_POST['item_selector'] ) ) : '';
		$discovery = new Custom_RSS_Builder_Element_Discovery();
		$result    = $discovery->discover( $html, $scope, $item_sel );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$suggested = crb_discover_suggest_slot_rules( $result['groups'] ?? array() );
		if ( ! empty( $suggested ) ) {
			$result['suggested_slots'] = $suggested;
		}

		$scope_preview = crb_build_discover_scope_preview( $html, $scope, $item_sel, $result['groups'] ?? array(), $url );
		$result['scope_preview']   = $scope_preview;
		$result['scope_slot_rows'] = $scope_preview['rows'] ?? array();

		$discover_config = crb_css_config_from_discover_suggested( $scope, $item_sel, $suggested );
		$has_discover_sel = '' !== trim( (string) ( $discover_config['link_selector'] ?? '' ) );
		if ( ! $has_discover_sel ) {
			foreach ( crb_extra_slot_storage_map() as $index => $meta ) {
				if ( '' !== trim( (string) ( $discover_config[ $meta['config_key'] ] ?? '' ) ) ) {
					$has_discover_sel = true;
					break;
				}
			}
		}

		if ( $has_discover_sel ) {
			$extractor = new Custom_RSS_Builder_Css_Extractor();
			$limit     = defined( 'CRB_RECORD_PREVIEW_LIMIT' ) ? (int) CRB_RECORD_PREVIEW_LIMIT : 3;
			$records   = $extractor->extract_preview_in_scope( $html, $discover_config, $url, $limit );
			if ( ! is_wp_error( $records ) && ! empty( $records ) ) {
				$result['sample_records'] = array_slice( crb_normalize_extract_rows_to_slots( $records ), 0, $limit );
				$result['sample_preview_limit'] = $limit;
				$result['slot_schema']          = crb_get_record_slot_schema_for_json( $discover_config );
			}
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: 指定した範囲（CSS セレクタ）に一致する DOM の生 HTML を返す。
	 */
	public function ajax_discover_scope_html() {
		check_ajax_referer( 'crb_discover_elements', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'custom-rss-builder' ) ), 403 );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( '対象 URL を入力してください。', 'custom-rss-builder' ) ) );
		}

		$scope = isset( $_POST['scope_selector'] ) ? crb_sanitize_css_selector( wp_unslash( $_POST['scope_selector'] ) ) : '';

		$html = $this->html_fetcher->fetch_html( $url, true );
		if ( is_wp_error( $html ) ) {
			wp_send_json_error( array( 'message' => $html->get_error_message() ) );
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			wp_send_json_error( array( 'message' => __( 'DOM 拡張が利用できません。', 'custom-rss-builder' ) ), 500 );
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
			wp_send_json_error( array( 'message' => __( 'HTML の解析に失敗しました。', 'custom-rss-builder' ) ) );
		}

		$xpath = new DOMXPath( $dom );
		$root  = $dom->documentElement; // <div id="crb-root">

		// 範囲が空ならトップ（解析後の root）を返す。
		if ( '' === trim( (string) $scope ) ) {
			$scope_node_html = (string) $dom->saveHTML( $root );
			wp_send_json_success(
				array(
					'scope_selector'  => '',
					'scope_label'     => __( 'ページ全体', 'custom-rss-builder' ),
					'scope_match_count' => 1,
					'scope_html'      => $scope_node_html,
				)
			);
		}

		$query = crb_css_to_xpath( $scope );
		if ( is_wp_error( $query ) ) {
			wp_send_json_error( array( 'message' => __( '範囲セレクタが不正です。', 'custom-rss-builder' ) ) );
		}

		$nodes = $xpath->query( $query, $root );
		if ( false === $nodes || 0 === $nodes->length ) {
			wp_send_json_error(
				array(
					'message'          => __( '範囲セレクタに一致する要素がありません。', 'custom-rss-builder' ),
					'scope_selector'  => $scope,
					'scope_match_count' => 0,
				)
			);
		}

		$node = $nodes->item( 0 );
		if ( ! $node instanceof DOMElement ) {
			wp_send_json_error( array( 'message' => __( '範囲の HTML を抽出できませんでした。', 'custom-rss-builder' ) ) );
		}

		$scope_node_html = (string) $dom->saveHTML( $node );

		$max_chars = 120000;
		$is_trunc   = false;
		if ( mb_strlen( $scope_node_html, 'UTF-8' ) > $max_chars ) {
			$is_trunc = true;
			if ( function_exists( 'mb_strcut' ) ) {
				$scope_node_html = mb_strcut( $scope_node_html, 0, $max_chars, 'UTF-8' ) . "\n<!-- (truncated) -->";
			} else {
				$scope_node_html = substr( $scope_node_html, 0, $max_chars ) . "\n<!-- (truncated) -->";
			}
		}

		wp_send_json_success(
			array(
				'scope_selector'     => $scope,
				'scope_label'        => trim( (string) $scope ),
				'scope_match_count'  => (int) $nodes->length,
				'scope_html'         => $scope_node_html,
				'scope_truncated'   => $is_trunc,
			)
		);
	}

	public function handle_form_submission() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST['crb_action'] ) || empty( $_POST['crb_nonce'] ) ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || self::MENU_SLUG !== $_GET['page'] ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['crb_nonce'] ) ), 'crb_admin_action' ) ) {
			wp_die( esc_html__( 'セキュリティチェックに失敗しました。', 'custom-rss-builder' ) );
		}

		$action  = sanitize_key( wp_unslash( $_POST['crb_action'] ) );
		$feed_id = isset( $_POST['feed_id'] ) ? (int) $_POST['feed_id'] : 0;

		switch ( $action ) {
			case 'save':
				$this->handle_save( $feed_id );
				break;
			case 'preview':
			case 'preview_posts':
				$this->handle_preview();
				break;
			case 'refresh':
				$this->handle_refresh( $feed_id );
				break;
			case 'import_posts':
				$this->handle_import_posts( $feed_id );
				break;
			case 'delete':
				$this->handle_delete( $feed_id );
				break;
		}
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$feed   = null;

		if ( 'edit' === $action ) {
			$feed_id = isset( $_GET['feed_id'] ) ? (int) $_GET['feed_id'] : 0;
			if ( $feed_id > 0 ) {
				$feed = $this->feed_manager->get_feed( $feed_id );
			}
		}

		echo '<div class="wrap crb-admin-wrap">';
		echo '<h1>' . esc_html__( 'Custom RSS Builder', 'custom-rss-builder' ) . '</h1>';

		$this->render_admin_notices();

		if ( 'edit' === $action ) {
			$preview_data = $this->preview_data;
			include CRB_PLUGIN_DIR . 'admin/views/edit-feed.php';
		} else {
			$feeds = $this->feed_manager->get_feeds();
			include CRB_PLUGIN_DIR . 'admin/views/settings-page.php';
		}

		echo '</div>';
	}

	private function render_admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['crb_message'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_key( wp_unslash( $_GET['crb_message'] ) );
		$map  = array(
			'saved'          => array( 'success', __( 'フィードを保存しました。', 'custom-rss-builder' ) ),
			'deleted'        => array( 'success', __( 'フィードを削除しました。', 'custom-rss-builder' ) ),
			'refreshed'      => array( 'success', __( 'HTMLキャッシュを更新しました。', 'custom-rss-builder' ) ),
			'import_ok'      => array( 'success', __( '投稿への取り込みが完了しました。', 'custom-rss-builder' ) ),
			'import_partial' => array( 'warning', __( '投稿への取り込みが一部完了しました。詳細は下記を確認してください。', 'custom-rss-builder' ) ),
			'import_error'   => array( 'error', __( '投稿への取り込みに失敗しました。', 'custom-rss-builder' ) ),
		);
		if ( ! isset( $map[ $code ] ) ) {
			return;
		}
		list( $type, $text ) = $map[ $code ];
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $text )
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['crb_detail'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-info inline"><p><code>' . esc_html( sanitize_text_field( wp_unslash( $_GET['crb_detail'] ) ) ) . '</code></p></div>';
		}
	}

	private function handle_save( $feed_id ) {
		$data = $this->collect_feed_data_from_post( $feed_id );
		if ( '' === $data['name'] || '' === $data['url'] ) {
			wp_die( esc_html__( '必須項目を入力してください。', 'custom-rss-builder' ) );
		}
		if ( 'css' === crb_get_feed_extraction_mode( $data ) ) {
			$css = $data['css'] ?? array();
			if ( '' === trim( (string) ( $css['item_selector'] ?? '' ) ) && '' === trim( (string) ( $css['link_selector'] ?? '' ) ) ) {
				wp_die( esc_html__( '1件ブロックまたは {%2} の CSS セレクタを指定してください。', 'custom-rss-builder' ) );
			}
		}
		if ( 'template' === crb_get_feed_extraction_mode( $data ) && '' === trim( (string) ( $data['template'] ?? '' ) ) ) {
			wp_die( esc_html__( '抽出テンプレートを入力してください。', 'custom-rss-builder' ) );
		}

		$new_id = $this->feed_manager->save_feed( $data );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'action'      => 'edit',
					'feed_id'     => $new_id,
					'crb_message' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function handle_delete( $feed_id ) {
		if ( $feed_id > 0 ) {
			$this->feed_manager->delete_feed( $feed_id );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'crb_message' => 'deleted',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function handle_refresh( $feed_id ) {
		$feed = $this->feed_manager->get_feed( $feed_id );
		if ( null === $feed ) {
			wp_die( esc_html__( 'フィードが見つかりません。', 'custom-rss-builder' ) );
		}
		$this->html_fetcher->fetch_html( $feed['url'], true );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'action'      => 'edit',
					'feed_id'     => $feed_id,
					'crb_message' => 'refreshed',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function handle_import_posts( $feed_id ) {
		if ( $feed_id <= 0 ) {
			wp_die( esc_html__( 'フィードが見つかりません。', 'custom-rss-builder' ) );
		}

		$data = $this->collect_feed_data_from_post( $feed_id );
		$this->feed_manager->save_feed( $data );

		$result = $this->post_importer->import_feed( $feed_id, true );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => self::MENU_SLUG,
						'action'      => 'edit',
						'feed_id'     => $feed_id,
						'crb_message' => 'import_error',
						'crb_detail'  => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$detail = sprintf(
			/* translators: 1: created count, 2: skipped count */
			__( '新規 %1$d 件 / スキップ %2$d 件', 'custom-rss-builder' ),
			(int) $result['created'],
			(int) $result['skipped']
		);
		if ( ! empty( $result['errors'] ) ) {
			$detail .= ' / ' . implode( '; ', array_map( 'strval', $result['errors'] ) );
		}

		$message = ( ! empty( $result['errors'] ) ) ? 'import_partial' : 'import_ok';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'action'      => 'edit',
					'feed_id'     => $feed_id,
					'crb_message' => $message,
					'crb_detail'  => rawurlencode( $detail ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function handle_preview() {
		$data = $this->collect_feed_data_from_post( isset( $_POST['feed_id'] ) ? (int) $_POST['feed_id'] : 0 );
		$html = $this->html_fetcher->fetch_html( $data['url'], true );
		if ( is_wp_error( $html ) ) {
			$this->preview_data = array( 'error' => $html->get_error_message() );
			return;
		}

		$this->preview_data = array(
			'html_snippet' => mb_substr( $html, 0, 2000 ) . ( mb_strlen( $html ) > 2000 ? '...' : '' ),
		);

		$parsed = crb_extract_items_from_html( $html, $data );
		if ( is_wp_error( $parsed ) ) {
			$this->preview_data['error'] = $parsed->get_error_message();
			return;
		}

		$this->preview_data['rows']          = $parsed;
		$this->preview_data['preview_limit'] = defined( 'CRB_RECORD_PREVIEW_LIMIT' ) ? (int) CRB_RECORD_PREVIEW_LIMIT : 3;
		$this->preview_data['plugin_version'] = CRB_VERSION;
		if ( 'css' === crb_get_feed_extraction_mode( $data ) ) {
			$this->preview_data['extraction_mode'] = 'css';
		} elseif ( '' !== trim( (string) ( $data['scope_template'] ?? '' ) ) ) {
			$this->preview_data['scope_applied'] = true;
		}

		$feed_for_preview = array(
			'url'     => $data['url'],
			'mapping' => $data['mapping'],
			'import'  => $data['import'],
		);

		$this->preview_data['import_posts'] = $this->post_importer->preview_import_items( $feed_for_preview, $parsed, 5 );
	}

	/**
	 * @param int $feed_id Existing feed ID.
	 * @return array<string, mixed>
	 */
	private function collect_feed_data_from_post( $feed_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$import_enabled = ! empty( $_POST['import_enabled'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$append_source = ! empty( $_POST['import_append_source'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$extraction_mode = sanitize_key( wp_unslash( $_POST['extraction_mode'] ?? 'css' ) );
		if ( ! in_array( $extraction_mode, array( 'template', 'css' ), true ) ) {
			$extraction_mode = 'css';
		}

		$css_raw = array(
			'scope_selector'        => isset( $_POST['css_scope_selector'] ) ? wp_unslash( $_POST['css_scope_selector'] ) : '',
			'item_selector'         => isset( $_POST['css_item_selector'] ) ? wp_unslash( $_POST['css_item_selector'] ) : '',
			'link_selector'         => isset( $_POST['css_link_selector'] ) ? wp_unslash( $_POST['css_link_selector'] ) : '',
			'title_mode'            => isset( $_POST['css_title_mode'] ) ? wp_unslash( $_POST['css_title_mode'] ) : 'attr',
			'title_attr'            => isset( $_POST['css_title_attr'] ) ? wp_unslash( $_POST['css_title_attr'] ) : 'title',
			'title_selector'        => isset( $_POST['css_title_selector'] ) ? wp_unslash( $_POST['css_title_selector'] ) : '',
			'image_selector'        => isset( $_POST['css_image_selector'] ) ? wp_unslash( $_POST['css_image_selector'] ) : '',
			'author_selector'       => isset( $_POST['css_author_selector'] ) ? wp_unslash( $_POST['css_author_selector'] ) : '',
			'review_title_selector' => isset( $_POST['css_review_title_selector'] ) ? wp_unslash( $_POST['css_review_title_selector'] ) : '',
			'summary_selector'      => isset( $_POST['css_summary_selector'] ) ? wp_unslash( $_POST['css_summary_selector'] ) : '',
			'category_selector'     => isset( $_POST['css_category_selector'] ) ? wp_unslash( $_POST['css_category_selector'] ) : '',
			'review_body_selector'  => isset( $_POST['css_review_body_selector'] ) ? wp_unslash( $_POST['css_review_body_selector'] ) : '',
			'slot_selector_9'       => isset( $_POST['css_slot_selector_9'] ) ? wp_unslash( $_POST['css_slot_selector_9'] ) : '',
			'slot_selector_10'      => isset( $_POST['css_slot_selector_10'] ) ? wp_unslash( $_POST['css_slot_selector_10'] ) : '',
			'slot_selector_11'      => isset( $_POST['css_slot_selector_11'] ) ? wp_unslash( $_POST['css_slot_selector_11'] ) : '',
			'slot_selector_12'      => isset( $_POST['css_slot_selector_12'] ) ? wp_unslash( $_POST['css_slot_selector_12'] ) : '',
			'slot_mode_3'           => isset( $_POST['css_slot_mode_3'] ) ? wp_unslash( $_POST['css_slot_mode_3'] ) : '',
			'slot_mode_4'           => isset( $_POST['css_slot_mode_4'] ) ? wp_unslash( $_POST['css_slot_mode_4'] ) : '',
			'slot_mode_5'           => isset( $_POST['css_slot_mode_5'] ) ? wp_unslash( $_POST['css_slot_mode_5'] ) : '',
			'slot_mode_6'           => isset( $_POST['css_slot_mode_6'] ) ? wp_unslash( $_POST['css_slot_mode_6'] ) : '',
			'slot_mode_7'           => isset( $_POST['css_slot_mode_7'] ) ? wp_unslash( $_POST['css_slot_mode_7'] ) : '',
			'slot_mode_8'           => isset( $_POST['css_slot_mode_8'] ) ? wp_unslash( $_POST['css_slot_mode_8'] ) : '',
			'slot_mode_9'           => isset( $_POST['css_slot_mode_9'] ) ? wp_unslash( $_POST['css_slot_mode_9'] ) : '',
			'slot_mode_10'          => isset( $_POST['css_slot_mode_10'] ) ? wp_unslash( $_POST['css_slot_mode_10'] ) : '',
			'slot_mode_11'          => isset( $_POST['css_slot_mode_11'] ) ? wp_unslash( $_POST['css_slot_mode_11'] ) : '',
			'slot_mode_12'          => isset( $_POST['css_slot_mode_12'] ) ? wp_unslash( $_POST['css_slot_mode_12'] ) : '',
		);

		return array(
			'id'               => $feed_id,
			'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'url'              => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'extraction_mode'  => $extraction_mode,
			'css'              => crb_sanitize_css_config( $css_raw ),
			'scope_template'   => crb_get_template_from_post( 'scope_template' ),
			'template'         => crb_get_template_from_post( 'template' ),
			'mapping'  => array(
				'link'        => (int) ( $_POST['map_link'] ?? 0 ),
				'title'       => (int) ( $_POST['map_title'] ?? 1 ),
				'description' => (int) ( $_POST['map_description'] ?? 2 ),
				'date'        => (int) ( $_POST['map_date'] ?? 3 ),
			),
			'import'   => array(
				'enabled'             => $import_enabled,
				'post_status'         => sanitize_key( wp_unslash( $_POST['import_post_status'] ?? 'draft' ) ),
				'post_type'           => sanitize_key( wp_unslash( $_POST['import_post_type'] ?? 'post' ) ),
				'post_title_template' => crb_get_import_template_from_post( 'import_post_title_template' ),
				'content_template'    => crb_get_import_template_from_post( 'import_content_template' ),
				'append_source'       => $append_source,
				'category_id'         => (int) ( $_POST['import_category_id'] ?? 0 ),
				'author_id'           => (int) ( $_POST['import_author_id'] ?? 0 ),
			),
		);
	}
}
