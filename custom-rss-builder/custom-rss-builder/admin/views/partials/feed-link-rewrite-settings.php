<?php
/**
 * リンク変換（アフィリエイト）設定。
 *
 * @var array<string, mixed> $values
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lr_defaults = function_exists( 'crb_default_link_rewrite_settings' ) ? crb_default_link_rewrite_settings() : array();
$lr_raw      = isset( $values['link_rewrite'] ) && is_array( $values['link_rewrite'] ) ? $values['link_rewrite'] : $lr_defaults;
$lr          = function_exists( 'crb_sanitize_link_rewrite_settings' ) ? crb_sanitize_link_rewrite_settings( $lr_raw ) : $lr_raw;
$lr_rules    = ! empty( $lr['rules'] ) && is_array( $lr['rules'] ) ? $lr['rules'] : array();
if ( empty( $lr_rules ) ) {
	$lr_rules = array(
		array(
			'type'          => 'prefix',
			'label'         => '',
			'enabled'       => true,
			'source_prefix' => '',
			'target_prefix' => '',
		),
	);
}
?>
<section class="crb-panel crb-panel--link-rewrite" id="crb-link-rewrite-panel">
	<header class="crb-panel__header">
		<h2 class="crb-panel__title"><?php esc_html_e( 'リンク変換（アフィリエイト）', 'custom-rss-builder' ); ?></h2>
	</header>
	<p class="crb-panel__lead">
		<?php esc_html_e( '抽出したリンク（主に {%2%}）を、アフィリエイト用 URL などに差し替えます。RSS と取り込み投稿に反映されます。', 'custom-rss-builder' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( '変換', 'custom-rss-builder' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="link_rewrite_enabled" value="1" <?php checked( ! empty( $lr['enabled'] ) ); ?>>
					<?php esc_html_e( 'リンク変換を有効にする', 'custom-rss-builder' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( '対象', 'custom-rss-builder' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="link_rewrite_all_slots" value="1" <?php checked( ! isset( $lr['all_slots'] ) || ! empty( $lr['all_slots'] ) ); ?>>
					<?php esc_html_e( '{%2%} 以外の URL スロットも変換する', 'custom-rss-builder' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<h3 class="crb-link-rewrite-rules__title"><?php esc_html_e( '変換ルール', 'custom-rss-builder' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'いちばん簡単なのは「先頭差し替え」です。リンクの固定されている先頭部分を別 URL の先頭に置き換え、残り（商品 ID など）はそのまま付けます。', 'custom-rss-builder' ); ?>
	</p>

	<?php foreach ( $lr_rules as $ri => $rule ) : ?>
		<div class="crb-link-rewrite-rule" data-rule-index="<?php echo esc_attr( (string) $ri ); ?>">
			<div class="crb-link-rewrite-rule__head">
				<label>
					<input type="checkbox" name="link_rewrite_rules[<?php echo esc_attr( (string) $ri ); ?>][enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>>
					<?php esc_html_e( 'このルールを有効', 'custom-rss-builder' ); ?>
				</label>
				<input type="hidden" name="link_rewrite_rules[<?php echo esc_attr( (string) $ri ); ?>][id]" value="<?php echo esc_attr( (string) ( $rule['id'] ?? '' ) ); ?>">
				<input
					type="text"
					name="link_rewrite_rules[<?php echo esc_attr( (string) $ri ); ?>][label]"
					class="regular-text crb-link-rewrite-rule__label"
					value="<?php echo esc_attr( (string) ( $rule['label'] ?? '' ) ); ?>"
					placeholder="<?php esc_attr_e( 'メモ（任意）例: 作品リンク', 'custom-rss-builder' ); ?>"
				>
			</div>
			<input type="hidden" name="link_rewrite_rules[<?php echo esc_attr( (string) $ri ); ?>][type]" value="prefix">

			<div class="crb-link-rewrite-prefix-fields">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label><?php esc_html_e( '元リンクの先頭', 'custom-rss-builder' ); ?></label></th>
						<td>
							<input type="url" name="link_rewrite_rules[<?php echo esc_attr( (string) $ri ); ?>][source_prefix]" class="large-text code" value="<?php echo esc_attr( (string) ( $rule['source_prefix'] ?? '' ) ); ?>" placeholder="https://example.com/list/item/">
							<p class="description"><?php esc_html_e( 'この文字列で始まるリンクだけを変換します。', 'custom-rss-builder' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label><?php esc_html_e( '差し替え先の先頭', 'custom-rss-builder' ); ?></label></th>
						<td>
							<input type="url" name="link_rewrite_rules[<?php echo esc_attr( (string) $ri ); ?>][target_prefix]" class="large-text code" value="<?php echo esc_attr( (string) ( $rule['target_prefix'] ?? '' ) ); ?>" placeholder="https://aff.example.net/track/">
						</td>
					</tr>
				</table>
			</div>
		</div>
	<?php endforeach; ?>

	<p class="description crb-link-rewrite-example">
		<?php esc_html_e( '例: 元が https://example.com/list/item/PRODUCT.html のとき、先頭を https://aff.example.net/track/ に差し替え、PRODUCT.html 部分はそのまま付けます。架空の URL です。実際の変換は「元リンクの先頭」と一致する URL だけに適用されます。', 'custom-rss-builder' ); ?>
	</p>
</section>
