<?php
/**
 * Plugin Name: Comment Avatar & CAPTCHA Pro
 * Description: The ultimate comment suite. Allow guests to upload avatars, secure comments with Multi-Captcha (Google, Cloudflare, hCaptcha), and add user bios/badges.
 * Version: 1.1.0
 * Author: Ashish Gupta
 * Text Domain: comment-avatar-captcha-pro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CACP_VERSION', '1.1.0' );
define( 'CACP_PATH', plugin_dir_path( __FILE__ ) );
define( 'CACP_URL', plugin_dir_url( __FILE__ ) );

require_once CACP_PATH . 'includes/class-cacp-admin.php';
require_once CACP_PATH . 'includes/class-cacp-security.php';
require_once CACP_PATH . 'includes/class-cacp-frontend.php';

function cacp_init_plugin() {
    (new CACP_Admin())->init();
    (new CACP_Frontend())->init();
    (new CACP_Security())->init();
}
add_action( 'plugins_loaded', 'cacp_init_plugin' );

register_activation_hook( __FILE__, function() {
    if ( ! get_option( 'cacp_captcha_provider' ) ) {
        update_option( 'cacp_captcha_provider', 'none' );
    }
});
