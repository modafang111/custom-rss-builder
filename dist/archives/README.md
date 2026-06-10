# Build archives (local rollback)

このディレクトリには **デプロイ成功時** または `python tools/record_build_snapshot.py` で保存した ZIP が入ります。

| ファイル例 | 内容 |
|-----------|------|
| `custom-rss-builder-client-20260610r.zip` | PluginTest 向け client 配布物 |
| `custom-rss-builder-license-server-20260610r.zip` | 正本サーバー向け（`--authority` 指定時） |
| `manifest.jsonl` | 保存履歴（1行1 JSON） |

## ロールバック（PluginTest）

```bash
python tools/deploy_client_from_archive.py --build-id 20260610r
```

## ソースから戻す（Git タグ）

```bash
git checkout build-20260610r -- custom-rss-builder/
python tools/deploy_client_plugin_ftp.py
```

ZIP と Git タグは **別物** です。確実なファイル復元は ZIP、開発の巻き戻しはタグを使います。

**注意:** client ZIP には API Secret が埋め込まれます。Git にはコミットしません（`.gitignore` 済み）。
