<?php

/**
 * Plugin Name: MenuItem Copy & Paste
 * Description: Enables copying and pasting items in the WordPress menu editing screen
 * Version: 1.0.0
 * Author: Sarap422
 */

//Direct access prohibited
if (!defined('ABSPATH')) {
  exit;
}

class Menu_Item_Copy_Paste {
  public function __construct() {
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    add_action('wp_ajax_copy_menu_item', array($this, 'copy_menu_item'));
    add_action('wp_ajax_paste_menu_item', array($this, 'paste_menu_item'));
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
      '1.0.0',
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
      '1.0.0'
    );
  }

  public function copy_menu_item() {
    check_ajax_referer('menuitem-copy-paste-nonce', 'nonce');

    if (!current_user_can('edit_theme_options')) {
      wp_send_json_error('権限がありません');
    }

    $menu_item_id = intval($_POST['menu_item_id']);
    $menu_item = get_post($menu_item_id);

    if (!$menu_item || $menu_item->post_type !== 'nav_menu_item') {
      wp_send_json_error('メニュー項目が見つかりません');
    }

    // メニュー項目の基本情報を取得
    $menu_item_type = get_post_meta($menu_item_id, '_menu_item_type', true);
    $menu_item_object = get_post_meta($menu_item_id, '_menu_item_object', true);
    $menu_item_object_id = get_post_meta($menu_item_id, '_menu_item_object_id', true);

    $menu_item_data = array(
      'type' => $menu_item_type,
      'object' => $menu_item_object,
      'object_id' => $menu_item_object_id,
      'title' => $menu_item->post_title,
      'url' => get_post_meta($menu_item_id, '_menu_item_url', true),
      'target' => get_post_meta($menu_item_id, '_menu_item_target', true),
      'classes' => get_post_meta($menu_item_id, '_menu_item_classes', true),
      'description' => $menu_item->post_content,
    );

    // 固定ページの場合、関連するページ情報も取得
    if ($menu_item_type === 'post_type' && $menu_item_object === 'page') {
      $page = get_post($menu_item_object_id);
      if ($page) {
        $menu_item_data['page_title'] = $page->post_title;
        $menu_item_data['page_id'] = $page->ID;
      }
    }

    wp_send_json_success($menu_item_data);
  }

  public function paste_menu_item() {
    check_ajax_referer('menuitem-copy-paste-nonce', 'nonce');

    if (!current_user_can('edit_theme_options')) {
      wp_send_json_error('権限がありません');
    }

    $menu_id = intval($_POST['menu_id']);
    $item_data = json_decode(stripslashes($_POST['item_data']), true);

    // メニュー項目のベース情報を設定
    $args = array(
      'menu-item-title' => $item_data['title'],
      'menu-item-url' => $item_data['url'],
      'menu-item-target' => $item_data['target'],
      'menu-item-type' => $item_data['type'],
      'menu-item-status' => 'publish',
      'menu-item-classes' => $item_data['classes'],
      'menu-item-description' => $item_data['description'],
    );

    // 固定ページの場合、オブジェクト情報を追加
    if ($item_data['type'] === 'post_type' && $item_data['object'] === 'page') {
      $args['menu-item-object'] = 'page';
      $args['menu-item-object-id'] = $item_data['page_id'];
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
new Menu_Item_Copy_Paste();
