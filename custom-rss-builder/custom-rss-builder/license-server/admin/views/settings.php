<?php
/**
 * @var string $api_secret
 * @var string $pro_payment_url
 * @var string $register_hint
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_authority_ops_notice = function_exists( 'crb_license_is_authoritative_server' )
	&& crb_license_is_authoritative_server();

$mail_from_name       = crb_ls_mail_from_name();
$mail_from_email      = crb_ls_mail_from_email();
$mail_subject_default = 'Custom RSS Builder ライセンスキーのご案内（{plan_label}）';
$mail_subject         = crb_ls_get_option( 'mail_subject', $mail_subject_default );
$mail_intro           = crb_ls_get_option( 'mail_intro', '' );
if ( '' === trim( $mail_intro ) && function_exists( 'crb_ls_mail_default_intro' ) ) {
	$mail_intro = crb_ls_mail_default_intro();
}
$mail_activation_hint = crb_ls_get_option( 'mail_activation_hint', '' );
$mail_body            = crb_ls_get_option( 'mail_body', '' );
$mail_download_url         = crb_ls_get_option( 'mail_download_url', '' );
$mail_download_url_pro     = crb_ls_get_option( 'mail_download_url_pro', '' );
$mail_download_url_free    = crb_ls_get_option( 'mail_download_url_free', '' );
$mail_download_password    = crb_ls_get_option( 'mail_download_password', '' );
$mail_download_heading     = crb_ls_get_option(
	'mail_download_heading',
	__( '【プラグインのダウンロード】', 'crb-license-server' )
);
$mail_download_install_hint = crb_ls_get_option( 'mail_download_install_hint', '' );
$mail_subject_preview = crb_ls_mail_subject_template( 'pro' );
$mail_body_preview_pro = function_exists( 'crb_ls_mail_body' )
	? crb_ls_mail_body( 'XXXX-XXXX-XXXX-XXXX', 'pro' )
	: '';
$mail_body_preview_free = function_exists( 'crb_ls_mail_body' )
	? crb_ls_mail_body( 'YYYY-YYYY-YYYY-YYYY', 'free' )
	: '';
$mail_install_manual_url = function_exists( 'crb_ls_mail_install_manual_url' )
	? crb_ls_mail_install_manual_url()
	: '';
$mail_locked          = defined( 'CRB_LS_MAIL_FROM' ) || defined( 'CRB_LS_MAIL_FROM_NAME' ) || defined( 'CRB_LS_MAIL_SUBJECT' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'CRB ライセンス設定', 'crb-license-server' ); ?></h1>

	<?php if ( ! empty( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '設定を保存しました。', 'crb-license-server' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['mail-reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'メール文面を推奨テンプレートにリセットしました。', 'crb-license-server' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['crb-samples-reinstalled'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '製品・練習用の固定ページを更新しました。', 'crb-license-server' ); ?></p></div>
	<?php endif; ?>

	<?php if ( function_exists( 'crb_license_is_authoritative_server' ) && crb_license_is_authoritative_server() && function_exists( 'crb_demo_samples_index_url' ) ) : ?>
		<div class="card" style="max-width:720px;margin:1em 0;padding:1em;">
			<h2 class="title"><?php esc_html_e( '製品・練習用固定ページ', 'crb-license-server' ); ?></h2>
			<p><?php esc_html_e( '販売用の親固定ページと、その下の練習用サンプル（子ページ）を作成します。旧バージョンの「投稿」は再作成時にゴミ箱へ移します。', 'crb-license-server' ); ?></p>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( crb_demo_samples_index_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '製品・練習用ページを開く', 'crb-license-server' ); ?></a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
				<?php wp_nonce_field( 'crb_demo_samples_reinstall' ); ?>
				<input type="hidden" name="action" value="crb_demo_samples_reinstall">
				<?php submit_button( __( 'サンプル固定ページを再作成', 'crb-license-server' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
	<?php endif; ?>

	<?php if ( function_exists( 'crb_license_is_authoritative_server' ) && crb_license_is_authoritative_server() && function_exists( 'crb_install_manual_page_url' ) ) : ?>
		<?php $crb_install_manual_url = crb_install_manual_page_url(); ?>
		<?php if ( '' !== $crb_install_manual_url ) : ?>
		<div class="card" style="max-width:720px;margin:1em 0;padding:1em;">
			<h2 class="title"><?php esc_html_e( 'インストール手順（クライアント向け）', 'crb-license-server' ); ?></h2>
			<p><?php esc_html_e( 'お客様 WordPress への ZIP インストールから利用開始までの手順を固定ページとして公開します。クライアントの管理画面からも同じ URL へリンクされます。', 'crb-license-server' ); ?></p>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( $crb_install_manual_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'インストール手順ページを開く', 'crb-license-server' ); ?></a>
			</p>
		</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( function_exists( 'crb_license_is_authoritative_server' ) && crb_license_is_authoritative_server() && function_exists( 'crb_ai_manual_page_url' ) ) : ?>
		<?php $crb_ai_manual_url = crb_ai_manual_page_url(); ?>
		<?php if ( '' !== $crb_ai_manual_url ) : ?>
		<div class="card" style="max-width:720px;margin:1em 0;padding:1em;">
			<h2 class="title"><?php esc_html_e( 'Gemini API キー設定手順（クライアント向け）', 'crb-license-server' ); ?></h2>
			<p><?php esc_html_e( 'Pro 利用者向けの設定手順を固定ページとして公開します。クライアントのライセンス画面からも同じ URL へリンクされます。', 'crb-license-server' ); ?></p>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( $crb_ai_manual_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '設定手順ページを開く', 'crb-license-server' ); ?></a>
			</p>
		</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $show_authority_ops_notice ) : ?>
	<div class="notice notice-info inline" style="margin:1em 0;padding:1em;">
		<p><strong><?php esc_html_e( 'このサイトでの使い方', 'crb-license-server' ); ?></strong></p>
		<p><?php esc_html_e( 'Pro キーは決済確認後にライセンス一覧から手動発行します。無料キーは [crb_free_license] の登録フォーム、またはクライアントサイトからの REST（issue-free）で発行されます。', 'crb-license-server' ); ?></p>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=crb-license-server' ) ); ?>"><?php esc_html_e( 'ライセンス一覧', 'crb-license-server' ); ?></a>
			<?php if ( function_exists( 'crb_is_client_app_enabled' ) && crb_is_client_app_enabled() ) : ?>
				<?php esc_html_e( ' · ', 'crb-license-server' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-rss-builder-license' ) ); ?>"><?php esc_html_e( 'プラグインのライセンス画面（開発用）', 'crb-license-server' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'crb_ls_settings' ); ?>

		<h2><?php esc_html_e( 'プラグイン ZIP 配布（ダウンロードマネージャ連携）', 'crb-license-server' ); ?></h2>
		<p class="description"><?php esc_html_e( 'WordPress Download Manager 等で発行した URL・パスワードをここに一度だけ登録すると、ライセンスメールに自動挿入されます（本文テンプレートへの手入力は不要）。', 'crb-license-server' ); ?></p>
		<table class="form-table">
			<tr>
				<th><label for="mail_download_url"><?php esc_html_e( 'ダウンロード URL（共通）', 'crb-license-server' ); ?></label></th>
				<td>
					<input type="url" class="large-text" name="mail_download_url" id="mail_download_url" value="<?php echo esc_attr( $mail_download_url ); ?>" placeholder="https://example.com/download/..." />
					<p class="description"><?php esc_html_e( '無料・Pro 共通の client ZIP リンク。プラン別 URL が空のときに使われます。', 'crb-license-server' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="mail_download_url_pro"><?php esc_html_e( 'ダウンロード URL（Pro のみ）', 'crb-license-server' ); ?></label></th>
				<td>
					<input type="url" class="large-text" name="mail_download_url_pro" id="mail_download_url_pro" value="<?php echo esc_attr( $mail_download_url_pro ); ?>" />
					<p class="description"><?php esc_html_e( '任意。空欄なら共通 URL を使用。', 'crb-license-server' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="mail_download_url_free"><?php esc_html_e( 'ダウンロード URL（無料のみ）', 'crb-license-server' ); ?></label></th>
				<td>
					<input type="url" class="large-text" name="mail_download_url_free" id="mail_download_url_free" value="<?php echo esc_attr( $mail_download_url_free ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="mail_download_password"><?php esc_html_e( 'ダウンロードパスワード', 'crb-license-server' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="mail_download_password" id="mail_download_password" value="<?php echo esc_attr( $mail_download_password ); ?>" autocomplete="off" />
				</td>
			</tr>
			<tr>
				<th><label for="mail_download_heading"><?php esc_html_e( 'ダウンロード見出し', 'crb-license-server' ); ?></label></th>
				<td>
					<input type="text" class="large-text" name="mail_download_heading" id="mail_download_heading" value="<?php echo esc_attr( $mail_download_heading ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="mail_download_install_hint"><?php esc_html_e( 'インストール案内（ZIP 取得後）', 'crb-license-server' ); ?></label></th>
				<td>
					<textarea name="mail_download_install_hint" id="mail_download_install_hint" class="large-text" rows="2" placeholder="<?php echo esc_attr__( '空欄のときはインストールマニュアルへの案内を自動挿入します。', 'crb-license-server' ); ?>"><?php echo esc_textarea( $mail_download_install_hint ); ?></textarea>
				</td>
			</tr>
			<?php if ( '' !== $mail_install_manual_url ) : ?>
			<tr>
				<th><?php esc_html_e( 'インストールマニュアル', 'crb-license-server' ); ?></th>
				<td>
					<code style="word-break:break-all;"><?php echo esc_html( $mail_install_manual_url ); ?></code>
					<p class="description"><?php esc_html_e( 'メールには {install_manual_block} として自動挿入されます。', 'crb-license-server' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<h2><?php esc_html_e( 'ライセンスキー通知メール', 'crb-license-server' ); ?></h2>
		<?php if ( $mail_locked ) : ?>
			<p class="description"><?php esc_html_e( '送信者名・送信者メール・件名は固定設定のため、この画面では変更できません。', 'crb-license-server' ); ?></p>
		<?php endif; ?>
		<table class="form-table">
			<tr>
				<th><label for="mail_from_name"><?php esc_html_e( '送信者名', 'crb-license-server' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" name="mail_from_name" id="mail_from_name" value="<?php echo esc_attr( crb_ls_get_option( 'mail_from_name', 'Custom RSS Builder' ) ); ?>" <?php disabled( defined( 'CRB_LS_MAIL_FROM_NAME' ) ); ?> />
				</td>
			</tr>
			<tr>
				<th><label for="mail_from_email"><?php esc_html_e( '送信者メール', 'crb-license-server' ); ?></label></th>
				<td>
					<input type="email" class="regular-text" name="mail_from_email" id="mail_from_email" value="<?php echo esc_attr( crb_ls_get_option( 'mail_from_email', '' ) ); ?>" placeholder="info@example.com" <?php disabled( defined( 'CRB_LS_MAIL_FROM' ) ); ?> />
					<p class="description"><?php esc_html_e( 'wordpress@ ドメインは迷惑メールになりやすいため、独自ドメインのアドレスを指定してください（例: info@123789.jp）。未設定の場合は WordPress デフォルトになります。', 'crb-license-server' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="mail_subject"><?php esc_html_e( '件名', 'crb-license-server' ); ?></label></th>
				<td>
					<input type="text" class="large-text" name="mail_subject" id="mail_subject" value="<?php echo esc_attr( $mail_subject ); ?>" <?php disabled( defined( 'CRB_LS_MAIL_SUBJECT' ) ); ?> />
					<p class="description">
						<?php esc_html_e( '利用可能: {plan_label} {plan} {site_name}', 'crb-license-server' ); ?>
						<?php if ( ! defined( 'CRB_LS_MAIL_SUBJECT' ) ) : ?>
							<br />
							<?php
							printf(
								/* translators: %s: preview subject */
								esc_html__( 'プレビュー（Pro）: %s', 'crb-license-server' ),
								esc_html( $mail_subject_preview )
							);
							?>
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="mail_intro"><?php esc_html_e( '本文（冒頭）', 'crb-license-server' ); ?></label></th>
				<td>
					<textarea name="mail_intro" id="mail_intro" class="large-text" rows="2"><?php echo esc_textarea( $mail_intro ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th><label for="mail_activation_hint"><?php esc_html_e( '本文（有効化の案内・上書き）', 'crb-license-server' ); ?></label></th>
				<td>
					<textarea name="mail_activation_hint" id="mail_activation_hint" class="large-text" rows="3" placeholder="<?php echo esc_attr__( '空欄のときは {activation_block} の標準手順を使用します。', 'crb-license-server' ); ?>"><?php echo esc_textarea( $mail_activation_hint ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th><label for="mail_body"><?php esc_html_e( '本文テンプレート（上級）', 'crb-license-server' ); ?></label></th>
				<td>
					<textarea name="mail_body" id="mail_body" class="large-text code" rows="8" placeholder="<?php echo esc_attr( function_exists( 'crb_ls_mail_default_body_template' ) ? crb_ls_mail_default_body_template() : '' ); ?>"><?php echo esc_textarea( $mail_body ); ?></textarea>
					<p class="description">
						<?php esc_html_e( '空欄のときは標準構成（ダウンロード → インストールマニュアル → 有効化）を使用。主なタグ:', 'crb-license-server' ); ?>
						{intro} {plan_label_line} {plan_features_line} {license_key_line} {license_key} {download_block} {install_manual_block} {activation_block} {ai_manual_block}
					</p>
					<?php if ( '' !== trim( $mail_body_preview_pro ) ) : ?>
						<details style="margin-top:12px;max-width:48rem;">
							<summary style="cursor:pointer;"><?php esc_html_e( 'プレビュー（Pro）', 'crb-license-server' ); ?></summary>
							<pre style="white-space:pre-wrap;background:#f6f7f7;padding:12px;margin-top:8px;"><?php echo esc_html( $mail_body_preview_pro ); ?></pre>
						</details>
					<?php endif; ?>
					<?php if ( '' !== trim( $mail_body_preview_free ) ) : ?>
						<details style="margin-top:8px;max-width:48rem;">
							<summary style="cursor:pointer;"><?php esc_html_e( 'プレビュー（無料）', 'crb-license-server' ); ?></summary>
							<pre style="white-space:pre-wrap;background:#f6f7f7;padding:12px;margin-top:8px;"><?php echo esc_html( $mail_body_preview_free ); ?></pre>
						</details>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<p>
			<button type="submit" name="crb_ls_reset_mail_defaults" value="1" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'メールの冒頭・テンプレートを推奨文面に戻します。ダウンロード URL は保持されます。よろしいですか？', 'crb-license-server' ) ); ?>');">
				<?php esc_html_e( 'メール文面を推奨テンプレートにリセット', 'crb-license-server' ); ?>
			</button>
		</p>

		<h2><?php esc_html_e( 'Pro 決済', 'crb-license-server' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( '外部決済 URL', 'crb-license-server' ); ?></th>
				<td>
					<?php if ( '' !== $pro_payment_url ) : ?>
						<code style="word-break:break-all;display:inline-block;padding:6px 10px;background:#f6f7f7;"><?php echo esc_html( $pro_payment_url ); ?></code>
						<p class="description"><?php esc_html_e( 'プラグインに組み込まれた決済 URL です。', 'crb-license-server' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( '未設定です。', 'crb-license-server' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( '無料登録ページ', 'crb-license-server' ); ?></h2>
		<p><?php esc_html_e( '固定ページに次のショートコードを貼ります:', 'crb-license-server' ); ?> <code><?php echo esc_html( $register_hint ); ?></code></p>

		<details style="margin:1.5em 0;max-width:48rem;">
			<summary style="cursor:pointer;font-weight:600;"><?php esc_html_e( 'クライアントサイト連携（管理者向け）', 'crb-license-server' ); ?></summary>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'API Secret', 'crb-license-server' ); ?></th>
					<td><code style="user-select:all;display:inline-block;padding:6px 10px;background:#f6f7f7;"><?php echo esc_html( $api_secret ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( '正本 URL', 'crb-license-server' ); ?></th>
					<td><code><?php echo esc_html( untrailingslashit( home_url() ) ); ?></code></td>
				</tr>
			</table>
		</details>

		<?php submit_button( __( '設定を保存', 'crb-license-server' ), 'primary', 'crb_ls_save_settings' ); ?>
	</form>
</div>
