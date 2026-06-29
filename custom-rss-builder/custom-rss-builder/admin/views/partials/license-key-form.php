<?php
/**
 * ライセンスキー入力フォーム（license-settings.php から include）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_client_screen        = function_exists( 'crb_license_ui_is_client_screen' ) && crb_license_ui_is_client_screen();
$license_key_input_value = (string) ( $settings['license_key'] ?? '' );
$is_pro_upgrade_form     = $is_client_screen && $state['usable'] && 'free' === $state['plan'];

if ( $is_pro_upgrade_form ) {
	$license_key_input_value = '';
}
?>
<form method="post" class="crb-panel crb-panel--license-key crb-panel--license-key-last">
	<?php wp_nonce_field( 'crb_license_settings' ); ?>
	<?php if ( $is_pro_upgrade_form ) : ?>
		<input type="hidden" name="crb_pro_upgrade" value="1" />
	<?php endif; ?>
	<h2 class="crb-panel__title">
		<?php
		if ( $is_pro_upgrade_form ) {
			esc_html_e( 'Pro にアップグレード', 'custom-rss-builder' );
		} elseif ( $is_client_screen ) {
			if ( $state['usable'] && 'pro' === $state['plan'] ) {
				esc_html_e( 'ライセンスキー', 'custom-rss-builder' );
			} elseif ( $state['usable'] ) {
				esc_html_e( 'Pro にアップグレード', 'custom-rss-builder' );
			} else {
				esc_html_e( 'ライセンスの有効化', 'custom-rss-builder' );
			}
		} elseif ( $state['usable'] && 'pro' === $state['plan'] ) {
			esc_html_e( 'ライセンスキーの確認', 'custom-rss-builder' );
		} else {
			esc_html_e( 'Pro ライセンスキー', 'custom-rss-builder' );
		}
		?>
	</h2>
	<p class="description">
		<?php if ( $is_pro_upgrade_form ) : ?>
			<?php esc_html_e( 'Pro 用ライセンスキーを入力して「有効化」を押してください。無料プランのキーはそのまま残しておいて構いません。', 'custom-rss-builder' ); ?>
		<?php elseif ( $is_client_screen ) : ?>
			<?php if ( $state['usable'] && 'pro' === $state['plan'] ) : ?>
				<?php esc_html_e( 'Pro が有効です。キーを変更するときだけ入力して「有効化」を押してください。', 'custom-rss-builder' ); ?>
			<?php elseif ( $state['usable'] ) : ?>
				<?php esc_html_e( 'Pro 用キーを入力して「Pro を有効化」を押してください。', 'custom-rss-builder' ); ?>
			<?php else : ?>
				<?php
				$portal_url = function_exists( 'crb_license_registration_portal_url' ) ? crb_license_registration_portal_url() : '';
				if ( '' !== $portal_url ) {
					echo wp_kses_post(
						sprintf(
							/* translators: 1: registration portal URL, 2: same URL as link text */
							__( 'まだキーをお持ちでない場合は、<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> で無料ライセンスを申請してください。届いたキーを入力し、「有効化」を押してください。', 'custom-rss-builder' ),
							esc_url( $portal_url ),
							esc_html( $portal_url )
						)
					);
				} else {
					esc_html_e( '届いたライセンスキーを入力し、「有効化」を押してください。認証コードの設定が必要な場合は、その上の「初期設定」を先に保存してください。', 'custom-rss-builder' );
				}
				?>
			<?php endif; ?>
		<?php elseif ( $state['usable'] && 'pro' === $state['plan'] ) : ?>
			<?php esc_html_e( '現在 Pro が有効です。別のキーに差し替える場合のみ入力して有効化してください。', 'custom-rss-builder' ); ?>
		<?php else : ?>
			<?php
			printf(
				wp_kses_post( __( 'Pro に切り替えるときだけキーを入力します。<a href="%s">ライセンス管理</a> で Pro を発行できます。', 'custom-rss-builder' ) ),
				esc_url( $license_manage_url )
			);
			?>
		<?php endif; ?>
	</p>
	<table class="form-table">
		<tr>
			<th><label for="crb-license-key"><?php esc_html_e( 'ライセンスキー', 'custom-rss-builder' ); ?></label></th>
			<td>
				<input
					type="text"
					class="large-text"
					name="license_key"
					id="crb-license-key"
					value="<?php echo esc_attr( $license_key_input_value ); ?>"
					autocomplete="off"
					placeholder="<?php echo $is_pro_upgrade_form ? esc_attr__( 'Pro 用キー（CRB-XXXXX-…）', 'custom-rss-builder' ) : esc_attr( 'CRB-XXXXX-XXXXX-XXXXX-XXXXX' ); ?>"
				/>
			</td>
		</tr>
	</table>
	<p>
		<button type="submit" name="crb_license_action" value="activate" class="button button-primary">
			<?php echo $is_pro_upgrade_form ? esc_html__( 'Pro を有効化', 'custom-rss-builder' ) : esc_html__( '有効化', 'custom-rss-builder' ); ?>
		</button>
		<?php if ( $state['usable'] ) : ?>
			<button type="submit" name="crb_license_action" value="check" class="button"><?php esc_html_e( '状態を再確認', 'custom-rss-builder' ); ?></button>
		<?php endif; ?>
	</p>
</form>
