<?php
/**
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRB_License_Server_Admin {

	const MENU_SLUG = 'crb-license-server';

	/** @var CRB_License_Server_License_Manager */
	private $manager;

	/** @var CRB_License_Server_REST_API */
	private $rest;

	/** @var CRB_License_Server_Registration */
	private $registration;

	public function __construct(
		CRB_License_Server_License_Manager $manager,
		CRB_License_Server_REST_API $rest,
		CRB_License_Server_Registration $registration
	) {
		$this->manager      = $manager;
		$this->rest         = $rest;
		$this->registration = $registration;
	}

	public function register_hooks() {
		$this->rest->register_hooks();
		$this->registration->register_hooks();

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	public function add_menu() {
		if ( function_exists( 'crb_is_client_app_enabled' ) && ! crb_is_client_app_enabled() ) {
			add_menu_page(
				__( 'CRB ライセンス', 'crb-license-server' ),
				__( 'CRB ライセンス', 'crb-license-server' ),
				'manage_options',
				self::MENU_SLUG,
				array( $this, 'render_licenses_page' ),
				'dashicons-admin-network',
				58
			);
			add_submenu_page(
				self::MENU_SLUG,
				__( 'ライセンス設定', 'crb-license-server' ),
				__( 'ライセンス設定', 'crb-license-server' ),
				'manage_options',
				self::MENU_SLUG . '-settings',
				array( $this, 'render_settings_page' )
			);
			return;
		}

		add_submenu_page(
			'custom-rss-builder',
			__( 'ライセンス管理（販売）', 'crb-license-server' ),
			__( 'ライセンス管理', 'crb-license-server' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_licenses_page' )
		);
		add_submenu_page(
			'custom-rss-builder',
			__( 'ライセンス設定', 'crb-license-server' ),
			__( 'ライセンス設定', 'crb-license-server' ),
			'manage_options',
			self::MENU_SLUG . '-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['crb_ls_save_settings'] ) ) {
			check_admin_referer( 'crb_ls_settings' );
			$this->save_settings();
			wp_safe_redirect( add_query_arg( 'settings-updated', '1', admin_url( 'admin.php?page=' . self::MENU_SLUG . '-settings' ) ) );
			exit;
		}

		if ( isset( $_POST['crb_ls_reset_mail_defaults'] ) ) {
			check_admin_referer( 'crb_ls_settings' );
			if ( function_exists( 'crb_ls_apply_recommended_mail_defaults' ) ) {
				crb_ls_apply_recommended_mail_defaults();
			}
			wp_safe_redirect( add_query_arg( 'mail-reset', '1', admin_url( 'admin.php?page=' . self::MENU_SLUG . '-settings' ) ) );
			exit;
		}

		if ( isset( $_POST['crb_ls_create_license'] ) ) {
			check_admin_referer( 'crb_ls_create_license' );
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$plan  = isset( $_POST['plan'] ) ? sanitize_key( wp_unslash( $_POST['plan'] ) ) : 'pro';
			$this->manager->create_license( $email, $plan );
			wp_safe_redirect( add_query_arg( 'created', '1', admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) );
			exit;
		}

		if ( isset( $_GET['crb_ls_action'], $_GET['license_id'], $_GET['_wpnonce'] ) ) {
			$action = sanitize_key( wp_unslash( $_GET['crb_ls_action'] ) );
			$id     = (int) $_GET['license_id'];
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'crb_ls_row_' . $id ) ) {
				return;
			}
			if ( 'expire' === $action ) {
				$this->manager->set_status( $id, 'expired' );
			} elseif ( 'activate' === $action ) {
				$this->manager->set_status( $id, 'active' );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
			exit;
		}
	}

	private function save_settings() {
		$from_email = isset( $_POST['mail_from_email'] ) ? sanitize_email( wp_unslash( $_POST['mail_from_email'] ) ) : '';
		$patch      = array(
			'mail_from_name'            => isset( $_POST['mail_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mail_from_name'] ) ) : '',
			'mail_from_email'           => $from_email,
			'mail_subject'              => isset( $_POST['mail_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['mail_subject'] ) ) : '',
			'mail_intro'                => isset( $_POST['mail_intro'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mail_intro'] ) ) : '',
			'mail_activation_hint'      => isset( $_POST['mail_activation_hint'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mail_activation_hint'] ) ) : '',
			'mail_body'                 => isset( $_POST['mail_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mail_body'] ) ) : '',
			'mail_download_url'         => isset( $_POST['mail_download_url'] ) ? esc_url_raw( wp_unslash( $_POST['mail_download_url'] ) ) : '',
			'mail_download_url_pro'     => isset( $_POST['mail_download_url_pro'] ) ? esc_url_raw( wp_unslash( $_POST['mail_download_url_pro'] ) ) : '',
			'mail_download_url_free'    => isset( $_POST['mail_download_url_free'] ) ? esc_url_raw( wp_unslash( $_POST['mail_download_url_free'] ) ) : '',
			'mail_download_password'    => isset( $_POST['mail_download_password'] ) ? sanitize_text_field( wp_unslash( $_POST['mail_download_password'] ) ) : '',
			'mail_download_heading'     => isset( $_POST['mail_download_heading'] ) ? sanitize_text_field( wp_unslash( $_POST['mail_download_heading'] ) ) : '',
			'mail_download_install_hint'      => isset( $_POST['mail_download_install_hint'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mail_download_install_hint'] ) ) : '',
			'mail_setup_service_payment_url'  => isset( $_POST['mail_setup_service_payment_url'] ) ? esc_url_raw( wp_unslash( $_POST['mail_setup_service_payment_url'] ) ) : '',
			'mail_setup_service_block'        => isset( $_POST['mail_setup_service_block'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mail_setup_service_block'] ) ) : '',
		);

		crb_ls_update_settings( $patch );
		if ( function_exists( 'crb_ls_sync_dlm_download_post_password' ) ) {
			crb_ls_sync_dlm_download_post_password();
		}
	}

	public function render_licenses_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$licenses = $this->manager->list_licenses();
		include CRB_LS_DIR . 'admin/views/licenses.php';
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$api_secret      = crb_ls_api_secret();
		$pro_payment_url = function_exists( 'crb_ls_pro_payment_url' ) ? crb_ls_pro_payment_url() : '';
		$register_hint   = '[crb_free_license]';
		include CRB_LS_DIR . 'admin/views/settings.php';
	}
}
