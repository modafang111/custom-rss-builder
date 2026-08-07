# CRB 補助プラグイン開発環境

**phub（物件収集）とは無関係。** 作業ルートは `D:\dev\custom-rss-builder\crb_helper`。  
本体 CRB 開発: `D:\dev\custom-rss-builder`（復元元 `\\MSI\dev\ai_spec_builder\plugins`）。  
対象サイト: [PluginTest](https://wordpress-123.com/PluginTest/) / 正本 [123789.jp/custom-rss-builder](https://123789.jp/custom-rss-builder/)

## 目的（次フェーズ）

Custom RSS Builder の素の取り込みはコピーコンテンツになりやすい。  
AI 変換は課金負担が大きいため、**補助プラグインでルール／テンプレ／差分加工**を挟む方針。

## 接続情報

| 項目 | 値 |
|------|-----|
| サイト | https://wordpress-123.com/PluginTest/ |
| REST ユーザー | `blog2@wordpress-123.com`（login: blog2 / id: 3 / administrator） |
| アプリパスワード | `env/wp.local.json`（gitignore・バックアップ対象外にしてもよい） |
| FTP | `env/deploy.local.json`（PluginTest）。本体側は `D:\dev\custom-rss-builder\deploy.local.json` |

## サイト上の関連プラグイン（確認済み）

| プラグイン | バージョン | 役割 |
|-----------|-----------|------|
| Custom RSS Builder | 0.9.1.20260717a | 本体（RSS・投稿取り込み） |
| CRB ID Split | 1.0.5 | テンプレ用 ID 分割 / DUGA プレイヤー |
| CRB Title Redirect | 1.0.0 | 同一タイトル旧→新 301（重複対策） |
| CRB Random Intro | 1.0.5 | Pro AI 向け汎用。3 ボックス抽選→取り込み本文へ（[配布](https://123789.jp/custom-rss-builder/crb-random-intro/)） |

## ディレクトリ

```
D:\dev\custom-rss-builder\crb_helper\
  reference/          # 開発参照用（ZIP展開・ドキュメント同梱）
  site_snapshot/      # PluginTest から FTP 取得した実体
  helpers_new/        # 補助プラグイン開発（例: crb-random-intro）
  tools/              # ビルド・FTP pull・公開スクリプト
  env/                # 接続情報（*.local.json は秘密）
  docs/               # CRB マニュアル類
```

## 補助プラグインが使える主なフック

- `crb_content_template_rendered` … コンテンツテンプレ描画後（既存 ID Split が利用）
- `wp_insert_post_data` / `the_content` … 投稿保存・表示時の加工
- 投稿メタ `_crb_item_key` / `_crb_feed_id` / `_crb_source_link` … CRB 取り込み識別

## 再取得コマンド

```powershell
cd D:\dev\custom-rss-builder\crb_helper
python tools\pull_plugintest_plugins.py
```
