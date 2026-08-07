# CRB ID Split (DUGA Helper)

Custom RSS Builder 用の**無料補助プラグイン**です。

- 作品 ID（例: `haisetsu-0684`）を `{{a:{%n}}}` / `{{b:{%n}}}` に分割
- `{{player:{%n}}}` でサンプルプレイヤー HTML を出力
- WordPress 上で再生するための簡易 CSS/JS 同梱

本体の Custom RSS Builder には DUGA 専用ロジックは含まれません。
このプラグインは必要なサイトだけに入れてください。

## 要件

- WordPress 5.8+
- Custom RSS Builder（クライアント）が有効

## 使い方（本文テンプレート例）

```html
{{player:{%9}}}
```

`{%9}` には `haisetsu-0684` やジャケット URL など、分割できる値が入っている必要があります。
