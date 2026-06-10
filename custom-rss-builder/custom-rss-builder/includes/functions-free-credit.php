<?php
/**
 * 無料プラン向けクレジット表示（クライアントサイトのみ）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** クレジットブロック識別子（削除後の再付与判定用）。 */
define( 'CRB_FREE_CREDIT_MARKER', 'crb-free-credit-v1' );

/**
 * 無料プランでクレジット表示が必要か。
 *
 * @return bool
 */
function crb_license_requires_free_credit() {
	if ( ! function_exists( 'crb_is_client_app_enabled' ) || ! crb_is_client_app_enabled() ) {
		return false;
	}
	if ( function_exists( 'crb_license_is_authoritative_server' ) && crb_license_is_authoritative_server() ) {
		return false;
	}
	if ( ! function_exists( 'crb_license_get_state' ) ) {
		return false;
	}
	$state = crb_license_get_state();
	return ! empty( $state['usable'] ) && 'free' === ( $state['plan'] ?? '' );
}

/**
 * クレジットリンク先（Pro では非表示のため正本 LP を既定）。
 *
 * @return string
 */
function crb_free_credit_url() {
	$default = 'https://123789.jp/custom-rss-builder/';
	if ( defined( 'CRB_FREE_CREDIT_URL' ) ) {
		$default = (string) CRB_FREE_CREDIT_URL;
	}
	$url = (string) apply_filters( 'crb_free_credit_url', $default );
	return esc_url( $url );
}

/**
 * @return string HTML（不要時は空文字）。
 */
function crb_license_get_free_credit_html() {
	static $cached = null;

	if ( null !== $cached ) {
		return $cached;
	}

	if ( ! crb_license_requires_free_credit() ) {
		$cached = '';
		return '';
	}

	$url  = crb_free_credit_url();
	$html = sprintf(
		'<!-- %1$s --><div class="crb-free-credit" data-crb-credit="%1$s" style="margin-top:1.5em;padding-top:0.75em;border-top:1px solid #e0e0e0;font-size:12px;line-height:1.5;color:#666;text-align:center;">'
		. '<a href="%2$s" rel="nofollow noopener noreferrer" target="_blank" style="color:inherit;text-decoration:underline;">%3$s</a></div>',
		esc_attr( CRB_FREE_CREDIT_MARKER ),
		esc_url( $url ),
		esc_html__( 'Custom RSS Builder', 'custom-rss-builder' )
	);

	$cached = (string) apply_filters( 'crb_free_credit_html', $html, $url );
	return $cached;
}

/**
 * @param string $html Content.
 * @return bool
 */
function crb_license_content_has_free_credit( $html ) {
	$html = (string) $html;
	return '' !== $html && (
		false !== strpos( $html, CRB_FREE_CREDIT_MARKER )
		|| false !== strpos( $html, 'data-crb-credit="' . CRB_FREE_CREDIT_MARKER . '"' )
	);
}

/**
 * 本文・RSS 説明の末尾にクレジットを付与（既にあればそのまま）。
 *
 * @param string $html HTML or plain text.
 * @return string
 */
function crb_license_append_free_credit( $html ) {
	$html = (string) $html;
	if ( ! crb_license_requires_free_credit() ) {
		return $html;
	}
	$credit = crb_license_get_free_credit_html();
	if ( '' === $credit || crb_license_content_has_free_credit( $html ) ) {
		return $html;
	}
	return $html . $credit;
}

/**
 * テーマフッターにクレジット（無料プラン・フロントのみ）。
 */
function crb_license_render_site_footer_credit() {
	if ( is_admin() || ! crb_license_requires_free_credit() ) {
		return;
	}

	// 取り込み投稿の単一表示では本文末尾に既に付与するため、テーマフッターは省略。
	if ( is_singular() && class_exists( 'Custom_RSS_Builder_Post_Importer' ) ) {
		$post_id = (int) get_queried_object_id();
		if ( $post_id > 0 && (int) get_post_meta( $post_id, Custom_RSS_Builder_Post_Importer::META_FEED_ID, true ) > 0 ) {
			return;
		}
	}

	$credit = crb_license_get_free_credit_html();
	if ( '' === $credit ) {
		return;
	}
	echo $credit; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* above.
}

/**
 * CRB 取り込み投稿で本文からクレジットが消されていたら再付与。
 *
 * @param string $content Post content.
 * @return string
 */
function crb_license_filter_the_content_free_credit( $content ) {
	if ( ! crb_license_requires_free_credit() || ! is_singular() ) {
		return $content;
	}

	$post_id = (int) get_the_ID();
	if ( $post_id <= 0 ) {
		return $content;
	}

	if ( ! class_exists( 'Custom_RSS_Builder_Post_Importer' ) ) {
		return $content;
	}

	$feed_id = (int) get_post_meta( $post_id, Custom_RSS_Builder_Post_Importer::META_FEED_ID, true );
	if ( $feed_id <= 0 ) {
		return $content;
	}

	return crb_license_append_free_credit( $content );
}

add_action( 'wp_footer', 'crb_license_render_site_footer_credit', 99 );
add_filter( 'the_content', 'crb_license_filter_the_content_free_credit', 99 );
