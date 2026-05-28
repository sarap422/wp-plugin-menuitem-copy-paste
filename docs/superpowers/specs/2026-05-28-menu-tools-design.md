# 設計: メニューツール機能追加（ショートコード / メニュー複製 / エクスポート / インポート）

- **タスク**: 0528.1
- **日付**: 2026-05-28
- **対象**: MenuItem Copy & Paste v1.0.7 →（機能追加）v1.1.0
- **ステータス**: 設計承認済み（実装前）

## 1. 目的

メニュー系プラグインを4つ（MenuItem Copy & Paste / Duplicate Menu / WPS Menu Exporter / Shortcode in Menus）併用している状態を解消し、本プラグイン単体で以下を実現する。

1. メニュー項目（カスタムリンク等）の URL / ラベル / 説明 でショートコードを使えるようにする
2. メニュー項目だけでなく、**メニュー自体**を複製できるようにする
3. **メニュー単位**でのエクスポート（WPS Menu Exporter は全メニュー一括のみ）
4. エクスポート形式を XML ではなく **JSON** にし、インポート（別サイトへの移行・テンプレート化メニューの取込）も可能にする

## 2. スコープ

### In Scope
- 機能1: フロント/保存時のショートコード実行（URL・ラベル・説明）
- 機能2: メニュー自体の複製（保存済み状態を複製、階層保持、名前自動採番）
- 機能3: 選択中メニューの JSON エクスポート
- 機能4: JSON インポート（新メニューとして作成、post_type/taxonomy は slug 優先で再解決）

### Out of Scope
- 専用「Shortcode」メタボックス（`gs_sim` 相当のカスタム object type）→ **作らない**
- 全メニュー一括エクスポート（メニュー単位のみ）
- DB プリセット保存・サーバ側でのファイル保管
- WXR(XML) フォーマット対応（JSON のみ）
- メニュー位置（theme location）割当の移行（将来検討）

## 3. 重要な技術前提（slug と ID）

WordPress のメニュー項目は内部的に **数値 ID 基準**で保存される（WXR エクスポートも同様）。

```
_menu_item_type      = taxonomy
_menu_item_object    = category
_menu_item_object_id = 895          ← slug ではなく数値 ID
```

フロントで `…/category/aicoding/` のように slug 入り URL が出るのは、表示時に `object_id` をパーマリンクへ解決しているため。保存実データは ID。

→ 別サイトへ移すと ID は食い違うため、**インポート時に slug で移行先を再解決する必要がある**。テンプレート化メニュー（複数サイトでページ slug が共通）の移行はこれで機能する。

## 4. アーキテクチャ / ファイル構成

単一ファイルを `includes/` へ責務分割。既存の項目操作ロジックは**移設のみ（挙動不変）**。

```
menuitem-copy-paste/
├─ menuitem-copy-paste.php              # ブートストラップ（ヘッダ/定数/require/初期化）
├─ includes/
│   ├─ class-menuitem-item-actions.php  # 既存5 AJAX（copy/paste/clone/delete/new）＋既存JS/CSS enqueue ※移設のみ
│   ├─ class-menuitem-shortcode.php     # 機能1：ショートコード処理（admin/front両ロード）
│   ├─ class-menuitem-tools.php         # 機能2-4：複製/エクスポート/インポートのAJAX＋tools用enqueue
│   └─ class-menuitem-builder.php       # 共通：正規化アイテム配列→メニュー項目生成（親ID再マップ）＋名前採番
├─ css/menuitem-copy-paste.css          # 既存＋ツールボタン用スタイル追記
├─ js/
│   ├─ menuitem-copy-paste.js           # 既存（6ボタン＋折りたたみ）変更なし
│   └─ menuitem-menu-tools.js           # 新規：複製ボタン注入 / Export download / Import file読込
└─ readme.txt
```

- フック接頭辞 `menucoam_`、権限 `edit_theme_options`、ツール用 nonce `menucoam_menu_tools`。
- `class-menuitem-builder.php` が中核：**「正規化アイテム配列＋親の一時ID」→ menu_order 順に生成しつつ old_id→new_id マップで親子を再リンク**。複製（DB 由来）とインポート（JSON 由来）の両方が共用（DRY）。

