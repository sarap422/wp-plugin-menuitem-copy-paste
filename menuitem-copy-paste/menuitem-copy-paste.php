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

//Direct access prohibited
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
