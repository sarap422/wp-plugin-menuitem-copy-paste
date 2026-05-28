# Menu Tools (Shortcode / Duplicate / Export / Import) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add shortcode support, whole-menu duplication, and per-menu JSON export/import to the MenuItem Copy & Paste plugin so it replaces Duplicate Menu, WPS Menu Exporter and Shortcode in Menus (v1.0.7 → v1.1.0).

**Architecture:** Split the single-file plugin into a bootstrap + `includes/` classes. A shared `Menuitem_CP_Builder` rebuilds menu items from a normalized array (parent-ID remapping), reused by both Duplicate (DB source) and Import (JSON source). Shortcodes run via frontend/save filters (no admin metabox). Duplicate/Export/Import are AJAX, with UI buttons injected into `nav-menus.php` by JS. Import resolves `post_type`/`taxonomy` items by slug on the target site.

**Tech Stack:** WordPress plugin (PHP 7.4+, jQuery). No automated test framework exists in this project — verification is `php -l` (PHP syntax), `node --check` (JS syntax) and a manual browser checklist on real WordPress (CODE-PLUS / グランフォーレ). This matches the project's existing practice and the user's Task 0528.1.3 test definition.

**Commit policy:** This project commits once at the end (user's Task 0528.1.4: `git add . && git commit && git push`). Therefore tasks below end with a **syntax-check verification**, NOT a per-task `git commit`. Do not commit until Task 0528.1.4.

**Reference (read before coding):**
- Existing plugin: `menuitem-copy-paste/menuitem-copy-paste.php` (current single-file class `Menuitem_Copy_Paste`)
- Design spec: `docs/superpowers/specs/2026-05-28-menu-tools-design.md`
- Patterns: `logs/PATTERNS.md`
- Shortcode approach reference: Shortcode in Menus (`clean_url` + `walker_nav_menu_start_el`)

---

## File Structure (after this plan)

```
menuitem-copy-paste/
├─ menuitem-copy-paste.php              # MODIFIED → bootstrap only (header/constants/require/init)
├─ includes/
│   ├─ class-menuitem-item-actions.php  # NEW (moved existing class Menuitem_Copy_Paste, paths/version fixed)
│   ├─ class-menuitem-shortcode.php     # NEW — Feature 1
│   ├─ class-menuitem-builder.php       # NEW — shared item builder + name uniquifier
│   └─ class-menuitem-tools.php         # NEW — Features 2-4 (enqueue + 3 AJAX)
├─ css/menuitem-copy-paste.css          # MODIFIED (tools button styles appended)
├─ js/
│   ├─ menuitem-copy-paste.js           # UNCHANGED
│   └─ menuitem-menu-tools.js           # NEW — duplicate/export/import UI
└─ readme.txt                          # MODIFIED (stable tag + changelog)
```

Constants (defined in bootstrap, used everywhere): `MENUCOAM_VERSION`, `MENUCOAM_PATH`, `MENUCOAM_URL`.

---

## Task 1: Scaffold bootstrap + move existing class + version bump

**Files:**
- Modify: `menuitem-copy-paste/menuitem-copy-paste.php` (becomes bootstrap)
- Create: `menuitem-copy-paste/includes/class-menuitem-item-actions.php` (existing class, relocated)

- [ ] **Step 1: Create the relocated item-actions file**

Move the **entire existing `class Menuitem_Copy_Paste { ... }`** body (lines 23–457 of the current `menuitem-copy-paste.php`, i.e. constructor + `enqueue_admin_scripts` + `copy_menu_item` + `get_all_descendants` + `paste_menu_item` + `clone_menu_item` + `delete_menu_item` + `add_new_custom_link`) verbatim into a new file `menuitem-copy-paste/includes/class-menuitem-item-actions.php`, wrapped as below. Do NOT change any handler logic. Only the file header guard and the `enqueue_admin_scripts` method change (next step).

```php
<?php
/**
 * Menu item actions (Copy / Paste / Clone / Delete / New) — existing feature, relocated.
 *
 * @package menuitem-copy-paste
 */

if (!defined('ABSPATH')) {
  exit;
}

class Menuitem_Copy_Paste {
  // ... paste the existing constructor and all existing methods here, UNCHANGED ...
}
```

- [ ] **Step 2: Fix asset paths and version in `enqueue_admin_scripts`**

Because the file moved into `includes/`, `__FILE__`-relative `plugins_url()` paths would now resolve to `includes/js/...`. Replace them with the `MENUCOAM_URL` constant, and use `MENUCOAM_VERSION` for cache-busting. Inside the moved `enqueue_admin_scripts`, change exactly these:

```php
    wp_enqueue_script(
      'menuitem-copy-paste',
      MENUCOAM_URL . '/js/menuitem-copy-paste.js',
      array('jquery', 'jquery-ui-sortable'),
      MENUCOAM_VERSION,
      true
    );

    wp_localize_script('menuitem-copy-paste', 'menuCopyPaste', array(
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('menuitem-copy-paste-nonce')
    ));

    wp_enqueue_style(
      'menuitem-copy-paste',
      MENUCOAM_URL . '/css/menuitem-copy-paste.css',
      array(),
      MENUCOAM_VERSION
    );
```

(The `nav-menus.php` hook guard at the top of the method stays unchanged. The nonce action string stays `menuitem-copy-paste-nonce` so the existing JS keeps working.)

- [ ] **Step 3: Rewrite the main plugin file as bootstrap**

Replace the entire contents of `menuitem-copy-paste/menuitem-copy-paste.php` with:

```php
<?php

/**
 * Plugin Name: MenuItem Copy & Paste
 * Description: WordPressのメニュー編集画面で項目のコピー、ペースト、複製、削除、折りたたみ、メニュー自体の複製・JSONエクスポート/インポート、ショートコード対応を行えます
 * Version: 1.1.0
 * Author: sarap422
 * Text Domain: menuitem-copy-paste
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package menuitem-copy-paste
 * @author sarap422
 * @license GPL-2.0+
 */

// Direct access prohibited
if (!defined('ABSPATH')) {
  exit;
}

define('MENUCOAM_VERSION', '1.1.0');
define('MENUCOAM_PATH', plugin_dir_path(__FILE__));
define('MENUCOAM_URL', untrailingslashit(plugins_url('', __FILE__)));

require_once MENUCOAM_PATH . 'includes/class-menuitem-builder.php';
require_once MENUCOAM_PATH . 'includes/class-menuitem-item-actions.php';
require_once MENUCOAM_PATH . 'includes/class-menuitem-shortcode.php';
require_once MENUCOAM_PATH . 'includes/class-menuitem-tools.php';

// 既存：項目操作（コピー/ペースト/複製/削除/新規）
new Menuitem_Copy_Paste();

// 機能1：ショートコード（フロント描画＋保存時のURL温存が必要なため常時ロード）
new Menuitem_CP_Shortcode();

// 機能2-4：メニュー複製/エクスポート/インポート（管理画面のみ）
if (is_admin()) {
  new Menuitem_CP_Tools();
}
```

- [ ] **Step 4: Add empty class stubs so the bootstrap loads (temporary, filled in later tasks)**

So `php -l` and a live load don't fatal before later tasks land, create minimal stub files now. They will be fully implemented in Tasks 2–4.

`menuitem-copy-paste/includes/class-menuitem-builder.php`:
```php
<?php
if (!defined('ABSPATH')) { exit; }
class Menuitem_CP_Builder {}
```

`menuitem-copy-paste/includes/class-menuitem-shortcode.php`:
```php
<?php
if (!defined('ABSPATH')) { exit; }
class Menuitem_CP_Shortcode {}
```

`menuitem-copy-paste/includes/class-menuitem-tools.php`:
```php
<?php
if (!defined('ABSPATH')) { exit; }
class Menuitem_CP_Tools {}
```

- [ ] **Step 5: Verify PHP syntax**

Run:
```bash
php -l menuitem-copy-paste/menuitem-copy-paste.php
php -l menuitem-copy-paste/includes/class-menuitem-item-actions.php
php -l menuitem-copy-paste/includes/class-menuitem-builder.php
php -l menuitem-copy-paste/includes/class-menuitem-shortcode.php
php -l menuitem-copy-paste/includes/class-menuitem-tools.php
```
Expected: `No syntax errors detected` for each.

- [ ] **Step 6: Manual regression check (real WP)**

Activate the plugin, open Appearance → Menus. Confirm the existing 6 buttons (New/Clone/Copy/Paste/Delete/Collapse) still appear and work, and collapse state persists. This proves the move + path fix didn't regress.

---

## Task 2: Feature 1 — Shortcode filters

**Files:**
- Modify: `menuitem-copy-paste/includes/class-menuitem-shortcode.php` (replace stub)

- [ ] **Step 1: Implement the shortcode class**

Replace the stub file contents with:

```php
<?php
/**
 * 機能1: メニュー項目（URL/ラベル/説明）でのショートコード実行。
 * Shortcode in Menus のカスタムリンク方式を踏襲（専用メタボックスは作らない）。
 *
 * @package menuitem-copy-paste
 */

if (!defined('ABSPATH')) {
  exit;
}

class Menuitem_CP_Shortcode {

  public function __construct() {
    // ラベル＋（テーマが描画する）説明：出力全体に do_shortcode
    add_filter('walker_nav_menu_start_el', array($this, 'do_shortcode_on_output'), 20, 4);
    // URL 表示時：ショートコードを実URLへ展開
    add_filter('clean_url', array($this, 'expand_shortcode_url'), 1, 3);
    // URL 保存時：ショートコードを含むURLを温存（権限者のみ・wp_loaded後に登録）
    add_action('wp_loaded', array($this, 'maybe_add_save_filter'));
  }

  /**
   * メニュー項目の出力HTML全体にショートコードを適用。
   */
  public function do_shortcode_on_output($item_output, $item, $depth, $args) {
    if (is_string($item_output) && strpos($item_output, '[') !== false) {
      return do_shortcode($item_output);
    }
    return $item_output;
  }

  /**
   * 表示コンテキストのURLに含まれるショートコードを展開。
   */
  public function expand_shortcode_url($url, $orig_url, $context) {
    if ('display' === $context && $this->has_shortcode($orig_url)) {
      return do_shortcode($orig_url);
    }
    return $url;
  }

  /**
   * 保存（db）時のURL温存フィルタを、権限者のときだけ登録。
   */
  public function maybe_add_save_filter() {
    if (current_user_can('edit_theme_options')) {
      add_filter('clean_url', array($this, 'preserve_shortcode_url'), 99, 3);
    }
  }

  /**
   * db コンテキストでショートコード入りURLを esc_url に壊させず生のまま保存。
   */
  public function preserve_shortcode_url($url, $orig_url, $context) {
    if ('db' === $context && $this->has_shortcode($orig_url)) {
      return $orig_url;
    }
    return $url;
  }

  /**
   * 文字列にショートコードが含まれるか（コアの has_shortcode を参考に簡略実装）。
   */
  private function has_shortcode($content) {
    if (is_string($content) && strpos($content, '[') !== false) {
      preg_match_all('/' . get_shortcode_regex() . '/s', $content, $matches, PREG_SET_ORDER);
      return !empty($matches);
    }
    return false;
  }
}
```

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l menuitem-copy-paste/includes/class-menuitem-shortcode.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Manual frontend check (real WP)**

On a menu item, set Navigation Label to `<img src="[topthemeurl]/img/test.jpg" alt="" /><p>TEST</p>` and URL to `[topthemeurl]/location/#x` (assuming the theme defines `[topthemeurl]`). Save. View the front page:
- The `<a href>` resolves to a real URL (shortcode expanded, not `%5Btopthemeurl%5D`).
- The label renders the `<img>` with a resolved `src`.
Expected: both shortcodes expand. (Description only shows if the theme renders menu descriptions.)

---

## Task 3: Shared menu-item builder

**Files:**
- Modify: `menuitem-copy-paste/includes/class-menuitem-builder.php` (replace stub)

- [ ] **Step 1: Implement the builder**

Replace the stub contents with:

```php
<?php
/**
 * 共通: 正規化アイテム配列から新メニューに項目を生成（親ID再マップ）＋メニュー名の一意化。
 * 複製（DB由来）とインポート（JSON由来）の両方で使用。
 *
 * 正規化アイテムのキー:
 *   temp_id, parent_temp_id, menu_order, title, type('custom'|'post_type'|'taxonomy'),
 *   object, object_id, url, target, attr_title, classes(array), xfn, description
 *
 * @package menuitem-copy-paste
 */

if (!defined('ABSPATH')) {
  exit;
}

class Menuitem_CP_Builder {

  /**
   * 正規化アイテム配列から $menu_id に項目を生成する。
   * menu_order 昇順で生成し、親が子より先に作られることを保証。
   *
   * @return array temp_id => 新規DBアイテムID のマップ
   */
  public static function create_menu_items($menu_id, $items) {
    usort($items, function ($a, $b) {
      $ao = isset($a['menu_order']) ? (int)$a['menu_order'] : 0;
      $bo = isset($b['menu_order']) ? (int)$b['menu_order'] : 0;
      return $ao - $bo;
    });

    $id_map = array();

    foreach ($items as $item) {
      $args = array(
        'menu-item-title'  => isset($item['title']) ? $item['title'] : '',
        'menu-item-type'   => isset($item['type']) ? $item['type'] : 'custom',
        'menu-item-status' => 'publish',
      );

      if (!empty($item['url']))        { $args['menu-item-url'] = $item['url']; }
      if (!empty($item['target']))     { $args['menu-item-target'] = $item['target']; }
      if (!empty($item['attr_title'])) { $args['menu-item-attr-title'] = $item['attr_title']; }
      if (!empty($item['classes']))    { $args['menu-item-classes'] = implode(' ', (array)$item['classes']); }
      if (!empty($item['xfn']))        { $args['menu-item-xfn'] = $item['xfn']; }
      if (isset($item['description']) && $item['description'] !== '') {
        $args['menu-item-description'] = $item['description'];
      }

      $type = isset($item['type']) ? $item['type'] : 'custom';
      if ('post_type' === $type || 'taxonomy' === $type) {
        $args['menu-item-object'] = isset($item['object']) ? $item['object'] : '';
        $args['menu-item-object-id'] = isset($item['object_id']) ? (int)$item['object_id'] : 0;
      }

      // 親の再マップ（親が既に生成済みなら新IDを設定）
      $parent_temp = isset($item['parent_temp_id']) ? (int)$item['parent_temp_id'] : 0;
      if ($parent_temp && isset($id_map[$parent_temp])) {
        $args['menu-item-parent-id'] = $id_map[$parent_temp];
      }

      $new_id = wp_update_nav_menu_item($menu_id, 0, $args);
      if (!is_wp_error($new_id)) {
        $temp_id = isset($item['temp_id']) ? (int)$item['temp_id'] : 0;
        if ($temp_id) {
          $id_map[$temp_id] = $new_id;
        }
      }
    }

    return $id_map;
  }

  /**
   * nav_menu に存在しない一意なメニュー名を返す。
   * 例: base 衝突時は "{base}-2", "{base}-3" ...
   */
  public static function unique_menu_name($base) {
    $name = $base;
    $i = 2;
    while (get_term_by('name', $name, 'nav_menu')) {
      $name = $base . '-' . $i;
      $i++;
    }
    return $name;
  }
}
```

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l menuitem-copy-paste/includes/class-menuitem-builder.php`
Expected: `No syntax errors detected`

(Builder behavior is exercised end-to-end by Duplicate (Task 4/5) and Import (Task 4/5 manual checks).)

---

## Task 4: Tools class — enqueue + Duplicate + Export + Import AJAX

**Files:**
- Modify: `menuitem-copy-paste/includes/class-menuitem-tools.php` (replace stub)

- [ ] **Step 1: Implement the full tools class**

Replace the stub contents with:

```php
<?php
/**
 * 機能2-4: メニュー複製 / JSONエクスポート / JSONインポート。
 * UIボタンは nav-menus.php に JS で注入。重い処理はサーバ側AJAX。
 *
 * @package menuitem-copy-paste
 */

if (!defined('ABSPATH')) {
  exit;
}

class Menuitem_CP_Tools {

  const NONCE_ACTION = 'menucoam_menu_tools';
  const JSON_FORMAT  = 'menuitem-copy-paste-menu';
  const JSON_VERSION = 1;
  const MAX_IMPORT_BYTES = 2097152; // 2MB

  public function __construct() {
    add_action('admin_enqueue_scripts', array($this, 'enqueue'));
    add_action('wp_ajax_menucoam_duplicate_menu', array($this, 'ajax_duplicate_menu'));
    add_action('wp_ajax_menucoam_export_menu', array($this, 'ajax_export_menu'));
    add_action('wp_ajax_menucoam_import_menu', array($this, 'ajax_import_menu'));
  }

  public function enqueue($hook) {
    if ('nav-menus.php' !== $hook) {
      return;
    }
    wp_enqueue_script(
      'menuitem-menu-tools',
      MENUCOAM_URL . '/js/menuitem-menu-tools.js',
      array('jquery'),
      MENUCOAM_VERSION,
      true
    );
    wp_localize_script('menuitem-menu-tools', 'menuCoamTools', array(
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonce'   => wp_create_nonce(self::NONCE_ACTION),
      'i18n'    => array(
        'duplicateConfirm' => '保存済みの内容を複製します。未保存の変更があれば先に「メニューを保存」してください。続けますか？',
        'duplicateBtn'     => 'メニューを複製',
        'exportBtn'        => 'Export Menu',
        'importBtn'        => 'Import Menu',
        'noMenu'           => 'メニューを選択してください。',
        'invalidFile'      => 'JSON ファイルを選択してください。',
        'parseError'       => 'JSON の解析に失敗しました。',
        'missingItems'     => 'ファイル形式が不正です（items がありません）。',
        'importedFmt'      => '件を取り込みました。',
        'unresolvedFmt'    => '件はリンク未解決（要確認）です。',
        'genericError'     => 'エラーが発生しました：',
      ),
    ));
  }

  /**
   * 全AJAX共通の nonce + 権限チェック。失敗時は即終了。
   */
  private function verify() {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');
    if (!current_user_can('edit_theme_options')) {
      wp_send_json_error('権限がありません');
    }
  }

  // ============================================================
  // 機能2: メニュー複製
  // ============================================================
  public function ajax_duplicate_menu() {
    $this->verify();

    $menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : 0;
    $menu = wp_get_nav_menu_object($menu_id);
    if (!$menu) {
      wp_send_json_error('メニューが見つかりません');
    }

    $new_name = Menuitem_CP_Builder::unique_menu_name($menu->name . '_copied');
    $new_menu_id = wp_create_nav_menu($new_name);
    if (is_wp_error($new_menu_id)) {
      wp_send_json_error($new_menu_id->get_error_message());
    }

    $items = wp_get_nav_menu_items($menu_id, array('orderby' => 'menu_order'));
    $normalized = array();
    foreach ((array)$items as $it) {
      $normalized[] = $this->normalize_db_item($it);
    }
    Menuitem_CP_Builder::create_menu_items($new_menu_id, $normalized);

    wp_send_json_success(array(
      'new_menu_id' => $new_menu_id,
      'redirect'    => admin_url('nav-menus.php?menu=' . $new_menu_id),
    ));
  }

  /**
   * wp_get_nav_menu_items のアイテムを builder 用の正規化配列へ。
   * 複製は同一サイトなので object_id はそのまま使う。
   */
  private function normalize_db_item($it) {
    return array(
      'temp_id'        => (int)$it->ID,
      'parent_temp_id' => (int)$it->menu_item_parent,
      'menu_order'     => (int)$it->menu_order,
      'title'          => $it->title,
      'type'           => $it->type,
      'object'         => $it->object,
      'object_id'      => (int)$it->object_id,
      'url'            => $it->url,
      'target'         => $it->target,
      'attr_title'     => $it->attr_title,
      'classes'        => (array)$it->classes,
      'xfn'            => $it->xfn,
      'description'    => $it->description,
    );
  }

  // ============================================================
  // 機能3: エクスポート
  // ============================================================
  public function ajax_export_menu() {
    $this->verify();

    $menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : 0;
    $menu = wp_get_nav_menu_object($menu_id);
    if (!$menu) {
      wp_send_json_error('メニューが見つかりません');
    }

    $items = wp_get_nav_menu_items($menu_id, array('orderby' => 'menu_order'));
    $export_items = array();
    foreach ((array)$items as $it) {
      $object_slug = '';
      $object_path = '';
      if ('post_type' === $it->type && $it->object_id) {
        $p = get_post((int)$it->object_id);
        if ($p) {
          $object_slug = $p->post_name;
          $object_path = get_page_uri($p);
        }
      } elseif ('taxonomy' === $it->type && $it->object_id) {
        $t = get_term((int)$it->object_id, $it->object);
        if ($t && !is_wp_error($t)) {
          $object_slug = $t->slug;
        }
      }

      $export_items[] = array(
        'id'          => (int)$it->ID,
        'parent'      => (int)$it->menu_item_parent,
        'menu_order'  => (int)$it->menu_order,
        'title'       => $it->title,
        'type'        => $it->type,
        'object'      => $it->object,
        'object_id'   => (int)$it->object_id,
        'object_slug' => $object_slug,
        'object_path' => $object_path,
        'url'         => $it->url,
        'target'      => $it->target,
        'attr_title'  => $it->attr_title,
        'classes'     => array_values(array_filter((array)$it->classes)),
        'xfn'         => $it->xfn,
        'description' => $it->description,
      );
    }

    wp_send_json_success(array(
      'format'      => self::JSON_FORMAT,
      'version'     => self::JSON_VERSION,
      'exported_at' => current_time('c'),
      'source_site' => home_url(),
      'menu'        => array('name' => $menu->name, 'slug' => $menu->slug),
      'items'       => $export_items,
      'filename'    => 'menu-' . $menu->slug . '-' . current_time('YmdHis') . '.json',
    ));
  }

  // ============================================================
  // 機能4: インポート
  // ============================================================
  public function ajax_import_menu() {
    $this->verify();

    $raw = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
    if (!is_string($raw) || strlen($raw) > self::MAX_IMPORT_BYTES) {
      wp_send_json_error('データが無効です');
    }

    $data = json_decode($raw, true);
    if (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) {
      wp_send_json_error('JSON の解析に失敗しました');
    }
    if (empty($data['items']) || !is_array($data['items'])) {
      wp_send_json_error('ファイル形式が不正です（items がありません）');
    }

    $base_name = (isset($data['menu']['name']) && '' !== $data['menu']['name'])
      ? sanitize_text_field($data['menu']['name'])
      : 'imported-menu';
    $name = Menuitem_CP_Builder::unique_menu_name($base_name);
    $new_menu_id = wp_create_nav_menu($name);
    if (is_wp_error($new_menu_id)) {
      wp_send_json_error($new_menu_id->get_error_message());
    }

    $normalized = array();
    $unresolved = array();
    foreach ($data['items'] as $row) {
      $type   = isset($row['type']) ? $row['type'] : 'custom';
      $object = isset($row['object']) ? $row['object'] : '';
      $object_id = 0;

      if ('post_type' === $type) {
        $object_id = $this->resolve_post($object, $row);
      } elseif ('taxonomy' === $type) {
        $object_id = $this->resolve_term($object, $row);
      }
      if (('post_type' === $type || 'taxonomy' === $type) && !$object_id) {
        $unresolved[] = array(
          'title' => isset($row['title']) ? $row['title'] : '',
          'type'  => $type,
        );
      }

      $normalized[] = array(
        'temp_id'        => isset($row['id']) ? (int)$row['id'] : 0,
        'parent_temp_id' => isset($row['parent']) ? (int)$row['parent'] : 0,
        'menu_order'     => isset($row['menu_order']) ? (int)$row['menu_order'] : 0,
        'title'          => isset($row['title']) ? $row['title'] : '',
        'type'           => $type,
        'object'         => $object,
        'object_id'      => $object_id,
        'url'            => isset($row['url']) ? $row['url'] : '',
        'target'         => isset($row['target']) ? $row['target'] : '',
        'attr_title'     => isset($row['attr_title']) ? $row['attr_title'] : '',
        'classes'        => isset($row['classes']) ? (array)$row['classes'] : array(),
        'xfn'            => isset($row['xfn']) ? $row['xfn'] : '',
        'description'    => isset($row['description']) ? $row['description'] : '',
      );
    }

    Menuitem_CP_Builder::create_menu_items($new_menu_id, $normalized);

    wp_send_json_success(array(
      'new_menu_id' => $new_menu_id,
      'imported'    => count($normalized),
      'unresolved'  => $unresolved,
      'redirect'    => admin_url('nav-menus.php?menu=' . $new_menu_id),
    ));
  }

  /**
   * post_type 項目を移行先サイトの slug/パスで再解決。見つからなければ 0。
   */
  private function resolve_post($object, $row) {
    $object = $object ? $object : 'page';
    $pt = get_post_type_object($object);

    if ($pt && $pt->hierarchical && !empty($row['object_path'])) {
      $p = get_page_by_path($row['object_path'], OBJECT, $object);
      if ($p) {
        return (int)$p->ID;
      }
    }
    if (!empty($row['object_slug'])) {
      $found = get_posts(array(
        'name'        => $row['object_slug'],
        'post_type'   => $object,
        'post_status' => 'publish',
        'numberposts' => 1,
        'fields'      => 'ids',
      ));
      if (!empty($found)) {
        return (int)$found[0];
      }
    }
    return 0;
  }

  /**
   * taxonomy 項目を移行先サイトの slug で再解決。見つからなければ 0。
   */
  private function resolve_term($object, $row) {
    if (empty($object) || empty($row['object_slug'])) {
      return 0;
    }
    $t = get_term_by('slug', $row['object_slug'], $object);
    if ($t && !is_wp_error($t)) {
      return (int)$t->term_id;
    }
    return 0;
  }
}
```

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l menuitem-copy-paste/includes/class-menuitem-tools.php`
Expected: `No syntax errors detected`

(UI wiring + end-to-end behavior is verified in Task 5 and Task 6.)

---

## Task 5: Tools UI — JS button injection + handlers + CSS

**Files:**
- Create: `menuitem-copy-paste/js/menuitem-menu-tools.js`
- Modify: `menuitem-copy-paste/css/menuitem-copy-paste.css` (append styles)

- [ ] **Step 1: Create the tools JS**

Create `menuitem-copy-paste/js/menuitem-menu-tools.js` with:

```js
jQuery(document).ready(function ($) {
  var T = window.menuCoamTools || {};
  var i18n = T.i18n || {};

  function currentMenuId() {
    var v = $('#menu').val();
    return v ? parseInt(v, 10) : 0;
  }

  // ---- ボタン注入 ----

  // 右上：Import / Export（メニュー選択行の .manage-menus 内）
  function injectTopButtons() {
    if ($('#menucoam-tools-top').length) { return; }
    var $anchor = $('.manage-menus');
    if (!$anchor.length) { return; }
    var $wrap = $('<span id="menucoam-tools-top" class="menucoam-tools-top"></span>');
    var $imp = $('<button type="button" class="button" id="menucoam-import"></button>').text(i18n.importBtn || 'Import Menu');
    var $exp = $('<button type="button" class="button" id="menucoam-export"></button>').text(i18n.exportBtn || 'Export Menu');
    var $file = $('<input type="file" id="menucoam-import-file" accept=".json,application/json" style="display:none;">');
    $wrap.append($imp).append(document.createTextNode(' ')).append($exp).append($file);
    $anchor.append($wrap);
  }

  // 下部：複製（#save_menu_footer の隣）
  function injectDuplicateButton() {
    if ($('#menucoam-duplicate').length) { return; }
    var $save = $('#save_menu_footer');
    if (!$save.length) { return; }
    var $btn = $('<button type="button" class="button" id="menucoam-duplicate"></button>').text(i18n.duplicateBtn || 'メニューを複製');
    $save.after($btn).after(document.createTextNode(' '));
  }

  // ---- ハンドラ ----

  // 複製
  $(document).on('click', '#menucoam-duplicate', function (e) {
    e.preventDefault();
    var menuId = currentMenuId();
    if (!menuId) { window.alert(i18n.noMenu); return; }
    if (!window.confirm(i18n.duplicateConfirm)) { return; }
    $.post(T.ajaxurl, {
      action: 'menucoam_duplicate_menu',
      nonce: T.nonce,
      menu_id: menuId
    }).done(function (res) {
      if (res && res.success) {
        window.location.href = res.data.redirect;
      } else {
        window.alert(i18n.genericError + (res && res.data ? res.data : ''));
      }
    }).fail(function () { window.alert(i18n.genericError); });
  });

  // エクスポート
  $(document).on('click', '#menucoam-export', function (e) {
    e.preventDefault();
    var menuId = currentMenuId();
    if (!menuId) { window.alert(i18n.noMenu); return; }
    $.post(T.ajaxurl, {
      action: 'menucoam_export_menu',
      nonce: T.nonce,
      menu_id: menuId
    }).done(function (res) {
      if (!res || !res.success) {
        window.alert(i18n.genericError + (res && res.data ? res.data : ''));
        return;
      }
      var data = res.data;
      var filename = data.filename || 'menu-export.json';
      var payload = $.extend({}, data);
      delete payload.filename;
      var blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }).fail(function () { window.alert(i18n.genericError); });
  });

  // インポート：ボタン → 隠しfile input をクリック（value リセットで同一ファイル再選択も発火）
  $(document).on('click', '#menucoam-import', function (e) {
    e.preventDefault();
    $('#menucoam-import-file').val('').trigger('click');
  });

  $(document).on('change', '#menucoam-import-file', function () {
    var file = this.files && this.files[0];
    if (!file) { return; }
    if (!/\.json$/i.test(file.name)) { window.alert(i18n.invalidFile); return; }
    var reader = new FileReader();
    reader.onload = function (ev) {
      var parsed;
      try {
        parsed = JSON.parse(ev.target.result);
      } catch (err) {
        window.alert(i18n.parseError);
        return;
      }
      if (!parsed || !parsed.items || !parsed.items.length) {
        window.alert(i18n.missingItems);
        return;
      }
      $.post(T.ajaxurl, {
        action: 'menucoam_import_menu',
        nonce: T.nonce,
        data: JSON.stringify(parsed)
      }).done(function (res) {
        if (res && res.success) {
          var d = res.data;
          var msg = d.imported + ' ' + (i18n.importedFmt || '件を取り込みました。');
          if (d.unresolved && d.unresolved.length) {
            msg += '\n' + d.unresolved.length + ' ' + (i18n.unresolvedFmt || '件はリンク未解決（要確認）です。');
          }
          window.alert(msg);
          window.location.href = d.redirect;
        } else {
          window.alert(i18n.genericError + (res && res.data ? res.data : ''));
        }
      }).fail(function () { window.alert(i18n.genericError); });
    };
    reader.onerror = function () { window.alert(i18n.genericError); };
    reader.readAsText(file);
  });

  // ---- 初期化 ----
  injectTopButtons();
  injectDuplicateButton();
});
```

- [ ] **Step 2: Append tools button CSS**

Append to `menuitem-copy-paste/css/menuitem-copy-paste.css`:

```css

/* === Menu Tools (Task 0528.1) === */

/* 右上 Import/Export ボタン群 */
.menucoam-tools-top {
	float: right;
	display: inline-flex;
	gap: 6px;
}

/* 下部 メニュー複製ボタン */
#menucoam-duplicate {
	margin-left: 6px;
}
```

- [ ] **Step 3: Verify JS syntax**

Run: `node --check menuitem-copy-paste/js/menuitem-menu-tools.js`
Expected: no output (exit 0).

- [ ] **Step 4: Manual check — buttons appear (real WP)**

Open Appearance → Menus. Confirm:
- Top-right of the menu-select box shows **Import Menu** and **Export Menu** buttons.
- Bottom, next to **メニューを保存**, shows **メニューを複製**.
If the top buttons don't sit where expected, adjust the `.manage-menus` selector / CSS float (note the actual container in your WP version) — refine here.

---

## Task 6: Docs, version sync, and full verification

**Files:**
- Modify: `menuitem-copy-paste/readme.txt`
- Modify: `README.md`

- [ ] **Step 1: Update plugin readme.txt**

In `menuitem-copy-paste/readme.txt`: set `Stable tag: 1.1.0`, and add at the top of `== Changelog ==`:

```
= 1.1.0 =
* Added Shortcode support: shortcodes now run in menu item URL, navigation label, and description (no extra metabox needed)
* Added Duplicate Menu: clone an entire menu (preserving hierarchy) from the editor, next to "Save Menu"; auto-named "{name}_copied"
* Added per-menu JSON Export (top-right "Export Menu")
* Added JSON Import (top-right "Import Menu"): rebuilds a menu as new; page/category items are re-resolved by slug on the target site (works across sites with matching slugs)
```

Also add an Upgrade Notice block:
```
= 1.1.0 =
Adds shortcode support in menu items, whole-menu duplication, and per-menu JSON export/import (with slug-based re-resolution for cross-site migration).
```

- [ ] **Step 2: Update README.md**

In `README.md`: bump the title/version line to v1.1.0, add the four features to the Features list, and add a `### 1.1.0 (2026-05-28)` changelog entry mirroring Step 1.

- [ ] **Step 3: Full syntax sweep**

Run:
```bash
php -l menuitem-copy-paste/menuitem-copy-paste.php
php -l menuitem-copy-paste/includes/class-menuitem-item-actions.php
php -l menuitem-copy-paste/includes/class-menuitem-builder.php
php -l menuitem-copy-paste/includes/class-menuitem-shortcode.php
php -l menuitem-copy-paste/includes/class-menuitem-tools.php
node --check menuitem-copy-paste/js/menuitem-copy-paste.js
node --check menuitem-copy-paste/js/menuitem-menu-tools.js
```
Expected: all PHP `No syntax errors detected`; JS exit 0.

- [ ] **Step 4: Manual end-to-end checklist (real WP — CODE-PLUS / グランフォーレ)**

Feature 1 (Shortcode):
- [ ] Menu item URL `[topthemeurl]/x/#y` renders a real URL on the front end (not `%5B...%5D`).
- [ ] Label `<img src="[topthemeurl]/img/a.svg" alt="A" />` renders the image with resolved src.

Feature 2 (Duplicate):
- [ ] Click メニューを複製 → confirm dialog → new menu `{name}_copied` created and opened.
- [ ] Hierarchy (parent/child, 2-3 levels) preserved; classes/target/labels preserved.
- [ ] Duplicate again → `{name}_copied-2`.

Feature 3 (Export):
- [ ] Select a menu → Export Menu → downloads `menu-{slug}-<timestamp>.json`.
- [ ] JSON has `format/version/exported_at/menu/items`; post_type/taxonomy items carry `object_slug` (and `object_path` for pages).

Feature 4 (Import):
- [ ] Same site: Import the just-exported JSON → new menu, object items re-resolve to the same pages/terms (correct links), hierarchy preserved.
- [ ] Cross site (グランフォーレ A → B): Import a template menu JSON → pages resolve by matching slug; labels/shortcodes intact; hierarchy preserved.
- [ ] Slug missing on target → that item imported but unlinked (object_id 0), reported in the "未解決" count; no silent wrong link.
- [ ] Round-trip: Export → Import → structures match.

Regression:
- [ ] Existing 6 item buttons (New/Clone/Copy/Paste/Delete/Collapse) + collapse persistence + drag still work.

- [ ] **Step 5: (Do NOT commit here)**

Per the project workflow, committing/pushing and the devlog/CHANGELOG/PATTERNS/ERRORLOG updates happen in **Task 0528.1.4**. Leave changes staged-free for that step.

---

## Self-Review (performed against the spec)

- **Spec coverage:** §5 Shortcode → Task 2. §6 Duplicate → Tasks 4 (PHP) + 5 (UI). §7 Export + JSON schema → Tasks 4 + 5. §8 Import + slug resolution → Tasks 4 + 5. §4 architecture/builder → Tasks 1 + 3. §9 error handling → built into Tasks 4/5 (verify(), client+server validation, unresolved collection). §10 tests → Task 6 checklist. §11 rollback → git (Task 0528.1.4). All sections covered.
- **Placeholder scan:** No TBD/TODO; all code shown in full; no "add error handling" hand-waving (validation shown inline).
- **Type/name consistency:** `Menuitem_CP_Builder::create_menu_items` / `::unique_menu_name`, `Menuitem_CP_Shortcode`, `Menuitem_CP_Tools`, normalized-item keys (`temp_id/parent_temp_id/menu_order/title/type/object/object_id/url/target/attr_title/classes/xfn/description`), AJAX actions (`menucoam_duplicate_menu/export_menu/import_menu`), nonce action `menucoam_menu_tools`, JS global `menuCoamTools`, constants `MENUCOAM_VERSION/PATH/URL` — all consistent across tasks.
- **Known soft spots to verify at execution:** the `.manage-menus` injection selector (Task 5 Step 4) and that the moved item-actions class loads with the new constant-based asset paths (Task 1 Step 6).
