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
   *
   * @param object $it メニュー項目（wp_setup_nav_menu_item 済み）
   * @return array
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
   *
   * @param string $object 投稿タイプ（page/post/CPT）
   * @param array  $row    JSONアイテム
   * @return int 解決した投稿ID（未解決は 0）
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
   *
   * @param string $object タクソノミー（category/post_tag/カスタムtax）
   * @param array  $row    JSONアイテム
   * @return int 解決したターム term_id（未解決は 0）
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
