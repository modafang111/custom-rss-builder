<?php
/**
 * 正本サーバー（123789.jp）の Gemini API キー設定手順（固定ページ）。
 *
 * @package Custom_RSS_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRB_AI_MANUAL_VERSION', '12' );
define( 'CRB_AI_MANUAL_OPTION_PAGE_ID', 'crb_ai_manual_page_id' );

/**
 * 手順ページ slug（製品・練習用親ページの子）。
 * 公開 URL を維持するため crb-openai-api-key-setup のまま（中身は Gemini 手順）。
 *
 * @return string
 */
function crb_ai_manual_page_slug() {
	$slug = 'crb-openai-api-key-setup';
	/**
	 * @param string $slug Manual page slug.
	 */
	return (string) apply_filters( 'crb_ai_manual_page_slug', $slug );
}

/**
 * 手順ページの公開 URL（未作成時は推測 URL）。
 *
 * @return string
 */
function crb_ai_manual_page_url() {
	$page_id = (int) get_option( CRB_AI_MANUAL_OPTION_PAGE_ID, 0 );
	if ( $page_id > 0 ) {
		$url = get_permalink( $page_id );
		if ( $url ) {
			return $url;
		}
	}
	$parent = function_exists( 'crb_demo_samples_parent_slug' ) ? crb_demo_samples_parent_slug() : 'crb-practice-samples';
	$base   = function_exists( 'crb_demo_samples_authority_site_url' )
		? crb_demo_samples_authority_site_url()
		: 'https://123789.jp/custom-rss-builder';
	return trailingslashit( $base ) . $parent . '/' . crb_ai_manual_page_slug() . '/';
}

/**
 * @param string $url   URL.
 * @param string $label Link text.
 * @return string
 */
function crb_ai_manual_link_li( $url, $label ) {
	return sprintf(
		'<li><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></li>',
		esc_url( $url ),
		esc_html( $label )
	);
}

/**
 * 既存の手順固定ページ ID を解決（移行用に旧 slug も参照）。
 *
 * @return int
 */
function crb_ai_manual_resolve_page_id() {
	$canonical_slug = crb_ai_manual_page_slug();
	if ( function_exists( 'crb_demo_samples_find_page_by_slug' ) ) {
		$canonical = crb_demo_samples_find_page_by_slug( $canonical_slug );
		if ( $canonical instanceof WP_Post ) {
			return (int) $canonical->ID;
		}
	}

	$page_id = (int) get_option( CRB_AI_MANUAL_OPTION_PAGE_ID, 0 );
	if ( $page_id > 0 ) {
		$post = get_post( $page_id );
		if ( $post instanceof WP_Post && 'page' === $post->post_type ) {
			return $page_id;
		}
	}

	if ( ! function_exists( 'crb_demo_samples_find_page_by_slug' ) ) {
		return 0;
	}

	foreach ( array( 'crb-gemini-api-key-setup', 'crb-openai-api-key-setup-2' ) as $legacy_slug ) {
		$found = crb_demo_samples_find_page_by_slug( $legacy_slug );
		if ( $found instanceof WP_Post ) {
			return (int) $found->ID;
		}
	}

	return 0;
}

/**
 * 移行時に増えた重複固定ページを削除（正本 URL は crb-openai-api-key-setup）。
 *
 * @param int $keep_id Page ID to keep.
 */
function crb_ai_manual_cleanup_duplicate_pages( $keep_id ) {
	if ( ! function_exists( 'crb_demo_samples_find_page_by_slug' ) ) {
		return;
	}
	$keep_id = (int) $keep_id;
	foreach ( array( 'crb-openai-api-key-setup-2', 'crb-gemini-api-key-setup' ) as $dup_slug ) {
		$dup = crb_demo_samples_find_page_by_slug( $dup_slug );
		if ( ! ( $dup instanceof WP_Post ) ) {
			continue;
		}
		if ( (int) $dup->ID === $keep_id ) {
			continue;
		}
		wp_delete_post( (int) $dup->ID, true );
	}
}

/**
 * @return string HTML
 */
