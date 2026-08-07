# Custom RSS Builder — 開発ワークスペース

作業ルート: `D:\dev\custom-rss-builder`  
補助プラグイン: `D:\dev\custom-rss-builder\crb_helper`  
元データのヒント: `\\MSI\dev\ai_spec_builder\plugins` / `\\MSI\dev\crb_helper`

現行ソースのビルド ID: **0.9.1.20260717a**（PluginTest 稼働版と一致）

## 対象サイト

| 用途 | URL |
|------|-----|
| 本番 | https://otona-column.com/ |
| テスト（PluginTest） | https://wordpress-123.com/PluginTest/ |
| ライセンス正本・配布 | https://123789.jp/custom-rss-builder/ |

## ディレクトリ

```
D:\dev\custom-rss-builder\          … リポジトリルート（ビルド・FTP スクリプト）
  custom-rss-builder\               … ドキュメント / テストケース / PACKAGING.md
    custom-rss-builder\             … WordPress プラグイン本体（フルツリー）
  tools\                            … client デプロイ・DLM・補助 ZIP 公開
  dist\                             … 配布 ZIP 出力
  crb_helper\                       … 補助プラグイン開発（ID Split / Random Intro 等）
    helpers_new\  env\  tools\  docs\  site_snapshot\
  deploy.local.json                 … FTP 認証（gitignore・コミット禁止）
  build_plugin_dist.py
  deploy_plugin_ftp.py              … 123789 正本（authority）
```

## よく使うコマンド

```powershell
cd D:\dev\custom-rss-builder

# client / authority ZIP をビルド
python build_plugin_dist.py
python custom-rss-builder\custom-rss-builder\tools\verify_build_packages.py

# テストサイト（PluginTest）へ client 相当を FTP
python tools\deploy_client_plugin_ftp.py

# ライセンス正本（123789）へ authority 相当を FTP
python deploy_plugin_ftp.py

# DLM のお客様向け client ZIP を差し替え
python tools\update_dlm_client_zip.py
```

補助側:

```powershell
cd D:\dev\custom-rss-builder\crb_helper
python tools\pull_plugintest_plugins.py
```

## 接続情報

- FTP: `deploy.local.json`（host/user/password。remote_path は参考値。各デプロイスクリプトがパスを決定）
- PluginTest REST: `crb_helper\env\wp.local.json`
- 詳細パッケージ仕様: `custom-rss-builder\PACKAGING.md`

## 復元メモ（この PC）

1. `\\MSI\dev\ai_spec_builder\plugins` → `D:\dev\custom-rss-builder` へ同期
2. `\\MSI\dev\crb_helper` → `D:\dev\custom-rss-builder\crb_helper` へ同期
3. Git remote: `https://github.com/modafang111/custom-rss-builder.git`
