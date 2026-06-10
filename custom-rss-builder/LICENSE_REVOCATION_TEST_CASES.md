# ライセンス無効化 — サーバー・クライアント テストケース

正本（123789.jp）で **無効化** したあと、クライアント（wordpress-123.com）が **Pro のまま** になる不具合の切り分け用です。

| 環境 | URL 例 |
|------|--------|
| 正本 | `https://123789.jp/custom-rss-builder` |
| クライアント | `https://wordpress-123.com/PluginTest` |

**前提:** クライアントは `接続=リモート`、正本の **API Secret** を保存済み、Pro キーで **利用可=はい** になっていること。

**方針（20260606n 以降）:** 正本でキーを無効化すると、クライアントは **`usable=false`** になります。プラン表示はキャッシュ上 **Pro のまま** になることがありますが、**利用可=いいえ** なら無効化は反映済みです。自動的に無料キーへ切り替え（issue-free）は **行いません**。

---

## A. サーバー側（正本・ライセンス管理）

| ID | 操作 | 期待結果 | 確認方法 |
|----|------|----------|----------|
| SRV-01 | ライセンス一覧で対象 Pro キーの **サイト URL** を確認 | クライアントの正規化 URL と一致（例: `https://wordpress-123.com/PluginTest`） | 一覧の「サイト」列 |
| SRV-02 | 対象行の **無効化** をクリックし確認 | ステータスが **expired**（または cancelled） | 一覧のステータス列 |
| SRV-03 | 無効化直後、同じキーで **有効化** リンクが出る／再発行が必要な状態 | active に戻らない | 一覧 UI |
| SRV-04 | （任意）DB／管理画面で `status` フィールド | `expired` 等、`active` ではない | 正本のみ管理者 |
| SRV-05 | REST `check` を Secret 付きで手動 POST（下記 curl 相当） | HTTP **403**、JSON `code` = **`crb_ls_inactive`**、`message` に「無効」 | `tools/probe_client_license_rest.py` または Postman |
| SRV-06 | 別サイト URL で `check` | `crb_ls_site_mismatch`（409） | site_url を故意にずらす |
| SRV-07 | Secret なしで `check` | `crb_ls_bad_secret` または Secret 案内（**inactive ではない**） | Secret ヘッダ省略 |
| SRV-08 | 無効化後に **有効** に戻す（テスト後片付け） | `status=active`、クライアントで再確認で Pro 復帰 | 手動または SRV-09 用 |

**SRV-05 期待 JSON 例（403）:**

```json
{
  "code": "crb_ls_inactive",
  "message": "ライセンスが無効です。",
  "data": { "status": 403 }
}
```

---

## B. クライアント側（ライセンス画面・機能）

| ID | 操作 | 期待結果（`20260604t` 以降） | NG の典型 |
|----|------|------------------------------|-----------|
| CLI-01 | ビルド確認 | ライセンス画面またはフィード画面に **`20260604t`** 以上 | 古いビルドのまま FTP 未反映 |
| CLI-02 | **認証サーバー** URL | 123789.jp の正本 URL | 空・別ドメイン |
| CLI-03 | **接続** | 「リモート」相当（組み込み正本と別サイト） | 誤って同一 DB 組み込みモード |
| CLI-04 | 正本で SRV-02 の **後**、クライアント **フィード一覧を再読み込み** | 画面上部の利用状態が **利用可=いいえ**（または Pro 制限メッセージ） | **利用可=はいのまま** → `20260604s` 以前の 403 誤処理 |
| CLI-05 | **ライセンス**画面を開く | **状態を再確認** 不要でも同期済み。メッセージに「無効」系 | プラン表示が Pro のままでも **利用可=いいえ** なら CLI-04 は合格 |
| CLI-06 | **状態を再確認** ボタン | 利用可=いいえ、エラーに「ライセンスが無効」 | 403 Secret 案内のみ → Secret 不一致 |
| CLI-07 | **2 件目のフィードを新規作成** | 拒否（無料上限メッセージ） | 作成できる → usable が true のまま |
| CLI-08 | 既存 Pro フィードで **{%4%}** スロットを保存 | 拒否または 3 スロットにクランプ | 4 スロット目が保存できる |
| CLI-09 | Pro フィードの **RSS URL** をブラウザで開く | 200 でもよいが、新規 Pro 操作は不可。または管理画面警告 | 制限なしで運用できる |
| CLI-10 | （復旧）SRV-08 で active に戻し、CLI-06 **再確認** | 利用可=はい、2 件目フィード作成可 | — |

**表示の注意（CLI-05）:** プラン欄が **Pro** のまま**でも**、**利用可=いいえ** なら無効化は反映済み。Pro「有効のまま」に見えるのはプラン表示だけのことがある。

---

## C. 結合（正本操作 → クライアント反映）

| ID | 手順 | 合格条件 |
|----|------|----------|
| INT-01 | SRV-02 → 待たずに CLI-04 | **1 リクエスト以内**で利用可=いいえ |
| INT-02 | SRV-02 → CLI-07 | 2 件目フィード不可 |
| INT-03 | SRV-07（Secret 誤り）→ CLI-06 | usable は **落とさない**（一時エラー）。Secret 修正後に再確認で復帰 |
| INT-04 | SRV-05（REST のみ）→ クライアントは触らない | REST だけ 403 inactive。クライアント画面は **INT-01 まで開かないと変わらない**（プッシュなし） |
| INT-05 | 自動テスト `run_client_acceptance_tests.py` の **P-31 / F-42** | `license_state.usable === false`（check がエラーでも state を必ず見る） |

---

## D. 既知バグとテストの落とし穴（`20260604s` 以前）

| 現象 | 原因 | テストでの見え方 |
|------|------|------------------|
| 無効化しても Pro のまま | 正本が 403 + `crb_ls_inactive` を返すが、クライアントが `crb_license_http_403` とみなし **usable を更新しない** | CLI-04 NG。再確認でメッセージだけ「無効」、利用可ははい |
| 自動テストが PASS なのに手動 NG | F-42 / P-31 が `license_check` の **例外だけ**で合格していた | INT-05 で state を必須化 |

---

## E. 実行コマンド（開発者向け）

```bash
# 無効化専用（結果 → LICENSE_REVOCATION_TEST_RESULTS.md）
python custom-rss-builder/custom-rss-builder/tools/run_revocation_tests.py

# クライアントへデプロイ（ビルド一致確認）
python tools/deploy_client_plugin_ftp.py

# 正本 REST プローブ（Secret を引数に）
python custom-rss-builder/custom-rss-builder/tools/probe_client_license_rest.py YOUR_SECRET

# クライアント受け入れ（ライセンス含む・テスト用キー発行・フィード削除あり）
python custom-rss-builder/custom-rss-builder/tools/run_client_acceptance_tests.py
```

**注意:** 自動テストは PluginTest 上で `license_reset` / テスト用キー発行を行います。本番キーでの確認は §F の手動項目で行ってください。

---

## F. チェックリスト（手動・1 回 15 分）

1. [ ] SRV-01〜02 実施  
2. [ ] SRV-05 で `crb_ls_inactive` を確認  
3. [ ] CLI-01 で `20260604t` 以上  
4. [ ] CLI-04〜08 で Pro 機能が止まる  
5. [ ] SRV-08 + CLI-10 で復旧  

不合格のときは **CLI-01 のビルド** と **SRV-05 の JSON code** をメモして共有してください。
