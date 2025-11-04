<?php

/**
 * Plugin Name: MenuItem Copy & Paste
 * Description: WordPressのメニュー編集画面で項目のコピー、ペースト、複製、削除を簡単に行えます
 * Version: 1.0.6
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

//Direct access prohibited
if (!defined('ABSPATH')) {
  exit;
}

class Menuitem_Copy_Paste {
  public function __construct() {
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    add_action('wp_ajax_menucoam_copy_menu_item', array($this, 'copy_menu_item'));
    add_action('wp_ajax_menucoam_paste_menu_item', array($this, 'paste_menu_item'));
    add_action('wp_ajax_menucoam_clone_menu_item', array($this, 'clone_menu_item'));
    add_action('wp_ajax_menucoam_delete_menu_item', array($this, 'delete_menu_item'));
    add_action('wp_ajax_menucoam_add_new_custom_link', array($this, 'add_new_custom_link'));
  }

  public function enqueue_admin_scripts($hook) {
    // nav-menus.php（メニュー編集画面）でのみ読み込み
    if ('nav-menus.php' !== $hook) {
      return;
    }

    wp_enqueue_script(
      'menuitem-copy-paste',
      plugins_url('js/menuitem-copy-paste.js', __FILE__),
      array('jquery'),
      '1.0.4',
      true
    );

    wp_localize_script('menuitem-copy-paste', 'menuCopyPaste', array(
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('menuitem-copy-paste-nonce')
    ));

    wp_enqueue_style(
      'menuitem-copy-paste',
      plugins_url('css/menuitem-copy-paste.css', __FILE__),
      array(),
      '1.0.4'
    );
  }

  public function copy_menu_item() {
    check_ajax_referer('menuitem-copy-paste-nonce', 'nonce');

    if (!current_user_can('edit_theme_options')) {
      wp_send_json_error('権限がありません');
    }

    if (!isset($_POST['menu_item_id'])) {
      wp_send_json_error('メニュー項目IDが指定されていません');
    }

    $menu_item_id = intval($_POST['menu_item_id']);
    $menu_item = get_post($menu_item_id);

    if (!$menu_item || $menu_item->post_type !== 'nav_menu_item') {
      wp_send_json_error('メニュー項目が見つかりません');
    }

    // メニュー項目のすべてのメタデータを取得
    $menu_item_data = array(
      'type' => get_post_meta($menu_item_id, '_menu_item_type', true),
      'object' => get_post_meta($menu_item_id, '_menu_item_object', true),
      'object_id' => get_post_meta($menu_item_id, '_menu_item_object_id', true),
      'title' => $menu_item->post_title,
      'url' => get_post_meta($menu_item_id, '_menu_item_url', true),
      'target' => get_post_meta($menu_item_id, '_menu_item_target', true),
      'attr_title' => get_post_meta($menu_item_id, '_menu_item_attr_title', true),
      'classes' => get_post_meta($menu_item_id, '_menu_item_classes', true),
      'xfn' => get_post_meta($menu_item_id, '_menu_item_xfn', true),
      'description' => $menu_item->post_content,
    );

    wp_send_json_success($menu_item_data);
  }

  /**
   * 指定された親IDを持つすべての子孫を再帰的に取得
   */
  private function get_all_descendants($menu_items, $parent_id) {
    $descendants = array();
    
    // 直接の子要素を取得
    foreach ($menu_items as $item) {
      $item_parent_id = get_post_meta($item->ID, '_menu_item_menu_item_parent', true);
      if ($item_parent_id == $parent_id) {
        $descendants[] = $item;
        // 再帰的に孫要素も取得
        $grandchildren = $this->get_all_descendants($menu_items, $item->ID);
        $descendants = array_merge($descendants, $grandchildren);
      }
    }
    
    // menu_order でソート
    usort($descendants, function($a, $b) {
      return $a->menu_order - $b->menu_order;
    });
    
    return $descendants;
  }

  public function paste_menu_item() {
    check_ajax_referer('menuitem-copy-paste-nonce', 'nonce');

    if (!current_user_can('edit_theme_options')) {
      wp_send_json_error('権限がありません');
    }

    if (!isset($_POST['menu_id']) || !isset($_POST['item_data'])) {
      wp_send_json_error('必要なパラメータが不足しています');
    }

    $menu_id = intval($_POST['menu_id']);
    $after_item_id = isset($_POST['after_item_id']) ? intval($_POST['after_item_id']) : 0;
    
    // JSON文字列のバリデーション
    $item_data_json = wp_unslash($_POST['item_data']);
    
    // 文字列型チェックと最大長チェック（10KB以内）
    if (!is_string($item_data_json) || strlen($item_data_json) > 10240) {
      wp_send_json_error('データが無効です');
    }
    
    // JSONデコード
    $item_data = json_decode($item_data_json, true);
    
    // デコード成功チェック
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($item_data)) {
      wp_send_json_error('データのデコードに失敗しました');
    }

    // メニュー項目の基本設定
    $args = array(
      'menu-item-title' => $item_data['title'],
      'menu-item-type' => $item_data['type'],
      'menu-item-status' => 'publish',
    );

    // オプション項目を追加
    if (!empty($item_data['url'])) {
      $args['menu-item-url'] = $item_data['url'];
    }
    
    if (!empty($item_data['target'])) {
      $args['menu-item-target'] = $item_data['target'];
    }
    
    if (!empty($item_data['attr_title'])) {
      $args['menu-item-attr-title'] = $item_data['attr_title'];
    }
    
    if (!empty($item_data['classes'])) {
      $args['menu-item-classes'] = implode(' ', (array)$item_data['classes']);
    }
    
    if (!empty($item_data['xfn'])) {
      $args['menu-item-xfn'] = $item_data['xfn'];
    }
    
    if (!empty($item_data['description'])) {
      $args['menu-item-description'] = $item_data['description'];
    }

    // タイプ別の処理
    if ($item_data['type'] === 'post_type') {
      // 投稿タイプ（固定ページ、投稿、カスタム投稿タイプ）
      $args['menu-item-object'] = $item_data['object'];
      $args['menu-item-object-id'] = $item_data['object_id'];
    } elseif ($item_data['type'] === 'taxonomy') {
      // タクソノミー（カテゴリ、タグなど）
      $args['menu-item-object'] = $item_data['object'];
      $args['menu-item-object-id'] = $item_data['object_id'];
    } elseif ($item_data['type'] === 'custom') {
      // カスタムリンク
      $args['menu-item-url'] = $item_data['url'];
    }

    // 挿入位置を計算
    if ($after_item_id > 0) {
      // after_item_idのメニュー項目情報を取得
      $after_item = get_post($after_item_id);
      
      if ($after_item && $after_item->post_type === 'nav_menu_item') {
        // メニュー内のすべてのアイテムを取得
        $menu_items = wp_get_nav_menu_items($menu_id);
        
        // 重要：after_item_idと同じ親を持つように設定（同じ階層に配置）
        $after_parent_id = get_post_meta($after_item_id, '_menu_item_menu_item_parent', true);
        if ($after_parent_id) {
          $args['menu-item-parent-id'] = $after_parent_id;
        }
        // 親がいない場合は、トップレベルとして扱う（parent-idは設定しない）
        
        // after_item_idのすべての子孫を再帰的に取得
        $descendants = $this->get_all_descendants($menu_items, $after_item_id);
        
        // 挿入位置を決定
        if (!empty($descendants)) {
          // 子孫要素がある場合：最後の子孫の後に挿入
          $last_descendant = end($descendants);
          $position = $last_descendant->menu_order;
        } else {
          // 子孫要素がない場合：after_item_idの直後に挿入
          $position = $after_item->menu_order;
        }
        $args['menu-item-position'] = $position;



      }
    }

    // 新しいメニュー項目を作成
    $item_id = wp_update_nav_menu_item($menu_id, 0, $args);

    if (is_wp_error($item_id)) {
      wp_send_json_error($item_id->get_error_message());
    }

    wp_send_json_success(array(
      'item_id' => $item_id,
      'menu_id' => $menu_id
    ));
  }

  public function clone_menu_item() {
    check_ajax_referer('menuitem-copy-paste-nonce', 'nonce');

    if (!current_user_can('edit_theme_options')) {
      wp_send_json_error('権限がありません');
    }

    if (!isset($_POST['menu_item_id'])) {
      wp_send_json_error('メニュー項目IDが指定されていません');
    }

    $menu_item_id = intval($_POST['menu_item_id']);
    $menu_item = get_post($menu_item_id);

    if (!$menu_item || $menu_item->post_type !== 'nav_menu_item') {
      wp_send_json_error('メニュー項目が見つかりません');
    }

    // メニュー項目のすべてのメタデータを取得
    $menu_item_data = array(
      'type' => get_post_meta($menu_item_id, '_menu_item_type', true),
      'object' => get_post_meta($menu_item_id, '_menu_item_object', true),
      'object_id' => get_post_meta($menu_item_id, '_menu_item_object_id', true),
      'title' => $menu_item->post_title,
      'url' => get_post_meta($menu_item_id, '_menu_item_url', true),
      'target' => get_post_meta($menu_item_id, '_menu_item_target', true),
      'attr_title' => get_post_meta($menu_item_id, '_menu_item_attr_title', true),
      'classes' => get_post_meta($menu_item_id, '_menu_item_classes', true),
      'xfn' => get_post_meta($menu_item_id, '_menu_item_xfn', true),
      'description' => $menu_item->post_content,
    );

    // メニューIDを取得
    $menu_terms = wp_get_post_terms($menu_item_id, 'nav_menu');
    if (empty($menu_terms) || is_wp_error($menu_terms)) {
      wp_send_json_error('メニューが見つかりません');
    }
    $menu_id = $menu_terms[0]->term_id;

    // メニュー項目の基本設定
    $args = array(
      'menu-item-title' => $menu_item_data['title'],
      'menu-item-type' => $menu_item_data['type'],
      'menu-item-status' => 'publish',
    );

    // オプション項目を追加
    if (!empty($menu_item_data['url'])) {
      $args['menu-item-url'] = $menu_item_data['url'];
    }
    
    if (!empty($menu_item_data['target'])) {
      $args['menu-item-target'] = $menu_item_data['target'];
    }
    
    if (!empty($menu_item_data['attr_title'])) {
      $args['menu-item-attr-title'] = $menu_item_data['attr_title'];
    }
    
    if (!empty($menu_item_data['classes'])) {
      $args['menu-item-classes'] = implode(' ', (array)$menu_item_data['classes']);
    }
    
    if (!empty($menu_item_data['xfn'])) {
      $args['menu-item-xfn'] = $menu_item_data['xfn'];
    }
    
    if (!empty($menu_item_data['description'])) {
      $args['menu-item-description'] = $menu_item_data['description'];
    }

    // タイプ別の処理
    if ($menu_item_data['type'] === 'post_type') {
      $args['menu-item-object'] = $menu_item_data['object'];
      $args['menu-item-object-id'] = $menu_item_data['object_id'];
    } elseif ($menu_item_data['type'] === 'taxonomy') {
      $args['menu-item-object'] = $menu_item_data['object'];
      $args['menu-item-object-id'] = $menu_item_data['object_id'];
    } elseif ($menu_item_data['type'] === 'custom') {
      $args['menu-item-url'] = $menu_item_data['url'];
    }

    // 元のアイテムと同じ親を持つように設定
    $parent_id = get_post_meta($menu_item_id, '_menu_item_menu_item_parent', true);
    if ($parent_id) {
      $args['menu-item-parent-id'] = $parent_id;
    }

    // メニュー内のすべてのアイテムを取得
    $menu_items = wp_get_nav_menu_items($menu_id);
    
    // 元のアイテムのすべての子孫を再帰的に取得
    $descendants = $this->get_all_descendants($menu_items, $menu_item_id);
    
    // 挿入位置を決定
    if (!empty($descendants)) {
      // 子孫要素がある場合：最後の子孫の後に挿入
      $last_descendant = end($descendants);
      $position = $last_descendant->menu_order;
    } else {
      // 子孫要素がない場合：元のアイテムの直後に挿入
      $position = $menu_item->menu_order;
    }
    $args['menu-item-position'] = $position;

    // 新しいメニュー項目を作成
    $item_id = wp_update_nav_menu_item($menu_id, 0, $args);

    if (is_wp_error($item_id)) {
      wp_send_json_error($item_id->get_error_message());
    }

    wp_send_json_success(array(
      'item_id' => $item_id,
      'menu_id' => $menu_id
    ));
  }

  public function delete_menu_item() {
    check_ajax_referer('menuitem-copy-paste-nonce', 'nonce');

    if (!current_user_can('edit_theme_options')) {
      wp_send_json_error('権限がありません');
    }

    if (!isset($_POST['menu_item_id'])) {
      wp_send_json_error('メニュー項目IDが指定されていません');
    }

    $menu_item_id = intval($_POST['menu_item_id']);
    $menu_item = get_post($menu_item_id);

    if (!$menu_item || $menu_item->post_type !== 'nav_menu_item') {
      wp_send_json_error('メニュー項目が見つかりません');
    }

    // メニュー項目を削除
    $result = wp_delete_post($menu_item_id, true);

    if ($result === false) {
      wp_send_json_error('削除に失敗しました');
    }

    wp_send_json_success(array(
      'deleted_id' => $menu_item_id
    ));
  }

  public function add_new_custom_link() {
    check_ajax_referer('menuitem-copy-paste-nonce', 'nonce');

    if (!current_user_can('edit_theme_options')) {
      wp_send_json_error('権限がありません');
    }

    if (!isset($_POST['menu_id'])) {
      wp_send_json_error('メニューIDが指定されていません');
    }

    $menu_id = intval($_POST['menu_id']);
    $after_item_id = isset($_POST['after_item_id']) ? intval($_POST['after_item_id']) : 0;

    // 新しいカスタムリンクの基本設定
    $args = array(
      'menu-item-title' => '新規カスタムリンク',
      'menu-item-url' => '/',
      'menu-item-type' => 'custom',
      'menu-item-status' => 'publish',
    );

    // 挿入位置を計算
    if ($after_item_id > 0) {
      // after_item_idのメニュー項目情報を取得
      $after_item = get_post($after_item_id);
      
      if ($after_item && $after_item->post_type === 'nav_menu_item') {
        // メニュー内のすべてのアイテムを取得
        $menu_items = wp_get_nav_menu_items($menu_id);
        
        // after_item_idと同じ親を持つように設定（同じ階層に配置）
        $after_parent_id = get_post_meta($after_item_id, '_menu_item_menu_item_parent', true);
        if ($after_parent_id) {
          $args['menu-item-parent-id'] = $after_parent_id;
        }
        
        // after_item_idのすべての子孫を再帰的に取得
        $descendants = $this->get_all_descendants($menu_items, $after_item_id);
        
        // 挿入位置を決定
        if (!empty($descendants)) {
          // 子孫要素がある場合：最後の子孫の後に挿入
          $last_descendant = end($descendants);
          $position = $last_descendant->menu_order;
        } else {
          // 子孫要素がない場合：after_item_idの直後に挿入
          $position = $after_item->menu_order;
        }
        $args['menu-item-position'] = $position;
      }
    }

    // 新しいメニュー項目を作成
    $item_id = wp_update_nav_menu_item($menu_id, 0, $args);

    if (is_wp_error($item_id)) {
      wp_send_json_error($item_id->get_error_message());
    }

    wp_send_json_success(array(
      'item_id' => $item_id,
      'menu_id' => $menu_id
    ));
  }
}



// プラグインの初期化
new Menuitem_Copy_Paste();
