<?php
/**
 * Plugin Name: WP-OSSN Bridge
 * Description: Embeds Open Source Social Network (OSSN) inside WordPress, rendering OSSN pages within the WP theme shell with shared authentication.
 * Version:     1.0.0
 * Author:      Ward
 * License:     GPL-2.0-or-later
 * Text Domain: wp-ossn-bridge
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_OSSN_BRIDGE_VERSION', '1.0.0');
define('WP_OSSN_BRIDGE_DIR', plugin_dir_path(__FILE__));
define('WP_OSSN_BRIDGE_URL', plugin_dir_url(__FILE__));

/**
 * Get an option with a default fallback.
 */
function wp_ossn_option($key, $default = '') {
    $options = get_option('wp_ossn_bridge_settings', array());
    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * Get the OSSN installation filesystem path (with trailing slash).
 */
function wp_ossn_get_ossn_path() {
    $path = wp_ossn_option('ossn_path', '');
    if (empty($path)) {
        return '';
    }
    return rtrim($path, '/') . '/';
}

/**
 * Get the URL prefix for OSSN pages (no slashes).
 */
function wp_ossn_get_prefix() {
    return wp_ossn_option('url_prefix', 'social');
}

require_once WP_OSSN_BRIDGE_DIR . 'includes/admin-settings.php';
require_once WP_OSSN_BRIDGE_DIR . 'includes/routing.php';
require_once WP_OSSN_BRIDGE_DIR . 'includes/template-bridge.php';
require_once WP_OSSN_BRIDGE_DIR . 'includes/asset-manager.php';
require_once WP_OSSN_BRIDGE_DIR . 'includes/auth-bridge.php';
require_once WP_OSSN_BRIDGE_DIR . 'includes/user-sync.php';

/**
 * Activation: flush rewrite rules so /social/* routes are registered.
 */
function wp_ossn_bridge_activate() {
    wp_ossn_register_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'wp_ossn_bridge_activate');

/**
 * Deactivation: clean up rewrite rules.
 */
function wp_ossn_bridge_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'wp_ossn_bridge_deactivate');
