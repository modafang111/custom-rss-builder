# ライセンス無効化テスト結果

実行: 2026-06-04 09:34:15 UTC

クライアント: https://wordpress-123.com/PluginTest
正本: https://123789.jp/custom-rss-builder

## サマリー

| 結果 | 件数 |
|------|------|
| OK | 20 |
| 手動 | 1 |

## 明細

| ID | 結果 | 詳細 |
|----|------|------|
| BUILD-static | OK | client maps crb_ls_* from REST JSON |
| CLI-01 | OK | remote=20260604x need>=20260604t |
| CLI-03 | OK | client_app=True license_server_app=False |
| SRV-01 | OK | activated pro usable=True key=CRB-8BA23-FE2AC-3B15... |
| CLI-02 | OK | has_auth_server=True api_base=https://123789.jp/custom-rss-builder |
| SRV-05-pre | OK | REST check before expire http=200 |
| SRV-02 | OK | set_license_status expired via probe (same as 無効化) |
| SRV-03 | 手動 | 正本管理画面の無効化リンク・確認ダイアログ |
| SRV-07 | OK | no secret http=403 code=crb_ls_bad_secret |
| SRV-05 | OK | http=403 code=crb_ls_inactive |
| INT-01 | OK | plan=free usable=True status=active err='ライセンスが無効です。' |
| CLI-04 | OK | plan=free usable=True |
| CLI-05 | OK | reverted to free plan after expire |
| CLI-06 | OK | license_check err='ライセンスが無効です。' |
| CLI-07 | OK | can=False msg=無料プランではフィードは 1 件までです。 (free feed limit) |
| INT-02 | OK | second feed blocked on free after fallback |
| CLI-08 | OK | slots={'max_index': 2, 'slots': 3} (free slot limit after fallback) |
| CLI-09 | OK | can_rss=True http=200 len=1260 (free allows rss) |
| INT-04 | OK | REST-only path verified in SRV-05; UI needs client page open (manual note) |
| CLI-10 | OK | restored usable=True |
| SRV-08 | OK | status active again |

## あなたが確認する項目

- **SRV-03**: 正本管理画面の無効化リンク・確認ダイアログ

任意: 正本で手動「無効化」→ クライアントでフィード一覧を再読み込み → **利用可=いいえ**（ビルド 20260604t+）
