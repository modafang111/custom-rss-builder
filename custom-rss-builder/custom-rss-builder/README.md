# Custom RSS Builder

WordPressプラグイン。RSS非対応Webページから Feed43風パターンで内容を抽出し、RSS 2.0フィードとして配信します。

## できること（v0.1.0）

- 管理画面でフィード設定（URL、抽出テンプレート、RSS要素割り当て）
- 対象ページHTMLの取得とプレビュー
- Feed43風 `{%}` / `{*}` パターンによる抽出
- RSS 2.0 XMLの生成と `/feed/custom-rss/{id}/` での配信
- フィード設定の保存・削除・手動更新

## インストール

1. `custom-rss-builder` フォルダを `wp-content/plugins/` に配置
2. WordPress管理画面 → プラグイン → **Custom RSS Builder** を有効化
3. 左メニュー **Custom RSS Builder** からフィードを作成

ZIP化する場合:

```bat
cd C:\ai_spec_builder\plugins
powershell Compress-Archive -Path custom-rss-builder -DestinationPath custom-rss-builder.zip -Force
```

## 使い方

1. **新規フィード作成** をクリック
2. フィード名、対象URL、抽出テンプレートを入力
3. **プレビュー** で抽出結果を確認
4. **保存** 後、表示された RSS URL を RSSリーダーに登録
5. 更新したいときは **手動更新**

### テンプレート例

```text
<div class="news">{*}<a href="{%}">{%}</a>{*}<span class="date">{%}</span>{*}
```

- `{%}` … 抽出したい可変部分
- `{*}` … 読み飛ばす部分

### 割り当てインデックス

`{%}` の出現順に 0, 1, 2... と番号が付きます。例では `[0]=リンク`, `[1]=タイトル`, `[2]=日付`。

## フォルダ構成

```text
custom-rss-builder/
  custom-rss-builder.php
  includes/
  admin/
  public/
  assets/
```

## 注意

- JavaScriptレンダリングが必要なページには未対応
- 抽出精度は対象HTMLの構造に依存します
- 対象サイトへの過剰アクセスを避けるため、HTMLは1時間キャッシュされます
- プラグイン有効化後に RSS URL が 404 になる場合、**設定 → パーマリンク** を開いて保存し直してください

## 開発元

設計書: `outputs/devdoc_20260524_161939_509218/`
