<?php
/**
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRB_License_Server_Database {

	const SCHEMA_VERSION = '1.0';

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . CRB_LS_TABLE;
	}

	/**
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
	}

	/**
	 * テーブルが無ければ作成（有効化を逃した環境向け）。
	 */
	public static function maybe_install() {
		$installed = get_option( 'crb_ls_db_version', '' );
		if ( self::table_exists() && self::SCHEMA_VERSION === $installed ) {
			return;
		}
		self::install();
		update_option( 'crb_ls_db_version', self::SCHEMA_VERSION, false );
	}

	public static function install() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			license_key varchar(64) NOT NULL,
			email varchar(191) NOT NULL DEFAULT '',
			plan varchar(16) NOT NULL DEFAULT 'free',
			status varchar(16) NOT NULL DEFAULT 'active',
			site_url varchar(255) NOT NULL DEFAULT '',
			paypal_subscription_id varchar(64) NOT NULL DEFAULT '',
			paypal_payer_email varchar(191) NOT NULL DEFAULT '',
			grace_until datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY license_key (license_key),
			KEY email (email),
			KEY paypal_subscription_id (paypal_subscription_id),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( ! self::table_exists() ) {
			// dbDelta が失敗した場合のフォールバック。
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $sql );
		}
	}
}
