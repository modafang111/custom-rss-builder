<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$feed_manager    = crb_plugin()->feed_manager;
$is_post         = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' );
$feed_is_array   = is_array( $feed ?? null );
$import_defaults = $feed_manager->default_import_settings();
$stored_import      = $feed_is_array ? $feed_manager->get_import_settings( $feed ) : $import_defaults;
$stored_link_rewrite = $feed_is_array && function_exists( 'crb_get_feed_link_rewrite' )
	? crb_get_feed_link_rewrite( $feed )
	: ( function_exists( 'crb_default_link_rewrite_settings' ) ? crb_default_link_rewrite_settings() : array() );
$stored_ai           = $feed_is_array && function_exists( 'crb_get_feed_ai_settings' )
	? crb_get_feed_ai_settings( $feed )
	: ( function_exists( 'crb_default_ai_transform_settings' ) ? crb_default_ai_transform_settings() : array() );
$preview_data    = isset( $preview_data ) && is_array( $preview_data ) ? $preview_data : array();
$has_preview       = ! empty( $preview_data );
$has_import_preview = $has_preview && isset( $preview_data['import_posts'] );
$stored_css      = $feed_is_array ? crb_get_feed_css_config( $feed ) : crb_empty_css_config();
$crb_demo_samples = function_exists( 'crb_get_demo_sample_patterns' ) ? crb_get_demo_sample_patterns() : array();
$crb_demo_index   = function_exists( 'crb_demo_samples_index_url' ) ? crb_demo_samples_index_url() : '';
$crb_feed_pack_manual_url = function_exists( 'crb_feed_pack_manual_page_url' ) ? crb_feed_pack_manual_page_url() : '';
$stored_mode     = $feed_is_array ? crb_get_feed_extraction_mode( $feed ) : 'css';
if ( $is_post ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$post_mode = sanitize_key( wp_unslash( $_POST['extraction_mode'] ?? 'css' ) );
	$stored_mode = in_array( $post_mode, array( 'template', 'css' ), true ) ? $post_mode : 'css';
	$stored_css = crb_sanitize_css_config(
		array_merge(
			array(
				'scope_selector'        => wp_unslash( $_POST['css_scope_selector'] ?? '' ),
				'item_selector'         => wp_unslash( $_POST['css_item_selector'] ?? '' ),
				'link_selector'         => wp_unslash( $_POST['css_link_selector'] ?? '' ),
				'title_mode'            => wp_unslash( $_POST['css_title_mode'] ?? 'attr' ),
				'title_attr'            => wp_unslash( $_POST['css_title_attr'] ?? 'title' ),
				'title_selector'        => wp_unslash( $_POST['css_title_selector'] ?? '' ),
			),
			function_exists( 'crb_collect_extra_slot_fields_from_post' ) ? crb_collect_extra_slot_fields_from_post() : array()
		)
	);
}
$crb_slot_rows      = function_exists( 'crb_extra_slot_form_rows' ) ? crb_extra_slot_form_rows() : array();
$crb_max_slot_index = function_exists( 'crb_license_get_max_slot_index' ) ? crb_license_get_max_slot_index() : ( (int) CRB_RECORD_SLOT_COUNT - 1 );
$crb_slot_rows      = array_filter(
	$crb_slot_rows,
	static function ( $slot_index ) use ( $crb_max_slot_index ) {
		return (int) $slot_index <= $crb_max_slot_index;
	},
	ARRAY_FILTER_USE_KEY
);
$crb_license_state  = function_exists( 'crb_license_get_state' ) ? crb_license_get_state() : array( 'plan' => 'free', 'usable' => false );
$mapping_defaults = crb_default_rss_mapping();
$values          = array(
	'id'              => $feed_is_array ? (int) ( $feed['id'] ?? 0 ) : 0,
	'name'            => $is_post ? sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) : ( $feed_is_array ? (string) ( $feed['name'] ?? '' ) : '' ),
	'url'             => $is_post ? esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ) : ( $feed_is_array ? (string) ( $feed['url'] ?? '' ) : '' ),
	'extraction_mode' => $stored_mode,
	'css'             => $stored_css,
	'scope_template'  => $is_post ? crb_get_template_from_post( 'scope_template' ) : ( $feed_is_array ? (string) ( $feed['scope_template'] ?? '' ) : '' ),
	'template'        => $is_post ? crb_get_template_from_post( 'template' ) : ( $feed_is_array ? (string) ( $feed['template'] ?? '' ) : '' ),
	'mapping'  => array(
		'title'       => $is_post ? (int) ( $_POST['map_title'] ?? $mapping_defaults['title'] ) : ( $feed_is_array ? (int) ( $feed['mapping']['title'] ?? $mapping_defaults['title'] ) : (int) $mapping_defaults['title'] ),
		'link'        => $is_post ? (int) ( $_POST['map_link'] ?? $mapping_defaults['link'] ) : ( $feed_is_array ? (int) ( $feed['mapping']['link'] ?? $mapping_defaults['link'] ) : (int) $mapping_defaults['link'] ),
		'description' => $is_post ? (int) ( $_POST['map_description'] ?? $mapping_defaults['description'] ) : ( $feed_is_array ? (int) ( $feed['mapping']['description'] ?? $mapping_defaults['description'] ) : (int) $mapping_defaults['description'] ),
		'date'        => $is_post ? (int) ( $_POST['map_date'] ?? $mapping_defaults['date'] ) : ( $feed_is_array ? (int) ( $feed['mapping']['date'] ?? $mapping_defaults['date'] ) : (int) $mapping_defaults['date'] ),
	),
	'link_rewrite' => $is_post && function_exists( 'crb_collect_link_rewrite_from_request' )
		? crb_collect_link_rewrite_from_request()
		: $stored_link_rewrite,
	'ai'         => $is_post && function_exists( 'crb_collect_ai_transform_from_request' )
		? crb_collect_ai_transform_from_request()
		: $stored_ai,
	'import'   => array(
		'enabled'             => $is_post ? ! empty( $_POST['import_enabled'] ) : ! empty( $stored_import['enabled'] ),
		'schedule'            => $is_post
			? ( function_exists( 'crb_import_schedule_slug_from_hours' )
				? crb_import_schedule_slug_from_hours( wp_unslash( $_POST['import_schedule_hours'] ?? 0 ) )
				: 'off' )
			: (string) ( $stored_import['schedule'] ?? 'off' ),
		'post_status'         => $is_post ? sanitize_key( wp_unslash( $_POST['import_post_status'] ?? 'draft' ) ) : (string) $stored_import['post_status'],
		'post_type'           => $is_post ? sanitize_key( wp_unslash( $_POST['import_post_type'] ?? 'post' ) ) : (string) $stored_import['post_type'],
		'append_source'       => false,
		'category_id'         => $is_post ? (int) ( $_POST['import_category_id'] ?? 0 ) : (int) $stored_import['category_id'],
		'tag_ids'             => $is_post
			? ( function_exists( 'crb_import_tag_ids_from_request' )
				? crb_import_tag_ids_from_request( $_POST['import_tag_id'] ?? 0 )
				: array() )
			: ( function_exists( 'crb_sanitize_import_tag_ids' )
				? crb_sanitize_import_tag_ids( $stored_import['tag_ids'] ?? array() )
				: array() ),
		'author_id'           => $is_post ? (int) ( $_POST['import_author_id'] ?? 0 ) : (int) $stored_import['author_id'],
		'post_title_template' => $is_post ? crb_get_import_template_from_post( 'import_post_title_template' ) : (string) ( $stored_import['post_title_template'] ?? '' ),
		'content_template'    => $is_post
			? crb_get_import_template_from_post( 'import_content_template' )
			: crb_import_content_template_for_ui( (string) ( $stored_import['content_template'] ?? '' ) ),
	),
);
$form_action = admin_url( 'admin.php?page=custom-rss-builder&action=edit' . ( $values['id'] > 0 ? '&feed_id=' . (int) $values['id'] : '' ) );
?>
<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-rss-builder' ) ); ?>">&larr; <?php esc_html_e( '一覧へ戻る', 'custom-rss-builder' ); ?></a></p>

