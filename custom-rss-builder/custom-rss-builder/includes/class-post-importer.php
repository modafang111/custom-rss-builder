<?php
/**
 * 抽出結果を WordPress 投稿として取り込む（FeedWordPress 相当）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Custom_RSS_Builder_Post_Importer {

	const META_ITEM_KEY   = '_crb_item_key';
	const META_FEED_ID    = '_crb_feed_id';
	const META_SOURCE_URL = '_crb_source_link';

	/** @var Custom_RSS_Builder_Feed_Manager */
	private $feed_manager;

	/** @var Custom_RSS_Builder_HTML_Fetcher */
	private $html_fetcher;

	/** @var Custom_RSS_Builder_Item_Builder */
	private $item_builder;

	/** @var Custom_RSS_Builder_Content_Template */
	private $content_template;

	public function __construct( $feed_manager, $html_fetcher, $item_builder = null, $content_template = null ) {
		$this->feed_manager     = $feed_manager;
		$this->html_fetcher     = $html_fetcher;
		$this->item_builder     = $item_builder ? $item_builder : new Custom_RSS_Builder_Item_Builder();
		$this->content_template = $content_template ? $content_template : new Custom_RSS_Builder_Content_Template();
	}

	/**
	 * @param int    $feed_id Feed ID.
	 * @param string $source  manual|cron|loopback|url.
	 * @return array{created:int,skipped:int,errors:string[]}|WP_Error
	 */
	public function import_feed( $feed_id, $source = 'manual' ) {
		$feed = $this->feed_manager->get_feed( $feed_id );
		if ( null === $feed ) {
			$error = new WP_Error( 'crb_feed_not_found', __( 'フィードが見つかりません。', 'custom-rss-builder' ) );
			if ( function_exists( 'crb_import_run_record' ) ) {
				crb_import_run_record( $feed_id, $source, $error );
			}
			return $error;
		}

		$import = $this->feed_manager->get_import_settings( $feed );
		if ( empty( $import['enabled'] ) ) {
			$error = new WP_Error( 'crb_import_disabled', __( '投稿への取り込みが無効です。フィード設定で有効にしてください。', 'custom-rss-builder' ) );
			if ( function_exists( 'crb_import_run_record' ) ) {
				crb_import_run_record( $feed_id, $source, $error );
			}
			return $error;
		}

		if ( '' === trim( (string) ( $import['content_template'] ?? '' ) ) ) {
			$error = new WP_Error(
				'crb_empty_content_template',
				__( '投稿本文テンプレートが未設定です。Feed43 の Item テンプレートのように、HTMLとプレースホルダーを入力してください。', 'custom-rss-builder' )
			);
			if ( function_exists( 'crb_import_run_record' ) ) {
				crb_import_run_record( $feed_id, $source, $error );
			}
			return $error;
		}

		$html = $this->html_fetcher->fetch_html( $feed['url'] );
		if ( is_wp_error( $html ) ) {
			if ( function_exists( 'crb_import_run_record' ) ) {
				crb_import_run_record( $feed_id, $source, $html );
			}
			return $html;
		}

		$parsed = crb_extract_items_from_html( $html, $feed );
		if ( is_wp_error( $parsed ) ) {
			if ( function_exists( 'crb_import_run_record' ) ) {
				crb_import_run_record( $feed_id, $source, $parsed );
			}
			return $parsed;
		}

		if ( function_exists( 'crb_ai_transform_rows' ) ) {
			$parsed = crb_ai_transform_rows( $feed, $parsed, 'import' );
		}

		$mapping = is_array( $feed['mapping'] ?? null ) ? $feed['mapping'] : array();
		$result  = array(
			'created' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		foreach ( $parsed as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$items = $this->item_builder->build_items( $feed, array( $row ) );
			if ( empty( $items ) ) {
				continue;
			}

			$import_result = $this->import_single_item( $feed, $row, $items[0], $import, $mapping );
			if ( is_wp_error( $import_result ) ) {
				$result['errors'][] = $import_result->get_error_message();
				continue;
			}
			if ( 'created' === $import_result ) {
				++$result['created'];
			} else {
				++$result['skipped'];
			}
		}

		if ( function_exists( 'crb_import_run_record' ) ) {
			crb_import_run_record( $feed_id, $source, $result );
		}

		return $result;
	}

	/**
	 * 投稿取り込みの表示プレビュー（最大5件）。
	 *
	 * @param array<string, mixed>           $feed        Feed settings.
	 * @param array<int, array<int, string>> $parsed_rows Parsed rows.
	 * @param int                            $limit       Max items.
	 * @return array{items: array<int, array{title: string, content: string}>, error?: string}
	 */
	public function preview_import_items( $feed, $parsed_rows, $limit = 5 ) {
		$import  = $this->feed_manager->get_import_settings( $feed );
		$mapping = is_array( $feed['mapping'] ?? null ) ? $feed['mapping'] : array();

		if ( '' === trim( (string) ( $import['content_template'] ?? '' ) ) ) {
			return array(
				'items' => array(),
				'error' => __( '投稿本文テンプレートを入力すると、プレビューが表示されます。', 'custom-rss-builder' ),
			);
		}

		$items_out = array();
		$count     = 0;

		foreach ( $parsed_rows as $row ) {
			if ( $count >= $limit ) {
				break;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}

			$built = $this->item_builder->build_items( $feed, array( $row ) );
			if ( empty( $built ) ) {
				continue;
			}

			$item    = $built[0];
			$title   = $this->build_post_title( $import, $item, $row, $mapping, (string) ( $item['title'] ?? '' ) );
			$content = $this->build_post_content( $import, $item, $row, $mapping );

			$items_out[] = array(
				'title'   => $title,
				'content' => $content,
			);
			++$count;
		}

		return array( 'items' => $items_out );
	}

	/**
	 * @param array<string, mixed>  $feed    Feed settings.
	 * @param array<int, string>    $row     Raw extracted row.
	 * @param array<string, string> $item    Mapped item.
	 * @param array<string, mixed>  $import  Import settings.
	 * @param array<string, int>    $mapping RSS mapping.
	 * @return string|WP_Error 'created'|'skipped'
	 */
	private function import_single_item( $feed, $row, $item, $import, $mapping ) {
		$feed_id = (int) ( $feed['id'] ?? 0 );
		$title   = (string) ( $item['title'] ?? '' );
		$link    = (string) ( $item['link'] ?? '' );

		if ( '' === $title ) {
			$title = __( '（無題）', 'custom-rss-builder' );
		}

		$item_key = $this->make_item_key( $feed_id, $link, $title );
		if ( $this->item_already_imported( $item_key ) ) {
			return 'skipped';
		}

		$post_title = $this->build_post_title( $import, $item, $row, $mapping, $title );
		$post_content = $this->build_post_content( $import, $item, $row, $mapping );

		if ( '' === trim( wp_strip_all_tags( $post_content ) ) ) {
			return new WP_Error(
				'crb_empty_post_content',
				__( '投稿本文テンプレートの結果が空です。プレースホルダーを確認してください。', 'custom-rss-builder' )
			);
		}

		$post_status = in_array( $import['post_status'], array( 'publish', 'draft', 'pending', 'private' ), true )
			? $import['post_status']
			: 'draft';
		$post_type   = post_type_exists( (string) ( $import['post_type'] ?? 'post' ) )
			? (string) $import['post_type']
			: 'post';

		$post_date = $this->parse_post_date( (string) ( $item['date'] ?? '' ) );

		$post_data = array(
			'post_title'   => wp_strip_all_tags( $post_title ),
			'post_content' => $post_content,
			'post_status'  => $post_status,
			'post_type'    => $post_type,
			'post_author'  => $this->resolve_author_id( $import ),
		);

		if ( $post_date ) {
			$post_data['post_date']     = $post_date;
			$post_data['post_date_gmt'] = get_gmt_from_date( $post_date );
		}

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( (int) $post_id, self::META_ITEM_KEY, $item_key );
		update_post_meta( (int) $post_id, self::META_FEED_ID, $feed_id );
		if ( '' !== $link ) {
			update_post_meta( (int) $post_id, self::META_SOURCE_URL, $link );
		}

		$category_id = (int) ( $import['category_id'] ?? 0 );
		if ( $category_id > 0 && 'post' === $post_type ) {
			wp_set_post_categories( (int) $post_id, array( $category_id ), false );
		}

		$tag_ids = isset( $import['tag_ids'] ) && is_array( $import['tag_ids'] )
			? $import['tag_ids']
			: array();
		if ( function_exists( 'crb_sanitize_import_tag_ids' ) ) {
			$tag_ids = crb_sanitize_import_tag_ids( $tag_ids );
		}
		if ( ! empty( $tag_ids ) && 'post' === $post_type ) {
			wp_set_post_tags( (int) $post_id, $tag_ids, false );
		}

		return 'created';
	}

	/**
	 * @param array<string, mixed>  $import  Import settings.
	 * @param array<string, string> $item    Mapped item.
	 * @param array<int, string>    $row     Raw row.
	 * @param array<string, int>    $mapping Mapping.
	 * @param string                $title   Fallback title.
	 * @return string
	 */
	private function build_post_title( $import, $item, $row, $mapping, $title ) {
		$template = trim( (string) ( $import['post_title_template'] ?? '' ) );
		if ( '' === $template ) {
			return $title;
		}

		$rendered = $this->content_template->render_plain_slot_template( $template, $row );
		$rendered = trim( wp_strip_all_tags( $rendered ) );
		return '' !== $rendered ? $rendered : $title;
	}

	/**
	 * @param array<string, mixed>  $import Import settings.
	 * @param array<string, string> $item   Mapped item.
	 * @param array<int, string>    $row    Raw row.
	 * @param array<string, int>    $mapping Mapping.
	 * @return string
	 */
	private function build_post_content( $import, $item, $row, $mapping ) {
		$template = (string) ( $import['content_template'] ?? '' );
		$content = $this->content_template->render( $template, $item, $row, $mapping );

		if ( function_exists( 'crb_license_append_free_credit' ) ) {
			$content = crb_license_append_free_credit( $content );
		}

		return $content;
	}

	private function make_item_key( $feed_id, $link, $title ) {
		$seed = ( '' !== $link ) ? $link : ( 'title:' . $title );
		return md5( (int) $feed_id . '|' . $seed );
	}

	private function item_already_imported( $item_key ) {
		$existing = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_ITEM_KEY,
				'meta_value'     => $item_key,
			)
		);
		return ! empty( $existing );
	}

	private function parse_post_date( $date ) {
		$date = trim( $date );
		if ( '' === $date ) {
			return '';
		}
		$time = strtotime( $date );
		if ( false === $time ) {
			return '';
		}
		return wp_date( 'Y-m-d H:i:s', $time );
	}

	private function resolve_author_id( $import ) {
		$author_id = (int) ( $import['author_id'] ?? 0 );
		if ( $author_id > 0 && get_userdata( $author_id ) ) {
			return $author_id;
		}
		$user_id = get_current_user_id();
		return $user_id > 0 ? $user_id : 1;
	}
}
