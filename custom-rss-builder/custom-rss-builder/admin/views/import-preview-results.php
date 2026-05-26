<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$import_preview = isset( $preview_data['import_posts'] ) && is_array( $preview_data['import_posts'] )
	? $preview_data['import_posts']
	: array();
$import_items   = isset( $import_preview['items'] ) && is_array( $import_preview['items'] )
	? $import_preview['items']
	: array();
?>
<div class="crb-import-preview-body">
	<?php if ( ! empty( $import_preview['error'] ) ) : ?>
		<div class="notice notice-warning inline"><p><?php echo esc_html( (string) $import_preview['error'] ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $import_items ) ) : ?>
		<p class="description"><?php esc_html_e( '実際の投稿に近い表示です（最大5件）。画像タグをテンプレートに含めた場合もここで確認できます。', 'custom-rss-builder' ); ?></p>
		<?php foreach ( $import_items as $index => $post_preview ) : ?>
			<article class="crb-post-preview-card">
				<h3 class="crb-post-preview-card__title">
					<?php echo esc_html( (string) ( $post_preview['title'] ?? '' ) ); ?>
				</h3>
				<div class="crb-post-preview-card__content">
					<?php
					// プレビュー用。取り込み時と同じ wp_kses_post 済み HTML。
					echo $post_preview['content'] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			</article>
		<?php endforeach; ?>
	<?php elseif ( empty( $import_preview['error'] ) ) : ?>
		<p class="crb-panel__placeholder"><?php esc_html_e( '投稿本文テンプレートを入力し、「投稿プレビュー」を実行してください。', 'custom-rss-builder' ); ?></p>
	<?php endif; ?>
</div>