<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="crb-feed-form">
	<div id="crb-ajax-notices" class="crb-ajax-notices" aria-live="polite"></div>
	<?php wp_nonce_field( 'crb_admin_action', 'crb_nonce' ); ?>
	<input type="hidden" name="feed_id" value="<?php echo esc_attr( (string) $values['id'] ); ?>">
	<input type="hidden" name="extraction_mode" value="css">

	<section class="crb-panel">
		<header class="crb-panel__header">
			<h2 class="crb-panel__title"><?php esc_html_e( 'フィードと抽出', 'custom-rss-builder' ); ?></h2>
		</header>

		<nav class="crb-workflow" aria-label="<?php esc_attr_e( '設定の手順', 'custom-rss-builder' ); ?>">
			<ol class="crb-workflow__list">
				<li class="crb-workflow__item is-active" data-crb-workflow-step="1">
					<button type="button" class="crb-workflow__btn" data-crb-workflow-target="crb-step-1">
						<span class="crb-workflow__num">1</span>
						<span class="crb-workflow__label"><?php esc_html_e( '対象URL', 'custom-rss-builder' ); ?></span>
					</button>
				</li>
				<li class="crb-workflow__item" data-crb-workflow-step="2">
					<button type="button" class="crb-workflow__btn" data-crb-workflow-target="crb-step-2">
						<span class="crb-workflow__num">2</span>
						<span class="crb-workflow__label"><?php esc_html_e( '範囲・1件', 'custom-rss-builder' ); ?></span>
					</button>
				</li>
				<li class="crb-workflow__item" data-crb-workflow-step="3">
					<button type="button" class="crb-workflow__btn" data-crb-workflow-target="crb-step-3">
						<span class="crb-workflow__num">3</span>
						<span class="crb-workflow__label"><?php esc_html_e( '要素を調べる', 'custom-rss-builder' ); ?></span>
					</button>
				</li>
				<li class="crb-workflow__item" data-crb-workflow-step="4">
					<button type="button" class="crb-workflow__btn" data-crb-workflow-target="crb-step-4">
						<span class="crb-workflow__num">4</span>
						<span class="crb-workflow__label"><?php esc_html_e( 'スロット割当', 'custom-rss-builder' ); ?></span>
					</button>
				</li>
				<li class="crb-workflow__item" data-crb-workflow-step="5">
					<button type="button" class="crb-workflow__btn" data-crb-workflow-target="crb-step-5">
						<span class="crb-workflow__num">5</span>
						<span class="crb-workflow__label"><?php esc_html_e( 'プレビュー', 'custom-rss-builder' ); ?></span>
					</button>
				</li>
			</ol>
		</nav>

		<div id="crb-step-1" class="crb-workflow-step is-active">
			<header class="crb-workflow-step__header">
				<span class="crb-step-badge">1</span>
				<h3 class="crb-workflow-step__title"><?php esc_html_e( '対象を指定', 'custom-rss-builder' ); ?></h3>
			</header>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="crb-name"><?php esc_html_e( 'フィード名', 'custom-rss-builder' ); ?></label></th>
					<td><input name="name" id="crb-name" type="text" class="regular-text" value="<?php echo esc_attr( $values['name'] ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="crb-url"><?php esc_html_e( '対象URL', 'custom-rss-builder' ); ?></label></th>
					<td><input name="url" id="crb-url" type="url" class="large-text" value="<?php echo esc_attr( $values['url'] ); ?>" required></td>
				</tr>
			</table>
		</div>

		<div class="crb-extract-panel crb-extract-panel--css" data-crb-mode="css">
			<div id="crb-step-2" class="crb-workflow-step">
				<header class="crb-workflow-step__header">
					<span class="crb-step-badge">2</span>
					<h3 class="crb-workflow-step__title"><?php esc_html_e( '一覧の場所と1件ぶんの区切り', 'custom-rss-builder' ); ?></h3>
				</header>
				<p class="crb-workflow-step__lead"><?php esc_html_e( '記事が並んでいる「一覧全体」と、必要なら「1記事ぶん」の区切りを指定します。まずは下の「正本サーバーの練習用サンプル」で触ってみるのがおすすめです。', 'custom-rss-builder' ); ?></p>
				<p class="crb-extraction-save-hint description">
					<strong><?php esc_html_e( '保存・プレビューについて', 'custom-rss-builder' ); ?></strong>
					<?php esc_html_e( '「一覧の場所」だけを必須にしているわけではありません。範囲・1件ぶん・④スロットのどれか1つ以上があれば保存できます（練習用パターン1は、範囲を空欄のまま1件ぶんだけ入れる形です）。', 'custom-rss-builder' ); ?>
				</p>
				<?php if ( ! empty( $crb_demo_samples ) ) : ?>
					<div class="crb-demo-samples-panel">
						<h4 class="crb-demo-samples-panel__title"><?php esc_html_e( '正本サーバー（123789.jp）の練習用サンプル', 'custom-rss-builder' ); ?></h4>
						<p class="description">
							<?php esc_html_e( '正本サイトの WordPress 固定ページ（販売ページの子ページ）です。クライアントサイトではなく、こちらを開いて class 名を確認します。', 'custom-rss-builder' ); ?>
							<?php if ( '' !== $crb_demo_index ) : ?>
								<a href="<?php echo esc_url( $crb_demo_index ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '製品・練習用トップ（固定ページ）', 'custom-rss-builder' ); ?></a>
							<?php endif; ?>
						</p>
						<table class="widefat striped crb-demo-samples-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'パターン', 'custom-rss-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( '一覧の場所', 'custom-rss-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( '1件ぶん', 'custom-rss-builder' ); ?></th>
									<th scope="col"><?php esc_html_e( 'ページ', 'custom-rss-builder' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $crb_demo_samples as $crb_sample ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( (string) ( $crb_sample['label'] ?? '' ) ); ?></strong>
											<p class="description"><?php echo esc_html( (string) ( $crb_sample['description'] ?? '' ) ); ?></p>
										</td>
										<td><code><?php echo esc_html( '' !== (string) ( $crb_sample['scope_selector'] ?? '' ) ? (string) $crb_sample['scope_selector'] : '（空欄）' ); ?></code></td>
										<td><code><?php echo esc_html( (string) ( $crb_sample['item_selector'] ?? '' ) ); ?></code></td>
										<td><a href="<?php echo esc_url( (string) ( $crb_sample['url'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( '開く', 'custom-rss-builder' ); ?></a></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="crb-preset-row">
							<label for="crb-preset-sample" class="screen-reader-text"><?php esc_html_e( 'サンプルパターン', 'custom-rss-builder' ); ?></label>
							<select id="crb-preset-sample" class="crb-preset-sample-select">
								<option value=""><?php esc_html_e( 'パターンを選ぶ…', 'custom-rss-builder' ); ?></option>
								<?php foreach ( $crb_demo_samples as $crb_sample ) : ?>
									<option value="<?php echo esc_attr( (string) ( $crb_sample['id'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $crb_sample['label'] ?? '' ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="button button-secondary" id="crb-apply-preset-sample"><?php esc_html_e( '選択したパターンを入力欄に反映', 'custom-rss-builder' ); ?></button>
							<button type="button" class="button button-link" id="crb-fill-sample-url"><?php esc_html_e( '対象 URL にサンプルページを入れる', 'custom-rss-builder' ); ?></button>
						</p>
					</div>
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="crb-css-scope"><?php esc_html_e( '一覧の場所（範囲）', 'custom-rss-builder' ); ?></label></th>
						<td>
							<input name="css_scope_selector" id="crb-css-scope" type="text" class="large-text code" value="<?php echo esc_attr( $values['css']['scope_selector'] ); ?>" placeholder=".crb-sample-list">
							<p class="description"><?php esc_html_e( '省略可。空欄のままでも保存できます（③はページ全体を調べます）。「範囲の HTML を確認」ボタンだけ、この欄が必要です。', 'custom-rss-builder' ); ?></p>
							<div class="crb-selector-primer">
								<p class="crb-selector-primer__title"><?php esc_html_e( 'CSSセレクタの読み方', 'custom-rss-builder' ); ?></p>
								<ul class="crb-selector-primer__list">
									<li><code>#</code><?php esc_html_e( '（シャープ）＝ id … ページ内で1つだけの目印。例: ', 'custom-rss-builder' ); ?><code>#content</code></li>
									<li><code>.</code><?php esc_html_e( '（ドット）＝ class … 同じデザインの部品に付く名前。例: ', 'custom-rss-builder' ); ?><code>.crb-sample-list</code></li>
								</ul>
								<p class="description"><?php esc_html_e( '上の練習用サンプルを開き、表示されている class 名をそのままコピーするか、③「取れる値を一覧表示」から選んでください。', 'custom-rss-builder' ); ?></p>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="crb-css-item"><?php esc_html_e( '1件ぶんの区切り', 'custom-rss-builder' ); ?></label>
						</th>
						<td>
							<input name="css_item_selector" id="crb-css-item" type="text" class="large-text code" value="<?php echo esc_attr( $values['css']['item_selector'] ); ?>" placeholder=".crb-sample-item">
							<p class="description"><?php esc_html_e( '省略可。一覧の中で「1記事分」の要素です。空欄のときは範囲全体を1件として扱います。', 'custom-rss-builder' ); ?></p>
							<p class="description"><?php esc_html_e( '範囲を空にする場合は、ここか④のスロットのどちらかを入れてから保存してください。③だけなら範囲・区切りが空でも候補は出せます。', 'custom-rss-builder' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div id="crb-step-3" class="crb-workflow-step crb-workflow-step--discover">
				<header class="crb-workflow-step__header">
					<span class="crb-step-badge">3</span>
					<h3 class="crb-workflow-step__title"><?php esc_html_e( '要素を調べる', 'custom-rss-builder' ); ?></h3>
				</header>
				<p class="crb-workflow-step__lead"><?php esc_html_e( '対象 URL を開き、②で指定した範囲（空欄ならページ全体）から、タイトルやリンク URL など「取れる値」の候補を表にします。表の行を選ぶと④にコピーされます。', 'custom-rss-builder' ); ?></p>
				<div class="crb-discover-actions">
					<div class="crb-discover-actions__buttons">
						<button type="button" class="button button-secondary" id="crb-discover-elements-scope"><?php esc_html_e( '範囲の HTML を確認', 'custom-rss-builder' ); ?></button>
						<button type="button" class="button button-primary" id="crb-discover-elements"><?php esc_html_e( '取れる値を一覧表示', 'custom-rss-builder' ); ?></button>
						<span class="spinner crb-discover-spinner"></span>
					</div>
					<ul class="crb-discover-actions__help description">
						<li><strong><?php esc_html_e( '範囲の HTML を確認', 'custom-rss-builder' ); ?></strong> — <?php esc_html_e( '「一覧の場所」が空欄のときは押せません（省略可の欄のため）。入れたときだけ HTML を目視確認します。', 'custom-rss-builder' ); ?></li>
						<li><strong><?php esc_html_e( '取れる値を一覧表示', 'custom-rss-builder' ); ?></strong> — <?php esc_html_e( 'まずはこちら。タイトル・リンクなどの候補が表で出ます。スロット列で「タイトル用」「リンク用」などを選ぶと④に入ります。', 'custom-rss-builder' ); ?></li>
					</ul>
				</div>
				<div id="crb-discover-results" class="crb-discover-results" hidden>
					<p class="crb-discover-results__lead"></p>
					<div class="crb-discover-table-wrap"></div>
				</div>
			</div>

			<div id="crb-step-4" class="crb-workflow-step">
				<header class="crb-workflow-step__header">
					<span class="crb-step-badge">4</span>
					<h3 class="crb-workflow-step__title"><?php esc_html_e( 'スロットに割り当て', 'custom-rss-builder' ); ?></h3>
				</header>
				<p class="crb-workflow-step__lead"><?php esc_html_e( '③の表で選んだ内容がここに入ります。{%1%}＝タイトル、{%2%}＝リンク URL など。手入力も可能です。', 'custom-rss-builder' ); ?></p>
				<?php if ( ! empty( $crb_license_state['usable'] ) && 'free' === ( $crb_license_state['plan'] ?? '' ) ) : ?>
					<p class="description">
						<?php
						$crb_free_slot_count = (int) CRB_LICENSE_FREE_SLOT_LIMIT;
						$crb_pro_slot_max    = function_exists( 'crb_license_pro_slot_count' ) ? (int) crb_license_pro_slot_count() : 20;
						$crb_free_slot_range = function_exists( 'crb_license_format_slot_range_text' )
							? crb_license_format_slot_range_text( $crb_free_slot_count )
							: (string) $crb_free_slot_count;
						printf(
							/* translators: 1: free slot range text, 2: pro max slot token, 3: first slot token */
							esc_html__( '現在のプラン（無料）: スロットは %1$s まで。Pro では %3$s〜%2$s まで利用できます。', 'custom-rss-builder' ),
							esc_html( $crb_free_slot_range ),
							'{%' . $crb_pro_slot_max . '%}',
							'{%1%}'
						);
						?>
					</p>
				<?php elseif ( ! empty( $crb_license_state['usable'] ) && 'pro' === ( $crb_license_state['plan'] ?? '' ) ) : ?>
					<p class="description">
						<?php
						$crb_pro_slot_max = function_exists( 'crb_license_pro_slot_count' ) ? (int) crb_license_pro_slot_count() : 20;
						printf(
							/* translators: 1: first slot token, 2: max slot token */
							esc_html__( '現在のプラン（Pro）: スロット %1$s〜%2$s、フィード数無制限。', 'custom-rss-builder' ),
							'{%1%}',
							'{%' . $crb_pro_slot_max . '%}'
						);
						?>
					</p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( '取り方＝その CSS で何を読むか（表示テキスト・リンクURL・HTML・画像の src など）。', 'custom-rss-builder' ); ?></p>
				<table class="widefat crb-slot-rules-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'スロット', 'custom-rss-builder' ); ?></th>
							<th><?php esc_html_e( 'CSS セレクタ', 'custom-rss-builder' ); ?></th>
							<th><?php esc_html_e( '取り方', 'custom-rss-builder' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>{%1}</code></td>
							<td>
								<input
									name="css_title_selector"
									id="crb-slot-0-sel"
									type="text"
									class="large-text code crb-slot-selector"
									data-slot-index="0"
									value="<?php echo esc_attr( (string) $values['css']['title_selector'] ); ?>"
								>
							</td>
							<td class="crb-slot-mode-cell">
								<select name="css_title_mode" id="crb-slot-0-mode" class="crb-slot-mode" data-slot-index="0">
									<?php foreach ( crb_title_slot_mode_options() as $mode_val => $mode_label ) : ?>
										<option value="<?php echo esc_attr( $mode_val ); ?>" <?php selected( $values['css']['title_mode'], $mode_val ); ?>><?php echo esc_html( $mode_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<input
									name="css_title_attr"
									id="crb-slot-0-attr"
									type="text"
									class="small-text code crb-slot-attr"
									data-slot-index="0"
									value="<?php echo esc_attr( (string) $values['css']['title_attr'] ); ?>"
									placeholder="title"
								>
							</td>
						</tr>
						<tr>
							<td><code>{%2}</code></td>
							<td>
								<input
									name="css_link_selector"
									id="crb-slot-1-sel"
									type="text"
									class="large-text code crb-slot-selector"
									data-slot-index="1"
									value="<?php echo esc_attr( (string) $values['css']['link_selector'] ); ?>"
									placeholder="a[href]"
								>
							</td>
							<td>
								<select class="crb-slot-mode" data-slot-index="1" disabled aria-readonly="true">
									<option selected><?php esc_html_e( 'リンクURL (href)', 'custom-rss-builder' ); ?></option>
								</select>
							</td>
						</tr>
						<?php
						foreach ( $crb_slot_rows as $slot_index => $row ) :
							$map      = crb_extra_slot_storage_map()[ $slot_index ];
							$mode_key = $map['mode_key'];
							$cfg_key  = $map['config_key'];
							$mode_val = crb_sanitize_slot_extract_mode( (string) ( $values['css'][ $mode_key ] ?? $map['default_mode'] ) );
							$sel_val  = (string) ( $values['css'][ $cfg_key ] ?? '' );
							?>
						<tr>
							<td><code><?php echo esc_html( crb_slot_token( $slot_index ) ); ?></code></td>
							<td>
								<input
									name="<?php echo esc_attr( $row['input_name'] ); ?>"
									id="<?php echo esc_attr( $row['input_id'] ); ?>"
									type="text"
									class="large-text code crb-slot-selector"
									data-slot-index="<?php echo esc_attr( (string) $slot_index ); ?>"
									value="<?php echo esc_attr( $sel_val ); ?>"
								>
							</td>
							<td class="crb-slot-mode-cell">
								<select name="css_<?php echo esc_attr( $mode_key ); ?>" class="crb-slot-mode" data-slot-index="<?php echo esc_attr( (string) $slot_index ); ?>">
									<?php foreach ( crb_extra_slot_mode_options() as $opt_val => $opt_label ) : ?>
										<option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $mode_val, $opt_val ); ?>><?php echo esc_html( $opt_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<?php if ( function_exists( 'crb_is_client_app_enabled' ) && crb_is_client_app_enabled() ) : ?>
			<?php include CRB_PLUGIN_DIR . 'admin/views/partials/feed-link-rewrite-settings.php'; ?>
			<?php include CRB_PLUGIN_DIR . 'admin/views/partials/feed-ai-transform-settings.php'; ?>
		<?php endif; ?>

		<div id="crb-step-5" class="crb-workflow-step crb-workflow-step--preview-action">
			<header class="crb-workflow-step__header">
				<span class="crb-step-badge">5</span>
				<h3 class="crb-workflow-step__title"><?php esc_html_e( '全体プレビューして保存', 'custom-rss-builder' ); ?></h3>
			</header>
			<p class="crb-workflow-step__lead"><?php esc_html_e( '④まで終わったら、ページ全体の抽出結果を確認します。③の試し読みと内容が違う場合は、こちらが保存前の最終確認です。', 'custom-rss-builder' ); ?></p>
			<p class="crb-panel__actions">
				<button type="submit" name="crb_action" value="preview" class="button button-secondary"><?php esc_html_e( 'プレビュー（全件）', 'custom-rss-builder' ); ?></button>
				<button type="submit" name="crb_action" value="save" class="button button-primary"><?php esc_html_e( '保存', 'custom-rss-builder' ); ?></button>
			</p>
		</div>

	</section>

	<section class="crb-panel crb-panel--preview" id="crb-preview-panel">
		<header class="crb-panel__header">
			<h2 class="crb-panel__title"><?php esc_html_e( '⑤ 抽出結果（プレビュー）', 'custom-rss-builder' ); ?></h2>
		</header>
		<div id="crb-preview-extract-body" class="crb-panel__body">
			<?php if ( $has_preview ) : ?>
				<?php include CRB_PLUGIN_DIR . 'admin/views/preview-results.php'; ?>
			<?php else : ?>
				<p class="crb-panel__placeholder"><?php esc_html_e( '上の「プレビュー（全件）」を押すと、ここに {%1} {%2} … の一覧が表示されます。', 'custom-rss-builder' ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<section class="crb-panel">
		<header class="crb-panel__header">
			<h2 class="crb-panel__title"><?php esc_html_e( 'WordPress投稿への取り込み', 'custom-rss-builder' ); ?></h2>
		</header>
		<p class="crb-panel__lead"><?php esc_html_e( '抽出結果をもとに、投稿の形（Feed43 の Item テンプレート）を指定します。', 'custom-rss-builder' ); ?></p>
		<?php
		// ライセンス警告は admin_notices で1回だけ表示（ここでは重複させない）。
		?>
		<?php
		$crb_import_disabled = empty( $crb_license_state['usable'] );
		include CRB_PLUGIN_DIR . 'admin/views/partials/feed-import-settings.php';
		?>
	</section>

	<section class="crb-panel crb-panel--preview crb-panel--post-preview">
		<header class="crb-panel__header">
			<h2 class="crb-panel__title"><?php esc_html_e( '投稿プレビュー', 'custom-rss-builder' ); ?></h2>
		</header>
		<p class="crb-panel__lead"><?php esc_html_e( '取り込み後の WordPress 投稿がどう見えるかを確認します。', 'custom-rss-builder' ); ?></p>
		<div id="crb-preview-import-body" class="crb-panel__body">
			<?php if ( $has_import_preview ) : ?>
				<?php include CRB_PLUGIN_DIR . 'admin/views/import-preview-results.php'; ?>
			<?php else : ?>
				<p class="crb-panel__placeholder"><?php esc_html_e( '「プレビュー（全件）」または「投稿プレビュー」を実行すると、取り込み後の表示がここに出ます。', 'custom-rss-builder' ); ?></p>
			<?php endif; ?>
		</div>
	</section>

	<div class="crb-form-footer">
		<p class="submit">
			<button type="submit" name="crb_action" value="save" class="button button-primary"><?php esc_html_e( '保存', 'custom-rss-builder' ); ?></button>
			<?php $crb_can_import = ! empty( $crb_license_state['usable'] ); ?>
			<span class="crb-feed-tools" <?php echo $values['id'] > 0 ? '' : ' hidden'; ?>>
				<button type="submit" name="crb_action" value="import_posts" class="button" <?php disabled( ! $crb_can_import ); ?>><?php esc_html_e( '投稿に取り込み', 'custom-rss-builder' ); ?></button>
			</span>
		</p>
	</div>
</form>

<section class="crb-panel crb-feed-pack" id="crb-feed-pack">
	<header class="crb-panel__header">
		<h2 class="crb-panel__title"><?php esc_html_e( '設定パック', 'custom-rss-builder' ); ?></h2>
	</header>
	<p class="crb-panel__lead">
		<?php esc_html_e( 'JSON 設定パックの読み込み・出力。インポートは現在の編集画面に反映するだけで、保存するまで DB は更新されません。', 'custom-rss-builder' ); ?>
	</p>
	<?php if ( '' !== $crb_feed_pack_manual_url ) : ?>
		<p class="crb-feed-pack-manual-link description">
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: feed pack manual URL on authority site */
					__( '詳しい手順: <a href="%s" target="_blank" rel="noopener noreferrer">フィード設定パック（エクスポート／インポート）手順（正本サイト）</a>', 'custom-rss-builder' ),
					esc_url( $crb_feed_pack_manual_url )
				)
			);
			?>
		</p>
	<?php endif; ?>
	<p class="crb-feed-pack__actions">
		<button type="button" class="button button-secondary" id="crb-import-feed-pack">
			<?php esc_html_e( '設定をインポート', 'custom-rss-builder' ); ?>
		</button>
		<input type="file" id="crb-import-feed-pack-file" class="crb-feed-pack-import-file" accept=".json,application/json" hidden>
	</p>
	<?php if ( $values['id'] > 0 ) : ?>
		<p class="crb-feed-pack__export-lead description">
			<?php esc_html_e( '保存済みの設定を JSON として取得します（未保存の変更は含まれません）。', 'custom-rss-builder' ); ?>
		</p>
		<p class="crb-feed-pack__actions">
			<button type="button" class="button button-secondary" id="crb-export-feed-pack" data-feed-id="<?php echo esc_attr( (string) $values['id'] ); ?>">
				<?php esc_html_e( '設定をエクスポート', 'custom-rss-builder' ); ?>
			</button>
		</p>
	<?php endif; ?>
</section>

<?php if ( $values['id'] > 0 ) : ?>
	<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="crb-delete-form" onsubmit="return confirm('<?php echo esc_js( __( '削除しますか？', 'custom-rss-builder' ) ); ?>');">
		<?php wp_nonce_field( 'crb_admin_action', 'crb_nonce' ); ?>
		<input type="hidden" name="feed_id" value="<?php echo esc_attr( (string) $values['id'] ); ?>">
		<button type="submit" name="crb_action" value="delete" class="button button-link-delete"><?php esc_html_e( 'フィードを削除', 'custom-rss-builder' ); ?></button>
	</form>
<?php endif; ?>
