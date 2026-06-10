# フィード設定パック（エクスポート／インポート）マニュアル

正本サーバー（123789.jp）の **固定ページ** として公開されます。ソースは PHP で組み立てています。

| 項目 | 内容 |
|------|------|
| 実装 | `custom-rss-builder/includes/functions-feed-pack-manual.php` |
| 公開 URL（想定） | `https://123789.jp/custom-rss-builder/crb-practice-samples/crb-feed-pack-manual/` |
| 更新 | 正本へ authority デプロイ後、`init` で自動作成・更新 |

## 手順ページの内容

- 設定パック（JSON `pack_version: 1`）の概要
- エクスポート／インポート手順（クライアント管理画面）
- JSON に含まれる／含まれない項目
- Pro 初期設定（運用者が JSON を渡す流れ）
- FAQ（既存フィードの上書き、DB 非更新など）

## 関連

- インストール手順: `functions-install-manual.php`
- 設定パック機能: `functions-feed-pack.php`
- 配布・デプロイ: `PACKAGING.md`
