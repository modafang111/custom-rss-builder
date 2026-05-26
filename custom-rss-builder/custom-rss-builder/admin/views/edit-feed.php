<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$feed_manager    = crb_plugin()->feed_manager;
$is_post         = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' );
$import_defaults = $feed_manager->default_import_settings();
$stored_import   = is_array( $feed ?? null ) ? $feed_manager->get_import_settings( $feed ) : $import_defaults;
$preview_data    = isset( $preview_data ) && is_array( $preview_data ) ? $preview_data : array();
$has_preview       = ! empty( $preview_data );
$has_import_preview = $has_preview && isset( $preview_data['import_posts'] );
$stored_css      = is_array( $feed ?? null ) ? crb_get_feed_css_config( $feed ) : crb_empty_css_config();
$stored_mode     = is_array( $feed ?? null ) ? crb_get_feed_extraction_mode( $feed ) : 'css';
if ( $is_post ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$post_mode = sanitize_key( wp_unslash( $_POST['extraction_mode'] ?? 'css' ) );
	$stored_mode = in_array( $post_mode, array( 'template', 'css' ), true ) ? $post_mode : 'css';
	$stored_css  = crb_sanitize_css_config(
		array(
			'scope_selector'        => wp_unslash( $_POST['css_scope_selector'] ?? '' ),
			'item_selector'         => wp_unslash( $_POST['css_item_selector'] ?? '' ),
			'link_selector'         => wp_unslash( $_POST['css_link_selector'] ?? '' ),
			'title_mode'            => wp_unslash( $_POST['css_title_mode'] ?? 'attr' ),
			'title_attr'            => wp_unslash( $_POST['css_title_attr'] ?? 'title' ),
			'title_selector'        => wp_unslash( $_POST['css_title_selector'] ?? '' ),
			'image_selector'        => wp_unslash( $_POST['css_image_selector'] ?? '' ),
			'author_selector'       => wp_unslash( $_POST['css_author_selector'] ?? '' ),
			'review_title_selector' => wp_unslash( $_POST['css_review_title_selector'] ?? '' ),
			'summary_selector'      => wp_unslash( $_POST['css_summary_selector'] ?? '' ),
			'category_selector'     => wp_unslash( $_POST['css_category_selector'] ?? '' ),
			'review_body_selector'  => wp_unslash( $_POST['css_review_body_selector'] ?? '' ),
			'slot_selector_9'       => wp_unslash( $_POST['css_slot_selector_9'] ?? '' ),
			'slot_selector_10'      => wp_unslash( $_POST['css_slot_selector_10'] ?? '' ),
			'slot_selector_11'      => wp_unslash( $_POST['css_slot_selector_11'] ?? '' ),
			'slot_selector_12'      => wp_unslash( $_POST['css_slot_selector_12'] ?? '' ),
			'slot_mode_3'           => wp_unslash( $_POST['css_slot_mode_3'] ?? '' ),
			'slot_mode_4'           => wp_unslash( $_POST['css_slot_mode_4'] ?? '' ),
			'slot_mode_5'           => wp_unslash( $_POST['css_slot_mode_5'] ?? '' ),
			'slot_mode_6'           => wp_unslash( $_POST['css_slot_mode_6'] ?? '' ),
			'slot_mode_7'           => wp_unslash( $_POST['css_slot_mode_7'] ?? '' ),
			'slot_mode_8'           => wp_unslash( $_POST['css_slot_mode_8'] ?? '' ),
			'slot_mode_9'           => wp_unslash( $_POST['css_slot_mode_9'] ?? '' ),
			'slot_mode_10'          => wp_unslash( $_POST['css_slot_mode_10'] ?? '' ),
			'slot_mode_11'          => wp_unslash( $_POST['css_slot_mode_11'] ?? '' ),
			'slot_mode_12'          => wp_unslash( $_POST['css_slot_mode_12'] ?? '' ),
		)
	);
}
$crb_slot_rows = array(
	2  => array( 'input_id' => 'crb-css-summary', 'input_name' => 'css_summary_selector' ),
	3  => array( 'input_id' => 'crb-css-image', 'input_name' => 'css_image_selector' ),
	4  => array( 'input_id' => 'crb-css-category', 'input_name' => 'css_category_selector' ),
	5  => array( 'input_id' => 'crb-css-review-title', 'input_name' => 'css_review_title_selector' ),
	6  => array( 'input_id' => 'crb-css-review-body', 'input_name' => 'css_review_body_selector' ),
	7  => array( 'input_id' => 'crb-css-author', 'input_name' => 'css_author_selector' ),
	8  => array( 'input_id' => 'crb-css-slot-9', 'input_name' => 'css_slot_selector_9' ),
	9  => array( 'input_id' => 'crb-css-slot-10', 'input_name' => 'css_slot_selector_10' ),
	10 => array( 'input_id' => 'crb-css-slot-11', 'input_name' => 'css_slot_selector_11' ),
	11 => array( 'input_id' => 'crb-css-slot-12', 'input_name' => 'css_slot_selector_12' ),
);
$values          = array(
	'id'              => $feed['id'] ?? 0,
	'name'            => $is_post ? sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) : (string) ( $feed['name'] ?? '' ),
	'url'             => $is_post ? esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ) : (string) ( $feed['url'] ?? '' ),
	'extraction_mode' => $stored_mode,
	'css'             => $stored_css,
	'scope_template'  => $is_post ? crb_get_template_from_post( 'scope_template' ) : (string) ( $feed['scope_template'] ?? '' ),
	'template'        => $is_post ? crb_get_template_from_post( 'template' ) : (string) ( $feed['template'] ?? '' ),
	'mapping'  => array(
		'title'       => $is_post ? (int) ( $_POST['map_title'] ?? 0 ) : (int) ( $feed['mapping']['title'] ?? 0 ),
		'link'        => $is_post ? (int) ( $_POST['map_link'] ?? 1 ) : (int) ( $feed['mapping']['link'] ?? 1 ),
		'description' => $is_post ? (int) ( $_POST['map_description'] ?? 2 ) : (int) ( $feed['mapping']['description'] ?? 2 ),
		'date'        => $is_post ? (int) ( $_POST['map_date'] ?? -1 ) : (int) ( $feed['mapping']['date'] ?? -1 ),
	),
	'import'   => array(
		'enabled'             => $is_post ? ! empty( $_POST['import_enabled'] ) : ! empty( $stored_import['enabled'] ),
		'post_status'         => $is_post ? sanitize_key( wp_unslash( $_POST['import_post_status'] ?? 'draft' ) ) : (string) $stored_import['post_status'],
		'post_type'           => $is_post ? sanitize_key( wp_unslash( $_POST['import_post_type'] ?? 'post' ) ) : (string) $stored_import['post_type'],
		'append_source'       => $is_post ? ! empty( $_POST['import_append_source'] ) : ! empty( $stored_import['append_source'] ),
		'category_id'         => $is_post ? (int) ( $_POST['import_category_id'] ?? 0 ) : (int) $stored_import['category_id'],
		'author_id'           => $is_post ? (int) ( $_POST['import_author_id'] ?? 0 ) : (int) $stored_import['author_id'],
		'post_title_template' => $is_post ? crb_get_import_template_from_post( 'import_post_title_template' ) : (string) ( $stored_import['post_title_template'] ?? '' ),
		'content_template'    => $is_post ? crb_get_import_template_from_post( 'import_content_template' ) : (string) ( $stored_import['content_template'] ?? '' ),
	),
);
$import_template_example = crb_preset_review_import_template();
$default_template        = '<div class="news-item">{*}<a href="{%}">{%}</a>{*}<span class="date">{%}</span>{*}';
if ( '' === $values['template'] || false !== strpos( $values['template'], '{*}{%}' ) ) {
	$values['template'] = $default_template;
}
$form_action = admin_url( 'admin.php?page=custom-rss-builder&action=edit' . ( $values['id'] > 0 ? '&feed_id=' . (int) $values['id'] : '' ) );
?>
<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-rss-builder' ) ); ?>">&larr; <?php esc_html_e( '一覧へ戻る', 'custom-rss-builder' ); ?></a></p>

