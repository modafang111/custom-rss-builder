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
?>
<section class="crb-panel crb-panel--link-rewrite" id="crb-link-rewrite-panel">
	<header class="crb-panel__header">
		<h2 class="crb-panel__title"><?php esc_html_e( 'リンク変換（アフィリエイト）', 'custom-rss-builder' ); ?></h2>
	</header>
	<p class="crb-panel__lead">
		<?php esc_html_e( '抽出した URL（{%2%} および URL スロット）をアフィリエイト用などに差し替えます。ルールを入れると自動で適用されます。⑤のプレビュー・RSS・取り込みに反映されます（③の試し読みには出ません）。', 'custom-rss-builder' ); ?>
	</p>

	<h3 class="crb-link-rewrite-rules__title"><?php esc_html_e( '変換ルール', 'custom-rss-builder' ); ?></h3>
	<p class="description">
		<?php esc_html_e( '通常は「先頭差し替え」です。#...# やキャプチャ付きの式を使う場合は「正規表現を使う」にチェックしてください（# で始まる式は自動判定もします）。ルールを空にすれば変換しません。', 'custom-rss-builder' ); ?>
	</p>

	<?php
	if ( function_exists( 'crb_render_link_rewrite_rules_editor' ) ) {
		crb_render_link_rewrite_rules_editor( $lr_rules, 'link_rewrite_rules' );
	}
	?>

	<p class="description crb-link-rewrite-example">
		<?php esc_html_e( '先頭差し替えの例: 元が https://example.com/list/item/PRODUCT.html のとき、先頭を https://aff.example.net/track/ に差し替え、PRODUCT.html 部分はそのまま付けます。', 'custom-rss-builder' ); ?>
		<br>
		<?php esc_html_e( '正規表現の例: パターン ^https?://(?:www\\.)?duga\\.jp(/ppv/[a-z0-9][a-z0-9\\-]*-\\d+)/? → 置換 https://click.duga.jp$1/24697-03（前後の #...# は不要）', 'custom-rss-builder' ); ?>
	</p>
</section>
