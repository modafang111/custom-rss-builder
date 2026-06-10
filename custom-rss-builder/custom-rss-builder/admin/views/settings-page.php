<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$feed_manager = crb_plugin()->feed_manager;
$license_url  = admin_url( 'admin.php?page=custom-rss-builder-license' );
$install_manual_url = function_exists( 'crb_install_manual_page_url' ) ? crb_install_manual_page_url() : '';
$show_license_notice = function_exists( 'crb_license_should_show_client_activation_ui' )
	&& crb_license_should_show_client_activation_ui();
?>
<p>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-rss-builder&action=edit' ) ); ?>" class="button button-primary">
		<?php esc_html_e( '新規フィード作成', 'custom-rss-builder' ); ?>
	</a>
	<a href="<?php echo esc_url( $license_url ); ?>" class="button"><?php esc_html_e( 'ライセンス', 'custom-rss-builder' ); ?></a>
	<?php if ( $show_license_notice && function_exists( 'crb_license_registration_portal_url' ) ) : ?>
		<?php $registration_portal_url = crb_license_registration_portal_url(); ?>
		<?php if ( '' !== $registration_portal_url ) : ?>
			<a href="<?php echo esc_url( $registration_portal_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '無料ライセンスを申請', 'custom-rss-builder' ); ?></a>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( '' !== $install_manual_url ) : ?>
		<a href="<?php echo esc_url( $install_manual_url ); ?>" class="button" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'インストール手順', 'custom-rss-builder' ); ?></a>
	<?php endif; ?>
</p>
<?php if ( empty( $feeds ) ) : ?>
	<p><?php esc_html_e( 'まだフィードがありません。', 'custom-rss-builder' ); ?></p>
<?php else : ?>
	<table class="widefat striped crb-feed-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'custom-rss-builder' ); ?></th>
				<th><?php esc_html_e( 'フィード名', 'custom-rss-builder' ); ?></th>
				<th><?php esc_html_e( '対象URL', 'custom-rss-builder' ); ?></th>
				<th><?php esc_html_e( '最終更新', 'custom-rss-builder' ); ?></th>
				<th><?php esc_html_e( '取り込み', 'custom-rss-builder' ); ?></th>
				<th><?php esc_html_e( 'RSS URL', 'custom-rss-builder' ); ?></th>
				<th><?php esc_html_e( '操作', 'custom-rss-builder' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $feeds as $feed ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $feed['id'] ); ?></td>
					<td><?php echo esc_html( (string) $feed['name'] ); ?></td>
					<td><a href="<?php echo esc_url( $feed['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $feed['url'] ); ?></a></td>
					<td><?php echo esc_html( (string) ( $feed['last_updated'] ?? '' ) ); ?></td>
					<td>
						<?php
						$import = $feed_manager->get_import_settings( $feed );
						echo ! empty( $import['enabled'] ) ? esc_html__( '有効', 'custom-rss-builder' ) : esc_html__( '無効', 'custom-rss-builder' );
						?>
					</td>
					<td><a href="<?php echo esc_url( $feed_manager->get_feed_url( (int) $feed['id'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $feed_manager->get_feed_url( (int) $feed['id'] ) ); ?></a></td>
					<td>
						<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=custom-rss-builder&action=edit&feed_id=' . (int) $feed['id'] ) ); ?>">
							<?php esc_html_e( '編集', 'custom-rss-builder' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
