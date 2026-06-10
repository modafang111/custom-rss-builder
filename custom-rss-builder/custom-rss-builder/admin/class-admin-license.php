<?php
/**
 * ライセンス設定画面・保存処理。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Admin_License {

	const MENU_SLUG = 'custom-rss-builder-license';

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_form' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_init', 'crb_license_maybe_refresh', 20 );
		add_action( 'crb_license_daily_check', array( $this, 'cron_check' ) );

		if ( ! wp_next_scheduled( 'crb_license_daily_check' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'crb_license_daily_check' );
		}
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}
		$js_path = CRB_PLUGIN_DIR . 'assets/js/admin.js';
		$build   = defined( 'CRB_BUILD_ID' ) ? CRB_BUILD_ID : CRB_VERSION;
		$ver_js  = CRB_VERSION . '.' . $build . '.' . ( is_readable( $js_path ) ? (string) filemtime( $js_path ) : $build );
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
				'formNonce' => wp_create_nonce( 'crb_admin_action' ),
			)
		);
	}

	public function add_submenu() {
		add_submenu_page(
			Custom_RSS_Builder_Admin_Page::MENU_SLUG,
			__( 'ライセンス', 'custom-rss-builder' ),
			__( 'ライセンス', 'custom-rss-builder' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function cron_check() {
		crb_license_maybe_refresh();
	}

	public function admin_notices() {
		static $shown = false;

		if ( $shown || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( crb_license_is_embedded_mode() ) {
			return;
		}

		// ライセンス画面では license-settings.php 側だけで表示（二重防止）。
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::MENU_SLUG === $page ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( $screen->id, 'custom-rss-builder' ) ) {
			return;
		}

		if ( false !== strpos( $screen->id, self::MENU_SLUG ) ) {
			return;
		}

		$state = crb_license_get_state();
		if ( $state['usable'] ) {
			return;
		}

		$message = function_exists( 'crb_license_admin_notice_message' )
			? crb_license_admin_notice_message( $state['message'] )
			: (string) $state['message'];

		$shown = true;
		if ( function_exists( 'crb_license_echo_client_activation_notice' ) ) {
			crb_license_echo_client_activation_notice();
			return;
		}

		$url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html( $message );
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'ライセンス設定', 'custom-rss-builder' ) . '</a>';
		echo '</p></div>';
	}

	public function handle_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_POST['crb_license_action'] ) ) {
			return;
		}

		check_admin_referer( 'crb_license_settings' );

		$action = sanitize_key( wp_unslash( $_POST['crb_license_action'] ) );
		$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$client = new Custom_RSS_Builder_License_Client();
		$result = null;

		switch ( $action ) {
			case 'activate':
				$is_pro_upgrade = ! empty( $_POST['crb_pro_upgrade'] );
				if ( '' === $key && ! $is_pro_upgrade ) {
					$key = trim( (string) ( crb_license_get_settings()['license_key'] ?? '' ) );
				}
				if ( '' === $key ) {
					$this->redirect_with_error( __( 'ライセンスキーを入力してください。', 'custom-rss-builder' ) );
				}
				crb_license_update_settings( array( 'license_key' => $key ) );
				$result = $client->activate( $key );
				break;

			case 'check':
				if ( '' === $key ) {
					$key = trim( (string) ( crb_license_get_settings()['license_key'] ?? '' ) );
				}
				if ( '' === $key ) {
					$this->redirect_with_error( __( 'ライセンスキーを入力してください。', 'custom-rss-builder' ) );
				}
				$result = $client->remote_check( $key );
				break;

			case 'save_ai_settings':
				if ( ! function_exists( 'crb_is_client_app_enabled' ) || ! crb_is_client_app_enabled() ) {
					$this->redirect_with_error( __( 'この操作はクライアントサイトでのみ利用できます。', 'custom-rss-builder' ) );
				}
				if ( ! function_exists( 'crb_save_ai_settings_from_post' ) ) {
					$this->redirect_with_error( __( 'AI 設定を保存できません。', 'custom-rss-builder' ) );
				}
				$saved = crb_save_ai_settings_from_post();
				if ( is_wp_error( $saved ) ) {
					$this->redirect_with_error( $saved->get_error_message() );
				}
				$query_arg = 'updated';
				if ( 'saved' === $saved ) {
					$query_arg = 'ai_saved';
				} elseif ( 'cleared' === $saved ) {
					$query_arg = 'ai_cleared';
				}
				wp_safe_redirect( add_query_arg( $query_arg, '1', admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
				exit;

			case 'save_connection':
				if ( function_exists( 'crb_license_ui_is_client_screen' ) && crb_license_ui_is_client_screen() ) {
					$mode = 'remote';
				} else {
					$mode = isset( $_POST['connection_mode'] ) ? sanitize_key( wp_unslash( $_POST['connection_mode'] ) ) : 'remote';
					if ( ! in_array( $mode, array( 'embedded', 'remote' ), true ) ) {
						$mode = 'remote';
					}
					if ( 'embedded' === $mode && function_exists( 'crb_license_may_use_embedded_connection' ) && ! crb_license_may_use_embedded_connection() ) {
						$mode = 'remote';
					}
				}
				$api_base = isset( $_POST['api_base'] ) ? crb_license_normalize_site_url( wp_unslash( $_POST['api_base'] ) ) : '';
				if ( function_exists( 'crb_license_ui_is_client_screen' ) && crb_license_ui_is_client_screen() && '' === $api_base ) {
					if ( function_exists( 'crb_license_client_remote_base_url' ) ) {
						$api_base = crb_license_client_remote_base_url();
					}
					if ( '' === $api_base && function_exists( 'crb_license_suggested_client_api_base' ) ) {
						$api_base = crb_license_suggested_client_api_base();
					}
				}
				crb_license_update_settings(
					array(
						'connection_mode' => $mode,
						'api_base'        => $api_base,
						'api_secret'      => isset( $_POST['api_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['api_secret'] ) ) : '',
					)
				);
				if ( 'embedded' === $mode && function_exists( 'crb_ls_install_tables' ) ) {
					crb_ls_install_tables();
				}
				wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
				exit;

			case 'apply_ops_authority':
				if ( ! crb_license_is_authoritative_server() ) {
					$this->redirect_with_error( __( 'このサイトはライセンス正本サーバーではありません。', 'custom-rss-builder' ) );
				}
				crb_license_update_settings(
					array(
						'connection_mode' => 'embedded',
						'api_base'        => '',
					)
				);
				if ( function_exists( 'crb_ls_install_tables' ) ) {
					crb_ls_install_tables();
				}
				wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
				exit;

			case 'apply_ops_client':
				if ( crb_license_is_authoritative_server() ) {
					$this->redirect_with_error( __( '正本サーバーでは REST クライアント設定は不要です。', 'custom-rss-builder' ) );
				}
				$base = function_exists( 'crb_license_suggested_client_api_base' )
					? crb_license_suggested_client_api_base()
					: '';
				crb_license_update_settings(
					array(
						'connection_mode' => 'remote',
						'api_base'        => $base,
					)
				);
				wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
				exit;

			case 'test_connection':
				if ( '' === $key ) {
					$key = trim( (string) ( crb_license_get_settings()['license_key'] ?? '' ) );
				}
				if ( '' === $key ) {
					$this->redirect_with_error( __( '接続テストにはライセンスキーが必要です。先に有効化するかキーを入力してください。', 'custom-rss-builder' ) );
				}
				$result = $client->remote_check( $key );
				if ( is_wp_error( $result ) ) {
					crb_license_apply_remote_result( $result );
					$this->redirect_with_error( $result->get_error_message() );
				}
				crb_license_apply_remote_result( $result );
				wp_safe_redirect(
					add_query_arg(
						array(
							'updated'       => '1',
							'conn_test'     => 'ok',
						),
						admin_url( 'admin.php?page=' . self::MENU_SLUG )
					)
				);
				exit;

			case 'switch_free':
				if ( ! crb_license_is_embedded_mode() ) {
					$this->redirect_with_error( __( 'この操作は組み込みモードでのみ利用できます。', 'custom-rss-builder' ) );
				}
				$result = crb_license_switch_to_free_local();
				break;

			case 'test_reset':
				if ( ! crb_license_is_embedded_mode() ) {
					$this->redirect_with_error( __( 'この操作は組み込みモードでのみ利用できます。', 'custom-rss-builder' ) );
				}
				crb_license_clear_settings_for_test();
				wp_safe_redirect(
					add_query_arg(
						'test_reset',
						'1',
						admin_url( 'admin.php?page=' . self::MENU_SLUG )
					)
				);
				exit;

			case 'test_run_ensure':
				if ( ! crb_license_is_embedded_mode() ) {
					$this->redirect_with_error( __( 'この操作は組み込みモードでのみ利用できます。', 'custom-rss-builder' ) );
				}
				delete_transient( 'crb_license_skip_ensure_once' );
				$result = crb_license_setup_free_local();
				break;
		}

		if ( null === $result ) {
			$this->redirect_with_error( __( '不明な操作です。', 'custom-rss-builder' ) );
		}

		if ( is_wp_error( $result ) ) {
			crb_license_apply_remote_result( $result );
			$this->redirect_with_error( $result->get_error_message() );
		}

		crb_license_apply_remote_result( $result );
		if ( 'activate' === $action && ! empty( $result['license']['license_key'] ) ) {
			crb_license_update_settings(
				array(
					'license_key' => (string) $result['license']['license_key'],
				)
			);
		}

		if ( in_array( $action, array( 'switch_free', 'test_run_ensure' ), true ) && ! empty( $result['license']['license_key'] ) ) {
			crb_license_update_settings(
				array(
					'license_key' => (string) $result['license']['license_key'],
				)
			);
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
		exit;
	}

	/**
	 * @param string $message Error message.
	 */
	private function redirect_with_error( $message ) {
		if ( function_exists( 'crb_license_admin_notice_message' ) ) {
			$message = crb_license_admin_notice_message( (string) $message );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'license_error' => rawurlencode( $message ),
				),
				admin_url( 'admin.php?page=' . self::MENU_SLUG )
			)
		);
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// テストリセット直後は ensure をスキップし、再読み込みで A-1（自動無料化）を確認できる。
		if ( ! empty( $_GET['test_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// prepare_request は呼ばない。
		} else {
			crb_license_prepare_request();
		}

		if ( function_exists( 'crb_license_refresh_on_settings_page' ) ) {
			crb_license_refresh_on_settings_page();
		}

		$state    = crb_license_get_state();
		$settings = crb_license_get_settings();

		if ( ! empty( $_GET['test_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			delete_transient( 'crb_license_skip_ensure_once' );
		}

		include CRB_PLUGIN_DIR . 'admin/views/license-settings.php';
	}
}
