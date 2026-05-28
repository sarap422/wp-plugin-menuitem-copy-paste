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
   *
   * @param string $item_output メニュー項目のHTML出力
   * @param object $item        メニュー項目オブジェクト
   * @param int    $depth       階層の深さ
   * @param object $args        wp_nav_menu の引数
   * @return string
   */
  public function do_shortcode_on_output($item_output, $item, $depth, $args) {
    if (is_string($item_output) && strpos($item_output, '[') !== false) {
      return do_shortcode($item_output);
    }
    return $item_output;
  }

  /**
   * 表示コンテキストのURLに含まれるショートコードを展開。
   *
   * @param string $url      esc_url 処理後のURL
   * @param string $orig_url 元のURL
   * @param string $context  'display' | 'db' など
   * @return string
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
   *
   * @param string $url      esc_url 処理後のURL
   * @param string $orig_url 元のURL
   * @param string $context  'display' | 'db' など
   * @return string
   */
  public function preserve_shortcode_url($url, $orig_url, $context) {
    if ('db' === $context && $this->has_shortcode($orig_url)) {
      return $orig_url;
    }
    return $url;
  }

  /**
   * 文字列にショートコードが含まれるか（コアの has_shortcode を参考に簡略実装）。
   *
   * @param string $content 検査対象
   * @return bool
   */
  private function has_shortcode($content) {
    if (is_string($content) && strpos($content, '[') !== false) {
      preg_match_all('/' . get_shortcode_regex() . '/s', $content, $matches, PREG_SET_ORDER);
      return !empty($matches);
    }
    return false;
  }
}
