<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table crb-import-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( '取り込み', 'custom-rss-builder' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="import_enabled" value="1" <?php checked( ! empty( $values['import']['enabled'] ) ); ?>>
				<?php esc_html_e( '有効にする', 'custom-rss-builder' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( '投稿設定', 'custom-rss-builder' ); ?></th>
		<td class="crb-inline-fields">
			<label><?php esc_html_e( 'ステータス', 'custom-rss-builder' ); ?>
				<select name="import_post_status">
					<option value="draft" <?php selected( $values['import']['post_status'], 'draft' ); ?>><?php esc_html_e( '下書き', 'custom-rss-builder' ); ?></option>
					<option value="publish" <?php selected( $values['import']['post_status'], 'publish' ); ?>><?php esc_html_e( '公開', 'custom-rss-builder' ); ?></option>
					<option value="pending" <?php selected( $values['import']['post_status'], 'pending' ); ?>><?php esc_html_e( '承認待ち', 'custom-rss-builder' ); ?></option>
					<option value="private" <?php selected( $values['import']['post_status'], 'private' ); ?>><?php esc_html_e( '非公開', 'custom-rss-builder' ); ?></option>
				</select>
			</label>
			<label><?php esc_html_e( '投稿タイプ', 'custom-rss-builder' ); ?>
				<input name="import_post_type" type="text" class="regular-text" value="<?php echo esc_attr( (string) $values['import']['post_type'] ); ?>">
			</label>
			<label><?php esc_html_e( 'カテゴリID', 'custom-rss-builder' ); ?>
				<input name="import_category_id" type="number" min="0" class="small-text" value="<?php echo esc_attr( (string) $values['import']['category_id'] ); ?>">
			</label>
			<label><?php esc_html_e( '著者ID', 'custom-rss-builder' ); ?>
				<input name="import_author_id" type="number" min="0" class="small-text" value="<?php echo esc_attr( (string) $values['import']['author_id'] ); ?>">
			</label>
			<p class="description"><?php esc_html_e( '著者ID 0 = 実行ユーザー（cron 時は要確認）', 'custom-rss-builder' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="crb-import-post-title-template"><?php esc_html_e( '投稿タイトル', 'custom-rss-builder' ); ?></label></th>
		<td>
			<input name="import_post_title_template" id="crb-import-post-title-template" type="text" class="large-text code" value="<?php echo esc_attr( (string) $values['import']['post_title_template'] ); ?>" placeholder="{%6}">
			<p class="description">
				<?php esc_html_e( '任意。空欄のときは抽出タイトルを使用。', 'custom-rss-builder' ); ?>
				<?php esc_html_e( '番号の意味: {%1}=タイトル、{%2}=リンクURL（本文の <a> も href="{%2}" 推奨）。', 'custom-rss-builder' ); ?>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="crb-import-content-template"><?php esc_html_e( '投稿本文', 'custom-rss-builder' ); ?></label></th>
		<td>
			<textarea name="import_content_template" id="crb-import-content-template" rows="6" class="large-text code" placeholder="<?php echo esc_attr( $import_template_example ); ?>"><?php echo esc_textarea( (string) $values['import']['content_template'] ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Feed43 の Item テンプレートと同様、HTMLを自由に記述できます。', 'custom-rss-builder' ); ?>
				<code>{%1}</code> <?php esc_html_e( 'タイトル', 'custom-rss-builder' ); ?>
				<code>{%2}</code> <?php esc_html_e( 'リンクURL', 'custom-rss-builder' ); ?>
				<code>{%3}</code> …
				<span class="description"><?php esc_html_e( '例: <a href="{%2}">{%1}</a>。{%1} と {%2} を逆にするとプレビューが壊れます。', 'custom-rss-builder' ); ?></span>
				· <code>{{0}}</code> <code>%1</code>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'オプション', 'custom-rss-builder' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="import_append_source" value="1" <?php checked( ! empty( $values['import']['append_source'] ) ); ?>>
				<?php esc_html_e( '本文のあとに「元記事を読む」を追記', 'custom-rss-builder' ); ?>
			</label>
			<p class="description"><?php esc_html_e( '同じリンクURLは1回だけ取り込みます。', 'custom-rss-builder' ); ?></p>
		</td>
	</tr>
</table>
<p class="crb-panel__actions">
	<button type="submit" name="crb_action" value="preview_posts" class="button button-secondary"><?php esc_html_e( '投稿プレビュー', 'custom-rss-builder' ); ?></button>
	<span class="description"><?php esc_html_e( '抽出＋投稿テンプレートを反映して再取得します。', 'custom-rss-builder' ); ?></span>
</p>
