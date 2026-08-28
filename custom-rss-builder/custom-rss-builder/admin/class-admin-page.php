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


	/** @var Custom_RSS_Builder_RSS_Generator */
	private $rss_generator;

	/** @var Custom_RSS_Builder_Post_Importer */
	private $post_importer;

	/** @var array<string, mixed> */
	private $preview_data = array();

	public function __construct( $feed_manager, $html_fetcher, $rss_generator, $post_importer ) {
		$this->feed_manager  = $feed_manager;
		$this->html_fetcher  = $html_fetcher;
		$this->rss_generator = $rss_generator;
		$this->post_importer = $post_importer;
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_csv_export' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_crb_discover_elements', array( $this, 'ajax_discover_elements' ) );
		add_action( 'wp_ajax_crb_discover_scope_html', array( $this, 'ajax_discover_scope_html' ) );
		add_action( 'wp_ajax_crb_admin_feed', array( $this, 'ajax_admin_feed' ) );
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
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'crb_discover_elements' ),
				'formNonce'  => wp_create_nonce( 'crb_admin_action' ),
				'editBaseUrl' => add_query_arg(
					array(
						'page'   => self::MENU_SLUG,
						'action' => 'edit',
					),
					admin_url( 'admin.php' )
				),
				'version'       => CRB_VERSION,
				'build'         => defined( 'CRB_BUILD_ID' ) ? CRB_BUILD_ID : '',
				'licensePlan'   => crb_license_get_state()['plan'],
				'licenseUsable' => crb_license_get_state()['usable'],
				'maxSlotCount'  => function_exists( 'crb_license_get_record_slot_count' )
					? crb_license_get_record_slot_count()
					: ( defined( 'CRB_RECORD_SLOT_COUNT' ) ? (int) CRB_RECORD_SLOT_COUNT : 20 ),
				'demoSamples'   => function_exists( 'crb_get_demo_sample_patterns' )
					? crb_get_demo_sample_patterns()
					: array(),
				'demoSamplesIndex' => function_exists( 'crb_demo_samples_index_url' )
					? crb_demo_samples_index_url()
					: '',
				'i18n'          => array(
					'bulkSelectFeeds'   => __( 'フィードを1件以上選択してください。', 'custom-rss-builder' ),
					'bulkChooseAction'  => __( '一括操作を選択してください。', 'custom-rss-builder' ),
					'bulkDeleteConfirm' => __( '選択したフィードを削除します。よろしいですか？', 'custom-rss-builder' ),
					'applySamplePreset' => __( '選択したパターンを入力欄に反映', 'custom-rss-builder' ),
					'fillSampleUrl'     => __( '対象 URL にサンプルページを入れる', 'custom-rss-builder' ),
					'chooseSample'      => __( 'パターンを選んでください。', 'custom-rss-builder' ),
					'discovering'  => __( '調べています…', 'custom-rss-builder' ),
					'discoverFail' => __( '要素の取得に失敗しました。', 'custom-rss-builder' ),
					'needUrl'      => __( '対象 URL を入力してください。', 'custom-rss-builder' ),
					'empty'        => __( '一致する要素がありませんでした。範囲セレクタを空にするか見直してください。', 'custom-rss-builder' ),
					'colCount'          => __( '回数', 'custom-rss-builder' ),
					'colSlot'           => __( 'スロット', 'custom-rss-builder' ),
					'colExtract'        => __( '取り方', 'custom-rss-builder' ),
					'colValue'          => __( '取れた値', 'custom-rss-builder' ),
					'discoverInScope'   => __( '取れる値を一覧表示（範囲内）', 'custom-rss-builder' ),
					'discoverPage'      => __( '取れる値を一覧表示', 'custom-rss-builder' ),
					'discoverScopeHtml' => __( '範囲の HTML を確認', 'custom-rss-builder' ),
					'scopeHtmlNeedsScope'   => __( '「一覧の場所」が空欄のときは使えません（省略可の欄です）', 'custom-rss-builder' ),
					'scopeHtmlPreviewLead'  => __( '範囲の HTML プレビュー', 'custom-rss-builder' ),
					'extractText'    => __( 'テキスト', 'custom-rss-builder' ),
					'extractHtml'    => __( 'HTML', 'custom-rss-builder' ),
					'extractSrc'     => __( '画像URL', 'custom-rss-builder' ),
					'extractHref'    => __( 'リンクURL', 'custom-rss-builder' ),
					'valueHint'    => __( '取得する値', 'custom-rss-builder' ),
					'extractCandidatesLead' => __( '範囲内で取れる値の一覧（CSS セレクタ・取り方・値）。どれをスロットに使うかは値を見て選んでください。', 'custom-rss-builder' ),
					'extractCandidatesCount' => __( '%d 件', 'custom-rss-builder' ),
					'extractCandidatesSlotHint' => sprintf(
						/* translators: %s: max slot token e.g. {%20%} */
						__( '回数は範囲内の一致件数です。{%%1%%}・{%%2%%} は④で設定します。スロット列では {%%3%%}〜%s を選ぶと、④の追加スロット欄に入ります。', 'custom-rss-builder' ),
						'{%' . ( function_exists( 'crb_license_pro_slot_count' ) ? (int) crb_license_pro_slot_count() : 20 ) . '%}'
					),
					'scopeSlotPreviewTitle' => __( '試し読み', 'custom-rss-builder' ),
					'scopeSlotPreviewItem' => __( '%d件目', 'custom-rss-builder' ),
					'scopeSlotEmpty'       => __( '—', 'custom-rss-builder' ),
					'applySequentialProposals' => __( '提案を④に反映', 'custom-rss-builder' ),
					'sequentialProposalHint' => __( '{%3} から順番に割り当てた自動提案です。値を確認し、問題なければ④に反映してください。', 'custom-rss-builder' ),
					'sequentialProposalApplied' => __( '④に反映しました。試し読みを更新しています…', 'custom-rss-builder' ),
					'extractCandidatesAdvanced' => __( '詳細: 候補一覧（上級者向け）', 'custom-rss-builder' ),
					'saving'           => __( '保存しています…', 'custom-rss-builder' ),
					'previewing'       => __( 'プレビューしています…', 'custom-rss-builder' ),
					'working'          => __( '処理しています…', 'custom-rss-builder' ),
					'requestFail'      => __( '通信に失敗しました。', 'custom-rss-builder' ),
					'exportFeed'       => __( '設定をエクスポート', 'custom-rss-builder' ),
					'exporting'        => __( 'エクスポートしています…', 'custom-rss-builder' ),
					'exportDone'       => __( 'JSON ファイルをダウンロードしました。', 'custom-rss-builder' ),
					'exportFail'       => __( 'エクスポートに失敗しました。', 'custom-rss-builder' ),
					'exportNeedSave'   => __( '保存済みのフィードのみエクスポートできます。先に保存してください。', 'custom-rss-builder' ),
					'importFeed'       => __( '設定をインポート', 'custom-rss-builder' ),
					'importDone'       => __( 'フォームに反映しました。保存で確定します。', 'custom-rss-builder' ),
					'importFail'       => __( 'インポートに失敗しました。', 'custom-rss-builder' ),
					'importInvalidFile' => __( 'JSON ファイルを選択してください。', 'custom-rss-builder' ),
				),
			)
		);
	}

	/**
	 * AJAX: 保存・プレビュー・取り込み（ページリロードなし）。
	 */
	public function ajax_admin_feed() {
		check_ajax_referer( 'crb_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'custom-rss-builder' ) ) );
		}

		crb_license_prepare_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action  = isset( $_POST['crb_action'] ) ? sanitize_key( wp_unslash( $_POST['crb_action'] ) ) : '';
		$feed_id = isset( $_POST['feed_id'] ) ? (int) $_POST['feed_id'] : 0;

		switch ( $action ) {
			case 'save':
				$this->ajax_handle_save( $feed_id );
				break;
			case 'preview':
			case 'preview_posts':
				$this->ajax_handle_preview( $feed_id );
				break;
			case 'import_posts':
				$this->ajax_handle_import( $feed_id );
				break;
			case 'export':
				$this->ajax_handle_export( $feed_id );
				break;
			case 'import_pack':
				$this->ajax_handle_import_pack();
				break;
			default:
				wp_send_json_error( array( 'message' => __( '不明な操作です。', 'custom-rss-builder' ) ) );
		}
	}

	/**
	 * @param int $feed_id Feed ID.
	 */
	/**
	 * @param string $feature License feature slug.
	 * @return WP_Error|null
	 */
	private function license_gate_error( $feature ) {
		if ( crb_license_can( $feature ) ) {
			return null;
		}
		return new WP_Error( 'crb_license_denied', crb_license_denied_message( $feature ) );
	}

	private function ajax_handle_save( $feed_id ) {
		$is_new = $feed_id <= 0 || ! $this->feed_manager->get_feed( $feed_id );
		if ( $is_new ) {
			$gate = $this->license_gate_error( 'create_feed' );
			if ( is_wp_error( $gate ) ) {
				wp_send_json_error( array( 'message' => $gate->get_error_message() ) );
			}
		}
		$gate = $this->license_gate_error( 'save' );
		if ( is_wp_error( $gate ) ) {
			wp_send_json_error( array( 'message' => $gate->get_error_message() ) );
		}

		$data = $this->collect_feed_data_from_post( $feed_id );
		if ( '' === $data['name'] || '' === $data['url'] ) {
			wp_send_json_error( array( 'message' => __( '必須項目を入力してください。', 'custom-rss-builder' ) ) );
		}
		if ( 'css' === crb_get_feed_extraction_mode( $data ) ) {
			$css = $data['css'] ?? array();
			if ( ! function_exists( 'crb_css_config_has_extraction_path' ) || ! crb_css_config_has_extraction_path( $css ) ) {
				$message = function_exists( 'crb_css_missing_extraction_path_message' )
					? crb_css_missing_extraction_path_message()
					: __( '抽出の指定がありません。', 'custom-rss-builder' );
				wp_send_json_error( array( 'message' => $message ) );
			}
		}

		$new_id  = $this->feed_manager->save_feed( $data );
		$message = __( 'フィードを保存しました。', 'custom-rss-builder' );
		$type    = 'success';
		$lr      = is_array( $data['link_rewrite'] ?? null ) ? $data['link_rewrite'] : array();
		if ( ! empty( $lr['enabled'] ) && empty( $lr['rules'] ) ) {
			$message = __( 'フィードを保存しました。リンク変換ルールが空のため URL 変換は行われません。', 'custom-rss-builder' );
			$type    = 'warning';
		}
		wp_send_json_success(
			array(
				'feed_id'      => (int) $new_id,
				'message'      => $message,
				'message_type' => $type,
				'edit_url'     => add_query_arg(
					array(
						'page'    => self::MENU_SLUG,
						'action'  => 'edit',
						'feed_id' => (int) $new_id,
					),
					admin_url( 'admin.php' )
				),
			)
		);
	}

	/**
	 * @param int $feed_id Feed ID.
	 */
	private function ajax_handle_preview( $feed_id ) {
		$preview_data = $this->build_preview_data_from_post( $feed_id );
		$data         = $this->collect_feed_data_from_post( $feed_id );
		$fragments    = $this->render_preview_html_fragments( $preview_data, $data );

		wp_send_json_success(
			array(
				'extract_html' => $fragments['extract_html'],
				'import_html'  => $fragments['import_html'],
			)
		);
	}

	/**
	 * @param int $feed_id Feed ID.
	 */
	private function ajax_handle_import( $feed_id ) {
		$gate = $this->license_gate_error( 'import_posts' );
		if ( is_wp_error( $gate ) ) {
			wp_send_json_error( array( 'message' => $gate->get_error_message() ) );
		}
		if ( $feed_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( '先にフィードを保存してください。', 'custom-rss-builder' ) ) );
		}
		$data = $this->collect_feed_data_from_post( $feed_id );
		$this->feed_manager->save_feed( $data );

		$result = $this->post_importer->import_feed( $feed_id, 'manual' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
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

		$message_type = ! empty( $result['errors'] ) ? 'warning' : 'success';
		$message      = ! empty( $result['errors'] )
			? __( '投稿への取り込みが一部完了しました。詳細は下記を確認してください。', 'custom-rss-builder' )
			: __( '投稿への取り込みが完了しました。', 'custom-rss-builder' );

		wp_send_json_success(
			array(
				'message'      => $message,
				'message_type' => $message_type,
				'detail'       => $detail,
			)
		);
	}

	/**
	 * 保存済みフィードの設定パックを JSON で返す（DB の内容。フォーム未保存分は含めない）。
	 *
	 * @param int $feed_id Feed ID.
	 */
	private function ajax_handle_export( $feed_id ) {
		$feed_id = (int) $feed_id;
		if ( $feed_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( '保存済みのフィードのみエクスポートできます。先に保存してください。', 'custom-rss-builder' ) )
			);
		}

		$feed = $this->feed_manager->get_feed( $feed_id );
		if ( ! is_array( $feed ) ) {
			wp_send_json_error( array( 'message' => __( 'フィードが見つかりません。', 'custom-rss-builder' ) ) );
		}

		if ( ! function_exists( 'crb_export_feed_pack' ) ) {
			wp_send_json_error( array( 'message' => __( 'エクスポート機能が利用できません。', 'custom-rss-builder' ) ) );
		}

		$pack = crb_export_feed_pack( $feed );
		if ( is_wp_error( $pack ) ) {
			wp_send_json_error( array( 'message' => $pack->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'JSON ファイルをダウンロードしました。', 'custom-rss-builder' ),
				'message_type' => 'success',
				'pack'         => $pack,
				'pack_json'    => crb_feed_pack_to_json( $pack ),
				'filename'     => crb_feed_pack_export_filename( $feed, $feed_id ),
			)
		);
	}

	/**
	 * JSON 設定パックを検証し、フォーム反映用フィールドを返す（DB には書かない）。
	 */
	private function ajax_handle_import_pack() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$json = isset( $_POST['pack_json'] ) ? wp_unslash( $_POST['pack_json'] ) : '';
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			wp_send_json_error( array( 'message' => __( 'JSON が空です。', 'custom-rss-builder' ) ) );
		}

		if ( ! function_exists( 'crb_parse_feed_pack_json' ) || ! function_exists( 'crb_feed_pack_to_form_fields' ) ) {
			wp_send_json_error( array( 'message' => __( 'インポート機能が利用できません。', 'custom-rss-builder' ) ) );
		}

		$pack = crb_parse_feed_pack_json( $json );
		if ( is_wp_error( $pack ) ) {
			wp_send_json_error( array( 'message' => $pack->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'フォームに反映しました。保存で確定します。', 'custom-rss-builder' ),
				'message_type' => 'success',
				'fields'       => crb_feed_pack_to_form_fields( $pack ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $preview_data Preview payload.
	 * @param array<string, mixed> $feed_data    Feed config from POST.
	 * @return array{extract_html: string, import_html: string}
	 */
	private function render_preview_html_fragments( array $preview_data, array $feed_data ) {
		$feed         = $feed_data;
		$has_rows     = ! empty( $preview_data['rows'] ) && is_array( $preview_data['rows'] );
		$has_import   = isset( $preview_data['import_posts'] );
		$extract_html = '';
		$import_html  = '';

		ob_start();
		if ( ! empty( $preview_data['error'] ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( (string) $preview_data['error'] ) . '</p></div>';
		} elseif ( $has_rows ) {
			include CRB_PLUGIN_DIR . 'admin/views/preview-results.php';
		} else {
			echo '<div class="notice notice-warning inline"><p>';
			esc_html_e( '抽出できた件数が 0 です。対象 URL・1件ブロック・各スロットのセレクタを確認してください。', 'custom-rss-builder' );
			echo '</p></div>';
			$preview_data['rows'] = array();
			include CRB_PLUGIN_DIR . 'admin/views/preview-results.php';
		}
		$extract_html = (string) ob_get_clean();

		ob_start();
		if ( $has_import ) {
			include CRB_PLUGIN_DIR . 'admin/views/import-preview-results.php';
		} else {
			echo '<p class="crb-panel__placeholder">';
			esc_html_e( '「プレビュー（全件）」または「投稿プレビュー」を実行すると、取り込み後の表示がここに出ます。', 'custom-rss-builder' );
			echo '</p>';
		}
		$import_html = (string) ob_get_clean();

		return array(
			'extract_html' => $extract_html,
			'import_html'  => $import_html,
		);
	}

	/**
	 * @param int $feed_id Feed ID from POST.
	 * @return array<string, mixed>
	 */
	private function build_preview_data_from_post( $feed_id ) {
		$data         = $this->collect_feed_data_from_post( $feed_id );
		$preview_data = array();

		$html = $this->html_fetcher->fetch_html(
			$data['url'],
			array(
				'context' => 'admin',
				'feed'    => $data,
			)
		);
		if ( is_wp_error( $html ) ) {
			return array( 'error' => $html->get_error_message() );
		}

		$parsed = crb_extract_items_from_html( $html, $data );

		if ( is_wp_error( $parsed ) ) {
			$preview_data['error'] = $parsed->get_error_message();
			return $preview_data;
		}

		if ( function_exists( 'crb_ai_transform_rows_result' ) ) {
			$ai_result = crb_ai_transform_rows_result( $data, $parsed, 'preview' );
			$parsed    = is_array( $ai_result['rows'] ?? null ) ? $ai_result['rows'] : $parsed;
			if ( ! empty( $ai_result['applied'] ) ) {
				$preview_data['ai_applied'] = true;
			}
			if ( ! empty( $ai_result['errors'] ) ) {
				$preview_data['ai_errors'] = $ai_result['errors'];
			}
			if ( ! empty( $ai_result['warnings'] ) ) {
				$preview_data['ai_warnings'] = $ai_result['warnings'];
			}
			if ( ! empty( $ai_result['stats'] ) && is_array( $ai_result['stats'] ) ) {
				$preview_data['ai_stats'] = $ai_result['stats'];
				if ( function_exists( 'crb_ai_transform_stats_summary' ) ) {
					$summary = crb_ai_transform_stats_summary( $ai_result['stats'] );
					if ( '' !== $summary ) {
						$preview_data['ai_summary'] = $summary;
					}
				}
			}
		}

		$preview_data['rows']           = $parsed;
		$preview_data['preview_limit']  = defined( 'CRB_RECORD_PREVIEW_LIMIT' ) ? (int) CRB_RECORD_PREVIEW_LIMIT : 3;
		$preview_data['plugin_version'] = CRB_VERSION;
		if ( 'css' === crb_get_feed_extraction_mode( $data ) ) {
			$preview_data['extraction_mode'] = 'css';
		} elseif ( '' !== trim( (string) ( $data['scope_template'] ?? '' ) ) ) {
			$preview_data['scope_applied'] = true;
		}

		$feed_for_preview = $data;

		if ( crb_license_can( 'preview_posts' ) ) {
			$preview_data['import_posts'] = $this->post_importer->preview_import_items( $feed_for_preview, $parsed, 5 );
		} else {
			$preview_data['import_posts'] = array(
				'error' => crb_license_denied_message( 'preview_posts' ),
			);
		}

		return $preview_data;
	}

	/**
	 * AJAX: 範囲内の要素候補一覧。
	 */
	public function ajax_discover_elements() {
		check_ajax_referer( 'crb_discover_elements', 'nonce' );

		if ( function_exists( 'crb_discover_prepare_ajax' ) ) {
			crb_discover_prepare_ajax();
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'custom-rss-builder' ) ), 403 );
		}

		crb_license_prepare_request();
		$gate = $this->license_gate_error( 'discover' );
		if ( is_wp_error( $gate ) ) {
			wp_send_json_error( array( 'message' => $gate->get_error_message() ) );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( '対象 URL を入力してください。', 'custom-rss-builder' ) ) );
		}

		$scope = isset( $_POST['scope_selector'] ) ? crb_sanitize_css_selector( wp_unslash( $_POST['scope_selector'] ) ) : '';

		$html = $this->html_fetcher->fetch_html( $url );
		if ( is_wp_error( $html ) ) {
			wp_send_json_error( array( 'message' => $html->get_error_message() ) );
		}

		$item_sel  = isset( $_POST['item_selector'] ) ? crb_sanitize_css_selector( wp_unslash( $_POST['item_selector'] ) ) : '';
		$discovery = new Custom_RSS_Builder_Element_Discovery( true );
		$result    = $discovery->discover( $html, $scope, $item_sel, 1, $url );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$suggested = crb_discover_suggest_slot_rules( $result['groups'] ?? array() );
		if ( ! empty( $suggested ) ) {
			$result['suggested_slots'] = $suggested;
		}

		$form_probe = function_exists( 'crb_css_config_from_discover_request' )
			? crb_css_config_from_discover_request( $scope, $item_sel )
			: null;

		$candidates = crb_build_extract_candidates( $html, $scope, $item_sel, $url, true );
		if ( is_wp_error( $candidates ) ) {
			$result['extract_candidates_error'] = $candidates->get_error_message();
			$result['item_selector_error']      = $candidates->get_error_message();
		} else {
			$result['extract_candidates'] = $candidates;
		}

		$use_sequential = ! ( is_array( $form_probe ) && function_exists( 'crb_css_config_has_assigned_slots' ) && crb_css_config_has_assigned_slots( $form_probe ) );
		$sequential     = array();
		if ( $use_sequential && function_exists( 'crb_build_sequential_slot_proposals' ) ) {
			$candidate_rows = is_array( $candidates ) && ! empty( $candidates['rows'] ) ? $candidates['rows'] : array();
			$sequential     = crb_build_sequential_slot_proposals( $result['groups'] ?? array(), $candidate_rows );
			if ( ! empty( $sequential ) ) {
				$result['sequential_proposals'] = $sequential;
			}
		}

		$scope_preview = crb_build_discover_scope_preview(
			$html,
			$scope,
			$item_sel,
			$result['groups'] ?? array(),
			$url,
			true,
			$form_probe,
			$use_sequential ? $sequential : null
		);
		$result['scope_preview']   = $scope_preview;
		$result['scope_slot_rows'] = $scope_preview['rows'] ?? array();
		if ( ! empty( $scope_preview['item_selector_error'] ) ) {
			$result['item_selector_error'] = (string) $scope_preview['item_selector_error'];
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: 指定した範囲（CSS セレクタ）に一致する DOM の生 HTML を返す。
	 */
	public function ajax_discover_scope_html() {
		check_ajax_referer( 'crb_discover_elements', 'nonce' );

		if ( function_exists( 'crb_discover_prepare_ajax' ) ) {
			crb_discover_prepare_ajax();
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'custom-rss-builder' ) ), 403 );
		}

		crb_license_prepare_request();
		$gate = $this->license_gate_error( 'discover' );
		if ( is_wp_error( $gate ) ) {
			wp_send_json_error( array( 'message' => $gate->get_error_message() ) );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( '対象 URL を入力してください。', 'custom-rss-builder' ) ) );
		}

		$scope = isset( $_POST['scope_selector'] ) ? crb_sanitize_css_selector( wp_unslash( $_POST['scope_selector'] ) ) : '';

		$html = $this->html_fetcher->fetch_html( $url );
		if ( is_wp_error( $html ) ) {
			wp_send_json_error( array( 'message' => $html->get_error_message() ) );
		}

		$dom = crb_scope_dom_load( $html );
		if ( is_wp_error( $dom ) ) {
			wp_send_json_error( array( 'message' => $dom->get_error_message() ) );
		}

		$root = crb_dom_parse_root( $dom );
		if ( is_wp_error( $root ) ) {
			wp_send_json_error( array( 'message' => $root->get_error_message() ) );
		}

		$xpath = new DOMXPath( $dom );

		// 範囲が空なら crb-root 全体を返す。
		if ( '' === trim( (string) $scope ) ) {
			$scope_node_html = (string) $dom->saveHTML( $root );
			wp_send_json_success(
				array(
					'scope_selector'    => '',
					'scope_label'       => __( 'ページ全体', 'custom-rss-builder' ),
					'scope_match_count' => 1,
					'scope_html'        => $scope_node_html,
				)
			);
		}

		if ( is_wp_error( crb_css_to_xpath( $scope ) ) ) {
			wp_send_json_error( array( 'message' => __( '範囲セレクタが不正です。', 'custom-rss-builder' ) ) );
		}

		$scope_el = crb_scope_resolve_element( $xpath, $root, $scope );
		if ( ! ( $scope_el instanceof DOMElement ) ) {
			$message     = crb_scope_explain_miss( $html, $url, $scope );
			$suggestions = crb_scope_suggestions_for_url( $url, $xpath, $root );
			wp_send_json_error(
				array(
					'message'            => $message,
					'scope_selector'     => $scope,
					'scope_match_count'  => 0,
					'scope_suggestions'  => $suggestions,
				)
			);
		}

		$match_count     = count( crb_scope_query_elements( $xpath, $root, $scope ) );
		$scope_node_html = (string) $dom->saveHTML( $scope_el );

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
				'scope_match_count'  => $match_count,
				'scope_html'         => $scope_node_html,
				'scope_truncated'   => $is_trunc,
			)
		);
	}

	public function handle_form_submission() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		crb_license_prepare_request();

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
				$this->handle_preview_posts();
				break;
			case 'import_posts':
				$this->handle_import_posts( $feed_id );
				break;
			case 'delete':
				$this->handle_delete( $feed_id );
				break;
			case 'bulk_list_action':
				$this->handle_bulk_list_action();
				break;
			case 'bulk_update':
				$this->handle_bulk_update();
				break;
			case 'csv_import':
				$this->handle_csv_import();
				break;
		}
	}

	/**
	 * Stream current feeds as CSV download.
	 */
	public function maybe_handle_csv_export() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['crb_csv_export'] ) || '1' !== (string) wp_unslash( $_GET['crb_csv_export'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		check_admin_referer( 'crb_csv_export' );

		if ( ! function_exists( 'crb_export_feeds_csv' ) ) {
			wp_die( esc_html__( 'CSV エクスポート機能を読み込めませんでした。', 'custom-rss-builder' ) );
		}

		$csv = crb_export_feeds_csv( $this->feed_manager->get_feeds() );
		$filename = 'custom-rss-builder-feeds-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) strlen( $csv ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw CSV binary download.
		echo $csv;
		exit;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		crb_license_prepare_request();

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$feed   = null;

		if ( 'edit' === $action ) {
			$feed_id = isset( $_GET['feed_id'] ) ? (int) $_GET['feed_id'] : 0;
			if ( $feed_id > 0 ) {
				$feed = $this->feed_manager->get_feed( $feed_id );
			}
			if ( ! is_array( $feed ) ) {
				$duplicate_from = isset( $_GET['duplicate_from'] ) ? (int) $_GET['duplicate_from'] : 0;
				if ( $duplicate_from > 0 ) {
					$source_feed = $this->feed_manager->get_feed( $duplicate_from );
					if ( is_array( $source_feed ) ) {
						$feed         = $source_feed;
						$feed['id']   = 0;
						$source_name  = isset( $source_feed['name'] ) ? (string) $source_feed['name'] : '';
						$feed['name'] = sprintf(
							/* translators: %s: source feed name */
							__( '%s のコピー', 'custom-rss-builder' ),
							$source_name
						);
					}
				}
			}
		}

		echo '<div class="wrap crb-admin-wrap">';
		$this->render_admin_page_header();

		$this->render_admin_notices();

		if ( 'edit' === $action ) {
			$preview_data = $this->preview_data;
			include CRB_PLUGIN_DIR . 'admin/views/edit-feed.php';
		} else {
			$feeds              = $this->feed_manager->get_feeds();
			$crb_bulk_feed_ids  = $this->get_bulk_edit_feed_ids_from_request();
			include CRB_PLUGIN_DIR . 'admin/views/settings-page.php';
		}

		echo '</div>';
	}

	private function render_admin_page_header() {
		$info  = crb_get_plugin_version_info();
		$ver   = (string) $info['version'];
		$build = (string) $info['build'];

		echo '<div class="crb-admin-header">';
		echo '<h1>';
		echo esc_html__( 'Custom RSS Builder', 'custom-rss-builder' );
		if ( '' !== $ver ) {
			printf(
				' <small class="crb-version-inline">%s</small>',
				esc_html(
					sprintf(
						/* translators: %s: version number */
						__( 'バージョン %s', 'custom-rss-builder' ),
						$ver
					)
				)
			);
		}
		echo '</h1>';

		if ( '' !== $ver ) {
			$line = 'v' . $ver;
			if ( '' !== $build ) {
				$line .= ' · ' . sprintf(
					/* translators: %s: build id */
					__( 'ビルド %s', 'custom-rss-builder' ),
					$build
				);
			}
			printf(
				'<div class="notice notice-info inline crb-version-notice"><p><strong>%s</strong></p></div>',
				esc_html( $line )
			);
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
			'bulk_updated'   => array( 'success', __( '選択したフィードを一括更新しました。', 'custom-rss-builder' ) ),
			'bulk_denied'    => array( 'error', __( '一括更新できませんでした。ライセンスまたは権限を確認してください。', 'custom-rss-builder' ) ),
			'bulk_deleted'   => array( 'success', __( '選択したフィードを削除しました。', 'custom-rss-builder' ) ),
			'bulk_no_change' => array( 'warning', __( '変更する項目が選ばれていません。', 'custom-rss-builder' ) ),
			'bulk_link_rules_invalid' => array( 'error', __( 'リンク変換ルールを保存できませんでした。正規表現を使う場合は「正規表現を使う」にチェックし、パターンと置換の両方を入力してください。', 'custom-rss-builder' ) ),
			'link_rewrite_empty' => array( 'warning', __( 'フィードを保存しました。リンク変換ルールが空のため URL 変換は行われません。パターンと置換を入力してください。', 'custom-rss-builder' ) ),
			'bulk_no_feeds'  => array( 'error', __( 'フィードが選択されていません。', 'custom-rss-builder' ) ),
			'bulk_invalid'   => array( 'error', __( '一括操作を選択してください。', 'custom-rss-builder' ) ),
			'import_ok'      => array( 'success', __( '投稿への取り込みが完了しました。', 'custom-rss-builder' ) ),
			'import_partial' => array( 'warning', __( '投稿への取り込みが一部完了しました。詳細は下記を確認してください。', 'custom-rss-builder' ) ),
			'import_error'   => array( 'error', __( '投稿への取り込みに失敗しました。', 'custom-rss-builder' ) ),
			'csv_imported'   => array( 'success', __( 'CSV からフィードを登録しました。', 'custom-rss-builder' ) ),
			'csv_partial'    => array( 'warning', __( 'CSV 登録が一部完了しました。詳細を確認してください。', 'custom-rss-builder' ) ),
			'csv_error'      => array( 'error', __( 'CSV 登録に失敗しました。', 'custom-rss-builder' ) ),
		);
		if ( ! isset( $map[ $code ] ) ) {
			return;
		}
		list( $type, $text ) = $map[ $code ];

		if ( in_array( $code, array( 'csv_imported', 'csv_partial', 'csv_error' ), true ) ) {
			$this->render_csv_import_notice( $code, $type, $text );
			return;
		}

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

	/**
	 * CSV import result notice (counts + first few row errors).
	 *
	 * @param string $code Notice code.
	 * @param string $type Notice type.
	 * @param string $text Default message.
	 */
	private function render_csv_import_notice( $code, $type, $text ) {
		$transient_key = 'crb_csv_import_result_' . get_current_user_id();
		$result        = get_transient( $transient_key );
		delete_transient( $transient_key );

		$created         = is_array( $result ) ? (int) ( $result['created'] ?? 0 ) : 0;
		$skipped         = is_array( $result ) ? (int) ( $result['skipped'] ?? 0 ) : 0;
		$license_stopped = is_array( $result ) ? (int) ( $result['license_stopped'] ?? 0 ) : 0;
		$errors          = is_array( $result ) && ! empty( $result['errors'] ) && is_array( $result['errors'] )
			? $result['errors']
			: array();

		if ( is_array( $result ) ) {
			$text = sprintf(
				/* translators: 1: created count, 2: skipped count, 3: license-stopped remaining count */
				__( 'CSV 登録結果: 成功 %1$d 件 / スキップ %2$d 件 / ライセンス上限で未処理 %3$d 件', 'custom-rss-builder' ),
				$created,
				$skipped,
				$license_stopped
			);
			if ( $created > 0 && 0 === $skipped && 0 === $license_stopped ) {
				$type = 'success';
			} elseif ( $created > 0 ) {
				$type = 'warning';
			} else {
				$type = 'error';
			}
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $text )
		);

		if ( empty( $errors ) ) {
			return;
		}

		$max   = 8;
		$shown = array_slice( $errors, 0, $max );
		echo '<div class="notice notice-info inline"><ul class="crb-csv-import-errors">';
		foreach ( $shown as $err ) {
			$line = isset( $err['line'] ) ? (int) $err['line'] : 0;
			$msg  = isset( $err['message'] ) ? (string) $err['message'] : '';
			printf(
				'<li>%s</li>',
				esc_html(
					$line > 0
						? sprintf(
							/* translators: 1: CSV line number, 2: error message */
							__( '%1$d行目: %2$s', 'custom-rss-builder' ),
							$line,
							$msg
						)
						: $msg
				)
			);
		}
		$remaining = count( $errors ) - count( $shown );
		if ( $remaining > 0 ) {
			printf(
				'<li>%s</li>',
				esc_html(
					sprintf(
						/* translators: %d: remaining error count */
						__( 'ほか %d 件のエラーがあります。', 'custom-rss-builder' ),
						$remaining
					)
				)
			);
		}
		echo '</ul></div>';
	}

	/**
	 * Handle CSV bulk feed create from list page.
	 */
	private function handle_csv_import() {
		crb_license_prepare_request();

		$redirect_result = static function ( $message, array $result = array() ) {
			if ( ! empty( $result ) ) {
				set_transient( 'crb_csv_import_result_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
			}
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => self::MENU_SLUG,
						'crb_message' => $message,
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		};

		$raw = '';
		if ( ! empty( $_FILES['crb_csv_file']['tmp_name'] ) && is_uploaded_file( $_FILES['crb_csv_file']['tmp_name'] ) ) {
			$size = isset( $_FILES['crb_csv_file']['size'] ) ? (int) $_FILES['crb_csv_file']['size'] : 0;
			if ( $size > 512 * 1024 ) {
				$redirect_result(
					'csv_error',
					array(
						'created'         => 0,
						'skipped'         => 0,
						'license_stopped' => 0,
						'errors'          => array(
							array(
								'line'    => 0,
								'message' => __( 'CSV ファイルが大きすぎます（最大 512KB）。', 'custom-rss-builder' ),
							),
						),
					)
				);
			}
			$contents = file_get_contents( $_FILES['crb_csv_file']['tmp_name'] );
			if ( false !== $contents ) {
				$raw = $contents;
			}
		}

		if ( '' === trim( $raw ) && isset( $_POST['crb_csv_text'] ) ) {
			$raw = (string) wp_unslash( $_POST['crb_csv_text'] );
		}

		if ( ! function_exists( 'crb_parse_feed_csv' ) || ! function_exists( 'crb_import_feeds_from_csv_rows' ) ) {
			$redirect_result(
				'csv_error',
				array(
					'created'         => 0,
					'skipped'         => 0,
					'license_stopped' => 0,
					'errors'          => array(
						array(
							'line'    => 0,
							'message' => __( 'CSV 登録機能を読み込めませんでした。', 'custom-rss-builder' ),
						),
					),
				)
			);
		}

		$parsed = crb_parse_feed_csv( $raw );
		if ( is_wp_error( $parsed ) ) {
			$redirect_result(
				'csv_error',
				array(
					'created'         => 0,
					'skipped'         => 0,
					'license_stopped' => 0,
					'errors'          => array(
						array(
							'line'    => 0,
							'message' => $parsed->get_error_message(),
						),
					),
				)
			);
		}

		$parse_errors = isset( $parsed['errors'] ) && is_array( $parsed['errors'] ) ? $parsed['errors'] : array();
		$rows         = isset( $parsed['rows'] ) && is_array( $parsed['rows'] ) ? $parsed['rows'] : array();

		if ( empty( $rows ) ) {
			$redirect_result(
				'csv_error',
				array(
					'created'         => 0,
					'skipped'         => count( $parse_errors ),
					'license_stopped' => 0,
					'errors'          => $parse_errors
						? $parse_errors
						: array(
							array(
								'line'    => 0,
								'message' => __( '登録対象の行がありません。', 'custom-rss-builder' ),
							),
						),
				)
			);
		}

		$row_count = count( $rows );
		if ( function_exists( 'crb_csv_feed_quota' ) ) {
			$quota = crb_csv_feed_quota();

			if ( empty( $quota['usable'] ) ) {
				$redirect_result(
					'csv_error',
					array(
						'created'         => 0,
						'skipped'         => 0,
						'license_stopped' => $row_count,
						'errors'          => array(
							array(
								'line'    => 0,
								'message' => function_exists( 'crb_license_denied_message' )
									? crb_license_denied_message( 'create_feed' )
									: __( 'ライセンスが有効でないため取り込めません。', 'custom-rss-builder' ),
							),
						),
					)
				);
			}

			if ( empty( $quota['unlimited'] ) && $row_count > (int) $quota['remaining'] ) {
				$message = sprintf(
					/* translators: 1: current count, 2: limit, 3: remaining, 4: csv row count */
					__( '上限のため取り込みを中止しました（1件も作成していません）。現在 %1$d 件 / 上限 %2$d 件（あと %3$d 件まで作成可）。CSV は %4$d 件のため超過します。不要なフィードを削除するか、CSV を %3$d 件以内にして再度お試しください。', 'custom-rss-builder' ),
					(int) $quota['current'],
					(int) $quota['limit'],
					(int) $quota['remaining'],
					$row_count
				);
				$redirect_result(
					'csv_error',
					array(
						'created'         => 0,
						'skipped'         => 0,
						'license_stopped' => $row_count,
						'errors'          => array(
							array(
								'line'    => 0,
								'message' => $message,
							),
						),
					)
				);
			}
		}

		$import = crb_import_feeds_from_csv_rows( $rows );
		$errors = array_merge( $parse_errors, isset( $import['errors'] ) && is_array( $import['errors'] ) ? $import['errors'] : array() );

		$result = array(
			'created'         => (int) ( $import['created'] ?? 0 ),
			'skipped'         => (int) ( $import['skipped'] ?? 0 ) + count( $parse_errors ),
			'license_stopped' => (int) ( $import['license_stopped'] ?? 0 ),
			'created_ids'     => isset( $import['created_ids'] ) && is_array( $import['created_ids'] ) ? $import['created_ids'] : array(),
			'errors'          => $errors,
		);

		if ( $result['created'] > 0 && 0 === $result['skipped'] && 0 === $result['license_stopped'] ) {
			$message = 'csv_imported';
		} elseif ( $result['created'] > 0 ) {
			$message = 'csv_partial';
		} else {
			$message = 'csv_error';
		}

		$redirect_result( $message, $result );
	}

	private function handle_save( $feed_id ) {
		crb_license_prepare_request();

		$is_new = $feed_id <= 0 || ! $this->feed_manager->get_feed( $feed_id );
		if ( $is_new ) {
			$gate = $this->license_gate_error( 'create_feed' );
			if ( is_wp_error( $gate ) ) {
				wp_die( esc_html( $gate->get_error_message() ) );
			}
		}
		$gate = $this->license_gate_error( 'save' );
		if ( is_wp_error( $gate ) ) {
			wp_die( esc_html( $gate->get_error_message() ) );
		}

		$data = $this->collect_feed_data_from_post( $feed_id );
		if ( '' === $data['name'] || '' === $data['url'] ) {
			wp_die( esc_html__( '必須項目を入力してください。', 'custom-rss-builder' ) );
		}
		if ( 'css' === crb_get_feed_extraction_mode( $data ) ) {
			$css = $data['css'] ?? array();
			if ( ! function_exists( 'crb_css_config_has_extraction_path' ) || ! crb_css_config_has_extraction_path( $css ) ) {
				$message = function_exists( 'crb_css_missing_extraction_path_message' )
					? crb_css_missing_extraction_path_message()
					: __( '抽出の指定がありません。', 'custom-rss-builder' );
				wp_die( esc_html( $message ) );
			}
		}
		if ( 'template' === crb_get_feed_extraction_mode( $data ) && '' === trim( (string) ( $data['template'] ?? '' ) ) ) {
			wp_die( esc_html__( '抽出テンプレートを入力してください。', 'custom-rss-builder' ) );
		}

		$new_id = $this->feed_manager->save_feed( $data );
		$lr_msg = 'saved';
		$lr     = is_array( $data['link_rewrite'] ?? null ) ? $data['link_rewrite'] : array();
		if ( ! empty( $lr['enabled'] ) && empty( $lr['rules'] ) ) {
			$lr_msg = 'link_rewrite_empty';
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'action'      => 'edit',
					'feed_id'     => $new_id,
					'crb_message' => $lr_msg,
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

	/**
	 * @return array<int, int>
	 */
	private function get_bulk_feed_ids_from_post() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = isset( $_POST['feed_ids'] ) ? wp_unslash( $_POST['feed_ids'] ) : array();
		if ( ! is_array( $raw ) ) {
			$raw = array( $raw );
		}
		$ids = array();
		foreach ( $raw as $feed_id ) {
			$feed_id = (int) $feed_id;
			if ( $feed_id > 0 ) {
				$ids[] = $feed_id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * @return array<int, int>
	 */
	private function get_bulk_edit_feed_ids_from_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['crb_bulk_edit'] ) ) {
			return array();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = isset( $_GET['crb_bulk_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['crb_bulk_nonce'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw_ids = sanitize_text_field( wp_unslash( $_GET['crb_bulk_edit'] ) );
		$parts   = array_filter( array_map( 'intval', explode( ',', $raw_ids ) ) );
		$ids     = array_values( array_unique( array_filter( $parts ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		if ( ! wp_verify_nonce( $nonce, 'crb_bulk_edit_' . implode( ',', $ids ) ) ) {
			return array();
		}
		$valid = array();
		foreach ( $ids as $feed_id ) {
			if ( is_array( $this->feed_manager->get_feed( $feed_id ) ) ) {
				$valid[] = $feed_id;
			}
		}
		return $valid;
	}

	private function handle_bulk_list_action() {
		$feed_ids = $this->get_bulk_feed_ids_from_post();
		if ( empty( $feed_ids ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => self::MENU_SLUG,
						'crb_message' => 'bulk_no_feeds',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$bulk_action = isset( $_POST['crb_bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['crb_bulk_action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $bulk_action || '-1' === $bulk_action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$bulk_action = isset( $_POST['crb_bulk_action2'] ) ? sanitize_key( wp_unslash( $_POST['crb_bulk_action2'] ) ) : '';
		}
		if ( '' === $bulk_action || '-1' === $bulk_action ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => self::MENU_SLUG,
						'crb_message' => 'bulk_invalid',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( 'delete' === $bulk_action ) {
			$deleted = 0;
			foreach ( $feed_ids as $feed_id ) {
				if ( $this->feed_manager->delete_feed( $feed_id ) ) {
					++$deleted;
				}
			}
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => self::MENU_SLUG,
						'crb_message' => 'bulk_deleted',
						'crb_detail'  => rawurlencode(
							sprintf(
								/* translators: %d: deleted feed count */
								__( '%d 件削除', 'custom-rss-builder' ),
								$deleted
							)
						),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		if ( 'edit' === $bulk_action ) {
			sort( $feed_ids, SORT_NUMERIC );
			$id_list = implode( ',', $feed_ids );
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'           => self::MENU_SLUG,
						'crb_bulk_edit'  => $id_list,
						'crb_bulk_nonce' => wp_create_nonce( 'crb_bulk_edit_' . $id_list ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'crb_message' => 'bulk_invalid',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function handle_bulk_update() {
		$feed_ids = $this->get_bulk_feed_ids_from_post();
		if ( empty( $feed_ids ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => self::MENU_SLUG,
						'crb_message' => 'bulk_no_feeds',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		sort( $feed_ids, SORT_NUMERIC );
		$changes = $this->collect_bulk_edit_changes_from_post();
		if ( ! empty( $changes['link_rewrite_rules_invalid'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'           => self::MENU_SLUG,
						'crb_bulk_edit'  => implode( ',', $feed_ids ),
						'crb_bulk_nonce' => wp_create_nonce( 'crb_bulk_edit_' . implode( ',', $feed_ids ) ),
						'crb_message'    => 'bulk_link_rules_invalid',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		if ( empty( $changes ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'           => self::MENU_SLUG,
						'crb_bulk_edit'  => implode( ',', $feed_ids ),
						'crb_bulk_nonce' => wp_create_nonce( 'crb_bulk_edit_' . implode( ',', $feed_ids ) ),
						'crb_message'    => 'bulk_no_change',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$updated = 0;
		$denied  = false;
		foreach ( $feed_ids as $feed_id ) {
			$feed = $this->feed_manager->get_feed( $feed_id );
			if ( ! is_array( $feed ) ) {
				continue;
			}

			if ( $this->bulk_edit_changes_are_denied( $changes ) ) {
				$denied = true;
				continue;
			}

			$feed = $this->apply_bulk_edit_changes_to_feed( $feed, $changes );
			$this->feed_manager->save_feed( $feed );
			++$updated;
		}

		if ( $denied && $updated <= 0 ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'           => self::MENU_SLUG,
						'crb_bulk_edit'  => implode( ',', $feed_ids ),
						'crb_bulk_nonce' => wp_create_nonce( 'crb_bulk_edit_' . implode( ',', $feed_ids ) ),
						'crb_message'    => 'bulk_denied',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::MENU_SLUG,
					'crb_message' => 'bulk_updated',
					'crb_detail'  => rawurlencode(
						sprintf(
							/* translators: %d: updated feed count */
							__( '%d 件更新', 'custom-rss-builder' ),
							$updated
						)
					),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @param array<string, mixed> $changes Bulk changes descriptor.
	 * @return bool
	 */
	private function bulk_edit_changes_are_denied( array $changes ) {
		if ( ! empty( $changes['ai'] ) && ( ! function_exists( 'crb_license_can' ) || ! crb_license_can( 'ai_transform' ) ) ) {
			return true;
		}
		if ( ! empty( $changes['requires_tag_sources'] ) && ( ! function_exists( 'crb_import_tag_sources_can_use' ) || ! crb_import_tag_sources_can_use() ) ) {
			return true;
		}
		if ( empty( $changes['per_feed'] ) || ! is_callable( $changes['per_feed'] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$targets = isset( $_POST['bulk_find_replace_targets'] ) ? wp_unslash( $_POST['bulk_find_replace_targets'] ) : array();
		if ( ! is_array( $targets ) ) {
			$targets = array( $targets );
		}
		if ( in_array( 'ai_instruction', array_map( 'sanitize_key', $targets ), true ) ) {
			return ! function_exists( 'crb_license_can' ) || ! crb_license_can( 'ai_transform' );
		}
		return false;
	}

	/**
	 * @param array<string, mixed>             $feed    Feed row.
	 * @param array<string, array<string,mixed>> $changes Bulk changes descriptor.
	 * @return array<string, mixed>
	 */
	private function apply_bulk_edit_changes_to_feed( array $feed, array $changes ) {
		if ( isset( $changes['import'] ) ) {
			$import         = $this->feed_manager->get_import_settings( $feed );
			$feed['import'] = array_merge( $import, $changes['import'] );
		}

		if ( isset( $changes['ai'] ) ) {
			$ai         = is_array( $feed['ai'] ?? null ) ? $feed['ai'] : array();
			$feed['ai'] = array_merge( $ai, $changes['ai'] );
		}

		if ( isset( $changes['link_rewrite'] ) ) {
			$current = function_exists( 'crb_get_feed_link_rewrite' )
				? crb_get_feed_link_rewrite( $feed )
				: ( is_array( $feed['link_rewrite'] ?? null ) ? $feed['link_rewrite'] : array() );
			$merged  = array_merge( $current, $changes['link_rewrite'] );
			$feed['link_rewrite'] = function_exists( 'crb_sanitize_link_rewrite_settings' )
				? crb_sanitize_link_rewrite_settings( $merged )
				: $merged;
		}

		if ( ! empty( $changes['per_feed'] ) && is_callable( $changes['per_feed'] ) ) {
			$feed = call_user_func( $changes['per_feed'], $feed );
		}

		return $feed;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function collect_bulk_edit_changes_from_post() {
		$changes = array();
		$has_per_feed = false;
		$per_feed_ops = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ai_instruction_raw = isset( $_POST['bulk_ai_instruction'] ) ? (string) wp_unslash( $_POST['bulk_ai_instruction'] ) : '';
		if ( '' !== trim( $ai_instruction_raw ) && function_exists( 'crb_license_can' ) && crb_license_can( 'ai_transform' ) ) {
			$sanitized = function_exists( 'crb_sanitize_ai_transform_settings' )
				? crb_sanitize_ai_transform_settings( array( 'instruction' => $ai_instruction_raw ), false )
				: array( 'instruction' => sanitize_textarea_field( $ai_instruction_raw ) );
			$changes['ai']['instruction'] = (string) ( $sanitized['instruction'] ?? '' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$title_raw = isset( $_POST['bulk_import_post_title_template'] ) ? (string) wp_unslash( $_POST['bulk_import_post_title_template'] ) : '';
		if ( '' !== trim( $title_raw ) ) {
			$changes['import']['post_title_template'] = function_exists( 'crb_sanitize_template' )
				? crb_sanitize_template( $title_raw )
				: sanitize_text_field( $title_raw );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$content_raw = isset( $_POST['bulk_import_content_template'] ) ? (string) wp_unslash( $_POST['bulk_import_content_template'] ) : '';
		if ( '' !== trim( $content_raw ) ) {
			$changes['import']['content_template'] = function_exists( 'crb_sanitize_import_content_template' )
				? crb_sanitize_import_content_template( $content_raw )
				: $content_raw;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$find_text = isset( $_POST['bulk_find_text'] ) ? (string) wp_unslash( $_POST['bulk_find_text'] ) : '';
		if ( '' !== $find_text ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$replace_text = isset( $_POST['bulk_replace_text'] ) ? (string) wp_unslash( $_POST['bulk_replace_text'] ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$targets = isset( $_POST['bulk_find_replace_targets'] ) ? wp_unslash( $_POST['bulk_find_replace_targets'] ) : array();
			if ( ! is_array( $targets ) ) {
				$targets = array( $targets );
			}
			$targets = array_values( array_filter( array_map( 'sanitize_key', $targets ) ) );
			if ( empty( $targets ) ) {
				$targets = array( 'ai_instruction', 'post_title_template', 'post_content_template' );
			}
			$has_per_feed = true;
			$per_feed_ops['find_replace'] = array(
				'find'    => $find_text,
				'replace' => $replace_text,
				'targets' => $targets,
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['bulk_import_enabled'] ) && '' !== (string) wp_unslash( $_POST['bulk_import_enabled'] ) ) {
			$changes['import']['enabled'] = '1' === (string) wp_unslash( $_POST['bulk_import_enabled'] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['bulk_import_schedule_hours'] ) && '' !== trim( (string) wp_unslash( $_POST['bulk_import_schedule_hours'] ) ) ) {
			$hours = (int) wp_unslash( $_POST['bulk_import_schedule_hours'] );
			$changes['import']['schedule'] = function_exists( 'crb_import_schedule_slug_from_hours' )
				? crb_import_schedule_slug_from_hours( $hours )
				: ( 0 === $hours ? 'off' : 'every_' . max( 1, $hours ) . '_hours' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['bulk_import_post_status'] ) && '' !== (string) wp_unslash( $_POST['bulk_import_post_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_POST['bulk_import_post_status'] ) );
			if ( in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
				$changes['import']['post_status'] = $status;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['bulk_import_post_type'] ) && '' !== trim( (string) wp_unslash( $_POST['bulk_import_post_type'] ) ) ) {
			$post_type = sanitize_key( wp_unslash( $_POST['bulk_import_post_type'] ) );
			if ( '' !== $post_type && post_type_exists( $post_type ) ) {
				$changes['import']['post_type'] = $post_type;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['bulk_import_category_id'] ) && '' !== (string) wp_unslash( $_POST['bulk_import_category_id'] ) ) {
			$category_id = function_exists( 'crb_sanitize_import_category_id' )
				? crb_sanitize_import_category_id( wp_unslash( $_POST['bulk_import_category_id'] ) )
				: max( 0, (int) wp_unslash( $_POST['bulk_import_category_id'] ) );
			$changes['import']['category_id'] = $category_id;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['bulk_import_author_id'] ) && '' !== (string) wp_unslash( $_POST['bulk_import_author_id'] ) ) {
			$author_id = function_exists( 'crb_sanitize_import_author_id' )
				? crb_sanitize_import_author_id( wp_unslash( $_POST['bulk_import_author_id'] ) )
				: max( 0, (int) wp_unslash( $_POST['bulk_import_author_id'] ) );
			$changes['import']['author_id'] = $author_id;
		}

		if ( function_exists( 'crb_license_can' ) && crb_license_can( 'ai_transform' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$slots = isset( $_POST['bulk_ai_transform_slots'] ) && is_array( $_POST['bulk_ai_transform_slots'] )
				? array_map( 'intval', wp_unslash( $_POST['bulk_ai_transform_slots'] ) )
				: array();
			$slots = array_values( array_filter( $slots, static function ( $n ) {
				return (int) $n >= 0;
			} ) );
			if ( ! empty( $slots ) ) {
				$sanitized = function_exists( 'crb_sanitize_ai_transform_settings' )
					? crb_sanitize_ai_transform_settings( array( 'slots' => $slots ), false )
					: array( 'slots' => $slots );
				$changes['ai']['slots'] = array_map( 'intval', (array) ( $sanitized['slots'] ?? array() ) );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['bulk_ai_enabled'] ) && '' !== (string) wp_unslash( $_POST['bulk_ai_enabled'] ) ) {
				$changes['ai']['enabled'] = '1' === (string) wp_unslash( $_POST['bulk_ai_enabled'] );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['bulk_ai_model'] ) && '' !== trim( (string) wp_unslash( $_POST['bulk_ai_model'] ) ) ) {
				$model = sanitize_key( wp_unslash( $_POST['bulk_ai_model'] ) );
				if ( function_exists( 'crb_ai_sanitize_model_slug' ) ) {
					$model = crb_ai_sanitize_model_slug( $model );
				}
				if ( '' !== $model ) {
					$changes['ai']['model'] = $model;
				}
			}
		}

		if ( function_exists( 'crb_import_tag_sources_can_use' ) && crb_import_tag_sources_can_use() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$slot_raw = isset( $_POST['bulk_import_tag_sources_slot'] ) && is_array( $_POST['bulk_import_tag_sources_slot'] )
				? array_map( 'intval', wp_unslash( $_POST['bulk_import_tag_sources_slot'] ) )
				: array();
			$slot_raw = array_values( array_filter( $slot_raw, static function ( $n ) {
				return (int) $n > 0;
			} ) );
			if ( ! empty( $slot_raw ) ) {
				$has_per_feed                     = true;
				$per_feed_ops['tag_sources_slots'] = $slot_raw;
				$changes['requires_tag_sources']  = true;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		// リンク変換のマスターON/OFF・対象スロット指定は廃止（ルール入力のみ）。

		$bulk_set_link_rules = false;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['bulk_link_rewrite_rules'] ) && is_array( $_POST['bulk_link_rewrite_rules'] ) ) {
			foreach ( (array) wp_unslash( $_POST['bulk_link_rewrite_rules'] ) as $rule_row ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( ! is_array( $rule_row ) ) {
					continue;
				}
				$src = trim( (string) ( $rule_row['source_prefix'] ?? '' ) );
				$dst = trim( (string) ( $rule_row['target_prefix'] ?? '' ) );
				if ( '' !== $src && '' !== $dst ) {
					$bulk_set_link_rules = true;
					break;
				}
			}
		}
		if ( $bulk_set_link_rules && function_exists( 'crb_collect_link_rewrite_from_request' ) ) {
			$collected = crb_collect_link_rewrite_from_request(
				'bulk_link_rewrite_rules',
				'bulk_link_rewrite_rules_enabled_dummy',
				'bulk_link_rewrite_rules_slots_dummy'
			);
			$rules = is_array( $collected['rules'] ?? null ) ? $collected['rules'] : array();
			if ( ! empty( $rules ) ) {
				$changes['link_rewrite']['rules'] = $rules;
			} else {
				$changes['link_rewrite_rules_invalid'] = true;
			}
		}

		if ( empty( $changes['import'] ) ) {
			unset( $changes['import'] );
		}
		if ( empty( $changes['ai'] ) ) {
			unset( $changes['ai'] );
		}
		if ( empty( $changes['link_rewrite'] ) ) {
			unset( $changes['link_rewrite'] );
		}

		if ( $has_per_feed ) {
			$changes['per_feed'] = function ( array $feed ) use ( $per_feed_ops ) {
				if ( ! empty( $per_feed_ops['tag_sources_slots'] ) && function_exists( 'crb_sanitize_import_tag_sources' ) ) {
					$import          = $this->feed_manager->get_import_settings( $feed );
					$existing        = is_array( $import['tag_sources'] ?? null ) ? $import['tag_sources'] : array();
					$fixed           = array();
					foreach ( $existing as $item ) {
						if ( is_array( $item ) && 'fixed' === (string) ( $item['type'] ?? '' ) ) {
							$fixed[] = $item;
						}
					}
					$slot_sources = array();
					foreach ( (array) $per_feed_ops['tag_sources_slots'] as $slot_number ) {
						$slot_number = (int) $slot_number;
						if ( $slot_number > 0 ) {
							$slot_sources[] = array(
								'type' => 'slot',
								'slot' => $slot_number,
							);
						}
					}
					$import['tag_sources'] = crb_sanitize_import_tag_sources( array_merge( $fixed, $slot_sources ), false );
					$feed['import']        = $import;
				}

				if ( empty( $per_feed_ops['find_replace'] ) ) {
					return $feed;
				}

				$op      = $per_feed_ops['find_replace'];
				$find    = (string) ( $op['find'] ?? '' );
				$replace = (string) ( $op['replace'] ?? '' );
				$targets = is_array( $op['targets'] ?? null ) ? $op['targets'] : array();
				if ( '' === $find || empty( $targets ) ) {
					return $feed;
				}

				if ( in_array( 'ai_instruction', $targets, true ) ) {
					$ai = is_array( $feed['ai'] ?? null ) ? $feed['ai'] : array();
					$instruction = str_replace( $find, $replace, (string) ( $ai['instruction'] ?? '' ) );
					$sanitized   = function_exists( 'crb_sanitize_ai_transform_settings' )
						? crb_sanitize_ai_transform_settings( array( 'instruction' => $instruction ), false )
						: array( 'instruction' => sanitize_textarea_field( $instruction ) );
					$ai['instruction'] = (string) ( $sanitized['instruction'] ?? '' );
					$feed['ai']          = $ai;
				}

				$import = $this->feed_manager->get_import_settings( $feed );
				if ( in_array( 'post_title_template', $targets, true ) ) {
					$title = str_replace( $find, $replace, (string) ( $import['post_title_template'] ?? '' ) );
					$import['post_title_template'] = function_exists( 'crb_sanitize_template' )
						? crb_sanitize_template( $title )
						: sanitize_text_field( $title );
				}
				if ( in_array( 'post_content_template', $targets, true ) ) {
					$content = str_replace( $find, $replace, (string) ( $import['content_template'] ?? '' ) );
					$import['content_template'] = function_exists( 'crb_sanitize_import_content_template' )
						? crb_sanitize_import_content_template( $content )
						: $content;
				}
				$feed['import'] = $import;

				return $feed;
			};
		}

		if ( empty( $changes['import'] ) && empty( $changes['ai'] ) && empty( $changes['link_rewrite'] ) && empty( $changes['per_feed'] ) ) {
			if ( ! empty( $changes['link_rewrite_rules_invalid'] ) ) {
				return $changes;
			}
			return array();
		}

		return $changes;
	}

	private function handle_import_posts( $feed_id ) {
		if ( ! crb_license_can( 'import_posts' ) ) {
			wp_die( esc_html( crb_license_denied_message( 'import_posts' ) ) );
		}
		if ( $feed_id <= 0 ) {
			wp_die( esc_html__( 'フィードが見つかりません。', 'custom-rss-builder' ) );
		}

		$data = $this->collect_feed_data_from_post( $feed_id );
		$this->feed_manager->save_feed( $data );

		$result = $this->post_importer->import_feed( $feed_id, 'manual' );
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

	private function handle_preview_posts() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$feed_id            = isset( $_POST['feed_id'] ) ? (int) $_POST['feed_id'] : 0;
		$this->preview_data = $this->build_preview_data_from_post( $feed_id );
	}

	/**
	 * @param int $feed_id Existing feed ID.
	 * @return array<string, mixed>
	 */
	private function collect_feed_data_from_post( $feed_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$import_enabled = ! empty( $_POST['import_enabled'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$mapping_defaults = crb_default_rss_mapping();

		$css_raw = array_merge(
			array(
				'scope_selector' => isset( $_POST['css_scope_selector'] ) ? wp_unslash( $_POST['css_scope_selector'] ) : '',
				'item_selector'  => isset( $_POST['css_item_selector'] ) ? wp_unslash( $_POST['css_item_selector'] ) : '',
				'link_selector'  => isset( $_POST['css_link_selector'] ) ? wp_unslash( $_POST['css_link_selector'] ) : '',
				'title_mode'     => isset( $_POST['css_title_mode'] ) ? wp_unslash( $_POST['css_title_mode'] ) : 'attr',
				'title_attr'     => isset( $_POST['css_title_attr'] ) ? wp_unslash( $_POST['css_title_attr'] ) : 'title',
				'title_selector' => isset( $_POST['css_title_selector'] ) ? wp_unslash( $_POST['css_title_selector'] ) : '',
			),
			function_exists( 'crb_collect_extra_slot_fields_from_post' ) ? crb_collect_extra_slot_fields_from_post() : array()
		);

		$data = array(
			'id'               => $feed_id,
			'name'             => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'url'              => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'extraction_mode'  => 'css',
			'css'              => crb_sanitize_css_config( $css_raw ),
			'scope_template'   => crb_get_template_from_post( 'scope_template' ),
			'template'         => crb_get_template_from_post( 'template' ),
			'mapping'          => array(
				'link'        => (int) ( $_POST['map_link'] ?? $mapping_defaults['link'] ),
				'title'       => (int) ( $_POST['map_title'] ?? $mapping_defaults['title'] ),
				'description' => (int) ( $_POST['map_description'] ?? $mapping_defaults['description'] ),
				'date'        => (int) ( $_POST['map_date'] ?? $mapping_defaults['date'] ),
			),
			'link_rewrite'     => function_exists( 'crb_collect_link_rewrite_from_request' )
				? crb_collect_link_rewrite_from_request()
				: array(),
			'ai'               => function_exists( 'crb_collect_ai_transform_from_request' )
				? crb_collect_ai_transform_from_request()
				: array(),
			'import'           => array(
				'enabled'             => $import_enabled,
				'schedule'            => function_exists( 'crb_import_schedule_slug_from_hours' )
					? crb_import_schedule_slug_from_hours( wp_unslash( $_POST['import_schedule_hours'] ?? 0 ) )
					: 'off',
				'post_status'         => sanitize_key( wp_unslash( $_POST['import_post_status'] ?? 'draft' ) ),
				'post_type'           => sanitize_key( wp_unslash( $_POST['import_post_type'] ?? 'post' ) ),
				'post_title_template' => crb_get_import_template_from_post( 'import_post_title_template' ),
				'content_template'    => crb_get_import_template_from_post( 'import_content_template' ),
				'append_source'       => false,
				'category_id'         => (int) ( $_POST['import_category_id'] ?? 0 ),
				'tag_sources'         => function_exists( 'crb_import_tag_sources_from_request' )
					? crb_import_tag_sources_from_request(
						$_POST['import_tag_sources_fixed'] ?? array(),
						$_POST['import_tag_sources_slot'] ?? array()
					)
					: array(),
				'author_id'           => (int) ( $_POST['import_author_id'] ?? 0 ),
			),
		);

		return crb_license_apply_feed_limits( $data );
	}
}