### 正規化アイテム（builder の入力スキーマ）
```
[
  'temp_id'        => int,    // 一時ID（親リンク用）
  'parent_temp_id' => int,    // 0 = トップレベル
  'menu_order'     => int,
  'title'          => string,
  'type'           => 'custom'|'post_type'|'taxonomy',
  'object'         => string, // page|post|category|<cpt>|<tax>
  'object_id'      => int,    // 解決済み（importはslug解決後、duplicateはそのまま）
  'url'            => string,
  'target'         => string,
  'attr_title'     => string,
  'classes'        => string[],
  'xfn'            => string,
  'description'    => string,
]
```
builder メソッド案:
- `create_menu_items( int $menu_id, array $normalized_items ) : array`（old→new マップ返却）
- `unique_menu_name( string $base ) : string`（`{base}` → 衝突時 `{base}-2,-3…`）

## 5. 機能1: ショートコード（`class-menuitem-shortcode.php`）

admin / front 両方でロード（保存時の URL 温存が admin 側でも必要なため）。Shortcode in Menus の実績ある方式を踏襲。

| フック | 優先度/引数 | 役割 |
|---|---|---|
| `walker_nav_menu_start_el` | 20 / 4 | 出力全体に `do_shortcode()`（ラベル＋テーマ描画の説明をカバー）。`strpos($out,'[')` で早期スキップ |
| `clean_url`（context=`display`）| 1 / 3 | URL 内ショートコードを `do_shortcode()` で実 URL へ展開 |
| `clean_url`（context=`db`）| 99 / 3 | 保存時、ショートコードを含む URL を生のまま温存（`esc_url` による `[ ]` 破壊を回避）。`has_shortcode()` ガード |

- `has_shortcode()`: `get_shortcode_regex()` ベースの内部ヘルパー。
- ショートコード自体はテーマ定義のものを**実行するだけ**（新規定義しない）。
- 機能4 との連携: db 温存フィルタにより、インポートで `wp_update_nav_menu_item` が URL を保存しても `[topthemeurl]` 等が壊れない。
- 制約: 説明欄のショートコードは「テーマがメニューの description を描画する場合のみ」表示。
- セキュリティ: db 温存はサイト全体の `esc_url('db')` に作用する広域フィルタだが、`has_shortcode` ガードで `[...]` を含む URL のみ対象。メニュー編集権限者前提（参照プラグインと同方式）。

## 6. 機能2: メニュー複製（`class-menuitem-tools.php` + builder）

UI: 「メニューを保存」隣に「メニューを複製」ボタンを JS で `#update-nav-menu` フッターへ注入。

動作（堅牢性重視で確定）:
1. クリック → `confirm("保存済みの内容を複製します。未保存の変更があれば先に『メニューを保存』してください。続けますか？")`
2. AJAX `menucoam_duplicate_menu(menu_id)`
3. サーバ: `wp_get_nav_menu_items(source_id)`（menu_order 順）→ 正規化（object_id はそのまま＝同一サイト）→ builder で生成、親 ID 再マップ
4. 名前: `{元名}_copied`、衝突時 `_copied-2,-3…`（`get_term_by('name',…,'nav_menu')` でチェック）
5. 新メニュー ID を返す → JS が新メニューへリダイレクト

> 判断記録: 「保存POSTに相乗りして自動保存→複製」案は WP コアの保存後リダイレクト挙動（バージョン差あり）に依存し壊れやすいため不採用。確認ダイアログ＋AJAX で保存済み状態を複製する確実な方式を採用。

## 7. 機能3: エクスポート（`class-menuitem-tools.php`）

UI: 画面右上に「Export Menu」ボタンを JS で注入（メニュー選択行付近、BSR-Plural 風）。

動作:
1. クリック → 選択中メニュー ID で AJAX `menucoam_export_menu`
2. サーバが JSON 構造を組んで返却（DB 保存済み状態）
3. JS が `Blob` 化して `menu-{slug}-YYYYMMDDHHMMSS.json` をダウンロード
- 注記: 出力は保存済み状態（ツールチップに明記、毎回 confirm は出さない）。

### JSON スキーマ（version 1）
```json
{
  "format": "menuitem-copy-paste-menu",
  "version": 1,
  "exported_at": "2026-05-28T17:55:00+09:00",
  "source_site": "https://code-plus.jp/gp",
  "menu": { "name": "headmenu1", "slug": "headmenu1" },
  "items": [
    {
      "id": 6264, "parent": 0, "menu_order": 1,
      "title": "<img src=\"[topthemeurl]/img/hdrCkaZ-menu_txt1.svg\" alt=\"TOP\" />",
      "type": "post_type",
      "object": "page",
      "object_id": 123,
      "object_slug": "concept",
      "object_path": "concept",
      "url": "", "target": "", "attr_title": "",
      "classes": ["tabs-item"], "xfn": "", "description": ""
    }
  ]
}
```
- `id`/`parent`: 一時ID（階層復元専用）。インポートで DB ID としては使わない。
- 取得方法: post_type → `get_post()->post_name` ＋ `get_page_uri()`、taxonomy → `get_term()->slug`。
- カスタムリンクは `type=custom` ＋ `url`（ショートコード verbatim）。

