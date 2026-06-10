<?php
/**
 * フィードごとのリンク変換（アフィリエイト URL 等）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, mixed>
 */
function crb_default_link_rewrite_settings() {
	return array(
		'enabled'   => false,
		'all_slots' => true,
		'rules'     => array(),
	);
}

/**
 * 旧版の入力例ボタンが保存したルールか。
 *
 * @param array<string, mixed> $rule Rule row.
 * @return bool
 */
function crb_is_legacy_bundled_link_rewrite_example( array $rule ) {
	return 'example-prefix-affiliate' === (string) ( $rule['id'] ?? '' );
}

/**
 * @param array<string, mixed> $settings Sanitized settings.
 * @return array<string, mixed>
 */
function crb_purge_legacy_bundled_link_rewrite( array $settings ) {
	$rules = isset( $settings['rules'] ) && is_array( $settings['rules'] ) ? $settings['rules'] : array();
	if ( empty( $rules ) ) {
		return $settings;
	}
	$out    = array();
	$purged = false;
	foreach ( $rules as $rule ) {
		if ( ! is_array( $rule ) ) {
			continue;
		}
		if ( crb_is_legacy_bundled_link_rewrite_example( $rule ) ) {
			$purged = true;
			continue;
		}
		$out[] = $rule;
	}
	if ( ! $purged ) {
		return $settings;
	}
	$settings['rules'] = $out;
	if ( empty( $out ) ) {
		$settings['enabled'] = false;
	}
	return $settings;
}

/**
 * @param string $url URL prefix.
 * @return string
 */
function crb_sanitize_link_rewrite_url_prefix( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
		return '';
	}
	return $url;
}

/**
 * @param array<string, mixed> $rule Rule row.
 * @return array<string, mixed>
 */
function crb_sanitize_link_rewrite_rule( array $rule ) {
	$source = crb_sanitize_link_rewrite_url_prefix( $rule['source_prefix'] ?? '' );
	$target = crb_sanitize_link_rewrite_url_prefix( $rule['target_prefix'] ?? '' );
	if ( '' === $source || '' === $target ) {
		return array();
	}

	return array(
		'id'            => sanitize_key( (string) ( $rule['id'] ?? '' ) ),
		'label'         => sanitize_text_field( (string) ( $rule['label'] ?? '' ) ),
		'enabled'       => ! empty( $rule['enabled'] ),
		'type'          => 'prefix',
		'source_prefix' => $source,
		'target_prefix' => $target,
	);
}

/**
 * @param mixed $raw Raw settings.
 * @return array<string, mixed>
 */
function crb_sanitize_link_rewrite_settings( $raw ) {
	$defaults = crb_default_link_rewrite_settings();
	if ( ! is_array( $raw ) ) {
		return $defaults;
	}

	$rules = array();
	if ( ! empty( $raw['rules'] ) && is_array( $raw['rules'] ) ) {
		foreach ( $raw['rules'] as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$clean = crb_sanitize_link_rewrite_rule( $rule );
			if ( ! empty( $clean ) ) {
				$rules[] = $clean;
			}
		}
	}

	$settings = array(
		'enabled'   => ! empty( $raw['enabled'] ),
		'all_slots' => ! isset( $raw['all_slots'] ) || ! empty( $raw['all_slots'] ),
		'rules'     => $rules,
	);

	return crb_purge_legacy_bundled_link_rewrite( $settings );
}

/**
 * @param array<string, mixed> $feed Feed row.
 * @return array<string, mixed>
 */
function crb_get_feed_link_rewrite( array $feed ) {
	$raw = $feed['link_rewrite'] ?? array();
	return crb_sanitize_link_rewrite_settings( $raw );
}

/**
 * @param string               $url  URL.
 * @param array<string, string> $rule Rule.
 * @return string
 */
function crb_apply_one_link_rewrite_rule( $url, array $rule ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$source = (string) ( $rule['source_prefix'] ?? '' );
	$target = (string) ( $rule['target_prefix'] ?? '' );
	if ( '' === $source || '' === $target ) {
		return $url;
	}
	if ( 0 !== stripos( $url, $source ) ) {
		return $url;
	}
	$tail = (string) substr( $url, strlen( $source ) );
	$out  = $target . $tail;
	return function_exists( 'crb_normalize_link' ) ? crb_normalize_link( $out ) : $out;
}

