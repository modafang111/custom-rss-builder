# CRB License Server（組み込み）

Custom RSS Builder 正本サイト用のライセンス発行・REST API。

## セットアップ

1. 正本 WordPress で Custom RSS Builder（authority）を有効化
2. **CRB ライセンス → ライセンス設定** で API Secret をクライアントへ共有
3. 固定ページに `[crb_free_license]` を設置（無料登録）
4. Pro 決済は **外部決済 URL** 経由。決済後に **ライセンス一覧** から Pro キーを手動発行

### 公開 URL（クライアント向け手順ページ）

正本サイトの固定ページ（製品・練習用の子ページ）:

- インストール: `https://123789.jp/custom-rss-builder/crb-practice-samples/crb-plugin-install-guide/`
- Gemini API キー: `https://123789.jp/custom-rss-builder/crb-practice-samples/crb-openai-api-key-setup/`

製品トップからも「サポート・手順」リンクで辿れます。プラグイン有効化時に自動作成され、**CRB ライセンス → ライセンス設定** にもリンクがあります。

## Gemini API キー（Pro AI 変換）

各クライアントサイトの **Custom RSS Builder → ライセンス → AI テキスト変換（Pro）** で設定します（BYOK）。正本サーバーには保存しません。

### クライアント案内手順（運用）

1. クライアントに Pro キーを有効化してもらう
2. クライアント管理画面で **Custom RSS Builder → ライセンス → AI テキスト変換（Pro）** を開く
3. `Gemini API キー` に Google AI Studio で発行したキー（`AIza...` または `AQ....`）を入力して保存
4. 「状態」が **設定済み** になったことを確認
5. 「接続テスト」で成功することを確認（デフォルトモデル: **Gemini 2.5 Flash**）
6. フィード編集の「AI テキスト変換（Pro）」でモデルが **Gemini 2.5 Flash（推奨・無料枠対応）** になっていること

案内用リンク:

- API キー作成: <https://aistudio.google.com/apikey>
- Google Cloud Credentials: <https://console.cloud.google.com/apis/credentials>

### 注意点

- API キーは **クライアントごと** に管理（使い回し非推奨）
- キーを共有しない（メール本文に平文で貼らない）
- 管理画面からはマスク入力。削除時は「保存済みのキーを削除する」を利用

## Pro 決済 URL

デフォルト: `https://www.wordpress-123.com/payment/f2pset.php?code=16&mode=button`

## ライセンスキー通知メール

**CRB ライセンス → ライセンス設定** で送信者・件名・本文を編集できます。

- **送信者メール**: `wordpress@` ではなく `info@your-domain` など独自ドメインを推奨
- **件名**: デフォルト `Custom RSS Builder ライセンスキーのご案内（{plan_label}）`
- タグ: `{plan_label}` `{plan}` `{site_name}` `{license_key}` 等
