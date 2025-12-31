<?php

/**
 * Plugin Name: Edel Membership
 * Plugin URI:
 * Description: 後で編集する。
 * Version: 1.0.0
 * Author: Edel Hearts
 * Author URI:
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) exit();

$info = get_file_data(__FILE__, array('plugin_name' => 'Plugin Name', 'version' => 'Version'));

define('EDEL_MEMBERSHIP_URL', plugins_url('', __FILE__));  // http(s)://〜/wp-content/plugins/edel-membership（URL）
define('EDEL_MEMBERSHIP_PATH', dirname(__FILE__));         // /home/〜/wp-content/plugins/edel-membership (パス)
define('EDEL_MEMBERSHIP_NAME', $info['plugin_name']);
define('EDEL_MEMBERSHIP_SLUG', 'edel-membership');
define('EDEL_MEMBERSHIP_PREFIX', 'edel_membership_');
define('EDEL_MEMBERSHIP_VERSION', $info['version']);
define('EDEL_MEMBERSHIP_DEVELOP', true);

class EdelMembership {
    public function init() {
        // 管理画面側の処理
        require_once EDEL_MEMBERSHIP_PATH . '/inc/class-admin.php';
        $admin = new EdelMembershipAdmin();

        add_action('admin_menu', array($admin, 'create_menu'));
        add_action('admin_init', array($admin, 'admin_init')); // これを追加！
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($admin, 'plugin_action_links'));
        add_action('admin_enqueue_scripts', array($admin, 'admin_enqueue'));

        // フロントエンドの処理
        require_once EDEL_MEMBERSHIP_PATH . '/inc/class-front.php';
        $front = new EdelMembershipFront();

        add_action('wp_enqueue_scripts', array($front, 'front_enqueue'));

        // ★ここでショートコードやフォーム送信のリスナーを登録することになります
    }
}

$instance = new EdelMembership();
$instance->init();