<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="crb-feed-form">
	<?php wp_nonce_field( 'crb_admin_action', 'crb_nonce' ); ?>
	<input type="hidden" name="feed_id" value="<?php echo esc_attr( (string) $values['id'] ); ?>">

	<section class="crb-panel">
		<header class="crb-panel__header">
			<h2 class="crb-panel__title"><?php esc_html_e( 'フィードと抽出', 'custom-rss-builder' ); ?></h2>
		</header>

		<div id="crb-step-1" class="crb-workflow-step">
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
				<tr>
					<th scope="row"><?php esc_html_e( '抽出方法', 'custom-rss-builder' ); ?></th>
					<td>
						<fieldset class="crb-mode-fieldset">
							<label><input type="radio" name="extraction_mode" value="css" <?php checked( $values['extraction_mode'], 'css' ); ?>> <?php esc_html_e( 'CSS セレクタ（おすすめ）', 'custom-rss-builder' ); ?></label>
							<label><input type="radio" name="extraction_mode" value="template" <?php checked( $values['extraction_mode'], 'template' ); ?>> <?php esc_html_e( 'HTML パターン（Feed43 風・上級者向け）', 'custom-rss-builder' ); ?></label>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>

		<div class="crb-extract-panel crb-extract-panel--css" data-crb-mode="css">
			<p class="crb-preset-row">
				<button type="button" class="button" id="crb-preset-example"><?php esc_html_e( 'レビュー／記事リスト型の入力例', 'custom-rss-builder' ); ?></button>
			</p>

			<div id="crb-step-2" class="crb-workflow-step">
				<header class="crb-workflow-step__header">
					<span class="crb-step-badge">2</span>
					<h3 class="crb-workflow-step__title"><?php esc_html_e( '1件ぶんの枠を決める', 'custom-rss-builder' ); ?></h3>
				</header>
				<p class="crb-workflow-step__lead"><?php esc_html_e( 'リストの「1行・1カード・1レビュー」に相当する CSS を指定します。ここが決まってから③の「調べる」が意味を持ちます。', 'custom-rss-builder' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="crb-css-item"><?php esc_html_e( '1件ずつのブロック', 'custom-rss-builder' ); ?></label></th>
						<td>
							<input name="css_item_selector" id="crb-css-item" type="text" class="large-text code" value="<?php echo esc_attr( $values['css']['item_selector'] ); ?>" placeholder=".review_contents">
							<p class="description"><?php esc_html_e( '例: レビュー1件を包む class。空欄のまま③を押すと、入力例のブロックで試します。', 'custom-rss-builder' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crb-css-scope"><?php esc_html_e( '範囲（任意）', 'custom-rss-builder' ); ?></label></th>
						<td>
							<input name="css_scope_selector" id="crb-css-scope" type="text" class="large-text code" value="<?php echo esc_attr( $values['css']['scope_selector'] ); ?>" placeholder="#review_list">
							<p class="description"><?php esc_html_e( 'ページ全体ではなく、一覧エリアだけに絞るときに指定。空欄ならページ全体。', 'custom-rss-builder' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div id="crb-step-3" class="crb-workflow-step crb-workflow-step--discover">
				<header class="crb-workflow-step__header">
					<span class="crb-step-badge">3</span>
					<h3 class="crb-workflow-step__title"><?php esc_html_e( '要素を調べる', 'custom-rss-builder' ); ?></h3>
				</header>
				<p class="crb-workflow-step__lead"><?php esc_html_e( '②の範囲の中だけを調べます。CSS セレクタ・取り方・取れた値を表示します（④の設定とは別）。', 'custom-rss-builder' ); ?></p>
				<p class="crb-discover-actions">
					<button type="button" class="button button-secondary" id="crb-discover-elements-scope"><?php esc_html_e( '範囲を調べる', 'custom-rss-builder' ); ?></button>
					<button type="button" class="button button-primary" id="crb-discover-elements"><?php esc_html_e( '範囲内の要素を調べる', 'custom-rss-builder' ); ?></button>
					<span class="spinner crb-discover-spinner"></span>
				</p>
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
				<p class="crb-workflow-step__lead"><?php esc_html_e( '③のプレビューと同じ内容です。セレクタと「取り方」を編集します（すべて任意）。', 'custom-rss-builder' ); ?></p>
				<p class="description"><?php esc_html_e( '取り方＝その CSS で何を読むか（表示テキスト・リンクURL・画像の src など）。{%1}・{%2} は1件ブロック内で最も有力なリンクのタイトルと URL です（Feed43 風）。', 'custom-rss-builder' ); ?></p>
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

		<div class="crb-extract-panel crb-extract-panel--template" data-crb-mode="template">
			<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="crb-scope-template"><?php esc_html_e( '範囲テンプレート（任意）', 'custom-rss-builder' ); ?></label></th>
				<td>
					<textarea name="scope_template" id="crb-scope-template" rows="3" class="large-text code" placeholder='<div id="review_list">{%}</div>'><?php echo esc_textarea( $values['scope_template'] ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Feed43 の「全体パターン」相当。{%} で囲んだ部分の中だけで抽出します。', 'custom-rss-builder' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="crb-template"><?php esc_html_e( '抽出テンプレート', 'custom-rss-builder' ); ?></label></th>
				<td>
					<textarea name="template" id="crb-template" rows="8" class="large-text code" data-crb-required-template="1"><?php echo esc_textarea( $values['template'] ); ?></textarea>
					<p class="description"><?php esc_html_e( '{%} = 可変部分、{*} = 読み飛ばし', 'custom-rss-builder' ); ?></p>
				</td>
			</tr>
			</table>
		</div>

		<div id="crb-step-5" class="crb-workflow-step crb-workflow-step--preview-action">
			<header class="crb-workflow-step__header">
				<span class="crb-step-badge">5</span>
				<h3 class="crb-workflow-step__title"><?php esc_html_e( '全体プレビューして保存', 'custom-rss-builder' ); ?></h3>
			</header>
			<p class="crb-workflow-step__lead"><?php esc_html_e( '④まで終わったら、ページ全体の抽出結果を確認します。③の試し読みと内容が違う場合は、こちらが保存前の最終確認です。', 'custom-rss-builder' ); ?></p>
			<p class="crb-panel__actions">
				<button type="submit" name="crb_action" value="preview" class="button button-secondary"><?php esc_html_e( 'プレビュー（全件）', 'custom-rss-builder' ); ?></button>
			</p>
		</div>

		<details class="crb-details crb-details--rss-mapping">
			<summary><?php esc_html_e( 'RSSフィード用の割り当て（Inoreader 等・任意）', 'custom-rss-builder' ); ?></summary>
			<div class="crb-details__body">
				<p class="description">
					<?php esc_html_e( 'RSS 配信時の割り当てです。標準は タイトル=0（{%1}）・リンク=1（{%2}）・本文=2（{%3}）。投稿テンプレートの {%n} とは別設定です。', 'custom-rss-builder' ); ?>
				</p>
				<p class="crb-inline-fields">
					<label><?php esc_html_e( 'リンク', 'custom-rss-builder' ); ?> <input name="map_link" type="number" min="0" value="<?php echo esc_attr( (string) $values['mapping']['link'] ); ?>" class="small-text"></label>
					<label><?php esc_html_e( 'タイトル', 'custom-rss-builder' ); ?> <input name="map_title" type="number" min="0" value="<?php echo esc_attr( (string) $values['mapping']['title'] ); ?>" class="small-text"></label>
					<label><?php esc_html_e( '本文', 'custom-rss-builder' ); ?> <input name="map_description" type="number" min="0" value="<?php echo esc_attr( (string) $values['mapping']['description'] ); ?>" class="small-text"></label>
					<label><?php esc_html_e( '日付', 'custom-rss-builder' ); ?> <input name="map_date" type="number" min="0" value="<?php echo esc_attr( (string) $values['mapping']['date'] ); ?>" class="small-text"></label>
				</p>
			</div>
		</details>
	</section>

	<section class="crb-panel crb-panel--preview" id="crb-preview-panel">
		<header class="crb-panel__header">
			<h2 class="crb-panel__title"><?php esc_html_e( '⑤ 抽出結果（プレビュー）', 'custom-rss-builder' ); ?></h2>
		</header>
		<?php if ( $has_preview ) : ?>
			<?php include CRB_PLUGIN_DIR . 'admin/views/preview-results.php'; ?>
		<?php else : ?>
			<p class="crb-panel__placeholder"><?php esc_html_e( '上の「プレビュー（全件）」を押すと、ここに {%1} {%2} … の一覧が表示されます。', 'custom-rss-builder' ); ?></p>
		<?php endif; ?>
	</section>

	<section class="crb-panel">
		<header class="crb-panel__header">
			<h2 class="crb-panel__title"><?php esc_html_e( 'WordPress投稿への取り込み', 'custom-rss-builder' ); ?></h2>
		</header>
		<p class="crb-panel__lead"><?php esc_html_e( '抽出結果をもとに、投稿の形（Feed43 の Item テンプレート）を指定します。', 'custom-rss-builder' ); ?></p>
		<?php include CRB_PLUGIN_DIR . 'admin/views/partials/feed-import-settings.php'; ?>
	</section>

	<section class="crb-panel crb-panel--preview crb-panel--post-preview">
		<header class="crb-panel__header">
			<h2 class="crb-panel__title"><?php esc_html_e( '投稿プレビュー', 'custom-rss-builder' ); ?></h2>
		</header>
		<p class="crb-panel__lead"><?php esc_html_e( '取り込み後の WordPress 投稿がどう見えるかを確認します。', 'custom-rss-builder' ); ?></p>
		<?php if ( $has_import_preview ) : ?>
			<?php include CRB_PLUGIN_DIR . 'admin/views/import-preview-results.php'; ?>
		<?php else : ?>
			<p class="crb-panel__placeholder"><?php esc_html_e( 'ステップ3でテンプレートを入力し、「投稿プレビュー」またはステップ1の「プレビュー」を実行してください。', 'custom-rss-builder' ); ?></p>
		<?php endif; ?>
	</section>

	<?php if ( $values['id'] > 0 ) : ?>
		<section class="crb-panel crb-panel--meta">
			<h2 class="crb-panel__title crb-panel__title--sub"><?php esc_html_e( '配信・自動実行', 'custom-rss-builder' ); ?></h2>
			<dl class="crb-meta-list">
				<div>
					<dt><?php esc_html_e( 'RSS URL', 'custom-rss-builder' ); ?></dt>
					<dd><a href="<?php echo esc_url( $feed_manager->get_feed_url( (int) $values['id'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $feed_manager->get_feed_url( (int) $values['id'] ) ); ?></a></dd>
				</div>
				<?php if ( ! empty( $feed['last_imported'] ) ) : ?>
					<div>
						<dt><?php esc_html_e( '最終取り込み', 'custom-rss-builder' ); ?></dt>
						<dd><?php echo esc_html( (string) $feed['last_imported'] ); ?></dd>
					</div>
				<?php endif; ?>
				<div>
					<dt><?php esc_html_e( 'OS cron 用URL', 'custom-rss-builder' ); ?></dt>
					<dd><code class="crb-cron-url"><?php echo esc_html( crb_get_import_cron_url( (int) $values['id'] ) ); ?></code></dd>
				</div>
			</dl>
			<p class="description"><?php esc_html_e( '取り込みが有効なフィードのみ、cron で上記URLを実行してください。', 'custom-rss-builder' ); ?></p>
		</section>
	<?php endif; ?>

	<div class="crb-form-footer">
		<p class="submit">
			<button type="submit" name="crb_action" value="save" class="button button-primary"><?php esc_html_e( '保存', 'custom-rss-builder' ); ?></button>
			<?php if ( $values['id'] > 0 ) : ?>
				<button type="submit" name="crb_action" value="refresh" class="button"><?php esc_html_e( 'HTMLキャッシュ更新', 'custom-rss-builder' ); ?></button>
				<button type="submit" name="crb_action" value="import_posts" class="button"><?php esc_html_e( '投稿に取り込み', 'custom-rss-builder' ); ?></button>
			<?php endif; ?>
		</p>
	</div>
</form>

<?php if ( $values['id'] > 0 ) : ?>
	<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="crb-delete-form" onsubmit="return confirm('<?php echo esc_js( __( '削除しますか？', 'custom-rss-builder' ) ); ?>');">
		<?php wp_nonce_field( 'crb_admin_action', 'crb_nonce' ); ?>
		<input type="hidden" name="feed_id" value="<?php echo esc_attr( (string) $values['id'] ); ?>">
		<button type="submit" name="crb_action" value="delete" class="button button-link-delete"><?php esc_html_e( 'フィードを削除', 'custom-rss-builder' ); ?></button>
	</form>
<?php endif; ?>
