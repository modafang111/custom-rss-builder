<?php
/**
 * @var string $message
 * @var string $type
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pro_url = function_exists( 'crb_ls_pro_payment_url' ) ? crb_ls_pro_payment_url() : '';
?>
<div class="crb-ls-register">
	<h2><?php esc_html_e( 'Custom RSS Builder 無料登録', 'crb-license-server' ); ?></h2>
	<p><?php esc_html_e( 'メールアドレスを入力すると、無料プランのライセンスキーをお送りします。', 'crb-license-server' ); ?></p>

	<?php if ( '' !== $message ) : ?>
		<p class="crb-ls-register__notice crb-ls-register__notice--<?php echo esc_attr( $type ); ?>">
			<?php echo esc_html( $message ); ?>
		</p>
	<?php endif; ?>

	<form method="post" class="crb-ls-register__form">
		<?php wp_nonce_field( 'crb_ls_free_register', 'crb_ls_free_nonce' ); ?>
		<p>
			<label for="crb_ls_email"><?php esc_html_e( 'メールアドレス', 'crb-license-server' ); ?></label><br />
			<input type="email" name="crb_ls_email" id="crb_ls_email" required class="regular-text" />
		</p>
		<p>
			<button type="submit" name="crb_ls_free_register" value="1" class="button button-primary">
				<?php esc_html_e( '無料ライセンスを申請', 'crb-license-server' ); ?>
			</button>
		</p>
	</form>

	<?php if ( '' !== $pro_url ) : ?>
		<hr />
		<h3><?php esc_html_e( 'Pro プラン（月額 3,000 円）', 'crb-license-server' ); ?></h3>
		<p><?php esc_html_e( 'お申し込み後、Pro ライセンスキーをメールでお送りします。', 'crb-license-server' ); ?></p>
		<p>
			<a class="button" href="<?php echo esc_url( $pro_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Pro を申し込む', 'crb-license-server' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
