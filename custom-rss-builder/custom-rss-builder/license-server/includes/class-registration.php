<?php
/**
 * 無料登録ショートコード [crb_free_license].
 *
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRB_License_Server_Registration {

	/** @var CRB_License_Server_License_Manager */
	private $manager;

	public function __construct( CRB_License_Server_License_Manager $manager ) {
		$this->manager = $manager;
	}

	public function register_hooks() {
		add_shortcode( 'crb_free_license', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets() {
		wp_register_style(
			'crb-ls-register',
			CRB_LS_URL . 'assets/register.css',
			array(),
			CRB_LS_VERSION
		);
	}

	/**
	 * @return string
	 */
	public function render_shortcode() {
		wp_enqueue_style( 'crb-ls-register' );

		$message = '';
		$type    = '';

		if ( isset( $_POST['crb_ls_free_register'] ) ) {
			check_admin_referer( 'crb_ls_free_register', 'crb_ls_free_nonce' );

			$email  = isset( $_POST['crb_ls_email'] ) ? sanitize_email( wp_unslash( $_POST['crb_ls_email'] ) ) : '';
			$result = $this->manager->register_free( $email );

			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
				$type    = 'error';
			} else {
				$message = __( 'ライセンスキーをメールでお送りしました。届かない場合は迷惑メールをご確認ください。', 'crb-license-server' );
				$type    = 'success';
			}
		}

		ob_start();
		include CRB_LS_DIR . 'templates/register-free.php';
		return (string) ob_get_clean();
	}
}
