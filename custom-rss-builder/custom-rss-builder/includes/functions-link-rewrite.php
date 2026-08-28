<?php
/**
 * フィードごとのリンク変換（アフィリエイト URL 等）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_LINK_REWRITE_PATTERN_MAX', 500 );
define( 'CRB_LINK_REWRITE_REPLACEMENT_MAX', 2000 );

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
 * 空のルール行（編集 UI 用）。
 *
 * @return array<string, mixed>
 */
function crb_default_link_rewrite_rule_row() {
	return array(
		'id'            => '',
		'label'         => '',
		'enabled'       => true,
		'type'          => 'prefix',
		'use_regex'     => false,
		'source_prefix' => '',
		'target_prefix' => '',
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
 * 正規表現パターンを正規化（区切り文字が無ければ #...#iu を付与）。
 *
 * @param string $pattern Raw pattern.
 * @return string Empty when invalid.
 */
function crb_sanitize_link_rewrite_regex_pattern( $pattern ) {
	$pattern = trim( (string) $pattern );
	if ( '' === $pattern ) {
		return '';
	}
	if ( function_exists( 'mb_substr' ) ) {
		$pattern = mb_substr( $pattern, 0, CRB_LINK_REWRITE_PATTERN_MAX );
	} else {
		$pattern = substr( $pattern, 0, CRB_LINK_REWRITE_PATTERN_MAX );
	}

	$delimited = crb_link_rewrite_ensure_regex_delimiters( $pattern );
	if ( '' === $delimited ) {
		return '';
	}

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$set = @preg_match( $delimited, '' );
	if ( false === $set ) {
		return '';
	}

	return $delimited;
}

/**
 * ソース欄が正規表現っぽいか（チェック漏れ救済用）。
 *
 * @param string $source Source field.
 * @return bool
 */
function crb_link_rewrite_source_looks_like_regex( $source ) {
	$source = trim( (string) $source );
	if ( '' === $source ) {
		return false;
	}
	// 区切り付き: #...#i  /.../u  など
	if ( preg_match( '#^([/#~%])(.+)\1[imsxuADSUXJ]*$#s', $source ) ) {
		return true;
	}
	// URL プレフィックスとしては不正だが正規表現によく出る目印
	if ( 0 === strpos( $source, '^' ) || false !== strpos( $source, '(?:' ) || false !== strpos( $source, '$1' ) ) {
		return true;
	}
	if ( preg_match( '/(?<!\\\\)\\([^?].*\\)/', $source ) && preg_match( '/\\\\[.?+*^$\[\](){}|]/', $source ) ) {
		return true;
	}
	return false;
}

/**
 * @param string $pattern Candidate PCRE.
 * @return bool
 */
function crb_link_rewrite_is_valid_pcre( $pattern ) {
	$pattern = (string) $pattern;
	if ( '' === $pattern ) {
		return false;
	}
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	return false !== @preg_match( $pattern, '' );
}

/**
 * 表示用に正規表現の外側区切りを外す。
 *
 * @param string $pattern Stored pattern.
 * @return string
 */
function crb_link_rewrite_strip_regex_delimiters_for_display( $pattern ) {
	$pattern = trim( (string) $pattern );
	if ( '' === $pattern ) {
		return '';
	}
	// 壊れた二重ラップ（#\#...\#i#iu 等）も含め、無効なら剥がして中身を出す。
	$body = $pattern;
	for ( $i = 0; $i < 5; $i++ ) {
		if ( ! preg_match( '/^([\/#~%!@])(.*)\1([imsxuADSUXJ]*)$/s', $body, $m ) ) {
			break;
		}
		$inner = (string) $m[2];
		$delim = (string) $m[1];
		$inner = str_replace( '\\' . $delim, $delim, $inner );
		if ( $inner === $body ) {
			break;
		}
		// 有効な区切り付きパターンはそのまま表示せず中身だけ見せる。
		$body = $inner;
	}
	return $body;
}

/**
 * パターン本体に衝突しない区切りでラップする。
 *
 * @param string $body Pattern body (no delimiters).
 * @return string Empty when invalid.
 */
function crb_link_rewrite_wrap_regex_body( $body ) {
	$body = (string) $body;
	if ( '' === $body ) {
		return '';
	}
	foreach ( array( '~', '!', '@', '%', ';', '`' ) as $delim ) {
		if ( false !== strpos( $body, $delim ) ) {
			continue;
		}
		$wrapped = $delim . $body . $delim . 'iu';
		if ( crb_link_rewrite_is_valid_pcre( $wrapped ) ) {
			return $wrapped;
		}
	}
	$wrapped = '/' . str_replace( '/', '\/', $body ) . '/iu';
	if ( crb_link_rewrite_is_valid_pcre( $wrapped ) ) {
		return $wrapped;
	}
	return '';
}

/**
 * @param string $pattern Pattern with or without delimiters.
 * @return string
 */
function crb_link_rewrite_ensure_regex_delimiters( $pattern ) {
	$pattern = trim( (string) $pattern );
	if ( '' === $pattern ) {
		return '';
	}

	/*
	 * 常に「本体を取り出して安全な区切りで付け直す」。
	 * # 区切り + パターン内の #（例: [^/?#]）や、二重ラップ（#\#...\#i#iu）で
	 * 「文法上は有効だが URL に絶対マッチしない」壊れた式になるのを防ぐ。
	 */
	$body = crb_link_rewrite_strip_regex_delimiters_for_display( $pattern );
	$wrapped = crb_link_rewrite_wrap_regex_body( $body );
	if ( '' !== $wrapped ) {
		return $wrapped;
	}

	// 本体だけでは無理なとき、元が有効なら残す（互換）。
	if ( crb_link_rewrite_is_valid_pcre( $pattern ) ) {
		return $pattern;
	}
	return '';
}

/**
 * @param string $replacement Replacement string ($1, ${1} 等可).
 * @return string
 */
function crb_sanitize_link_rewrite_regex_replacement( $replacement ) {
	$replacement = (string) $replacement;
	if ( function_exists( 'mb_substr' ) ) {
		$replacement = mb_substr( $replacement, 0, CRB_LINK_REWRITE_REPLACEMENT_MAX );
	} else {
		$replacement = substr( $replacement, 0, CRB_LINK_REWRITE_REPLACEMENT_MAX );
	}
	// 制御文字のみ除去（$1 等は残す）。
	$replacement = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $replacement );
	return is_string( $replacement ) ? $replacement : '';
}

/**
 * @param array<string, mixed> $rule Rule row.
 * @return array<string, mixed>
 */
function crb_sanitize_link_rewrite_rule( array $rule ) {
	$use_regex = ! empty( $rule['use_regex'] ) || 'regex' === sanitize_key( (string) ( $rule['type'] ?? '' ) );
	$raw_source = trim( (string) ( $rule['source_prefix'] ?? '' ) );
	// 「正規表現を使う」未チェックでも #...#i などを正規表現として救済する。
	if ( ! $use_regex && crb_link_rewrite_source_looks_like_regex( $raw_source ) ) {
		$use_regex = true;
	}

	if ( $use_regex ) {
		$source = crb_sanitize_link_rewrite_regex_pattern( $rule['source_prefix'] ?? '' );
		$target = crb_sanitize_link_rewrite_regex_replacement( $rule['target_prefix'] ?? '' );
		if ( '' === $source || '' === $target ) {
			return array();
		}

		return array(
			'id'            => sanitize_key( (string) ( $rule['id'] ?? '' ) ),
			'label'         => sanitize_text_field( (string) ( $rule['label'] ?? '' ) ),
			'enabled'       => ! empty( $rule['enabled'] ),
			'type'          => 'regex',
			'use_regex'     => true,
			'source_prefix' => $source,
			'target_prefix' => $target,
		);
	}

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
		'use_regex'     => false,
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
		// マスターON/OFF は廃止。有効なルールがあれば変換する。
		'enabled'   => false,
		'all_slots' => true,
		'rules'     => $rules,
	);
	foreach ( $rules as $rule ) {
		if ( ! empty( $rule['enabled'] ) ) {
			$settings['enabled'] = true;
			break;
		}
	}

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
 * @param string                $url  URL.
 * @param array<string, mixed>  $rule Rule.
 * @return string
 */
function crb_apply_one_link_rewrite_rule( $url, array $rule ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$use_regex = ! empty( $rule['use_regex'] ) || 'regex' === (string) ( $rule['type'] ?? '' );
	$source    = (string) ( $rule['source_prefix'] ?? '' );
	$target    = (string) ( $rule['target_prefix'] ?? '' );
	if ( '' === $source || '' === $target ) {
		return $url;
	}

	if ( $use_regex ) {
		$pattern = crb_link_rewrite_ensure_regex_delimiters( $source );
		if ( '' === $pattern ) {
			return $url;
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$replaced = @preg_replace( $pattern, $target, $url, 1 );
		if ( ! is_string( $replaced ) || $replaced === $url ) {
			return $url;
		}
		$out = trim( $replaced );
		return function_exists( 'crb_normalize_link' ) ? crb_normalize_link( $out ) : $out;
	}

	if ( 0 !== stripos( $url, $source ) ) {
		return $url;
	}
	$tail = (string) substr( $url, strlen( $source ) );
	$out  = $target . $tail;
	return function_exists( 'crb_normalize_link' ) ? crb_normalize_link( $out ) : $out;
}

/**
 * @param string               $url      URL.
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
 * @param string               $url  URL.
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
 * @param array<int, string>   $row      Slot row.
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
 * @param string $rules_key   POST key for rules array.
 * @param string $enabled_key Unused (互換のため残す)。
 * @param string $slots_key   Unused (互換のため残す)。
 * @return array<string, mixed>
 */
function crb_collect_link_rewrite_from_request( $rules_key = 'link_rewrite_rules', $enabled_key = 'link_rewrite_enabled', $slots_key = 'link_rewrite_all_slots' ) {
	unset( $enabled_key, $slots_key );
	$rules = array();
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! empty( $_POST[ $rules_key ] ) && is_array( $_POST[ $rules_key ] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		foreach ( $_POST[ $rules_key ] as $rule ) {
			if ( is_array( $rule ) ) {
				$rules[] = wp_unslash( $rule );
			}
		}
	}
	return crb_sanitize_link_rewrite_settings(
		array(
			'rules' => $rules,
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

/**
 * リンク変換ルール編集 UI（フィード編集・一括編集共用）。
 *
 * @param array<int, array<string, mixed>> $rules      Rules (display; may be empty).
 * @param string                           $field_name POST name prefix (e.g. link_rewrite_rules).
 * @return void
 */
function crb_render_link_rewrite_rules_editor( array $rules, $field_name = 'link_rewrite_rules' ) {
	$field_name = preg_replace( '/[^a-z0-9_]/', '', (string) $field_name );
	if ( '' === $field_name ) {
		$field_name = 'link_rewrite_rules';
	}
	if ( empty( $rules ) ) {
		$rules = array( crb_default_link_rewrite_rule_row() );
	}

	foreach ( $rules as $ri => $rule ) {
		if ( ! is_array( $rule ) ) {
			continue;
		}
		$use_regex = ! empty( $rule['use_regex'] ) || 'regex' === (string) ( $rule['type'] ?? '' );
		$idx       = (string) (int) $ri;
		$source    = (string) ( $rule['source_prefix'] ?? '' );
		$target    = (string) ( $rule['target_prefix'] ?? '' );
		// 表示用: 区切り文字を外して見やすくする（保存時に再付与）。
		if ( $use_regex ) {
			$source = crb_link_rewrite_strip_regex_delimiters_for_display( $source );
		}
		?>
		<div class="crb-link-rewrite-rule" data-rule-index="<?php echo esc_attr( $idx ); ?>">
			<div class="crb-link-rewrite-rule__head">
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $idx ); ?>][enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>>
					<?php esc_html_e( 'このルールを有効', 'custom-rss-builder' ); ?>
				</label>
				<label class="crb-link-rewrite-rule__regex">
					<input
						type="checkbox"
						class="crb-link-rewrite-use-regex"
						name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $idx ); ?>][use_regex]"
						value="1"
						<?php checked( $use_regex ); ?>
					>
					<?php esc_html_e( '正規表現を使う', 'custom-rss-builder' ); ?>
				</label>
				<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $idx ); ?>][id]" value="<?php echo esc_attr( (string) ( $rule['id'] ?? '' ) ); ?>">
				<input
					type="text"
					name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $idx ); ?>][label]"
					class="regular-text crb-link-rewrite-rule__label"
					value="<?php echo esc_attr( (string) ( $rule['label'] ?? '' ) ); ?>"
					placeholder="<?php esc_attr_e( 'メモ（任意）例: 作品リンク', 'custom-rss-builder' ); ?>"
				>
			</div>

			<div class="crb-link-rewrite-prefix-fields" data-mode="<?php echo esc_attr( $use_regex ? 'regex' : 'prefix' ); ?>">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label class="crb-link-rewrite-source-label">
								<span class="crb-link-rewrite-label--prefix"><?php esc_html_e( '元リンクの先頭', 'custom-rss-builder' ); ?></span>
								<span class="crb-link-rewrite-label--regex"><?php esc_html_e( '正規表現パターン', 'custom-rss-builder' ); ?></span>
							</label>
						</th>
						<td>
							<input
								type="text"
								name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $idx ); ?>][source_prefix]"
								class="large-text code crb-link-rewrite-source-input"
								value="<?php echo esc_attr( $source ); ?>"
								placeholder="<?php echo esc_attr( $use_regex ? 'https://www\\.example\\.com/.*/item/([A-Za-z0-9_-]+)' : 'https://example.com/list/item/' ); ?>"
							>
							<p class="description crb-link-rewrite-desc--prefix">
								<?php esc_html_e( 'この文字列で始まるリンクだけを変換します。', 'custom-rss-builder' ); ?>
							</p>
							<p class="description crb-link-rewrite-desc--regex">
								<?php esc_html_e( '区切り文字なしでも可（自動で #...#iu を付与）。キャプチャは $1, $2 … で置換側に使えます。', 'custom-rss-builder' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label class="crb-link-rewrite-target-label">
								<span class="crb-link-rewrite-label--prefix"><?php esc_html_e( '差し替え先の先頭', 'custom-rss-builder' ); ?></span>
								<span class="crb-link-rewrite-label--regex"><?php esc_html_e( '置換後 URL', 'custom-rss-builder' ); ?></span>
							</label>
						</th>
						<td>
							<input
								type="text"
								name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $idx ); ?>][target_prefix]"
								class="large-text code crb-link-rewrite-target-input"
								value="<?php echo esc_attr( $target ); ?>"
								placeholder="<?php echo esc_attr( $use_regex ? 'https://aff.example.net/track/$1' : 'https://aff.example.net/track/' ); ?>"
							>
							<p class="description crb-link-rewrite-desc--regex">
								<?php esc_html_e( '例: https://aff.example.net/track/$1 （$1 はパターン側の1番目のキャプチャ）', 'custom-rss-builder' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}
}