## 8. 機能4: インポート（`class-menuitem-tools.php` + builder）

UI: 右上に「Import Menu」ボタン＋隠し `<input type="file" accept=".json">`。

動作:
1. クリック → ファイル選択 → FileReader 読込 → クライアント一次検証（拡張子 / parse / `items` 存在）
2. AJAX `menucoam_import_menu(jsonString)`
3. サーバ二次検証（is_string / 上限長 / `json_decode` / `items` 配列）
4. メニュー名決定（`menu.name`、衝突時採番）→ `wp_create_nav_menu()`
5. 各項目を menu_order 順に**オブジェクト解決**:
   - `custom` → `object_id=0`、`url` 温存
   - `post_type` 階層（page 等）→ `get_page_by_path(object_path, OBJECT, object)`、非階層 → `get_posts(['name'=>slug,'post_type'=>object])`
   - `taxonomy` → `get_term_by('slug', object_slug, object)`
   - 発見 → 現地 ID に貼替 / **未発見 → `object_id=0`（未解決＝無効項目として取込、誤リンク防止）** ＋ 未解決リスト収集
6. builder で生成（親 ID 再マップ）
7. レスポンス: `new_menu_id`, `imported`, `unresolved[]`（title/type）→ JS が「X件取込／Y件リンク未解決（要確認）」通知 → 新メニューへリダイレクト

- HTML 温存: ラベル等の HTML（`<img>`/`<ruby>`）は過剰サニタイズせず WP 標準のメニュー保存経路に委ねる（権限者なら HTML 保持）。

## 9. エラーハンドリング設計

### AJAX 共通
全 AJAX で `check_ajax_referer` ＋ `current_user_can('edit_theme_options')` ＋ 入力検証 ＋ `is_wp_error` ＋ `wp_send_json_error/success`。

### インポート（クライアント＋サーバ二重）
| エラー | メッセージ（例） | 対応 |
|---|---|---|
| 非 .json | "JSON ファイルを選択してください。" | 中止 |
| parse 失敗 | "JSON の解析に失敗しました。" | 中止 |
| `items` 欠落 | "ファイル形式が不正です（items がありません）。" | 中止 |
| items 空 | "取り込む項目がありません。" | 中止 |
| サイズ超過 | "ファイルが大きすぎます。" | 中止 |
| メニュー作成失敗 | "メニューの作成に失敗しました。" | 中止 |
| slug 未解決（項目単位） | （致命ではない）未解決リストに収集し通知 | 続行 |

### 複製
- AJAX 失敗時はメッセージ表示。サーバで WP_Error 全段ハンドリング。

### 非破壊性
複製/インポートとも**新規メニュー/項目を作るだけ**。既存メニュー・DB スキーマは無変更。

## 10. テスト計画

### 静的
- 全 PHP: `php -l`
- 全 JS: `node --check`

### 実機（CODE-PLUS / グランフォーレ）
- 機能1: URL＋ラベルの `[topthemeurl]` がフロントで実 URL/画像へ展開。説明はテーマ描画時のみ。
- 機能2: 複製→`{name}_copied`、階層保持、新メニュー遷移、衝突採番、確認ダイアログ挙動。
- 機能3: Export→JSON 構造・slug 記録を確認。
- 機能4: 同一サイト取込（同 slug→正しく再解決）、別サイト取込（granfore A→B、slug 一致で解決・階層保持・ラベル/ショートコード保持）、slug 不一致（未解決=object_id 0）、ラウンドトリップ（export→import 一致）。
- リグレッション: 既存6ボタン（New/Clone/Copy/Paste/Delete/Collapse）＋折りたたみ＋ドラッグが無傷。

### エッジ
空メニュー / 3階層以上 / 特殊文字・ショートコード入り URL / classes 配列 / target=_blank。

## 11. ロールバック計画
- 全変更を git 管理。
- DB は追加のみ（破壊なし、スキーマ変更なし）。
- 問題時は `git revert` ＋ テスト用に作成したメニューを削除。

## 12. 既存規約との整合
- バージョン文字列を本体と同期しキャッシュバスト（PATTERNS 準拠）。
- `nav-menus.php` 限定 enqueue（PATTERNS 準拠）。
- フラット DOM + depth クラスは複製/エクスポートでは無関係（サーバ側は DB の `menu_item_parent`/`menu_order` を使用）。
- AJAX プレフィックス `menucoam_`、2スペースインデント、日本語コメント。
