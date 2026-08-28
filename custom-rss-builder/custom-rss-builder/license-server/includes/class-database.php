<?php
/**
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRB_License_Server_Database {

	const SCHEMA_VERSION = '1.1';

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . CRB_LS_TABLE;
	}

	/**
	 * @return string
	 */
	public static function sites_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'crb_license_sites';
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
		if ( self::table_exists() && self::sites_table_exists() && self::SCHEMA_VERSION === $installed ) {
			return;
		}
		self::install();
		self::migrate_legacy_site_urls();
		update_option( 'crb_ls_db_version', self::SCHEMA_VERSION, false );
	}

	/**
	 * @return bool
	 */
	public static function sites_table_exists() {
		global $wpdb;

		$table = self::sites_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $found === $table;
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

		$sites_table = self::sites_table_name();
		$sites_sql   = "CREATE TABLE {$sites_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			license_id bigint(20) unsigned NOT NULL,
			site_url varchar(255) NOT NULL DEFAULT '',
			activated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY license_site (license_id, site_url),
			KEY license_id (license_id)
		) {$charset};";

		dbDelta( $sites_sql );

		if ( ! self::sites_table_exists() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $sites_sql );
		}
	}

	/**
	 * 旧 site_url 列の値を crb_license_sites へ移行。
	 */
	public static function migrate_legacy_site_urls() {
		if ( ! self::table_exists() || ! self::sites_table_exists() ) {
			return;
		}

		global $wpdb;

		$table = self::table_name();
		$sites = self::sites_table_name();
		$rows  = $wpdb->get_results(
			"SELECT id, site_url FROM {$table} WHERE site_url <> ''",
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return;
		}

		$now = current_time( 'mysql' );
		foreach ( $rows as $row ) {
			$license_id = (int) ( $row['id'] ?? 0 );
			$site_url   = trim( (string) ( $row['site_url'] ?? '' ) );
			if ( $license_id <= 0 || '' === $site_url ) {
				continue;
			}
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$sites} WHERE license_id = %d AND site_url = %s LIMIT 1",
					$license_id,
					$site_url
				)
			);
			if ( $exists ) {
				continue;
			}
			$wpdb->insert(
				$sites,
				array(
					'license_id'   => $license_id,
					'site_url'     => $site_url,
					'activated_at' => $now,
				),
				array( '%d', '%s', '%s' )
			);
		}
	}
}
