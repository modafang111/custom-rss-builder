<?php
/**
 * フィード一覧の一括編集パネル。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$crb_bulk_feed_ids = isset( $crb_bulk_feed_ids ) && is_array( $crb_bulk_feed_ids ) ? array_map( 'intval', $crb_bulk_feed_ids ) : array();
$crb_bulk_feed_ids = array_values( array_filter( $crb_bulk_feed_ids, static function ( $id ) {
	return $id > 0;
} ) );

if ( empty( $crb_bulk_feed_ids ) ) {
	return;
}

$schedule_min_hours = function_exists( 'crb_import_schedule_plan_min_hours' ) ? (int) crb_import_schedule_plan_min_hours() : 1;
$schedule_max_hours = defined( 'CRB_IMPORT_SCHEDULE_MAX_HOURS' ) ? (int) CRB_IMPORT_SCHEDULE_MAX_HOURS : 168;
$ai_status          = function_exists( 'crb_ai_transform_admin_status_snapshot' ) ? crb_ai_transform_admin_status_snapshot() : array();
$ai_license         = ! empty( $ai_status['can_edit'] );
$ai_runtime         = ! empty( $ai_status['runtime_ready'] );
$model_opts         = function_exists( 'crb_ai_transform_model_options' ) ? crb_ai_transform_model_options() : array();
$instruction_max    = defined( 'CRB_AI_TRANSFORM_MAX_INSTRUCTION' ) ? (int) CRB_AI_TRANSFORM_MAX_INSTRUCTION : 2000;
$max_slot_index     = function_exists( 'crb_license_get_max_slot_index' ) ? (int) crb_license_get_max_slot_index() : 19;
$tag_sources_license = function_exists( 'crb_import_tag_sources_can_use' ) && crb_import_tag_sources_can_use();
$list_url           = admin_url( 'admin.php?page=custom-rss-builder' );
?>
<div class="crb-bulk-edit-panel crb-panel" id="crb-bulk-edit-panel">
	<div class="crb-panel__header">
		<h2 class="crb-panel__title">
			<?php
			printf(
				/* translators: %d: number of feeds */
				esc_html__( '一括編集（%d件のフィード）', 'custom-rss-builder' ),
				count( $crb_bulk_feed_ids )
			);
			?>
		</h2>
	</div>
	<p class="description">
		<?php esc_html_e( '入力・選択した項目だけ反映します。空欄や「変更しない」の項目はそのままです。余分なチェックは不要です。', 'custom-rss-builder' ); ?>
	</p>
	<form method="post" class="crb-bulk-edit-form">
		<?php wp_nonce_field( 'crb_admin_action', 'crb_nonce' ); ?>
		<input type="hidden" name="crb_action" value="bulk_update">
		<?php foreach ( $crb_bulk_feed_ids as $crb_bulk_feed_id ) : ?>
			<input type="hidden" name="feed_ids[]" value="<?php echo esc_attr( (string) (int) $crb_bulk_feed_id ); ?>">
		<?php endforeach; ?>

		<fieldset class="crb-bulk-edit-section">
			<legend><strong><?php esc_html_e( 'テンプレート・AI（選択フィードを同じ内容にする）', 'custom-rss-builder' ); ?></strong></legend>
			<?php if ( ! empty( $ai_status ) ) : ?>
				<div class="notice notice-info inline crb-bulk-edit-ai-status">
					<p>
						<?php
						printf(
							/* translators: 1: plan slug, 2: license yes/no, 3: gemini key yes/no, 4: runtime yes/no */
							esc_html__( '現在の状態 — プラン: %1$s / ライセンス: %2$s / Gemini API キー: %3$s / 変換実行: %4$s', 'custom-rss-builder' ),
							'' !== (string) ( $ai_status['plan'] ?? '' ) ? esc_html( (string) $ai_status['plan'] ) : esc_html__( '不明', 'custom-rss-builder' ),
							! empty( $ai_status['license_usable'] ) ? esc_html__( '有効', 'custom-rss-builder' ) : esc_html__( '無効', 'custom-rss-builder' ),
							! empty( $ai_status['gemini_key'] ) ? esc_html__( '設定済み', 'custom-rss-builder' ) : esc_html__( '未設定', 'custom-rss-builder' ),
							$ai_runtime ? esc_html__( '可能', 'custom-rss-builder' ) : esc_html__( '不可', 'custom-rss-builder' )
						);
						?>
					</p>
					<?php if ( ! $ai_license ) : ?>
						<p><?php esc_html_e( 'AI 変換指示の保存には Pro ライセンス（有効化済み）が必要です。', 'custom-rss-builder' ); ?></p>
					<?php elseif ( ! $ai_runtime ) : ?>
						<p>
							<?php
							printf(
								/* translators: %s: license admin URL */
								wp_kses_post( __( '指示の保存はできます。プレビュー・取り込みでの変換には <a href="%s">ライセンス画面</a> の Gemini API キーも必要です。', 'custom-rss-builder' ) ),
								esc_url( admin_url( 'admin.php?page=custom-rss-builder-license' ) )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="crb-bulk-edit-text-fields">
				<div class="crb-bulk-edit-text-field">
					<label for="crb-bulk-ai-instruction"><strong><?php esc_html_e( 'AI 変換指示', 'custom-rss-builder' ); ?></strong></label>
					<textarea
						name="bulk_ai_instruction"
						id="crb-bulk-ai-instruction"
						class="large-text code"
						rows="8"
						maxlength="<?php echo esc_attr( (string) $instruction_max ); ?>"
						placeholder="<?php esc_attr_e( '入力した場合のみ上書き。空欄なら変更しません。', 'custom-rss-builder' ); ?>"
					></textarea>
					<p class="description">
						<?php
						printf(
							/* translators: %d: max characters */
							esc_html__( '最大 %d 文字。', 'custom-rss-builder' ),
							(int) $instruction_max
						);
						?>
					</p>
				</div>
				<div class="crb-bulk-edit-text-field">
					<label for="crb-bulk-import-post-title-template"><strong><?php esc_html_e( '投稿タイトル', 'custom-rss-builder' ); ?></strong></label>
					<input
						type="text"
						name="bulk_import_post_title_template"
						id="crb-bulk-import-post-title-template"
						class="large-text code"
						placeholder="<?php esc_attr_e( '入力した場合のみ上書き。例: {%1%}の購入判断｜買うべき？', 'custom-rss-builder' ); ?>"
					>
				</div>
				<div class="crb-bulk-edit-text-field">
					<label for="crb-bulk-import-content-template"><strong><?php esc_html_e( '投稿本文', 'custom-rss-builder' ); ?></strong></label>
					<textarea
						name="bulk_import_content_template"
						id="crb-bulk-import-content-template"
						class="large-text code"
						rows="10"
						placeholder="<?php esc_attr_e( '入力した場合のみ上書き（HTML + スロット）。', 'custom-rss-builder' ); ?>"
					></textarea>
					<p class="description">
						<?php esc_html_e( '{%1%}=タイトル、{%2%}=リンクURL、{%3%}… などスロット番号が使えます。', 'custom-rss-builder' ); ?>
					</p>
				</div>
			</div>
		</fieldset>

		<fieldset class="crb-bulk-edit-section">
			<legend><strong><?php esc_html_e( 'テキストの検索・置換', 'custom-rss-builder' ); ?></strong></legend>
			<p class="description">
				<?php esc_html_e( '各フィードの既存テキスト内で、検索文字列を置換文字列に一括で置き換えます（フィードごとに内容が違っていても可）。', 'custom-rss-builder' ); ?>
			</p>
			<div class="crb-bulk-edit-find-replace">
				<p>
					<label for="crb-bulk-find-text"><?php esc_html_e( '検索', 'custom-rss-builder' ); ?></label><br>
					<input type="text" name="bulk_find_text" id="crb-bulk-find-text" class="large-text code">
				</p>
				<p>
					<label for="crb-bulk-replace-text"><?php esc_html_e( '置換後', 'custom-rss-builder' ); ?></label><br>
					<input type="text" name="bulk_replace_text" id="crb-bulk-replace-text" class="large-text code">
				</p>
				<p class="crb-bulk-edit-find-replace__targets">
					<strong><?php esc_html_e( '適用先（未選択なら全部）', 'custom-rss-builder' ); ?></strong><br>
					<label><input type="checkbox" name="bulk_find_replace_targets[]" value="ai_instruction"> <?php esc_html_e( 'AI 変換指示', 'custom-rss-builder' ); ?></label>
					<label><input type="checkbox" name="bulk_find_replace_targets[]" value="post_title_template"> <?php esc_html_e( '投稿タイトル', 'custom-rss-builder' ); ?></label>
					<label><input type="checkbox" name="bulk_find_replace_targets[]" value="post_content_template"> <?php esc_html_e( '投稿本文', 'custom-rss-builder' ); ?></label>
				</p>
			</div>
		</fieldset>

		<fieldset class="crb-bulk-edit-section">
			<legend><strong><?php esc_html_e( '取り込み・AI の基本設定', 'custom-rss-builder' ); ?></strong></legend>
			<div class="crb-bulk-edit-grid">
				<div class="crb-bulk-edit-field">
					<label for="crb-bulk-import-enabled"><?php esc_html_e( '取り込み', 'custom-rss-builder' ); ?></label>
					<select name="bulk_import_enabled" id="crb-bulk-import-enabled">
						<option value=""><?php esc_html_e( '— 変更しない —', 'custom-rss-builder' ); ?></option>
						<option value="1"><?php esc_html_e( '有効', 'custom-rss-builder' ); ?></option>
						<option value="0"><?php esc_html_e( '無効', 'custom-rss-builder' ); ?></option>
					</select>
				</div>
				<div class="crb-bulk-edit-field">
					<label for="crb-bulk-import-schedule-hours"><?php esc_html_e( '自動取り込み間隔（時間）', 'custom-rss-builder' ); ?></label>
					<input
						type="number"
						name="bulk_import_schedule_hours"
						id="crb-bulk-import-schedule-hours"
						class="small-text"
						min="-1"
						max="<?php echo esc_attr( (string) $schedule_max_hours ); ?>"
						step="1"
						placeholder="<?php esc_attr_e( '変更しない', 'custom-rss-builder' ); ?>"
					>
					<p class="description">
						<?php
						printf(
							/* translators: 1: min hours, 2: max hours */
							esc_html__( '%1$d〜%2$d。0 は自動オフ。空欄は変更しません。', 'custom-rss-builder' ),
							(int) $schedule_min_hours,
							(int) $schedule_max_hours
						);
						?>
					</p>
				</div>
				<div class="crb-bulk-edit-field">
					<label for="crb-bulk-import-post-status"><?php esc_html_e( '投稿ステータス', 'custom-rss-builder' ); ?></label>
					<select name="bulk_import_post_status" id="crb-bulk-import-post-status">
						<option value=""><?php esc_html_e( '— 変更しない —', 'custom-rss-builder' ); ?></option>
						<option value="draft"><?php esc_html_e( '下書き', 'custom-rss-builder' ); ?></option>
						<option value="publish"><?php esc_html_e( '公開', 'custom-rss-builder' ); ?></option>
						<option value="pending"><?php esc_html_e( '承認待ち', 'custom-rss-builder' ); ?></option>
						<option value="private"><?php esc_html_e( '非公開', 'custom-rss-builder' ); ?></option>
					</select>
				</div>
				<div class="crb-bulk-edit-field">
					<label for="crb-bulk-import-post-type"><?php esc_html_e( '投稿タイプ', 'custom-rss-builder' ); ?></label>
					<input type="text" name="bulk_import_post_type" id="crb-bulk-import-post-type" class="regular-text" placeholder="<?php esc_attr_e( '変更しない', 'custom-rss-builder' ); ?>">
				</div>
				<div class="crb-bulk-edit-field">
					<label for="crb-bulk-import-category-id"><?php esc_html_e( 'カテゴリー', 'custom-rss-builder' ); ?></label>
					<?php
					if ( function_exists( 'wp_dropdown_categories' ) ) {
						wp_dropdown_categories(
							array(
								'show_option_none'  => __( '— 変更しない —', 'custom-rss-builder' ),
								'option_none_value' => '',
								'name'              => 'bulk_import_category_id',
								'id'                => 'crb-bulk-import-category-id',
								'hide_empty'        => 0,
								'hierarchical'      => 1,
								'orderby'           => 'name',
								'class'             => 'crb-bulk-import-category-select',
							)
						);
					} else {
						?>
						<input type="number" name="bulk_import_category_id" id="crb-bulk-import-category-id" class="small-text" min="0" placeholder="<?php esc_attr_e( '変更しない', 'custom-rss-builder' ); ?>">
						<?php
					}
					?>
				</div>
				<div class="crb-bulk-edit-field">
					<label for="crb-bulk-import-author-id"><?php esc_html_e( '投稿者', 'custom-rss-builder' ); ?></label>
					<?php
					if ( function_exists( 'wp_dropdown_users' ) ) {
						wp_dropdown_users(
							array(
								'show_option_none'  => __( '— 変更しない —', 'custom-rss-builder' ),
								'option_none_value' => '',
								'name'              => 'bulk_import_author_id',
								'id'                => 'crb-bulk-import-author-id',
								'class'             => 'crb-bulk-import-author-select',
								'who'               => 'authors',
								'orderby'           => 'display_name',
							)
						);
					} else {
						?>
						<input type="number" name="bulk_import_author_id" id="crb-bulk-import-author-id" class="small-text" min="0" placeholder="<?php esc_attr_e( '変更しない', 'custom-rss-builder' ); ?>">
						<?php
					}
					?>
				</div>
				<div class="crb-bulk-edit-field">
					<label for="crb-bulk-ai-enabled"><?php esc_html_e( 'AIテキスト変換', 'custom-rss-builder' ); ?></label>
					<select name="bulk_ai_enabled" id="crb-bulk-ai-enabled">
						<option value=""><?php esc_html_e( '— 変更しない —', 'custom-rss-builder' ); ?></option>
						<option value="1"><?php esc_html_e( '有効', 'custom-rss-builder' ); ?></option>
						<option value="0"><?php esc_html_e( '無効', 'custom-rss-builder' ); ?></option>
					</select>
				</div>
				<?php if ( ! empty( $model_opts ) ) : ?>
					<div class="crb-bulk-edit-field">
						<label for="crb-bulk-ai-model"><?php esc_html_e( 'AI モデル', 'custom-rss-builder' ); ?></label>
						<select name="bulk_ai_model" id="crb-bulk-ai-model">
							<option value=""><?php esc_html_e( '— 変更しない —', 'custom-rss-builder' ); ?></option>
							<?php foreach ( $model_opts as $model_slug => $model_label ) : ?>
								<option value="<?php echo esc_attr( (string) $model_slug ); ?>"><?php echo esc_html( (string) $model_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
			</div>
			<?php if ( $ai_license ) : ?>
				<div class="crb-bulk-edit-text-field">
					<strong><?php esc_html_e( 'AI 変換の対象スロット', 'custom-rss-builder' ); ?></strong>
					<fieldset class="crb-ai-transform-slots crb-bulk-edit-slot-choices">
						<legend class="screen-reader-text"><?php esc_html_e( 'AI 変換の対象スロット', 'custom-rss-builder' ); ?></legend>
						<?php for ( $slot_index = 0; $slot_index <= $max_slot_index; $slot_index++ ) : ?>
							<?php
							$token = function_exists( 'crb_slot_token' ) ? crb_slot_token( $slot_index ) : '{%' . ( $slot_index + 1 ) . '}';
							?>
							<label class="crb-ai-transform-slot">
								<input
									type="checkbox"
									name="bulk_ai_transform_slots[]"
									value="<?php echo esc_attr( (string) $slot_index ); ?>"
								>
								<code><?php echo esc_html( $token ); ?></code>
							</label>
						<?php endfor; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( '1つ以上選んだ場合のみ上書きします。未選択なら変更しません。', 'custom-rss-builder' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( $tag_sources_license ) : ?>
				<div class="crb-bulk-edit-text-field">
					<strong><?php esc_html_e( 'タグ（スロットから生成）', 'custom-rss-builder' ); ?></strong>
					<fieldset class="crb-import-tag-sources__group crb-bulk-edit-slot-choices">
						<legend class="screen-reader-text"><?php esc_html_e( 'スロットから生成（記事ごと）', 'custom-rss-builder' ); ?></legend>
						<div class="crb-import-tag-sources__choices">
							<?php for ( $slot_index = 0; $slot_index <= $max_slot_index; $slot_index++ ) : ?>
								<?php
								$slot_number = $slot_index + 1;
								$token       = function_exists( 'crb_slot_token' ) ? crb_slot_token( $slot_index ) : '{%' . $slot_number . '}';
								?>
								<label class="crb-import-tag-sources__choice">
									<input
										type="checkbox"
										name="bulk_import_tag_sources_slot[]"
										value="<?php echo esc_attr( (string) $slot_number ); ?>"
									>
									<code><?php echo esc_html( $token ); ?></code>
								</label>
							<?php endfor; ?>
						</div>
					</fieldset>
					<p class="description"><?php esc_html_e( '1つ以上選んだ場合のみ上書き（固定タグは残します）。未選択なら変更しません。', 'custom-rss-builder' ); ?></p>
				</div>
			<?php endif; ?>
		</fieldset>

		<fieldset class="crb-bulk-edit-section">
			<legend><strong><?php esc_html_e( 'リンク変換（アフィリエイト）', 'custom-rss-builder' ); ?></strong></legend>
			<p class="description">
				<?php esc_html_e( '下のルール（元／置換）を入力したときだけ上書きします。ルールがあれば自動で適用され、{%2%} および URL スロットが対象です。', 'custom-rss-builder' ); ?>
			</p>
			<div class="crb-bulk-edit-text-field">
				<strong><?php esc_html_e( '変換ルール', 'custom-rss-builder' ); ?></strong>
				<p class="description">
					<?php esc_html_e( '正規表現を使う場合は「正規表現を使う」にチェック。パターンと置換の両方を入力したときだけ上書きします。', 'custom-rss-builder' ); ?>
				</p>
				<?php
				if ( function_exists( 'crb_render_link_rewrite_rules_editor' ) ) {
					crb_render_link_rewrite_rules_editor( array(), 'bulk_link_rewrite_rules' );
				}
				?>
			</div>
		</fieldset>

		<p class="crb-bulk-edit-actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( '更新', 'custom-rss-builder' ); ?></button>
			<a class="button" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'キャンセル', 'custom-rss-builder' ); ?></a>
		</p>
	</form>
</div>