function crb_ai_manual_build_page_content() {
	$lines   = array();
	$lines[] = '<div class="crb-ai-manual">';

	$lines[] = '<nav class="crb-ai-manual-toc" aria-label="' . esc_attr__( '目次', 'custom-rss-builder' ) . '">';
	$lines[] = '<h2>' . esc_html__( '目次', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li><a href="#crb-ai-overview">' . esc_html__( '概要（BYOK とは）', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-ai-checklist">' . esc_html__( '作業チェックリスト', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-ai-gemini-account">' . esc_html__( '手順 1：Google アカウントの準備', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-ai-gemini-key">' . esc_html__( '手順 2：Gemini API キーの作成', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-ai-wp-setup">' . esc_html__( '手順 3：WordPress への登録', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-ai-verify">' . esc_html__( '手順 4：設定の確認', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-ai-models">' . esc_html__( 'デフォルトモデル（Gemini 2.5 Flash）', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-ai-usage-cost">' . esc_html__( '利用量・料金の管理', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-ai-security">' . esc_html__( 'セキュリティ上の注意', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-ai-troubleshoot">' . esc_html__( 'うまくいかないとき（トラブルシューティング）', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '<li><a href="#crb-ai-faq">' . esc_html__( 'よくある質問', 'custom-rss-builder' ) . '</a></li>';
	$lines[] = '</ol>';
	$lines[] = '</nav>';

	$lines[] = '<h2 id="crb-ai-overview">' . esc_html__( '概要（BYOK とは）', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<p>' . esc_html__( 'Custom RSS Builder の Pro プランでは、抽出したテキストを AI で整形・要約・言い換えする「AI テキスト変換」機能を利用できます。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<p>' . esc_html__( 'この機能は BYOK（Bring Your Own Key）方式です。Google Gemini の API 利用料はお客様の Google アカウントに直接請求され、API キーもお客様の WordPress サイトにだけ保存されます。販売元（正本）サーバーへキーが送信されたり、正本側に保管されたりすることはありません。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<ul>';
	$lines[] = '<li><strong>' . esc_html__( 'Pro ライセンス', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( 'Custom RSS Builder の機能利用権（当社へのお支払い）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li><strong>' . esc_html__( 'Gemini API キー', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( 'AI 変換の実行に必要な認証情報（Google へのお支払い）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';
	$lines[] = '<p class="crb-ai-manual-note"><em>' . esc_html__( 'Pro ライセンスだけでは AI 変換は動きません。必ず Google 側の準備と、本ページの手順 3 までを完了してください。', 'custom-rss-builder' ) . '</em></p>';

	$lines[] = '<h2 id="crb-ai-checklist">' . esc_html__( '作業チェックリスト', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'Custom RSS Builder の Pro ライセンスキーを有効化し、「現在の状態」が利用可になっている', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'Google アカウントでログインできる', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'Google AI Studio で Gemini API キーを発行し、安全な場所に控えている', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'WordPress 管理画面 → Custom RSS Builder → ライセンス → AI テキスト変換（Pro）にキーを保存した', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '同画面の「状態」が「設定済み」、保存済みキーのマスク表示が出ている', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「接続テスト」が成功する', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'フィード編集 → AI テキスト変換（Pro）でモデルが Gemini 2.5 Flash（推奨）になっている（変更していなければそのまま）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';

	$lines[] = '<h2 id="crb-ai-gemini-account">' . esc_html__( '手順 1：Google アカウントの準備', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<p>' . esc_html__( 'Gemini API キーは Google アカウントに紐づきます。お試しは無料枠でも可能ですが、本番利用では Google Cloud の課金設定を確認してください。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( '1-1. Google アカウント', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . crb_ai_manual_link_li( 'https://accounts.google.com/signup', __( 'Google アカウントを作成', 'custom-rss-builder' ) ) . '</li>';
	$lines[] = '<li>' . esc_html__( '普段使う Google アカウントでログインできる状態にする', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$lines[] = '<h3>' . esc_html__( '1-2. 課金・利用上限（本番利用時）', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . sprintf(
		'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> %3$s',
		esc_url( 'https://console.cloud.google.com/billing' ),
		esc_html__( 'Google Cloud Billing', 'custom-rss-builder' ),
		esc_html__( 'で支払い方法を確認', 'custom-rss-builder' )
	) . '</li>';
	$lines[] = '<li>' . esc_html__( '無料枠を超える利用が見込まれる場合は、予算アラートの設定を推奨', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$lines[] = '<p class="crb-ai-manual-note">' . esc_html__( '※ Google の画面構成や料金体系は変更されることがあります。不明な点は Google AI Studio / Google Cloud の公式ドキュメントを参照してください。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<h2 id="crb-ai-gemini-key">' . esc_html__( '手順 2：Gemini API キーの作成', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . sprintf(
		'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> %3$s',
		esc_url( 'https://aistudio.google.com/apikey' ),
		esc_html__( 'Google AI Studio — API Keys', 'custom-rss-builder' ),
		esc_html__( 'を開く', 'custom-rss-builder' )
	) . '</li>';
	$lines[] = '<li>' . esc_html__( '「Create API key」（API キーを作成）を押す', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '既存の Google Cloud プロジェクトを選ぶか、新規プロジェクトを作成する', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '表示されたキーをコピーし、パスワード管理ツールなど安全な場所に保存する', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$lines[] = '<p><strong>' . esc_html__( '重要：', 'custom-rss-builder' ) . '</strong> ' . esc_html__( 'キーは再表示できない場合があります。作成直後に必ず控えてください。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( 'キー形式について', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ul>';
	$lines[] = '<li>' . esc_html__( '従来形式: AIza で始まるキー（例: AIzaSy...）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '新形式: AQ. で始まるキー（例: AQ.Ab8R...）— Google AI Studio で新規発行される場合があります', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'Custom RSS Builder は上記どちらも利用できます（旧 OpenAI の sk- 形式は非対応）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';
	$lines[] = '<h3>' . esc_html__( '推奨：サイト専用のキーを使う', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p>' . esc_html__( '1 つの WordPress サイトにつき 1 本の API キーを発行することを推奨します。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<h2 id="crb-ai-wp-setup">' . esc_html__( '手順 3：WordPress（Custom RSS Builder）への登録', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<p>' . esc_html__( 'API キーは、RSS を配信・取り込みする WordPress サイト（クライアントサイト）の管理画面で設定します。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( '3-1. Pro ライセンスの確認', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( 'WordPress 管理画面 → Custom RSS Builder → ライセンス', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'プランが Pro、利用可であることを確認', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$lines[] = '<h3>' . esc_html__( '3-2. API キーの保存', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ol>';
	$lines[] = '<li>' . esc_html__( '「AI テキスト変換（Pro）」パネルを開く', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「Gemini API キー」欄に発行したキーを貼り付ける', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「AI 設定を保存」を押す', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '再読み込み後、「状態」が「設定済み」、「保存済みキー」にマスク表示がある', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '「接続テスト」で「Gemini API への接続に成功しました。」を確認', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ol>';
	$lines[] = '<p class="crb-ai-manual-note">' . esc_html__( '入力欄はセキュリティのため保存後に空欄に戻ります。空欄＝未保存ではありません。マスク表示と「設定済み」バッジで確認してください。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<h2 id="crb-ai-verify">' . esc_html__( '手順 4：設定の確認', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<h3>' . esc_html__( 'WordPress 側', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ul>';
	$lines[] = '<li>' . esc_html__( 'ライセンス画面 → 状態「設定済み」、接続テスト成功', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'フィード編集 → AI テキスト変換（Pro）でモデル（Gemini 2.5 Flash 等）を選べる', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';

	$lines[] = '<h2 id="crb-ai-models">' . esc_html__( 'デフォルトモデル（Gemini 2.5 Flash）', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<p>' . esc_html__( 'AI テキスト変換の初期モデルは Gemini 2.5 Flash です。Google AI Studio で発行した API キーなら、無料枠の範囲内で試しやすいモデルとして推奨しています。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<ul>';
	$lines[] = '<li><strong>' . esc_html__( 'Gemini 2.5 Flash', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( 'デフォルト・推奨。接続テストもこのモデルで実行します。', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li><strong>' . esc_html__( 'その他のテキスト出力モデル', 'custom-rss-builder' ) . '</strong> — ' . esc_html__( 'フィード編集の「モデル」から次から選択できます（Google AI Studio の「テキスト出力モデル」と同じ区分）:', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';
	$lines[] = '<ul class="crb-ai-manual-model-list">';
	if ( function_exists( 'crb_ai_transform_text_model_catalog' ) ) {
		foreach ( crb_ai_transform_text_model_catalog() as $slug => $label ) {
			if ( CRB_AI_TRANSFORM_DEFAULT_MODEL === $slug ) {
				continue;
			}
			$lines[] = '<li><code>' . esc_html( $slug ) . '</code> — ' . esc_html( $label ) . '</li>';
		}
	}
	$lines[] = '</ul>';
	$lines[] = '<p class="crb-ai-manual-note">' . esc_html__( '無料枠の上限・対象モデルは Google 側のポリシーで変わることがあります。エラーが出た場合は Google AI Studio の利用状況と、フィード側のモデル選択を確認してください。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( 'Google 側', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<ul>';
	$lines[] = crb_ai_manual_link_li( 'https://aistudio.google.com/', __( 'Google AI Studio', 'custom-rss-builder' ) );
	$lines[] = crb_ai_manual_link_li( 'https://console.cloud.google.com/apis/credentials', __( 'API Credentials（キー一覧）', 'custom-rss-builder' ) );
	$lines[] = '</ul>';

	$lines[] = '<h2 id="crb-ai-usage-cost">' . esc_html__( '利用量・料金の管理', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ul>';
	$lines[] = '<li>' . esc_html__( 'AI 変換の料金は Google Gemini の従量課金です。Custom RSS Builder の Pro 月額料金とは別請求です', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'デフォルトの Gemini 2.5 Flash は Google AI Studio の無料枠で試せる場合があります（上限は Google の規定に従います）', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '無料枠を超える利用が見込まれる場合は、Google Cloud の利用状況を定期的に確認してください', 'custom-rss-builder' ) . '</li>';
	$lines[] = crb_ai_manual_link_li( 'https://ai.google.dev/gemini-api/docs/pricing', __( 'Gemini API Pricing（公式）', 'custom-rss-builder' ) );
	$lines[] = '</ul>';

	$lines[] = '<h2 id="crb-ai-security">' . esc_html__( 'セキュリティ上の注意', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<ul>';
	$lines[] = '<li>' . esc_html__( 'API キーをメール・チャット・スクリーンショットに平文で載せない', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( '漏洩の疑いがあるときは Google Cloud Credentials でキーを削除し、新しいキーを発行', 'custom-rss-builder' ) . '</li>';
	$lines[] = '<li>' . esc_html__( 'WordPress から削除：「保存済みのキーを削除する」にチェック →「AI 設定を保存」', 'custom-rss-builder' ) . '</li>';
	$lines[] = '</ul>';

	$lines[] = '<h2 id="crb-ai-troubleshoot">' . esc_html__( 'うまくいかないとき（トラブルシューティング）', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<table class="crb-ai-manual-table"><thead><tr><th>' . esc_html__( '症状', 'custom-rss-builder' ) . '</th><th>' . esc_html__( '確認・対処', 'custom-rss-builder' ) . '</th></tr></thead><tbody>';

	$rows = array(
		array(
			__( 'キー欄が編集できない', 'custom-rss-builder' ),
			__( 'Pro ライセンスが有効か確認。', 'custom-rss-builder' ),
		),
		array(
			__( '保存しても「未設定」のまま', 'custom-rss-builder' ),
			__( 'AIza... または AQ.... で始まる形式か確認。保存後にページを再読み込み。', 'custom-rss-builder' ),
		),
		array(
			__( '入力欄が空欄で不安', 'custom-rss-builder' ),
			__( '正常です。「保存済みキー」のマスク表示と「設定済み」バッジを確認。', 'custom-rss-builder' ),
		),
		array(
			__( '接続テストで quota / billing エラー', 'custom-rss-builder' ),
			__( 'Google Cloud Billing と Generative Language API の有効化を確認。デフォルトの Gemini 2.5 Flash が無料枠対象か Google AI Studio で確認。', 'custom-rss-builder' ),
		),
		array(
			__( 'sk- 形式のキーを保存しようとしてエラー', 'custom-rss-builder' ),
			__( 'OpenAI キーは非対応です。Google AI Studio で Gemini キー（AIza... または AQ....）を発行してください。', 'custom-rss-builder' ),
		),
	);
	foreach ( $rows as $row ) {
		$lines[] = '<tr><td>' . esc_html( $row[0] ) . '</td><td>' . esc_html( $row[1] ) . '</td></tr>';
	}
	$lines[] = '</tbody></table>';

	$lines[] = '<h2 id="crb-ai-faq">' . esc_html__( 'よくある質問', 'custom-rss-builder' ) . '</h2>';
	$lines[] = '<h3>' . esc_html__( 'Q. Pro を複数サイトで使うとき、API キーは共有できますか？', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p>' . esc_html__( 'A. ライセンスキー（Pro）は最大 10 台の WordPress で同じものを有効化できます。Gemini API キーは各サイトの「ライセンス → AI テキスト変換」に個別に保存してください（1 サイト 1 キーを推奨）。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( 'Q. API キーは販売元サーバーに送られますか？', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p>' . esc_html__( 'A. いいえ。キーはお客様の WordPress にのみ保存されます。', 'custom-rss-builder' ) . '</p>';
	$lines[] = '<h3>' . esc_html__( 'Q. OpenAI キーは使えますか？', 'custom-rss-builder' ) . '</h3>';
	$lines[] = '<p>' . esc_html__( 'A. いいえ。現バージョンは Google Gemini API キー（AIza... または AQ....）のみ対応しています。', 'custom-rss-builder' ) . '</p>';

	$lines[] = '<hr />';
	$lines[] = '<p class="crb-ai-manual-footer"><small>' . esc_html__( '本ページは Custom RSS Builder プラグインにより自動更新されます。', 'custom-rss-builder' ) . '</small></p>';
	$lines[] = '</div>';

	return implode( "\n", $lines );
}

/**
 * 正本サーバーに手順固定ページを作成・更新。
 *
 * @param bool $force Force reinstall.
 * @return array{ok:bool,message:string,url:string}
 */
function crb_ai_manual_install( $force = false ) {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return array(
			'ok'      => false,
			'message' => 'not authority',
			'url'     => '',
		);
	}

	$stored_ver = (string) get_option( 'crb_ai_manual_install_version', '' );
	if ( ! $force && CRB_AI_MANUAL_VERSION === $stored_ver ) {
		return array(
			'ok'      => true,
			'message' => 'already installed',
			'url'     => crb_ai_manual_page_url(),
		);
	}

	if ( ! function_exists( 'crb_demo_samples_upsert_page' ) || ! function_exists( 'crb_demo_samples_get_sales_page_id' ) ) {
		return array(
			'ok'      => false,
			'message' => 'demo samples helpers missing',
			'url'     => crb_ai_manual_page_url(),
		);
	}

	$parent_id = crb_demo_samples_get_sales_page_id();
	if ( $parent_id <= 0 && function_exists( 'crb_demo_samples_find_page_by_slug' ) && function_exists( 'crb_demo_samples_parent_slug' ) ) {
		$parent = crb_demo_samples_find_page_by_slug( crb_demo_samples_parent_slug() );
		if ( $parent instanceof WP_Post ) {
			$parent_id = (int) $parent->ID;
		}
	}
	if ( $parent_id <= 0 ) {
		return array(
			'ok'      => false,
			'message' => 'parent page missing',
			'url'     => crb_ai_manual_page_url(),
		);
	}

	$title   = __( 'Gemini API キー設定手順（Pro AI 変換）', 'custom-rss-builder' );
	$content = crb_ai_manual_build_page_content();
	$slug    = crb_ai_manual_page_slug();
	$page_id = crb_ai_manual_resolve_page_id();

	if ( $page_id > 0 ) {
		$result = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_parent'  => $parent_id,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'message' => 'page update failed',
				'url'     => crb_ai_manual_page_url(),
			);
		}
		$page_id = (int) $result;
	} else {
		$page_id = crb_demo_samples_upsert_page( $slug, $title, $content, $parent_id );
	}

	if ( $page_id <= 0 ) {
		return array(
			'ok'      => false,
			'message' => 'page upsert failed',
			'url'     => crb_ai_manual_page_url(),
		);
	}

	update_option( CRB_AI_MANUAL_OPTION_PAGE_ID, (int) $page_id, false );
	update_option( 'crb_ai_manual_install_version', CRB_AI_MANUAL_VERSION, false );
	crb_ai_manual_cleanup_duplicate_pages( (int) $page_id );

	return array(
		'ok'      => true,
		'message' => 'installed',
		'url'     => (string) get_permalink( $page_id ),
	);
}

/**
 * init / activate 用。
 */
function crb_ai_manual_maybe_install() {
	if ( ! function_exists( 'crb_license_is_authoritative_server' ) || ! crb_license_is_authoritative_server() ) {
		return;
	}
	crb_ai_manual_install( false );
}

add_action( 'init', 'crb_ai_manual_maybe_install', 21 );
