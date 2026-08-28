<?php
/**
 * AI テキスト変換（フィード単位）。
 *
 * @var array<string, mixed> $values
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ai_defaults = function_exists( 'crb_default_ai_transform_settings' ) ? crb_default_ai_transform_settings() : array();
$ai_raw      = isset( $values['ai'] ) && is_array( $values['ai'] ) ? $values['ai'] : $ai_defaults;
$ai          = function_exists( 'crb_sanitize_ai_transform_settings' )
	? crb_sanitize_ai_transform_settings( $ai_raw, false )
	: $ai_raw;
$ai_license  = function_exists( 'crb_ai_transform_can_edit_feed_settings' ) && crb_ai_transform_can_edit_feed_settings();
$ai_runtime  = function_exists( 'crb_ai_transform_is_configured_globally' ) && crb_ai_transform_is_configured_globally();
$ai_slots    = array_map( 'intval', (array) ( $ai['slots'] ?? array() ) );
$max_index   = function_exists( 'crb_license_get_max_slot_index' )
	? (int) crb_license_get_max_slot_index()
	: ( ( defined( 'CRB_RECORD_SLOT_COUNT' ) ? (int) CRB_RECORD_SLOT_COUNT : 20 ) - 1 );
$model_opts  = function_exists( 'crb_ai_transform_model_options' ) ? crb_ai_transform_model_options() : array();
$key_status  = function_exists( 'crb_gemini_api_key_status_label' ) ? crb_gemini_api_key_status_label() : '';
$key_masked  = function_exists( 'crb_gemini_api_key_masked_display' ) ? crb_gemini_api_key_masked_display() : '';
?>
<section class="crb-panel crb-panel--ai-transform" id="crb-ai-transform-panel">
	<header class="crb-panel__header">
		<h2 class="crb-panel__title"><?php esc_html_e( 'AI テキスト変換（Pro）', 'custom-rss-builder' ); ?></h2>
	</header>
	<p class="crb-panel__lead">
		<?php esc_html_e( '抽出したスロットのテキストを Google Gemini で整形・要約・言い換えします。API キーはライセンス画面で設定したものを使います（BYOK）。', 'custom-rss-builder' ); ?>
	</p>

	<?php if ( ! $ai_license ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'Pro ライセンスが必要です。', 'custom-rss-builder' ); ?></p>
		</div>
	<?php elseif ( ! $ai_runtime ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				printf(
					/* translators: %s: license admin URL */
					wp_kses_post( __( 'Gemini API キーが未設定です。設定の保存はできますが、プレビュー・取り込みでの変換には <a href="%s">ライセンス画面</a> でキーが必要です。', 'custom-rss-builder' ) ),
					esc_url( admin_url( 'admin.php?page=custom-rss-builder-license' ) )
				);
				?>
			</p>
		</div>
	<?php else : ?>
		<p class="description">
			<span class="crb-ai-api-key-status crb-ai-api-key-status--saved"><?php echo esc_html( $key_status ); ?></span>
			<?php if ( '' !== $key_masked ) : ?>
				<code class="crb-ai-api-key-mask"><?php echo esc_html( $key_masked ); ?></code>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( '変換', 'custom-rss-builder' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="ai_transform_enabled" value="1" <?php checked( ! empty( $ai['enabled'] ) ); ?> <?php disabled( ! $ai_license ); ?>>
					<?php esc_html_e( 'AI テキスト変換を有効にする', 'custom-rss-builder' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="crb-ai-transform-instruction"><?php esc_html_e( '変換指示', 'custom-rss-builder' ); ?></label></th>
			<td>
				<textarea
					name="ai_transform_instruction"
					id="crb-ai-transform-instruction"
					class="large-text"
					rows="4"
					<?php disabled( ! $ai_license ); ?>
					placeholder="<?php esc_attr_e( '例: 300字以内に要約し、です・ます調に書き直す。固有名詞は維持する。', 'custom-rss-builder' ); ?>"
				><?php echo esc_textarea( (string) ( $ai['instruction'] ?? '' ) ); ?></textarea>
				<p class="description"><?php esc_html_e( '選んだスロットのテキストすべてに同じ指示を適用します。', 'custom-rss-builder' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( '対象スロット', 'custom-rss-builder' ); ?></th>
			<td>
				<fieldset class="crb-ai-transform-slots">
					<legend class="screen-reader-text"><?php esc_html_e( '対象スロット', 'custom-rss-builder' ); ?></legend>
					<?php for ( $slot_index = 0; $slot_index <= $max_index; $slot_index++ ) : ?>
						<?php
						if ( function_exists( 'crb_ai_transform_slot_is_link' ) && crb_ai_transform_slot_is_link( array( 'css' => $values['css'] ?? array() ), $slot_index ) ) {
							continue;
						}
						$token = function_exists( 'crb_slot_token' ) ? crb_slot_token( $slot_index ) : '{%' . ( $slot_index + 1 ) . '}';
						?>
						<label class="crb-ai-transform-slot">
							<input
								type="checkbox"
								name="ai_transform_slots[]"
								value="<?php echo esc_attr( (string) $slot_index ); ?>"
								<?php checked( in_array( $slot_index, $ai_slots, true ) ); ?>
								<?php disabled( ! $ai_license ); ?>
							>
							<code><?php echo esc_html( $token ); ?></code>
						</label>
					<?php endfor; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'リンク URL（{%2%} など）は対象外です。', 'custom-rss-builder' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( '適用', 'custom-rss-builder' ); ?></th>
			<td>
				<p class="description">
					<?php
					printf(
						/* translators: %d: preview row limit for AI */
						esc_html__( '有効時はプレビューと投稿取り込みのたびに変換します（RSS 配信には適用しません）。プレビューでは先頭 %d 件×選択スロット数ぶん API を呼び出します。', 'custom-rss-builder' ),
						(int) ( defined( 'CRB_AI_TRANSFORM_PREVIEW_ROW_LIMIT' ) ? CRB_AI_TRANSFORM_PREVIEW_ROW_LIMIT : 3 )
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="crb-ai-transform-model"><?php esc_html_e( 'モデル', 'custom-rss-builder' ); ?></label></th>
			<td>
				<select name="ai_transform_model" id="crb-ai-transform-model" <?php disabled( ! $ai_license ); ?>>
					<?php foreach ( $model_opts as $model_val => $model_label ) : ?>
						<option value="<?php echo esc_attr( $model_val ); ?>" <?php selected( (string) ( $ai['model'] ?? '' ), $model_val ); ?>><?php echo esc_html( $model_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'テキスト出力モデルのみ選択できます（TTS・Imagen 等は対象外）。初期値は Gemini 2.5 Flash です。無料枠の RPD はモデルごとに異なります。', 'custom-rss-builder' ); ?></p>
			</td>
		</tr>
	</table>
</section>
