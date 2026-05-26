<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array<string, mixed> $preview_data
 */
$preview_feed  = isset( $feed ) && is_array( $feed ) ? $feed : array();
$slot_schema   = crb_get_record_slot_schema( crb_get_feed_css_config( $preview_feed ) );
$slot_count    = count( $slot_schema );
$preview_cap   = isset( $preview_data['preview_limit'] ) ? max( 1, (int) $preview_data['preview_limit'] ) : 3;
$total_rows    = ! empty( $preview_data['rows'] ) && is_array( $preview_data['rows'] ) ? count( $preview_data['rows'] ) : 0;
$preview_rows  = $total_rows > 0 ? array_slice( $preview_data['rows'], 0, $preview_cap ) : array();
$first_row     = $preview_rows[0] ?? array();
$is_slot_row   = is_array( $first_row ) && crb_is_slot_indexed_row( $first_row );
$plugin_ver    = isset( $preview_data['plugin_version'] ) ? (string) $preview_data['plugin_version'] : '';
?>
<div class="crb-preview-body">
	<?php if ( '' !== $plugin_ver ) : ?>
		<p class="description crb-build-version"><?php esc_html_e( 'プラグイン', 'custom-rss-builder' ); ?>: v<?php echo esc_html( $plugin_ver ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $preview_data['error'] ) ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( (string) $preview_data['error'] ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $preview_data['extraction_mode'] ) && 'css' === $preview_data['extraction_mode'] ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'CSS セレクタで抽出しました。', 'custom-rss-builder' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $preview_data['scope_applied'] ) ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( '範囲テンプレートを適用したうえで抽出しました。', 'custom-rss-builder' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $preview_data['html_snippet'] ) ) : ?>
		<h3><?php esc_html_e( '取得HTML（抜粋）', 'custom-rss-builder' ); ?></h3>
		<pre class="crb-snippet"><?php echo esc_html( (string) $preview_data['html_snippet'] ); ?></pre>
	<?php endif; ?>
	<?php if ( ! empty( $preview_rows ) ) : ?>
		<h3 class="crb-extraction-preview-title">
			<?php esc_html_e( '1件あたりの抽出プレビュー', 'custom-rss-builder' ); ?>
			<span class="crb-extraction-preview-count">
				<?php
				printf(
					/* translators: 1: shown count, 2: total extracted count */
					esc_html__( '（%1$d / %2$d 件を表示）', 'custom-rss-builder' ),
					min( $preview_cap, count( $preview_rows ) ),
					$total_rows
				);
				?>
			</span>
		</h3>
		<?php if ( $is_slot_row ) : ?>
			<?php foreach ( $preview_rows as $index => $row ) : ?>
				<?php if ( ! is_array( $row ) ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<div class="crb-record-preview">
					<p class="crb-record-preview__heading">
						<?php
						printf(
							/* translators: %d: item number */
							esc_html__( '%d件目', 'custom-rss-builder' ),
							(int) $index + 1
						);
						?>
					</p>
					<div class="crb-slot-lines">
						<?php for ( $slot_index = 0; $slot_index < $slot_count; $slot_index++ ) : ?>
							<?php
							$slot  = $slot_schema[ $slot_index ];
							$value = isset( $row[ $slot_index ] ) ? trim( (string) $row[ $slot_index ] ) : '';
							if ( '' === $value ) {
								continue;
							}
							$token = crb_slot_token( $slot_index );
							?>
							<div class="crb-slot-line">
								<code class="crb-slot-line__token"><?php echo esc_html( $token ); ?></code>
								<span class="crb-slot-line__value">
									<?php if ( '' === $value ) : ?>
										<span class="crb-slot-empty">—</span>
									<?php elseif ( ! empty( $slot['is_html'] ) ) : ?>
										<span class="crb-slot-line__html"><?php echo wp_kses_post( $value ); ?></span>
									<?php elseif ( ! empty( $slot['is_url'] ) || preg_match( '#^https?://#i', $value ) || 0 === strpos( $value, '//' ) ) : ?>
										<a href="<?php echo esc_url( crb_normalize_link( $value ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $value ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $value ); ?>
									<?php endif; ?>
								</span>
							</div>
						<?php endfor; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>#</th><th><?php esc_html_e( '抽出値', 'custom-rss-builder' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $preview_rows as $index => $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
						<td>
							<?php if ( is_array( $row ) ) : ?>
								<ul class="crb-preview-list">
								<?php foreach ( $row as $value_index => $value ) : ?>
									<li><strong>[<?php echo esc_html( (string) $value_index ); ?>]</strong> <?php echo esc_html( (string) $value ); ?></li>
								<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>
</div>
