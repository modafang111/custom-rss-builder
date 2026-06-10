<?php
/**
 * フィード設定の保存・取得。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Feed_Manager {

	public function get_feeds() {
		$feeds = get_option( CRB_OPTION_FEEDS, array() );
		return is_array( $feeds ) ? $feeds : array();
	}

	public function get_feed( $feed_id ) {
		$feeds   = $this->get_feeds();
		$feed_id = (int) $feed_id;
		return isset( $feeds[ $feed_id ] ) ? $feeds[ $feed_id ] : null;
	}

	public function save_feed( $feed_data ) {
		$feeds   = $this->get_feeds();
		$feed_id = isset( $feed_data['id'] ) ? (int) $feed_data['id'] : 0;

		if ( $feed_id <= 0 || ! isset( $feeds[ $feed_id ] ) ) {
			$feed_id = $this->get_next_id();
		}

		$existing = isset( $feeds[ $feed_id ] ) ? $feeds[ $feed_id ] : array();

		$extraction_mode = sanitize_key( (string) ( $feed_data['extraction_mode'] ?? 'template' ) );
		if ( ! in_array( $extraction_mode, array( 'template', 'css' ), true ) ) {
			$extraction_mode = 'template';
		}

		$feeds[ $feed_id ] = array(
			'id'              => $feed_id,
			'name'            => sanitize_text_field( (string) ( $feed_data['name'] ?? '' ) ),
			'url'             => esc_url_raw( (string) ( $feed_data['url'] ?? '' ) ),
			'extraction_mode' => $extraction_mode,
			'css'             => crb_sanitize_css_config( $feed_data['css'] ?? array() ),
			'scope_template'  => crb_sanitize_template( (string) ( $feed_data['scope_template'] ?? '' ) ),
			'template'        => crb_sanitize_template( (string) ( $feed_data['template'] ?? '' ) ),
			'mapping'         => $this->sanitize_mapping( $feed_data['mapping'] ?? array() ),
			'link_rewrite'    => $this->sanitize_link_rewrite( $feed_data['link_rewrite'] ?? ( $existing['link_rewrite'] ?? array() ) ),
			'ai'              => $this->sanitize_ai_transform( $feed_data['ai'] ?? ( $existing['ai'] ?? array() ) ),
			'import'        => $this->sanitize_import( $feed_data['import'] ?? ( $existing['import'] ?? array() ) ),
			'last_updated'  => current_time( 'mysql' ),
			'last_imported'   => (string) ( $existing['last_imported'] ?? '' ),
			'last_import_run' => is_array( $existing['last_import_run'] ?? null ) ? $existing['last_import_run'] : array(),
		);

		update_option( CRB_OPTION_FEEDS, $feeds, false );

		if ( function_exists( 'crb_import_scheduler' ) ) {
			$scheduler = crb_import_scheduler();
			if ( $scheduler ) {
				$scheduler->reschedule_feed( $feed_id, true );
			}
		}

		return $feed_id;
	}

	/**
	 * @param int                  $feed_id Feed ID.
	 * @param array<string, mixed> $fields  Fields to merge.
	 */
	public function touch_feed( $feed_id, $fields = array() ) {
		$feeds   = $this->get_feeds();
		$feed_id = (int) $feed_id;
		if ( ! isset( $feeds[ $feed_id ] ) ) {
			return;
		}
		foreach ( $fields as $key => $value ) {
			$feeds[ $feed_id ][ $key ] = $value;
		}
		$feeds[ $feed_id ]['last_updated'] = current_time( 'mysql' );
		update_option( CRB_OPTION_FEEDS, $feeds, false );
	}

	public function delete_feed( $feed_id ) {
		$feeds   = $this->get_feeds();
		$feed_id = (int) $feed_id;
		if ( ! isset( $feeds[ $feed_id ] ) ) {
			return false;
		}
		unset( $feeds[ $feed_id ] );

		if ( function_exists( 'crb_import_scheduler' ) ) {
			$scheduler = crb_import_scheduler();
			if ( $scheduler ) {
				$scheduler->unschedule_feed( $feed_id );
			}
		}

		update_option( CRB_OPTION_FEEDS, $feeds, false );
		return true;
	}

	/**
	 * RSS 配信用 URL（クエリ形式。サブディレクトリ設置でも確実に動作）。
	 *
	 * @param int $feed_id Feed ID.
	 * @return string
	 */
	public function get_feed_url( $feed_id ) {
		return add_query_arg( 'custom_rss_builder_feed_id', (int) $feed_id, home_url( '/' ) );
	}

	/**
	 * @param array<string, mixed> $feed Feed row.
	 * @return array<string, mixed>
	 */
	public function get_import_settings( $feed ) {
		$defaults = $this->default_import_settings();
		$import   = is_array( $feed['import'] ?? null ) ? $feed['import'] : array();
		return array_merge( $defaults, $import );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function default_import_settings() {
		return array(
			'enabled'             => false,
			'schedule'            => 'off',
			'post_status'         => 'draft',
			'post_type'           => 'post',
			'post_title_template' => '',
			'content_template'    => '',
			'append_source'       => false,
			'category_id'         => 0,
			'tag_ids'             => array(),
			'author_id'           => 0,
		);
	}

	private function sanitize_mapping( $mapping ) {
		$defaults = array(
			'title'       => 0,
			'link'        => 1,
			'description' => 2,
			'date'        => -1,
		);
		if ( ! is_array( $mapping ) ) {
			return $defaults;
		}
		$result = array();
		foreach ( $defaults as $key => $default ) {
			$value = isset( $mapping[ $key ] ) ? (int) $mapping[ $key ] : $default;
			if ( 'date' === $key && $value < 0 ) {
				$result[ $key ] = -1;
				continue;
			}
			$result[ $key ] = max( 0, $value );
		}
		if ( function_exists( 'crb_license_clamp_mapping' ) ) {
			$result = crb_license_clamp_mapping( $result );
		}
		return $result;
	}

	/**
	 * @param mixed $import Raw import settings.
	 * @return array<string, mixed>
	 */
	/**
	 * @param mixed $raw Raw link rewrite settings.
	 * @return array<string, mixed>
	 */
	private function sanitize_link_rewrite( $raw ) {
		return function_exists( 'crb_sanitize_link_rewrite_settings' )
			? crb_sanitize_link_rewrite_settings( $raw )
			: array();
	}

	/**
	 * @param mixed $raw Raw AI transform settings.
	 * @return array<string, mixed>
	 */
	private function sanitize_ai_transform( $raw ) {
		return function_exists( 'crb_sanitize_ai_transform_settings' )
			? crb_sanitize_ai_transform_settings( $raw, true )
			: array();
	}

	private function sanitize_import( $import ) {
		$defaults = $this->default_import_settings();
		if ( ! is_array( $import ) ) {
			return $defaults;
		}

		$status = (string) ( $import['post_status'] ?? 'draft' );
		if ( ! in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
			$status = 'draft';
		}

		$post_type = sanitize_key( (string) ( $import['post_type'] ?? 'post' ) );
		if ( ! post_type_exists( $post_type ) ) {
			$post_type = 'post';
		}

		$enabled  = ! empty( $import['enabled'] );
		$schedule = function_exists( 'crb_sanitize_import_schedule' )
			? crb_sanitize_import_schedule( $import['schedule'] ?? 'off' )
			: 'off';
		if ( ! $enabled ) {
			$schedule = 'off';
		}

		return array(
			'enabled'             => $enabled,
			'schedule'            => $schedule,
			'post_status'         => $status,
			'post_type'           => $post_type,
			'post_title_template' => crb_sanitize_template( (string) ( $import['post_title_template'] ?? '' ) ),
			'content_template'    => crb_sanitize_import_content_template( (string) ( $import['content_template'] ?? '' ) ),
			'append_source'       => false,
			'category_id'         => function_exists( 'crb_sanitize_import_category_id' )
				? crb_sanitize_import_category_id( $import['category_id'] ?? 0 )
				: max( 0, (int) ( $import['category_id'] ?? 0 ) ),
			'tag_ids'             => function_exists( 'crb_sanitize_import_tag_ids' )
				? crb_sanitize_import_tag_ids( $import['tag_ids'] ?? array() )
				: array(),
			'author_id'           => function_exists( 'crb_sanitize_import_author_id' )
				? crb_sanitize_import_author_id( $import['author_id'] ?? 0 )
				: max( 0, (int) ( $import['author_id'] ?? 0 ) ),
		);
	}

	private function get_next_id() {
		$next_id = (int) get_option( CRB_OPTION_NEXT_ID, 1 );
		if ( $next_id < 1 ) {
			$next_id = 1;
		}
		update_option( CRB_OPTION_NEXT_ID, $next_id + 1, false );
		return $next_id;
	}
}
