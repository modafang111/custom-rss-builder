<?php
/**
 * @var array<int, array<string, mixed>> $licenses
 * @package CRB_License_Server
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'CRB ライセンス一覧', 'crb-license-server' ); ?></h1>

	<p class="description"><?php esc_html_e( 'キーの発行・有効/無効の管理を行います。Custom RSS Builder の「ライセンス」画面でキーを有効化すると、このサイトに紐づきます。', 'crb-license-server' ); ?></p>

	<?php if ( ! empty( $_GET['created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'ライセンスを発行しました（メール送信済みの場合あり）。', 'crb-license-server' ); ?></p></div>
	<?php endif; ?>

	<h2><?php esc_html_e( '手動発行', 'crb-license-server' ); ?></h2>
	<form method="post" class="crb-ls-inline-form">
		<?php wp_nonce_field( 'crb_ls_create_license' ); ?>
		<label>
			<?php esc_html_e( 'メールアドレス', 'crb-license-server' ); ?>
			<input type="email" name="email" required />
		</label>
		<label>
			<?php esc_html_e( 'プラン', 'crb-license-server' ); ?>
			<select name="plan">
				<option value="free" selected><?php esc_html_e( '無料', 'crb-license-server' ); ?></option>
				<option value="pro"><?php esc_html_e( 'Pro', 'crb-license-server' ); ?></option>
			</select>
		</label>
		<?php submit_button( __( '発行', 'crb-license-server' ), 'secondary', 'crb_ls_create_license', false ); ?>
	</form>

	<table class="widefat striped" style="margin-top:1.5rem;">
		<thead>
			<tr>
				<th>ID</th>
				<th><?php esc_html_e( 'キー', 'crb-license-server' ); ?></th>
				<th><?php esc_html_e( 'メールアドレス', 'crb-license-server' ); ?></th>
				<th><?php esc_html_e( 'プラン', 'crb-license-server' ); ?></th>
				<th><?php esc_html_e( '状態', 'crb-license-server' ); ?></th>
				<th><?php esc_html_e( 'サイト', 'crb-license-server' ); ?></th>
				<th><?php esc_html_e( '操作', 'crb-license-server' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $licenses ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'ライセンスがありません。', 'crb-license-server' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $licenses as $row ) : ?>
					<?php
					$id      = (int) ( $row['id'] ?? 0 );
					$nonce   = wp_create_nonce( 'crb_ls_row_' . $id );
					$base    = admin_url( 'admin.php?page=crb-license-server' );
					$expire  = add_query_arg(
						array(
							'crb_ls_action' => 'expire',
							'license_id'    => $id,
							'_wpnonce'      => $nonce,
						),
						$base
					);
					$activate = add_query_arg(
						array(
							'crb_ls_action' => 'activate',
							'license_id'    => $id,
							'_wpnonce'      => $nonce,
						),
						$base
					);
					$is_active = 'active' === ( $row['status'] ?? '' );
					?>
					<tr>
						<td><?php echo esc_html( (string) $id ); ?></td>
						<td><code><?php echo esc_html( (string) ( $row['license_key'] ?? '' ) ); ?></code></td>
						<td><?php echo esc_html( (string) ( $row['email'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( 'pro' === ( $row['plan'] ?? '' ) ? __( 'Pro', 'crb-license-server' ) : __( '無料', 'crb-license-server' ) ); ?></td>
						<td><?php echo esc_html( function_exists( 'crb_ls_admin_status_label' ) ? crb_ls_admin_status_label( (string) ( $row['status'] ?? '' ) ) : (string) ( $row['status'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $row['site_url'] ?? '' ) ); ?></td>
						<td>
							<?php if ( ! $is_active ) : ?>
								<a href="<?php echo esc_url( $activate ); ?>"><?php esc_html_e( '有効化', 'crb-license-server' ); ?></a>
								|
							<?php endif; ?>
							<a href="<?php echo esc_url( $expire ); ?>" onclick="return confirm('<?php echo esc_js( __( '無効にしますか？', 'crb-license-server' ) ); ?>');"><?php esc_html_e( '無効化', 'crb-license-server' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
