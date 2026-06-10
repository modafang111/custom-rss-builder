<?php
/**
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRB_License_Server_License_Manager {

	/**
	 * @param string $license_key Key.
	 * @return array<string, mixed>|null
	 */
	public function get_by_key( $license_key ) {
		global $wpdb;

		$license_key = sanitize_text_field( (string) $license_key );
		if ( '' === $license_key ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . CRB_License_Server_Database::table_name() . ' WHERE license_key = %s LIMIT 1',
				$license_key
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param string $email Email.
	 * @param string $plan free|pro.
	 * @param array<string, mixed> $extra Extra columns.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_license( $email, $plan = 'free', array $extra = array() ) {
		global $wpdb;

		$email = sanitize_email( (string) $email );
		$plan  = sanitize_key( (string) $plan );
		if ( ! in_array( $plan, array( 'free', 'pro' ), true ) ) {
			$plan = 'free';
		}

		$now = current_time( 'mysql' );
		$key = crb_ls_generate_license_key();

		$data = array_merge(
			array(
				'license_key'              => $key,
				'email'                    => $email,
				'plan'                     => $plan,
				'status'                   => 'active',
				'site_url'                 => '',
				'paypal_subscription_id'   => '',
				'paypal_payer_email'       => $email,
				'grace_until'              => null,
				'created_at'               => $now,
				'updated_at'               => $now,
			),
			$extra
		);

		$inserted = $wpdb->insert(
			CRB_License_Server_Database::table_name(),
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'crb_ls_db_insert', __( 'ライセンスの保存に失敗しました。', 'crb-license-server' ) );
		}

		$row = $this->get_by_key( $key );
		if ( ! $row ) {
			return new WP_Error( 'crb_ls_db_read', __( 'ライセンスの読み込みに失敗しました。', 'crb-license-server' ) );
		}

		if ( '' !== $email ) {
			crb_ls_email_license_key( $email, $key, $plan );
		}

		return $row;
	}

	/**
	 * @param string $license_key Key.
	 * @param string $site_url Site URL.
	 * @return array<string, mixed>|WP_Error
	 */
	public function activate( $license_key, $site_url ) {
		$row = $this->get_by_key( $license_key );
		if ( ! $row ) {
			return new WP_Error( 'crb_ls_not_found', __( 'ライセンスキーが見つかりません。', 'crb-license-server' ), array( 'status' => 404 ) );
		}

		if ( ! crb_ls_license_is_usable( $row ) ) {
			return new WP_Error( 'crb_ls_inactive', __( 'ライセンスが無効です。お支払い状況をご確認ください。', 'crb-license-server' ), array( 'status' => 403 ) );
		}

		$site_url = crb_ls_normalize_site_url( $site_url );
		if ( '' === $site_url ) {
			return new WP_Error( 'crb_ls_bad_site', __( 'サイト URL が不正です。', 'crb-license-server' ), array( 'status' => 400 ) );
		}

		$bound = trim( (string) ( $row['site_url'] ?? '' ) );
		if ( '' !== $bound && $bound !== $site_url ) {
			return new WP_Error(
				'crb_ls_site_mismatch',
				__( 'このライセンスは別のサイトで既に有効化されています。', 'crb-license-server' ),
				array( 'status' => 409 )
			);
		}

		global $wpdb;
		$wpdb->update(
			CRB_License_Server_Database::table_name(),
			array(
				'site_url'   => $site_url,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $row['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$fresh = $this->get_by_key( $license_key );
		return $fresh ? $fresh : $row;
	}

	/**
	 * @param string $license_key Key.
	 * @param string $site_url Site URL.
	 * @return true|WP_Error
	 */
	public function deactivate( $license_key, $site_url ) {
		$row = $this->get_by_key( $license_key );
		if ( ! $row ) {
			return new WP_Error( 'crb_ls_not_found', __( 'ライセンスキーが見つかりません。', 'crb-license-server' ), array( 'status' => 404 ) );
		}

		$site_url = crb_ls_normalize_site_url( $site_url );
		$bound    = trim( (string) ( $row['site_url'] ?? '' ) );
		if ( '' !== $bound && '' !== $site_url && $bound !== $site_url ) {
			return new WP_Error( 'crb_ls_site_mismatch', __( 'サイト URL が一致しません。', 'crb-license-server' ), array( 'status' => 409 ) );
		}

		global $wpdb;
		$wpdb->update(
			CRB_License_Server_Database::table_name(),
			array(
				'site_url'   => '',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $row['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * @param string $license_key Key.
	 * @param string $site_url Site URL.
	 * @return array<string, mixed>|WP_Error
	 */
	public function check( $license_key, $site_url ) {
		$row = $this->get_by_key( $license_key );
		if ( ! $row ) {
			return new WP_Error( 'crb_ls_not_found', __( 'ライセンスキーが見つかりません。', 'crb-license-server' ), array( 'status' => 404 ) );
		}

		$site_url = crb_ls_normalize_site_url( $site_url );
		$bound    = trim( (string) ( $row['site_url'] ?? '' ) );
		if ( '' !== $bound && $bound !== $site_url ) {
			return new WP_Error( 'crb_ls_site_mismatch', __( '別のサイトで有効化されています。', 'crb-license-server' ), array( 'status' => 409 ) );
		}

		if ( ! crb_ls_license_is_usable( $row ) ) {
			return new WP_Error( 'crb_ls_inactive', __( 'ライセンスが無効です。', 'crb-license-server' ), array( 'status' => 403 ) );
		}

		return $this->format_public_license( $row );
	}

	/**
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	public function format_public_license( array $row ) {
		return array(
			'license_key' => (string) ( $row['license_key'] ?? '' ),
			'plan'        => crb_ls_license_plan( $row ),
			'status'      => sanitize_key( (string) ( $row['status'] ?? '' ) ),
			'site_url'    => (string) ( $row['site_url'] ?? '' ),
			'usable'      => crb_ls_license_is_usable( $row ),
		);
	}

	/**
	 * @param int    $id License ID.
	 * @param string $status Status.
	 * @param string $plan Plan.
	 * @return array<string, mixed>|WP_Error
	 */
	public function set_status( $id, $status, $plan = '' ) {
		global $wpdb;

		$id = (int) $id;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . CRB_License_Server_Database::table_name() . ' WHERE id = %d LIMIT 1',
				$id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'crb_ls_not_found', __( 'ライセンスが見つかりません。', 'crb-license-server' ) );
		}

		$patch = array(
			'status'     => sanitize_key( (string) $status ),
			'updated_at' => current_time( 'mysql' ),
		);

		if ( '' !== $plan ) {
			$patch['plan'] = sanitize_key( (string) $plan );
		}

		if ( 'past_due' === $patch['status'] ) {
			$patch['grace_until'] = gmdate( 'Y-m-d H:i:s', time() + ( 7 * DAY_IN_SECONDS ) );
		} elseif ( in_array( $patch['status'], array( 'active', 'cancelled', 'expired' ), true ) ) {
			$patch['grace_until'] = null;
		}

		$wpdb->update(
			CRB_License_Server_Database::table_name(),
			$patch,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);

		$fresh = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . CRB_License_Server_Database::table_name() . ' WHERE id = %d LIMIT 1',
				$id
			),
			ARRAY_A
		);

		return is_array( $fresh ) ? $fresh : $row;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_licenses( $limit = 200 ) {
		global $wpdb;

		$limit = max( 1, min( 500, (int) $limit ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . CRB_License_Server_Database::table_name() . ' ORDER BY id DESC LIMIT %d',
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * One free license per email.
	 *
	 * @param string $email Email.
	 * @return array<string, mixed>|WP_Error
	 */
	public function register_free( $email ) {
		global $wpdb;

		$email = sanitize_email( (string) $email );
		if ( ! crb_ls_is_valid_email( $email ) ) {
			return new WP_Error( 'crb_ls_bad_email', __( 'メールアドレスが不正です。', 'crb-license-server' ) );
		}

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . CRB_License_Server_Database::table_name() . ' WHERE email = %s AND plan = %s LIMIT 1',
				$email,
				'free'
			),
			ARRAY_A
		);

		if ( is_array( $existing ) ) {
			if ( crb_ls_license_is_usable( $existing ) ) {
				crb_ls_email_license_key( $email, (string) $existing['license_key'], 'free' );
				return $existing;
			}
		}

		return $this->create_license( $email, 'free' );
	}
}
