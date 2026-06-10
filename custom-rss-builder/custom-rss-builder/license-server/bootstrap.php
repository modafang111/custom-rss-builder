<?php
/**
 * Built-in license server (loaded from Custom RSS Builder).
 *
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'CRB_LS_BOOTSTRAPPED' ) ) {
	return;
}

define( 'CRB_LS_BOOTSTRAPPED', true );

if ( ! defined( 'CRB_LS_VERSION' ) ) {
	define( 'CRB_LS_VERSION', '0.1.0' );
}
if ( ! defined( 'CRB_LS_FILE' ) ) {
	define( 'CRB_LS_FILE', __FILE__ );
}
if ( ! defined( 'CRB_LS_DIR' ) ) {
	define( 'CRB_LS_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CRB_LS_URL' ) ) {
	define( 'CRB_LS_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'CRB_LS_TABLE' ) ) {
	define( 'CRB_LS_TABLE', 'crb_licenses' );
}

$crb_ls_includes = array(
	'includes/functions-license-server.php',
	'includes/class-database.php',
	'includes/class-license-manager.php',
	'includes/class-rest-api.php',
	'includes/class-registration.php',
	'admin/class-admin.php',
);

foreach ( $crb_ls_includes as $crb_ls_rel ) {
	$crb_ls_path = CRB_LS_DIR . $crb_ls_rel;
	if ( is_readable( $crb_ls_path ) ) {
		require_once $crb_ls_path;
	}
}

if ( ! function_exists( 'crb_ls_plugin' ) ) {
	/**
	 * @return CRB_License_Server_Admin
	 */
	function crb_ls_plugin() {
		static $instance = null;
		if ( null === $instance ) {
			$manager = new CRB_License_Server_License_Manager();
			$instance = new CRB_License_Server_Admin(
				$manager,
				new CRB_License_Server_REST_API( $manager ),
				new CRB_License_Server_Registration( $manager )
			);
		}
		return $instance;
	}
}

if ( ! function_exists( 'crb_ls_install_tables' ) ) {
	function crb_ls_install_tables() {
		if ( ! class_exists( 'CRB_License_Server_Database' ) ) {
			return;
		}
		CRB_License_Server_Database::maybe_install();
	}
}

if ( ! function_exists( 'crb_ls_register_hooks' ) ) {
	function crb_ls_register_hooks() {
		crb_ls_plugin()->register_hooks();
	}
}

add_action( 'plugins_loaded', 'crb_ls_register_hooks', 20 );
