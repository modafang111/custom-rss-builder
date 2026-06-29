<?php

if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



$crb_import_disabled = ! empty( $crb_import_disabled );

$schedule_hours      = 0;
$schedule_min_hours  = function_exists( 'crb_import_schedule_plan_min_hours' ) ? (int) crb_import_schedule_plan_min_hours() : 1;

if ( function_exists( 'crb_import_schedule_hours_from_slug' ) ) {

	$schedule_hours = crb_import_schedule_hours_from_slug( $values['import']['schedule'] ?? 'off' );

}

?>

<table class="form-table crb-import-table" role="presentation">

	<tr>

		<th scope="row"><?php esc_html_e( '取り込み', 'custom-rss-builder' ); ?></th>

		<td>

			<label>

				<input type="checkbox" name="import_enabled" value="1" <?php checked( ! empty( $values['import']['enabled'] ) ); ?> <?php disabled( $crb_import_disabled ); ?>>

				<?php esc_html_e( '有効にする', 'custom-rss-builder' ); ?>

			</label>

			<p class="description">
				<?php esc_html_e( '有効にすると「投稿に取り込み」で WordPress 投稿を作成できます。', 'custom-rss-builder' ); ?>
				<?php esc_html_e( '同じリンクURLは1回だけ取り込みます。', 'custom-rss-builder' ); ?>
			</p>

		</td>

	</tr>

	<tr class="crb-import-schedule-row">

		<th scope="row"><label for="crb-import-schedule-hours"><?php esc_html_e( '自動取り込みの間隔', 'custom-rss-builder' ); ?></label></th>

		<td>

			<label class="crb-import-schedule-field" for="crb-import-schedule-hours">

				<input

					type="number"

					name="import_schedule_hours"

					id="crb-import-schedule-hours"

					class="small-text"

					min="<?php echo esc_attr( '0' ); ?>"

					max="<?php echo esc_attr( (string) ( defined( 'CRB_IMPORT_SCHEDULE_MAX_HOURS' ) ? CRB_IMPORT_SCHEDULE_MAX_HOURS : 168 ) ); ?>"

					step="1"

					value="<?php echo esc_attr( (string) $schedule_hours ); ?>"

					<?php disabled( $crb_import_disabled ); ?>

				>

				<?php esc_html_e( '時間ごと', 'custom-rss-builder' ); ?>

			</label>

			<p class="description">

				<?php

				printf(

					/* translators: 1: min hours for current plan, 2: max hours */

					esc_html__( '数字を入力（%1$d〜%2$d）。0 は自動オフ。%1$d 以上で保存時に WordPress が自分へアクセスして取り込みを開始し、以降も間隔ごとに自己アクセスします（OS cron 不要）。無料プランは最短 24 時間です。', 'custom-rss-builder' ),

					(int) $schedule_min_hours,

					(int) ( defined( 'CRB_IMPORT_SCHEDULE_MAX_HOURS' ) ? CRB_IMPORT_SCHEDULE_MAX_HOURS : 168 )

				);

				?>

			</p>

		</td>

	</tr>

	<tr>

		<th scope="row"><?php esc_html_e( '投稿設定', 'custom-rss-builder' ); ?></th>

		<td class="crb-inline-fields">

			<span class="crb-import-post-status-field">
				<span class="crb-import-post-status-field__label"><?php esc_html_e( 'ステータス', 'custom-rss-builder' ); ?></span>
				<select name="import_post_status" id="crb-import-post-status">
					<option value="draft" <?php selected( $values['import']['post_status'], 'draft' ); ?>><?php esc_html_e( '下書き', 'custom-rss-builder' ); ?></option>
					<option value="publish" <?php selected( $values['import']['post_status'], 'publish' ); ?>><?php esc_html_e( '公開', 'custom-rss-builder' ); ?></option>
					<option value="pending" <?php selected( $values['import']['post_status'], 'pending' ); ?>><?php esc_html_e( '承認待ち', 'custom-rss-builder' ); ?></option>
					<option value="private" <?php selected( $values['import']['post_status'], 'private' ); ?>><?php esc_html_e( '非公開', 'custom-rss-builder' ); ?></option>
				</select>
			</span>

			<span class="crb-import-post-type-field">
				<span class="crb-import-post-type-field__label"><?php esc_html_e( '投稿タイプ', 'custom-rss-builder' ); ?></span>
				<input name="import_post_type" id="crb-import-post-type" type="text" class="regular-text" value="<?php echo esc_attr( (string) $values['import']['post_type'] ); ?>">
			</span>

			<span class="crb-import-category-field">
				<span class="crb-import-category-field__label"><?php esc_html_e( 'カテゴリー', 'custom-rss-builder' ); ?></span>
				<?php
				if ( function_exists( 'crb_render_import_category_dropdown' ) ) {
					crb_render_import_category_dropdown( (int) ( $values['import']['category_id'] ?? 0 ) );
				} else {
					?>
					<input name="import_category_id" id="crb-import-category-id" type="number" min="0" class="small-text" value="<?php echo esc_attr( (string) $values['import']['category_id'] ); ?>">
					<?php
				}
				?>
			</span>

			<div class="crb-import-tag-sources-wrap">
				<p class="crb-import-tag-sources-wrap__label">
					<strong><?php esc_html_e( 'タグ（Pro）', 'custom-rss-builder' ); ?></strong>
				</p>
				<?php
				if ( function_exists( 'crb_render_import_tag_sources_field' ) ) {
					crb_render_import_tag_sources_field(
						is_array( $values['import']['tag_sources'] ?? null )
							? $values['import']['tag_sources']
							: ( function_exists( 'crb_import_tag_sources_from_legacy' )
								? crb_import_tag_sources_from_legacy( $values['import'] ?? array() )
								: array() ),
						$values
					);
				}
				?>
			</div>

			<span class="crb-import-author-field">
				<span class="crb-import-author-field__label"><?php esc_html_e( '投稿者', 'custom-rss-builder' ); ?></span>
				<?php
				if ( function_exists( 'crb_render_import_author_dropdown' ) ) {
					crb_render_import_author_dropdown( (int) ( $values['import']['author_id'] ?? 0 ) );
				} else {
					?>
					<input name="import_author_id" id="crb-import-author-id" type="number" min="0" class="small-text" value="<?php echo esc_attr( (string) $values['import']['author_id'] ); ?>">
					<?php
				}
				?>
			</span>

			<p class="description">
				<?php esc_html_e( 'カテゴリー・タグは投稿タイプが post のときのみ適用されます。', 'custom-rss-builder' ); ?>
				<?php esc_html_e( 'タグ（Pro）では固定タグとスロット由来タグをまとめて指定できます。', 'custom-rss-builder' ); ?>
				<?php esc_html_e( '投稿者を「既定」のままにした場合、手動取り込みでは実行中のユーザー、自動取り込みでは管理者（ID 1）が著者になります。', 'custom-rss-builder' ); ?>
			</p>

		</td>

	</tr>

	<tr>

		<th scope="row"><label for="crb-import-post-title-template"><?php esc_html_e( '投稿タイトル', 'custom-rss-builder' ); ?></label></th>

		<td>

			<input name="import_post_title_template" id="crb-import-post-title-template" type="text" class="large-text code" value="<?php echo esc_attr( (string) $values['import']['post_title_template'] ); ?>">

			<p class="description">

				<?php esc_html_e( '任意。空欄のときは抽出タイトルを使用。', 'custom-rss-builder' ); ?>

				<?php esc_html_e( '番号の意味: {%1}=タイトル、{%2}=リンクURL（本文の <a> も href="{%2}" 推奨）。', 'custom-rss-builder' ); ?>

			</p>

		</td>

	</tr>

	<tr>

		<th scope="row"><label for="crb-import-content-template"><?php esc_html_e( '投稿本文', 'custom-rss-builder' ); ?></label></th>

		<td>

			<textarea name="import_content_template" id="crb-import-content-template" rows="6" class="large-text code" aria-describedby="crb-import-content-template-desc"><?php echo esc_textarea( (string) $values['import']['content_template'] ); ?></textarea>

			<p class="description" id="crb-import-content-template-desc">

				<?php esc_html_e( '未入力のまま保存できます。取り込みを使う場合だけ、HTML とスロット番号を記述してください。', 'custom-rss-builder' ); ?>

				<code>{%1}</code> <?php esc_html_e( 'タイトル', 'custom-rss-builder' ); ?>

				<code>{%2}</code> <?php esc_html_e( 'リンクURL', 'custom-rss-builder' ); ?>

				<code>{%3}</code> …

				<?php esc_html_e( '例: <a href="{%2}">{%1}</a>', 'custom-rss-builder' ); ?>

			</p>

			<p class="crb-build-stamp description">

				<?php
				printf(
					/* translators: %s: build id */
					esc_html__( 'プラグイン UI ビルド: %s（ここが 20260604f 以降で、投稿本文が空なら正しいファイルです）', 'custom-rss-builder' ),
					esc_html( defined( 'CRB_BUILD_ID' ) ? (string) CRB_BUILD_ID : '?' )
				);
				?>

			</p>

		</td>

	</tr>

</table>

<p class="crb-panel__actions">

	<button type="submit" name="crb_action" value="preview_posts" class="button button-secondary" <?php disabled( $crb_import_disabled ); ?>><?php esc_html_e( '投稿プレビュー', 'custom-rss-builder' ); ?></button>

	<span class="description"><?php esc_html_e( '抽出＋投稿テンプレートを反映して再取得します。', 'custom-rss-builder' ); ?></span>

</p>

