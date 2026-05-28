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
   * @param int   $menu_id 生成先メニューID
   * @param array $items   正規化アイテム配列
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
   *
   * @param string $base 希望するメニュー名
   * @return string 一意なメニュー名
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
