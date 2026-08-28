<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$feed_manager = crb_plugin()->feed_manager;
$license_url  = admin_url( 'admin.php?page=custom-rss-builder-license' );
$install_manual_url = function_exists( 'crb_install_manual_page_url' ) ? crb_install_manual_page_url() : '';
$show_license_notice = function_exists( 'crb_license_should_show_client_activation_ui' )
	&& crb_license_should_show_client_activation_ui();
$crb_bulk_feed_ids = isset( $crb_bulk_feed_ids ) && is_array( $crb_bulk_feed_ids ) ? $crb_bulk_feed_ids : array();
$list_url          = admin_url( 'admin.php?page=custom-rss-builder' );
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

<?php if ( ! empty( $crb_bulk_feed_ids ) ) : ?>
	<?php include CRB_PLUGIN_DIR . 'admin/views/partials/feed-bulk-edit-panel.php'; ?>
<?php endif; ?>
<?php if ( empty( $feeds ) ) : ?>
	<p><?php esc_html_e( 'まだフィードがありません。', 'custom-rss-builder' ); ?></p>
<?php else : ?>
	<form method="post" class="crb-feed-list-form" id="crb-feed-list-form">
		<?php wp_nonce_field( 'crb_admin_action', 'crb_nonce' ); ?>
		<input type="hidden" name="crb_action" value="bulk_list_action">
		<div class="tablenav top crb-feed-list-nav">
			<div class="alignleft actions bulkactions crb-feed-bulkactions">
				<label for="crb-bulk-action-selector-top" class="screen-reader-text"><?php esc_html_e( '一括操作を選択', 'custom-rss-builder' ); ?></label>
				<select name="crb_bulk_action" id="crb-bulk-action-selector-top">
					<option value="-1"><?php esc_html_e( '一括操作', 'custom-rss-builder' ); ?></option>
					<option value="edit"><?php esc_html_e( '一括編集', 'custom-rss-builder' ); ?></option>
					<option value="delete"><?php esc_html_e( '削除', 'custom-rss-builder' ); ?></option>
				</select>
				<input type="submit" class="button action" id="crb-do-bulk-action-top" value="<?php esc_attr_e( '適用', 'custom-rss-builder' ); ?>">
			</div>
			<br class="clear">
		</div>
		<table class="widefat striped crb-feed-table">
			<thead>
				<tr>
					<td id="cb" class="manage-column column-cb check-column">
						<input id="crb-select-all-feeds" type="checkbox" aria-label="<?php esc_attr_e( 'すべて選択', 'custom-rss-builder' ); ?>">
					</td>
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
					<?php
					$feed_id = (int) ( $feed['id'] ?? 0 );
					$is_bulk_selected = in_array( $feed_id, $crb_bulk_feed_ids, true );
					?>
					<tr<?php echo $is_bulk_selected ? ' class="crb-feed-row--bulk-selected"' : ''; ?>>
						<th scope="row" class="check-column">
							<input
								type="checkbox"
								name="feed_ids[]"
								value="<?php echo esc_attr( (string) $feed_id ); ?>"
								class="crb-feed-checkbox"
								<?php checked( $is_bulk_selected ); ?>
								aria-label="<?php echo esc_attr( (string) ( $feed['name'] ?? '' ) ); ?>"
							>
						</th>
						<td><?php echo esc_html( (string) $feed_id ); ?></td>
						<td><?php echo esc_html( (string) $feed['name'] ); ?></td>
						<td><a href="<?php echo esc_url( $feed['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $feed['url'] ); ?></a></td>
						<td><?php echo esc_html( (string) ( $feed['last_updated'] ?? '' ) ); ?></td>
						<td>
							<?php
							$import = $feed_manager->get_import_settings( $feed );
							echo ! empty( $import['enabled'] ) ? esc_html__( '有効', 'custom-rss-builder' ) : esc_html__( '無効', 'custom-rss-builder' );
							?>
						</td>
						<td><a href="<?php echo esc_url( $feed_manager->get_feed_url( $feed_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $feed_manager->get_feed_url( $feed_id ) ); ?></a></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=custom-rss-builder&action=edit&feed_id=' . $feed_id ) ); ?>">
								<?php esc_html_e( '編集', 'custom-rss-builder' ); ?>
							</a>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=custom-rss-builder&action=edit&duplicate_from=' . $feed_id ) ); ?>">
								<?php esc_html_e( '複製', 'custom-rss-builder' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<td class="manage-column column-cb check-column">
						<input id="crb-select-all-feeds-bottom" type="checkbox" aria-label="<?php esc_attr_e( 'すべて選択', 'custom-rss-builder' ); ?>">
					</td>
					<th><?php esc_html_e( 'ID', 'custom-rss-builder' ); ?></th>
					<th><?php esc_html_e( 'フィード名', 'custom-rss-builder' ); ?></th>
					<th><?php esc_html_e( '対象URL', 'custom-rss-builder' ); ?></th>
					<th><?php esc_html_e( '最終更新', 'custom-rss-builder' ); ?></th>
					<th><?php esc_html_e( '取り込み', 'custom-rss-builder' ); ?></th>
					<th><?php esc_html_e( 'RSS URL', 'custom-rss-builder' ); ?></th>
					<th><?php esc_html_e( '操作', 'custom-rss-builder' ); ?></th>
				</tr>
			</tfoot>
		</table>
		<div class="tablenav bottom crb-feed-list-nav">
			<div class="alignleft actions bulkactions crb-feed-bulkactions">
				<label for="crb-bulk-action-selector-bottom" class="screen-reader-text"><?php esc_html_e( '一括操作を選択', 'custom-rss-builder' ); ?></label>
				<select name="crb_bulk_action2" id="crb-bulk-action-selector-bottom">
					<option value="-1"><?php esc_html_e( '一括操作', 'custom-rss-builder' ); ?></option>
					<option value="edit"><?php esc_html_e( '一括編集', 'custom-rss-builder' ); ?></option>
					<option value="delete"><?php esc_html_e( '削除', 'custom-rss-builder' ); ?></option>
				</select>
				<input type="submit" class="button action" id="crb-do-bulk-action-bottom" value="<?php esc_attr_e( '適用', 'custom-rss-builder' ); ?>">
			</div>
			<br class="clear">
		</div>
	</form>
<?php endif; ?>

<div class="crb-panel crb-csv-import-panel" id="crb-csv-import-panel">
	<div class="crb-panel__header">
		<h2 class="crb-panel__title"><?php esc_html_e( 'CSV一括登録・ダウンロード', 'custom-rss-builder' ); ?></h2>
	</div>
	<p class="crb-panel__lead">
		<?php esc_html_e( 'URL・カテゴリーに加え、CSS セレクタ、スロット割当、取り込み／AI／リンク変換まで列で指定して一括登録できます。空欄の列はデフォルトのままです。', 'custom-rss-builder' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( '1行目はヘッダー。必須は url のみ。改行を含むテンプレは "..." で囲んでください。', 'custom-rss-builder' ); ?>
	</p>
	<?php if ( function_exists( 'crb_csv_column_help_lines' ) ) : ?>
		<ul class="crb-csv-import-help">
			<?php foreach ( crb_csv_column_help_lines() as $help_line ) : ?>
				<li><?php echo esc_html( $help_line ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<pre class="crb-csv-import-sample" aria-label="<?php esc_attr_e( 'CSV見本', 'custom-rss-builder' ); ?>"><?php
	echo esc_html( "url,category,name,scope_selector,item_selector,link_selector,import_enabled,import_schedule_hours,import_post_status,import_post_title_template\n" );
	echo esc_html( "https://example.com/list/,ニュース,サンプル,#main,article.item,a,1,24,draft,{%1%}\n" );
	echo esc_html( 'https://other.example/articles/,12,その他,,,,,,' );
	?></pre>
	<form method="post" enctype="multipart/form-data" class="crb-csv-import-form" action="<?php echo esc_url( $list_url ); ?>">
		<?php wp_nonce_field( 'crb_admin_action', 'crb_nonce' ); ?>
		<input type="hidden" name="crb_action" value="csv_import">
		<p>
			<label for="crb-csv-file"><strong><?php esc_html_e( 'CSVファイル', 'custom-rss-builder' ); ?></strong></label><br>
			<input type="file" name="crb_csv_file" id="crb-csv-file" accept=".csv,text/csv,text/plain">
		</p>
		<p>
			<label for="crb-csv-text"><strong><?php esc_html_e( 'または CSV を貼り付け', 'custom-rss-builder' ); ?></strong></label><br>
			<textarea name="crb_csv_text" id="crb-csv-text" class="large-text code" rows="8" cols="60" placeholder="<?php echo esc_attr( function_exists( 'crb_csv_sample_header_line' ) ? crb_csv_sample_header_line() : 'url,category,name' ); ?>"></textarea>
		</p>
		<p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'CSVから登録', 'custom-rss-builder' ); ?></button>
			<?php if ( ! empty( $feeds ) && function_exists( 'crb_csv_export_download_url' ) ) : ?>
				<a href="<?php echo esc_url( crb_csv_export_download_url() ); ?>" class="button">
					<?php esc_html_e( '現在のフィードをCSVダウンロード', 'custom-rss-builder' ); ?>
				</a>
			<?php endif; ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'ダウンロード CSV は登録とほぼ同じ列です（先頭に id）。再インポートすると新規フィードとして追加されます（id での上書き更新はしません）。', 'custom-rss-builder' ); ?>
		</p>
	</form>
</div>