/**
 * @param string               $url  URL.
 * @param array<string, mixed> $settings Sanitized link_rewrite settings.
 * @return string
 */
function crb_apply_link_rewrite_rules( $url, array $settings ) {
	$url = trim( (string) $url );
	if ( '' === $url || empty( $settings['enabled'] ) ) {
		return $url;
	}
	$rules = isset( $settings['rules'] ) && is_array( $settings['rules'] ) ? $settings['rules'] : array();
	foreach ( $rules as $rule ) {
		if ( empty( $rule['enabled'] ) || ! is_array( $rule ) ) {
			continue;
		}
		$next = crb_apply_one_link_rewrite_rule( $url, $rule );
		if ( $next !== $url ) {
			return $next;
		}
	}
	return $url;
}

/**
 * @param string               $url URL.
 * @param array<string, mixed> $feed Feed row.
 * @return string
 */
function crb_feed_rewrite_url( $url, array $feed ) {
	return crb_apply_link_rewrite_rules( $url, crb_get_feed_link_rewrite( $feed ) );
}

/**
 * @param string $value Candidate URL.
 * @return bool
 */
function crb_looks_like_http_url( $value ) {
	return (bool) preg_match( '#^https?://#i', trim( (string) $value ) );
}

/**
 * @param array<int, string>   $row Slot row.
 * @param array<string, mixed> $settings Link rewrite settings.
 * @return array<int, string>
 */
function crb_apply_link_rewrite_to_slot_row( array $row, array $settings ) {
	if ( empty( $settings['enabled'] ) ) {
		return $row;
	}

	$link_index = 1;
	if ( isset( $row[ $link_index ] ) ) {
		$row[ $link_index ] = crb_apply_link_rewrite_rules( (string) $row[ $link_index ], $settings );
	}

	if ( empty( $settings['all_slots'] ) ) {
		return $row;
	}

	foreach ( $row as $index => $value ) {
		$index = (int) $index;
		if ( $index === $link_index ) {
			continue;
		}
		$value = (string) $value;
		if ( '' === $value ) {
			continue;
		}
		if ( crb_looks_like_http_url( $value ) ) {
			$row[ $index ] = crb_apply_link_rewrite_rules( $value, $settings );
		}
	}

	return $row;
}

/**
 * 管理画面 POST から link_rewrite を組み立てる。
 *
 * @return array<string, mixed>
 */
function crb_collect_link_rewrite_from_request() {
	$rules = array();
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! empty( $_POST['link_rewrite_rules'] ) && is_array( $_POST['link_rewrite_rules'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		foreach ( $_POST['link_rewrite_rules'] as $rule ) {
			if ( is_array( $rule ) ) {
				$rules[] = $rule;
			}
		}
	}
	return crb_sanitize_link_rewrite_settings(
		array(
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'enabled'   => ! empty( $_POST['link_rewrite_enabled'] ),
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			'all_slots' => ! empty( $_POST['link_rewrite_all_slots'] ),
			'rules'     => $rules,
		)
	);
}

/**
 * @param array<int, array<int|string, string>> $rows Extracted rows.
 * @param array<string, mixed>                  $feed Feed row.
 * @return array<int, array<int|string, string>>
 */
function crb_feed_apply_link_rewrites_to_rows( array $rows, array $feed ) {
	$settings = crb_get_feed_link_rewrite( $feed );
	if ( empty( $settings['enabled'] ) ) {
		return $rows;
	}
	$out = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			$out[] = $row;
			continue;
		}
		if ( crb_is_slot_indexed_row( $row ) ) {
			$out[] = crb_apply_link_rewrite_to_slot_row( $row, $settings );
			continue;
		}
		if ( function_exists( 'crb_is_named_record' ) && crb_is_named_record( $row ) ) {
			$slot  = crb_record_to_slot_row( $row );
			$out[] = crb_apply_link_rewrite_to_slot_row( $slot, $settings );
			continue;
		}
		$out[] = $row;
	}
	return $out;
}
