<?php
/**
 * ライセンス設定画面。
 *
 * クライアント表示順: 通知 → 現在の状態 → プラン比較 → Pro 案内 → AI 設定 → 初期設定 → キー有効化（最後）
 * 未ライセンス時のみ: 初期設定（サイト管理者）
 * サーバー開発用順: 通知 → 現在の状態 → … → 接続設定 → キー → テスト用
 *
 * @var array{plan:string,status:string,usable:bool,message:string,license_key:string} $state
 * @var array<string, mixed> $settings
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$license_manage_url   = admin_url( 'admin.php?page=crb-license-server' );
$is_local_server      = crb_license_is_local_server();
$is_client_screen     = function_exists( 'crb_license_ui_is_client_screen' ) && crb_license_ui_is_client_screen();
$is_embedded_mode     = crb_license_is_embedded_mode();
$uses_remote          = crb_license_uses_remote_api();
$connection_mode      = crb_license_get_connection_mode();
$connection_label     = crb_license_connection_mode_label();
$api_base_value       = (string) ( $settings['api_base'] ?? '' );
$api_secret_value     = crb_license_api_secret_for_form();
$suggested_api_base   = function_exists( 'crb_license_suggested_client_api_base' ) ? crb_license_suggested_client_api_base() : '';
$api_base_default     = $is_local_server
	? crb_license_site_url()
	: ( '' !== $suggested_api_base ? $suggested_api_base : crb_license_site_url() );
$show_server_config   = $is_local_server
	|| (
		$is_client_screen
		&& ! $state['usable']
		&& function_exists( 'crb_license_client_needs_manual_server_config' )
		&& crb_license_client_needs_manual_server_config()
	);
// クライアント: 無料＋利用可のときスタンダード／Pro 申込を表示。サーバー開発用 ol は正本のみ。
$pro_subscribe_url           = function_exists( 'crb_license_pro_payment_url' ) ? crb_license_pro_payment_url() : '';
$standard_subscribe_url      = function_exists( 'crb_license_standard_payment_url' ) ? crb_license_standard_payment_url() : '';
$show_pro_upgrade_panel      = ( 'free' === $state['plan'] ) && $state['usable'];
$show_client_free_upgrade_ctas = $is_client_screen && $show_pro_upgrade_panel;
$show_standard_to_pro_cta    = $is_client_screen && $state['usable'] && 'standard' === $state['plan'] && '' !== $pro_subscribe_url;
$show_paid_setup_panel       = $is_client_screen && $state['usable'] && in_array( $state['plan'], array( 'standard', 'pro' ), true );
$client_remote_base     = ( $is_client_screen && function_exists( 'crb_license_client_remote_base_url' ) )
	? crb_license_client_remote_base_url()
	: '';
$api_base_from_config   = $is_client_screen
	&& function_exists( 'crb_license_client_has_valid_remote_base' )
	&& crb_license_client_has_valid_remote_base();
$max_slots              = function_exists( 'crb_license_get_record_slot_count' ) ? crb_license_get_record_slot_count() : 3;
$slot_range_label       = function_exists( 'crb_license_format_slot_range_text' )
	? crb_license_format_slot_range_text( (int) $max_slots )
	: (string) $max_slots;
$plan_label             = function_exists( 'crb_license_plan_label_for_state' )
	? crb_license_plan_label_for_state( $state )
	: ( function_exists( 'crb_license_plan_label' ) ? crb_license_plan_label( $state['plan'] ) : $state['plan'] );
$comparison_rows        = function_exists( 'crb_license_plan_comparison_rows' ) ? crb_license_plan_comparison_rows() : array();
$status_label           = function_exists( 'crb_license_status_label_for_display' )
	? crb_license_status_label_for_display( $state )
	: ( function_exists( 'crb_license_status_label' ) ? crb_license_status_label( $state['status'] ) : $state['status'] );
$show_plan_comparison        = ! empty( $comparison_rows );
$registration_portal_url     = function_exists( 'crb_license_registration_portal_url' ) ? crb_license_registration_portal_url() : '';
$show_client_registration    = $is_client_screen && ! $state['usable'] && '' !== $registration_portal_url;
$crb_install_manual_url      = function_exists( 'crb_install_manual_page_url' ) ? crb_install_manual_page_url() : '';
$crb_feed_pack_manual_url     = function_exists( 'crb_feed_pack_manual_page_url' ) ? crb_feed_pack_manual_page_url() : '';
$feed_limit_label = (string) CRB_LICENSE_FREE_FEED_LIMIT;
if ( $state['usable'] && 'standard' === $state['plan'] ) {
	$feed_limit_label = sprintf(
		/* translators: %d: max feeds on standard plan */
		__( '%d 件まで', 'custom-rss-builder' ),
		defined( 'CRB_LICENSE_STANDARD_FEED_LIMIT' ) ? (int) CRB_LICENSE_STANDARD_FEED_LIMIT : 3
	);
} elseif ( $state['usable'] && 'pro' === $state['plan'] ) {
	$feed_limit_label = sprintf(
		/* translators: %d: max feeds on pro plan */
		__( '%d 件まで', 'custom-rss-builder' ),
		defined( 'CRB_LICENSE_PRO_FEED_LIMIT' ) ? (int) CRB_LICENSE_PRO_FEED_LIMIT : 10
	);
}
$version_info         = function_exists( 'crb_get_plugin_version_info' ) ? crb_get_plugin_version_info() : array( 'version' => '', 'build' => '' );
$version_line         = '';
if ( ! empty( $version_info['version'] ) ) {
	$version_line = 'v' . (string) $version_info['version'];
	if ( ! empty( $version_info['build'] ) ) {
		$version_line .= ' · ' . sprintf(
			/* translators: %s: build id */
			__( 'ビルド %s', 'custom-rss-builder' ),
			(string) $version_info['build']
		);
	}
}
?>
<div class="wrap crb-admin-wrap">
	<h1>
		<?php esc_html_e( 'ライセンス', 'custom-rss-builder' ); ?>
		<?php if ( '' !== $version_line ) : ?>
			<small class="crb-version-inline"><?php echo esc_html( $version_line ); ?></small>
		<?php endif; ?>
	</h1>
	<?php if ( $is_client_screen ) : ?>
		<?php if ( '' !== $crb_install_manual_url ) : ?>
		<p class="description">
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: install manual URL on authority site */
					__( 'はじめての方: <a href="%s" target="_blank" rel="noopener noreferrer">プラグインのインストール手順（正本サイト）</a>', 'custom-rss-builder' ),
					esc_url( $crb_install_manual_url )
				)
			);
			?>
		</p>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['ai_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p>
			<strong><?php esc_html_e( 'Gemini API キーを保存しました。', 'custom-rss-builder' ); ?></strong>
			<?php esc_html_e( '入力欄はセキュリティのため空欄のままです。下の「保存済みキー」にマスク表示が出ていれば正常に保存されています。', 'custom-rss-builder' ); ?>
		</p>
		<p>
			<?php
			$crb_ai_saved_model_label = function_exists( 'crb_ai_transform_model_label' )
				? crb_ai_transform_model_label( defined( 'CRB_AI_TRANSFORM_DEFAULT_MODEL' ) ? CRB_AI_TRANSFORM_DEFAULT_MODEL : 'gemini-2.5-flash' )
				: 'Gemini 2.5 Flash（推奨・無料枠対応）';
			printf(
				/* translators: %s: default Gemini model label */
				esc_html__( '接続テストおよび AI テキスト変換（フィードでモデル未変更時）は %s を使用します。Google AI Studio の無料枠で試しやすいデフォルトです。', 'custom-rss-builder' ),
				esc_html( $crb_ai_saved_model_label )
			);
			?>
		</p>
		<p class="description">
			<?php
			printf(
				/* translators: %s: feed list admin URL */
				wp_kses_post( __( '次のステップ: 「接続テスト」で成功を確認 → <a href="%s">フィード編集</a> の「AI テキスト変換（Pro）」で変換指示を設定', 'custom-rss-builder' ) ),
				esc_url( admin_url( 'admin.php?page=custom-rss-builder' ) )
			);
			?>
		</p></div>
	<?php elseif ( ! empty( $_GET['ai_cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '保存済みの Gemini API キーを削除しました。', 'custom-rss-builder' ); ?></p></div>
	<?php elseif ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'ライセンス設定を更新しました。', 'custom-rss-builder' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['license_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php
		$license_error_raw = sanitize_text_field( wp_unslash( $_GET['license_error'] ) );
		$license_error_msg = function_exists( 'crb_license_admin_notice_message' )
			? crb_license_admin_notice_message( $license_error_raw )
			: $license_error_raw;
		?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $license_error_msg ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['conn_test'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php
			if ( $is_client_screen ) {
				esc_html_e( '認証サーバーへの接続を確認しました。ライセンス状態を更新しました。', 'custom-rss-builder' );
			} else {
				esc_html_e( 'REST 接続テストに成功しました。ライセンス状態も更新しました。', 'custom-rss-builder' );
			}
			?>
		</p></div>
	<?php endif; ?>

	<?php if ( $is_embedded_mode && $state['usable'] && ! $is_client_screen ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( '組み込みモード: 無料ライセンスが自動で有効です。通常は操作不要です。Pro にする場合だけ下のキー入力を使います。', 'custom-rss-builder' ); ?></p></div>
	<?php elseif ( $state['usable'] && $is_client_screen ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'ライセンスは有効です。このサイトで Custom RSS Builder を利用できます。', 'custom-rss-builder' ); ?></p></div>
	<?php elseif ( $uses_remote && $state['usable'] && ! $is_client_screen ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'REST（分離）モード: ライセンスサーバーへ HTTP で接続しています。', 'custom-rss-builder' ); ?></p></div>
	<?php elseif ( ! $state['usable'] && ! $show_client_registration ) : ?>
		<?php
		$license_notice = function_exists( 'crb_license_admin_notice_message' )
			? crb_license_admin_notice_message( $state['message'] )
			: $state['message'];
		?>
		<div class="notice notice-warning"><p><?php echo esc_html( $license_notice ); ?></p></div>
	<?php endif; ?>

	<?php if ( $show_client_registration ) : ?>
	<div class="crb-panel crb-panel--registration-cta">
		<h2 class="crb-panel__title"><?php esc_html_e( 'ライセンスキーの取得', 'custom-rss-builder' ); ?></h2>
		<p><?php esc_html_e( 'Custom RSS Builder を使うには、販売元サイトで無料ライセンスを申請し、届いたキーをこの画面の下で有効化してください。', 'custom-rss-builder' ); ?></p>
		<ol class="crb-license-steps">
			<li>
				<a href="<?php echo esc_url( $registration_portal_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '販売元サイトで無料ライセンスを申請', 'custom-rss-builder' ); ?></a>
				<?php esc_html_e( '（メールアドレスを入力するとキーが届きます）', 'custom-rss-builder' ); ?>
			</li>
			<li><?php esc_html_e( 'メールに記載されたライセンスキーをコピーする', 'custom-rss-builder' ); ?></li>
			<li><?php esc_html_e( 'このページ下部の「ライセンスの有効化」にキーを貼り付けて「有効化」を押す', 'custom-rss-builder' ); ?></li>
		</ol>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $registration_portal_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( '無料ライセンスを申請する', 'custom-rss-builder' ); ?>
			</a>
			<?php if ( '' !== $pro_subscribe_url ) : ?>
				<a class="button" href="<?php echo esc_url( $pro_subscribe_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Pro を申し込む', 'custom-rss-builder' ); ?>
				</a>
			<?php endif; ?>
		</p>
		<?php if ( '' !== $crb_install_manual_url ) : ?>
		<p class="description">
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: install manual URL on authority site */
					__( 'プラグインの導入手順: <a href="%s" target="_blank" rel="noopener noreferrer">インストール手順（正本サイト）</a>', 'custom-rss-builder' ),
					esc_url( $crb_install_manual_url )
				)
			);
			?>
		</p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="crb-panel">
		<h2 class="crb-panel__title"><?php esc_html_e( '現在の状態', 'custom-rss-builder' ); ?></h2>
		<table class="form-table crb-license-status-table">
			<tr>
				<th><?php esc_html_e( 'プラン', 'custom-rss-builder' ); ?></th>
				<td><strong class="crb-license-plan-badge crb-license-plan-badge--<?php echo esc_attr( sanitize_key( $state['plan'] ) ); ?>"><?php echo esc_html( $plan_label ); ?></strong></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'ライセンス状態', 'custom-rss-builder' ); ?></th>
				<td><?php echo esc_html( $status_label ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'このサイトで利用可', 'custom-rss-builder' ); ?></th>
				<td><?php echo $state['usable'] ? esc_html__( 'はい', 'custom-rss-builder' ) : esc_html__( 'いいえ', 'custom-rss-builder' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( '利用可能スロット', 'custom-rss-builder' ); ?></th>
				<td>
					<?php
					if ( $state['usable'] ) {
						echo esc_html( $slot_range_label );
					} else {
						esc_html_e( '—', 'custom-rss-builder' );
					}
					?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'フィード数上限', 'custom-rss-builder' ); ?></th>
				<td><?php echo esc_html( $feed_limit_label ); ?></td>
			</tr>
			<?php if ( '' !== $state['license_key'] ) : ?>
			<tr>
				<th><?php esc_html_e( 'ライセンスキー', 'custom-rss-builder' ); ?></th>
				<td><code class="crb-license-key"><?php echo esc_html( $state['license_key'] ); ?></code></td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><?php esc_html_e( '登録サイト URL', 'custom-rss-builder' ); ?></th>
				<td><code><?php echo esc_html( crb_license_site_url() ); ?></code></td>
			</tr>
			<?php if ( ! $is_client_screen ) : ?>
			<tr>
				<th><?php esc_html_e( '接続方式', 'custom-rss-builder' ); ?></th>
				<td><strong><?php echo esc_html( $connection_label ); ?></strong></td>
			</tr>
			<?php if ( $uses_remote ) : ?>
			<tr>
				<th><?php esc_html_e( 'API ベース URL', 'custom-rss-builder' ); ?></th>
				<td><code><?php echo esc_html( crb_license_get_configured_api_base() ); ?></code></td>
			</tr>
			<?php endif; ?>
			<?php elseif ( $is_client_screen && $state['usable'] && '' !== $client_remote_base ) : ?>
			<tr>
				<th><?php esc_html_e( '認証サーバー', 'custom-rss-builder' ); ?></th>
				<td><code><?php echo esc_html( $client_remote_base ); ?></code></td>
			</tr>
			<?php endif; ?>
		</table>
	</div>

	<?php if ( $show_plan_comparison ) : ?>
	<div class="crb-panel">
		<h2 class="crb-panel__title"><?php esc_html_e( 'プランの違い', 'custom-rss-builder' ); ?></h2>
		<table class="widefat striped crb-license-plan-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( '機能', 'custom-rss-builder' ); ?></th>
					<th scope="col"><?php esc_html_e( '無料', 'custom-rss-builder' ); ?></th>
					<th scope="col"><?php esc_html_e( 'スタンダード', 'custom-rss-builder' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Pro', 'custom-rss-builder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $comparison_rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['label'] ); ?></td>
						<td><?php echo esc_html( $row['free'] ); ?></td>
						<td><?php echo esc_html( $row['standard'] ?? '' ); ?></td>
						<td><?php echo esc_html( $row['pro'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'プラン差は主に「フィード数」「スロット数」「自動取り込み間隔」「クレジット表示」「AI テキスト変換（Pro・BYOK）」です。', 'custom-rss-builder' ); ?></p>
	</div>
	<?php endif; ?>

	<?php if ( $show_client_free_upgrade_ctas ) : ?>
	<div class="crb-panel crb-panel--upgrade-cta">
		<h2 class="crb-panel__title"><?php esc_html_e( '有料プランのご案内', 'custom-rss-builder' ); ?></h2>

		<h3 class="crb-panel__subtitle"><?php esc_html_e( 'スタンダードプラン', 'custom-rss-builder' ); ?></h3>
		<p>
			<?php
			printf(
				/* translators: 1: standard slot range, 2: monthly price label, 3: max feeds */
				esc_html__( 'フィード %3$d 件まで・スロット %1$s・自動取り込み 1 時間〜（%2$s）。', 'custom-rss-builder' ),
				function_exists( 'crb_license_format_slot_range_text' )
					? crb_license_format_slot_range_text( defined( 'CRB_LICENSE_STANDARD_SLOT_LIMIT' ) ? (int) CRB_LICENSE_STANDARD_SLOT_LIMIT : 5 )
					: '{%1%}〜{%5%}',
				function_exists( 'crb_standard_monthly_price_label' ) ? crb_standard_monthly_price_label() : __( '月額 1,100 円（税込）', 'custom-rss-builder' ),
				defined( 'CRB_LICENSE_STANDARD_FEED_LIMIT' ) ? (int) CRB_LICENSE_STANDARD_FEED_LIMIT : 3
			);
			?>
		</p>
		<?php if ( '' !== $standard_subscribe_url ) : ?>
		<p>
			<a class="button button-secondary" href="<?php echo esc_url( $standard_subscribe_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'スタンダードを申し込む', 'custom-rss-builder' ); ?>
			</a>
		</p>
		<?php else : ?>
		<p class="description"><?php esc_html_e( 'スタンダードのお申し込み URL は準備中です。', 'custom-rss-builder' ); ?></p>
		<?php endif; ?>
		<p class="description">
			<?php esc_html_e( 'お支払い後、スタンダード ライセンスキーをメールでお送りします。届いたキーを下のフォームに入力して「有効化」してください。', 'custom-rss-builder' ); ?>
		</p>

		<h3 class="crb-panel__subtitle"><?php esc_html_e( 'Pro プラン', 'custom-rss-builder' ); ?></h3>
		<p>
			<?php
			printf(
				/* translators: 1: max slot token e.g. {%20%}, 2: monthly price label, 3: max feeds */
				esc_html__( 'フィード %3$d 件まで・スロット {%%1%%}〜%1$s・AI テキスト変換（%2$s）。', 'custom-rss-builder' ),
				'{%' . ( function_exists( 'crb_license_pro_slot_count' ) ? (int) crb_license_pro_slot_count() : 20 ) . '%}',
				function_exists( 'crb_pro_monthly_price_label' ) ? crb_pro_monthly_price_label() : __( '月額 3,300 円（税込）', 'custom-rss-builder' ),
				defined( 'CRB_LICENSE_PRO_FEED_LIMIT' ) ? (int) CRB_LICENSE_PRO_FEED_LIMIT : 10
			);
			?>
		</p>
		<?php if ( '' !== $pro_subscribe_url ) : ?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $pro_subscribe_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Pro を申し込む', 'custom-rss-builder' ); ?>
			</a>
		</p>
		<?php endif; ?>
		<p class="description">
			<?php esc_html_e( 'お支払い後、Pro ライセンスキーをメールでお送りします。届いたキーを下のフォームに入力して「有効化」してください。', 'custom-rss-builder' ); ?>
		</p>
		<p class="description">
			<?php
			printf(
				/* translators: %s: repeat setup price label */
				esc_html__( 'スタンダード・Pro 共通: 最初の 1 フィードの初期設定代行が 1 回無料です（2 回目以降 %s）。', 'custom-rss-builder' ),
				esc_html( function_exists( 'crb_pro_setup_repeat_price_label' ) ? crb_pro_setup_repeat_price_label() : __( '1,100 円（税込）／回', 'custom-rss-builder' ) )
			);
			?>
			<?php if ( '' !== $crb_feed_pack_manual_url ) : ?>
				<?php
				echo ' ';
				echo wp_kses_post(
					sprintf(
						/* translators: %s: feed pack manual URL */
						__( '<a href="%s" target="_blank" rel="noopener noreferrer">詳細は手順ページ</a>', 'custom-rss-builder' ),
						esc_url( $crb_feed_pack_manual_url . '#crb-fpack-pro-service' )
					)
				);
				?>
			<?php endif; ?>
		</p>
	</div>
	<?php elseif ( $show_standard_to_pro_cta ) : ?>
	<div class="crb-panel crb-panel--pro-cta">
		<h2 class="crb-panel__title"><?php esc_html_e( 'Pro へのアップグレード', 'custom-rss-builder' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: max slot token, 2: monthly price label, 3: max feeds */
				esc_html__( 'フィード %3$d 件まで・スロット {%%1%%}〜%1$s・AI テキスト変換が使える Pro プラン（%2$s）です。', 'custom-rss-builder' ),
				'{%' . ( function_exists( 'crb_license_pro_slot_count' ) ? (int) crb_license_pro_slot_count() : 20 ) . '%}',
				function_exists( 'crb_pro_monthly_price_label' ) ? crb_pro_monthly_price_label() : __( '月額 3,300 円（税込）', 'custom-rss-builder' ),
				defined( 'CRB_LICENSE_PRO_FEED_LIMIT' ) ? (int) CRB_LICENSE_PRO_FEED_LIMIT : 10
			);
			?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $pro_subscribe_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Pro を申し込む', 'custom-rss-builder' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'お支払い後、Pro ライセンスキーをメールでお送りします。届いたキーを下のフォームに入力して「有効化」してください。', 'custom-rss-builder' ); ?>
		</p>
	</div>
	<?php elseif ( $show_paid_setup_panel ) : ?>
	<div class="crb-panel crb-panel--pro-setup">
		<h2 class="crb-panel__title"><?php esc_html_e( '初期設定代行', 'custom-rss-builder' ); ?></h2>
		<p>
			<?php esc_html_e( 'フィード設定（セレクタ・スロット等）を当方で作成し、設定パック（JSON）でお渡しします。', 'custom-rss-builder' ); ?>
		</p>
		<ul class="crb-license-steps">
			<li><?php esc_html_e( '初回（有料プランお申し込み後・最初の 1 フィード）: 無料', 'custom-rss-builder' ); ?></li>
			<li>
				<?php
				printf(
					/* translators: %s: repeat setup price label */
					esc_html__( '2 回目以降: %s', 'custom-rss-builder' ),
					esc_html( function_exists( 'crb_pro_setup_repeat_price_label' ) ? crb_pro_setup_repeat_price_label() : __( '1,100 円（税込）／回', 'custom-rss-builder' ) )
				);
				?>
			</li>
		</ul>
		<p class="description">
			<?php esc_html_e( 'お客様側: フィード編集の「設定をインポート」→ プレビュー →「保存」。', 'custom-rss-builder' ); ?>
		</p>
		<?php if ( '' !== $crb_feed_pack_manual_url ) : ?>
		<p>
			<a href="<?php echo esc_url( $crb_feed_pack_manual_url . '#crb-fpack-pro-service' ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( '代行の詳細・お申し込み（正本サイト）', 'custom-rss-builder' ); ?>
			</a>
		</p>
		<?php elseif ( '' !== $registration_portal_url ) : ?>
		<p>
			<a href="<?php echo esc_url( $registration_portal_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'お申し込みは販売元サイトへ', 'custom-rss-builder' ); ?>
			</a>
		</p>
		<?php endif; ?>
	</div>
	<?php elseif ( $show_pro_upgrade_panel && ! $is_client_screen ) : ?>
	<div class="crb-panel">
		<h2 class="crb-panel__title"><?php esc_html_e( 'Pro にアップグレード', 'custom-rss-builder' ); ?></h2>
		<ol class="crb-license-steps">
			<?php if ( '' !== $pro_subscribe_url ) : ?>
				<li>
					<a href="<?php echo esc_url( $pro_subscribe_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Pro を申し込む（外部決済）', 'custom-rss-builder' ); ?></a>
					<?php esc_html_e( '（お支払い後、キーがメール等で届きます）', 'custom-rss-builder' ); ?>
				</li>
			<?php endif; ?>
			<?php if ( $is_client_screen ) : ?>
				<li><?php esc_html_e( '届いた Pro ライセンスキーを下のフォームに貼り付けて「有効化」を押してください。', 'custom-rss-builder' ); ?></li>
			<?php else : ?>
				<li>
					<a href="<?php echo esc_url( $license_manage_url ); ?>"><?php esc_html_e( 'ライセンス管理', 'custom-rss-builder' ); ?></a>
					<?php esc_html_e( 'でプラン「Pro」を選んで手動発行（テスト用）', 'custom-rss-builder' ); ?>
				</li>
				<li><?php esc_html_e( '発行されたキーを下のフォームに貼り付けて「有効化」', 'custom-rss-builder' ); ?></li>
				<?php if ( $is_embedded_mode ) : ?>
					<li><?php esc_html_e( 'テスト後に無料へ戻す場合は、下の「テスト用」→「無料プランに戻す」', 'custom-rss-builder' ); ?></li>
				<?php endif; ?>
			<?php endif; ?>
		</ol>
	</div>
	<?php endif; ?>

	<?php if ( $is_client_screen && function_exists( 'crb_gemini_api_key_status_label' ) ) : ?>
		<?php $crb_ai_editable = function_exists( 'crb_ai_settings_editable_for_current_license' ) && crb_ai_settings_editable_for_current_license(); ?>
		<?php
		$crb_gemini_configured = function_exists( 'crb_gemini_api_key_configured' ) && crb_gemini_api_key_configured();
		$crb_gemini_masked     = function_exists( 'crb_gemini_api_key_masked_display' ) ? crb_gemini_api_key_masked_display() : '';
		$crb_gemini_status_cls = function_exists( 'crb_gemini_api_key_status_class' ) ? crb_gemini_api_key_status_class() : '';
		$crb_ai_default_model  = defined( 'CRB_AI_TRANSFORM_DEFAULT_MODEL' ) ? CRB_AI_TRANSFORM_DEFAULT_MODEL : 'gemini-2.5-flash';
		$crb_ai_default_label  = function_exists( 'crb_ai_transform_model_label' )
			? crb_ai_transform_model_label( $crb_ai_default_model )
			: 'Gemini 2.5 Flash（推奨・無料枠対応）';
		?>
		<form method="post" class="crb-panel crb-panel--ai-settings">
			<?php wp_nonce_field( 'crb_license_settings' ); ?>
			<h2 class="crb-panel__title"><?php esc_html_e( 'AI テキスト変換（Pro）', 'custom-rss-builder' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'AI テキスト変換は BYOK（Bring Your Own Key）方式です。このサイトがお持ちの Google Gemini API キーで実行し、利用料は Google アカウントへ直接請求されます。キーはこの WordPress にだけ保存され、販売元サーバーには送られません。', 'custom-rss-builder' ); ?>
			</p>
			<p class="description">
				<?php
				$crb_ai_manual_url = function_exists( 'crb_ai_manual_page_url' ) ? crb_ai_manual_page_url() : '';
				if ( '' !== $crb_ai_manual_url ) {
					echo wp_kses_post(
						sprintf(
							/* translators: %s: setup manual URL on authority site */
							__( '設定手順: <a href="%s" target="_blank" rel="noopener noreferrer">Gemini API キー設定手順（正本サイト）</a>', 'custom-rss-builder' ),
							esc_url( $crb_ai_manual_url )
						)
					);
					echo ' ';
				}
				echo wp_kses_post(
					sprintf(
						/* translators: 1: Google AI Studio API Keys URL, 2: Google Cloud credentials URL */
						__(
							'キー作成: <a href="%1$s" target="_blank" rel="noopener noreferrer">Google AI Studio — API Keys</a> / キー一覧・管理: <a href="%2$s" target="_blank" rel="noopener noreferrer">Google Cloud Credentials</a>',
							'custom-rss-builder'
						),
						esc_url( 'https://aistudio.google.com/apikey' ),
						esc_url( 'https://console.cloud.google.com/apis/credentials' )
					)
				);
				?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( '状態', 'custom-rss-builder' ); ?></th>
					<td>
						<span class="crb-ai-api-key-status <?php echo esc_attr( $crb_gemini_status_cls ); ?>">
							<?php echo esc_html( crb_gemini_api_key_status_label() ); ?>
						</span>
						<?php if ( $crb_gemini_configured && '' !== $crb_gemini_masked ) : ?>
							<p class="crb-ai-api-key-saved">
								<strong><?php esc_html_e( '保存済みキー:', 'custom-rss-builder' ); ?></strong>
								<code class="crb-ai-api-key-mask"><?php echo esc_html( $crb_gemini_masked ); ?></code>
							</p>
							<p class="description"><?php esc_html_e( '入力欄はセキュリティのため保存後に空欄に戻ります。空欄＝未保存ではありません。変更するときだけ下の入力欄に新しいキーを貼り付けて保存してください。', 'custom-rss-builder' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'デフォルトモデル', 'custom-rss-builder' ); ?></th>
					<td>
						<code><?php echo esc_html( $crb_ai_default_model ); ?></code>
						— <?php echo esc_html( $crb_ai_default_label ); ?>
						<p class="description">
							<?php esc_html_e( '接続テストと、フィードでモデルを変更していない AI 変換はこのモデルを使います。', 'custom-rss-builder' ); ?>
							<?php
							printf(
								/* translators: %s: feed list admin URL */
								wp_kses_post( __( '変更する場合は <a href="%s">フィード編集</a> →「AI テキスト変換（Pro）」→ モデル', 'custom-rss-builder' ) ),
								esc_url( admin_url( 'admin.php?page=custom-rss-builder' ) )
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="crb-gemini-api-key"><?php esc_html_e( 'Gemini API キー', 'custom-rss-builder' ); ?></label></th>
					<td>
						<?php if ( defined( 'CRB_GEMINI_API_KEY' ) && '' !== (string) CRB_GEMINI_API_KEY ) : ?>
							<p class="description"><?php esc_html_e( 'API キーは固定設定のため、管理画面からは変更できません。', 'custom-rss-builder' ); ?></p>
						<?php elseif ( ! $crb_ai_editable ) : ?>
							<p class="description"><?php esc_html_e( 'Pro ライセンスを有効化すると、ここで API キーを設定できます。', 'custom-rss-builder' ); ?></p>
						<?php else : ?>
							<input type="password" class="large-text code" name="gemini_api_key" id="crb-gemini-api-key" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $crb_gemini_configured ? __( '新しいキーを入力すると差し替え（空欄のまま保存＝変更なし）', 'custom-rss-builder' ) : 'AIza... または AQ....' ); ?>">
							<?php if ( ! $crb_gemini_configured ) : ?>
								<p class="description"><?php esc_html_e( 'Google AI Studio で発行した API キー（AIza... または AQ....）を貼り付けて「AI 設定を保存」を押してください。保存後は入力欄が空欄に戻りますが、上の「保存済みキー」にマスク表示が出れば成功です。', 'custom-rss-builder' ); ?></p>
							<?php endif; ?>
							<label>
								<input type="checkbox" name="gemini_api_key_clear" value="1">
								<?php esc_html_e( '保存済みのキーを削除する', 'custom-rss-builder' ); ?>
							</label>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php if ( $crb_ai_editable ) : ?>
				<p>
					<?php if ( ! defined( 'CRB_GEMINI_API_KEY' ) || '' === (string) CRB_GEMINI_API_KEY ) : ?>
						<button type="submit" name="crb_license_action" value="save_ai_settings" class="button button-primary">
							<?php esc_html_e( 'AI 設定を保存', 'custom-rss-builder' ); ?>
						</button>
					<?php endif; ?>
					<button type="button" class="button" id="crb-test-gemini-api">
						<?php esc_html_e( '接続テスト', 'custom-rss-builder' ); ?>
					</button>
					<span id="crb-test-gemini-result" class="description crb-ai-api-test-result" aria-live="polite"></span>
				</p>
				<p class="description">
					<?php
					printf(
						/* translators: 1: model slug, 2: model label */
						esc_html__( '接続テストは %1$s（%2$s）で実行します。', 'custom-rss-builder' ),
						esc_html( $crb_ai_default_model ),
						esc_html( $crb_ai_default_label )
					);
					?>
				</p>
				<p class="description">
					<?php
					printf(
						/* translators: %s: feed list admin URL */
						wp_kses_post( __( 'フィードごとの変換指示は <a href="%s">フィード編集</a> の「AI テキスト変換（Pro）」で設定します。', 'custom-rss-builder' ) ),
						esc_url( admin_url( 'admin.php?page=custom-rss-builder' ) )
					);
					?>
				</p>
			<?php endif; ?>
		</form>
	<?php endif; ?>

	<?php if ( $show_server_config ) : ?>
	<form method="post" class="crb-panel crb-panel--connection">
		<?php wp_nonce_field( 'crb_license_settings' ); ?>
		<?php if ( $is_client_screen ) : ?>
			<input type="hidden" name="connection_mode" value="remote" />
			<h2 class="crb-panel__title"><?php esc_html_e( '初期設定（サイト管理者）', 'custom-rss-builder' ); ?></h2>
			<p class="description"><?php esc_html_e( '通常はプラグイン導入時に設定済みです。キーの有効化が失敗する場合だけ、販売元から案内された認証情報を入力してください。', 'custom-rss-builder' ); ?></p>
		<?php else : ?>
			<h2 class="crb-panel__title"><?php esc_html_e( 'ライセンスサーバー接続', 'custom-rss-builder' ); ?></h2>
			<p class="description"><?php esc_html_e( '通常は「組み込み（同一DB）」のままで問題ありません。「REST（分離）」に切り替えると、同一サイト内でも HTTP 経由でライセンス API を呼び出します（分離テスト用）。', 'custom-rss-builder' ); ?></p>
		<?php endif; ?>
		<table class="form-table">
			<?php if ( ! $is_client_screen ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( '接続モード', 'custom-rss-builder' ); ?></th>
				<td>
					<label>
						<input type="radio" name="connection_mode" value="embedded" <?php checked( 'embedded', $connection_mode ); ?> <?php disabled( ! $is_local_server ); ?> />
						<?php esc_html_e( '組み込み（同一DB）', 'custom-rss-builder' ); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="connection_mode" value="remote" <?php checked( 'remote', $connection_mode ); ?> />
						<?php esc_html_e( 'REST（分離）', 'custom-rss-builder' ); ?>
					</label>
				</td>
			</tr>
			<?php endif; ?>
			<?php if ( ! $api_base_from_config ) : ?>
			<tr>
				<th scope="row"><label for="crb-api-base"><?php echo $is_client_screen ? esc_html__( '認証サーバー URL', 'custom-rss-builder' ) : esc_html__( 'API ベース URL', 'custom-rss-builder' ); ?></label></th>
				<td>
					<input type="url" class="large-text" name="api_base" id="crb-api-base" value="<?php echo esc_attr( $api_base_value ); ?>" placeholder="<?php echo esc_attr( '' !== $client_remote_base ? $client_remote_base : $api_base_default ); ?>" />
					<?php if ( $is_client_screen ) : ?>
						<p class="description"><?php esc_html_e( 'ライセンスを発行しているサーバー（例: 123789.jp）の URL。自サイトの URL ではありません。', 'custom-rss-builder' ); ?></p>
					<?php else : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: example site URL */
								esc_html__( 'ライセンスサーバーがあるサイトの URL（末尾スラッシュ不要）。例: %s', 'custom-rss-builder' ),
								esc_html( $api_base_default )
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<?php elseif ( $is_client_screen && '' !== $client_remote_base ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( '認証サーバー URL', 'custom-rss-builder' ); ?></th>
				<td><code><?php echo esc_html( $client_remote_base ); ?></code></td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><label for="crb-api-secret"><?php echo $is_client_screen ? esc_html__( '認証コード（Secret）', 'custom-rss-builder' ) : esc_html__( 'API Secret', 'custom-rss-builder' ); ?></label></th>
				<td>
					<input type="text" class="large-text" name="api_secret" id="crb-api-secret" value="<?php echo esc_attr( $api_secret_value ); ?>" autocomplete="off" />
					<p class="description">
						<?php if ( $is_client_screen ) : ?>
							<?php esc_html_e( '販売元・ホスティング提供者から案内された認証コードを入力してください。', 'custom-rss-builder' ); ?>
						<?php else : ?>
							<?php
							printf(
								wp_kses_post( __( 'REST モードでは <a href="%s">ライセンス設定</a> の API Secret をコピーしてください。組み込みモードでは未入力でも自動連携します。', 'custom-rss-builder' ) ),
								esc_url( $license_manage_url )
							);
							?>
						<?php endif; ?>
					</p>
				</td>
			</tr>
		</table>
		<p>
			<button type="submit" name="crb_license_action" value="save_connection" class="button button-primary">
				<?php echo $is_client_screen ? esc_html__( '設定を保存', 'custom-rss-builder' ) : esc_html__( '接続設定を保存', 'custom-rss-builder' ); ?>
			</button>
			<?php if ( $uses_remote || 'remote' === $connection_mode || $is_client_screen ) : ?>
				<button type="submit" name="crb_license_action" value="test_connection" class="button">
					<?php echo $is_client_screen ? esc_html__( '接続を確認', 'custom-rss-builder' ) : esc_html__( '接続テスト', 'custom-rss-builder' ); ?>
				</button>
			<?php endif; ?>
		</p>
	</form>
	<?php endif; ?>

	<?php include __DIR__ . '/partials/license-key-form.php'; ?>

	<?php if ( $is_local_server ) : ?>
	<div class="crb-panel crb-panel--test-tools">
		<h2 class="crb-panel__title"><?php esc_html_e( 'テスト用（組み込みモードのみ）', 'custom-rss-builder' ); ?></h2>
		<p class="description">
			<?php if ( $is_embedded_mode ) : ?>
				<?php esc_html_e( '受け入れテスト用の操作です。本番では通常使いません。', 'custom-rss-builder' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'REST（分離）モード中は DB 直結のテスト操作は使えません。組み込みモードに戻してから実行してください。', 'custom-rss-builder' ); ?>
			<?php endif; ?>
		</p>
		<?php if ( ! empty( $_GET['test_reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( '設定をクリアしました。F5 で無料ライセンスが自動発行されます（テスト A-1）。', 'custom-rss-builder' ); ?></p></div>
		<?php endif; ?>
		<table class="widefat crb-license-test-table">
			<thead>
				<tr>
					<th><?php esc_html_e( '操作', 'custom-rss-builder' ); ?></th>
					<th><?php esc_html_e( '用途', 'custom-rss-builder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( '無料プランに戻す', 'custom-rss-builder' ); ?></td>
					<td><?php esc_html_e( 'Pro テスト後に free へ戻す（B-3）', 'custom-rss-builder' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '設定をクリア', 'custom-rss-builder' ); ?></td>
					<td><?php esc_html_e( '自動無料化の確認（A-1）→ F5', 'custom-rss-builder' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( '無料を今すぐ再発行', 'custom-rss-builder' ); ?></td>
					<td><?php esc_html_e( 'クリア後すぐ free に復帰', 'custom-rss-builder' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'ライセンス管理で status=expired', 'custom-rss-builder' ); ?></td>
					<td><?php esc_html_e( '無効ライセンステスト →「状態を再確認」（D系）', 'custom-rss-builder' ); ?></td>
				</tr>
			</tbody>
		</table>
		<form method="post" class="crb-inline-test-actions">
			<?php wp_nonce_field( 'crb_license_settings' ); ?>
			<button type="submit" name="crb_license_action" value="switch_free" class="button" <?php disabled( ! $is_embedded_mode ); ?>><?php esc_html_e( '無料プランに戻す', 'custom-rss-builder' ); ?></button>
			<button type="submit" name="crb_license_action" value="test_reset" class="button" <?php disabled( ! $is_embedded_mode ); ?> onclick="return confirm('<?php echo esc_js( __( 'ライセンス設定をクリアします。よろしいですか？', 'custom-rss-builder' ) ); ?>');"><?php esc_html_e( '設定をクリア', 'custom-rss-builder' ); ?></button>
			<button type="submit" name="crb_license_action" value="test_run_ensure" class="button" <?php disabled( ! $is_embedded_mode ); ?>><?php esc_html_e( '無料を今すぐ再発行', 'custom-rss-builder' ); ?></button>
		</form>
	</div>
	<?php endif; ?>
</div>
