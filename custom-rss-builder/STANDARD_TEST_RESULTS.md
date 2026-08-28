# Standard plan acceptance test run

Generated: 2026-07-03T08:06:20.681826+00:00
Client: https://wordpress-123.com/PluginTest
Client build: 20260629b
Standard key (last 8): ...D1-5E256

> 既存フィードは削除していません。`CRB_STD_ACCEPT_*` のみクリーンアップ済み。
> 終了時プラン: **スタンダード**（手動テスト継続用）

| ID | Result | Detail |
|----|--------|--------|
| C-04 | OK | api secret available |
| S-01 | OK | plan=standard usable=True feeds 2->2 |
| S-02 | OK | activated via license_activate |
| S-03 | OK | state={'plan': 'standard', 'status': 'active', 'usable': True, 'message': '', 'license_key |
| S-04 | OK | plan comparison table |
| S-07 | OK | setup service panel for paid plan |
| S-08 | OK | recheck ok |
| S-05 | SKIP | manual: free plan CTA |
| S-06 | OK | standard CTA count=0 (upgrade form=Pro) |
| S-09 | OK | authority bind site=https://wordpress-123.com/PluginTest |
| S-10 | OK | feed_id=4 |
| S-11 | SKIP | already >=2 feeds |
| S-12 | SKIP | already >=3 feeds |
| S-13 | OK | スタンダードプランではフィードは 3 件までです。 |
| S-14 | OK | edit save ok |
| S-15 | OK | {'max_index': 4, 'slots': 5} |
| S-16 | OK | slot4 save ok |
| S-17 | OK | slot4 kept='.slot4-test' |
| S-18 | OK | license screen mentions standard plan |
| S-20 | OK | items=3 |
| S-21 | OK | extract ok |
| S-22 | OK | import_preview err=None |
| S-23 | OK | http=200 len=1098 |
| S-24 | OK | no free credit in RSS |
| S-30 | SKIP | manual import UI check |
| S-31 | SKIP | manual post credit check |
| S-32 | OK | {'plan_min_hours': 1, 'slug_1h': 'crb_every_1_hours', 'slug_24h': 'crb_every_24_hours'} |
| S-33 | OK | slug_24h=crb_every_24_hours |
| S-34 | OK | min 1h for standard (not 24h free lock) |
| S-35 | OK | http=500 |
| S-40 | OK | ai_transform can=False |
| S-42 | OK | import_tag_sources can=False |
| S-41 | OK | AI section shows pro guidance |
| S-43 | OK | same as S-40 |
| S-24b | OK | free_credit not required on standard |
| S-50 | OK | このライセンスは別のサイトで既に有効化されています。 |
| S-52 | OK | usable=False after expire |
| S-53 | OK | 認証サーバーに接続できませんでした。しばらくしてから再度お試しください。解決しない場合は販売元までお問い合わせください。 |
| S-54 | OK | feeds preserved count=3 |
| S-51 | SKIP | key swap detail manual |
| S-60 | OK | feeds preserved after standard activation |
| S-61 | OK | 5 slots available |
| S-62 | OK | 1h schedule allowed |
| S-63 | SKIP | standard to Pro manual |
| S-64 | SKIP | standard to free manual |
| S-65 | SKIP | credit restore manual |
| S-70 | OK | http=200 |
| S-71 | OK | standard payment link on LP |
| S-72 | SKIP | standard key email manual |

## Summary

- OK: 38
- NG: 0
- SKIP (手動): 10

## 手動確認推奨

- S-05: 無料プラン時の申込 CTA
- S-30 / S-31: 手動取り込み・投稿クレジット
- S-63〜S-65: Pro/無料への移行
- S-72: スタンダードキーメール
- ライセンス画面の見た目（S-06 補足）
