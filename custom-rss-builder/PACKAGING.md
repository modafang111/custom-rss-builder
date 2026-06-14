# Custom RSS Builder — 配布パッケージ（Level 3）

## ZIP の種類

| ファイル | 用途 | インストール先の想定 |
|----------|------|----------------------|
| `dist/custom-rss-builder-client.zip` | フィード・RSS・取り込み | お客様 WordPress |
| `dist/custom-rss-builder-license-server.zip` | キー発行・REST | ライセンス正本サーバー（123789.jp 等） |

どちらも ZIP 内フォルダ名は **`custom-rss-builder`** です（既存インストールの上書き更新に対応）。

## ビルド

リポジトリルート（`plugins/`）で:

```bash
python build_plugin_dist.py
python custom-rss-builder/custom-rss-builder/tools/verify_build_packages.py
```

## 配布 ZIP の既定値

追加のサーバー設定は不要です。ZIP 内で次が固定されます。

| 項目 | client ZIP | authority ZIP |
|------|------------|---------------|
| パッケージ種別 | `client` | `authority` |
| ライセンス正本 UI | 非表示 | 表示 |
| 認証サーバー URL（既定） | `https://123789.jp/custom-rss-builder` | 自サイト |
| Pro スロット上限 | 20 | 20 |
| 認証サーバー接続 | ZIP 内蔵（手入力不要） | 自サイト |

誤って正本 ZIP をクライアントサイトに入れても、キー発行 UI は起動しません。

クライアント ZIP の管理画面「ライセンス」では、組み込み／分離などの用語は出さず、**キーの有効化**を中心に表示します。認証コード（API Secret）は **ZIP ビルド時に正本サーバーから取得して埋め込み** ます（`python tools/fetch_authority_api_secret.py` → `build_plugin_dist.py`）。利用者が「初期設定」で Secret を入力する必要はありません。

## 開発用フルツリー

`custom-rss-builder/custom-rss-builder/` は両方のコードを含みます。FTP デプロイ:

- `python deploy_plugin_ftp.py` — 123789（**authority ZIP と同一内容**）
- `python tools/deploy_client_plugin_ftp.py` — wordpress-123（**client ZIP と同一内容**）

配布物の検証は ZIP ビルドと `verify_build_packages.py` を使います。

## 正本ダウンロードページ（DLM）への client ZIP 配置

お客様向け client ZIP は、正本サイト（123789.jp）の **既存 Download Monitor ページ** から配布します。

| 項目 | 内容 |
|------|------|
| ダウンロードページ | `https://123789.jp/custom-rss-builder/download/695/` |
| 実体 ZIP | `wp-content/uploads/dlm_uploads/.../custom-rss-builder-client.zip` |
| パスワード | 正本 WP「CRB ライセンス設定」→ `mail_download_password` と **同一**（DLM 側のパスワード設定） |
| メール挿入 URL | 同設定画面の `mail_download_url`（共通）にダウンロードページ URL を登録 |

**ZIP 差し替えのみ**（client 本体の PHP 変更なし）のときは `CRB_BUILD_ID` は上げません。PluginTest への FTP デプロイも不要です。

```bash
# リポジトリルート（plugins/）で
python build_plugin_dist.py
python custom-rss-builder/custom-rss-builder/tools/verify_build_packages.py
python tools/update_dlm_client_zip.py
```

`update_dlm_client_zip.py` は `dist/custom-rss-builder-client.zip` を DLM 上の実体とバイト比較し、差分があるときだけ FTP で上書きします。完了後、ブラウザでダウンロードページからパスワード入力 → ZIP 取得を確認してください。

インストール手順固定ページへのダウンロードリンク追加は行いません（ダウンロードページと役割が重複するため）。

### プラグイン削除とライセンス設定

WordPress は **`uninstall.php` が無いと「削除」しても `wp_options` が残ります**。以前 Pro を有効化したサイトでは、ZIP を入れ直すだけで Pro のままになります。

client ZIP には `uninstall.php` があり、削除時に `crb_license_settings` などを消します。**完全な初期状態で試す手順:** 無効化 → **削除** → 再インストール。

### Chrome「安全でないダウンロードがブロックされました」

HTTPS ページ上の Download Monitor ボタンが `http://` になると Chrome がブロックします。正本では `includes/functions-third-party-compat.php` が `home_url` / DLM リンクを https に補正します。それでも出る場合は WordPress「設定 → 一般」の **WordPress アドレス** と **サイトアドレス** を `https://` に直してください。

## バージョン管理（BUILD_ID / ロールバック）

プラグインには **2種類の番号** があります。

| 定数 | 例 | いつ上げるか |
|------|-----|-------------|
| `CRB_VERSION` | `0.9.0` | 顧客向けリリース単位（WordPress の Version ヘッダと一致） |
| `CRB_BUILD_ID` | `20260610r` | **デプロイごと**（管理 UI・キャッシュ bust・検証用） |

`CRB_BUILD_ID` **だけではソースを戻せません**。次の2つをセットで使います。

1. **Git タグ** `build-<CRB_BUILD_ID>` … ソースのスナップショット
2. **`dist/archives/*.zip`** … 実際にデプロイした client 配布物（ロールバック用）

### 初回セットアップ（1回）

```bash
python tools/verify_versioning_setup.py
```

### デプロイ前後の標準手順

```bash
# 1. BUILD_ID を custom-rss-builder.php で更新
python custom-rss-builder/custom-rss-builder/tools/run_all_verify.py

# 2. 変更をコミット（タグの前提）
git add …
git commit -m "…"

# 3. Git タグ（作業ツリーが clean であること）
python tools/tag_build.py

# 4. アーカイブ ZIP を dist/archives/ に保存
python tools/record_build_snapshot.py

# 5. PluginTest へデプロイ（成功時も自動で archive を試みる）
python tools/deploy_client_plugin_ftp.py
```

`deploy_client_plugin_ftp.py` はデプロイ成功後、同 BUILD_ID の archive が無ければ `dist/archives/custom-rss-builder-client-<BUILD_ID>.zip` を自動保存します。

### バージョンダウン（PluginTest）

**配布 ZIP から戻す（推奨・DB は触らない）:**

```bash
python tools/deploy_client_from_archive.py --build-id 20260610r
```

**ソースから戻して再デプロイ:**

```bash
git checkout build-20260610r -- custom-rss-builder/
python tools/deploy_client_plugin_ftp.py
```

### やらないこと

- `CRB_BUILD_ID` の文字列だけ書き換えて「戻したつもり」にする
- ロールバック時に `feed_cleanup` / `license_reset` / 全フィード削除
- `dist/archives/*.zip` を Git にコミット（API Secret 含有のため `.gitignore` 対象）

### 関連スクリプト

| スクリプト | 用途 |
|-----------|------|
| `tools/versioning.py` | BUILD_ID 読取・パス共通 |
| `tools/record_build_snapshot.py` | ZIP を `dist/archives/` へ保存 |
| `tools/tag_build.py` | `git tag build-<BUILD_ID>` |
| `tools/deploy_client_from_archive.py` | アーカイブ ZIP から PluginTest へ復元 |
| `tools/verify_versioning_setup.py` | 上記の存在チェック |

