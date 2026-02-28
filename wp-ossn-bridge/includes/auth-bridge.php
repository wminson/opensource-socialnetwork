<?php
/**
 * Authentication bridge: syncs WP login state into OSSN's session.
 *
 * Called on every OSSN request after the OSSN bootstrap has run.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sync the current WP user into OSSN's session.
 *
 * - If WP user is logged in: ensure matching OSSN user exists, set OSSN session.
 * - If WP user is logged out: clear OSSN session (without destroying the PHP session).
 */
function wp_ossn_sync_auth() {
    $wp_user = wp_get_current_user();

    if ($wp_user->exists()) {
        // Ensure an OSSN user exists for this WP user.
        $ossn_user = ossn_user_by_email($wp_user->user_email);
        if (!$ossn_user) {
            $guid = wp_ossn_create_ossn_user($wp_user);
            if ($guid) {
                $ossn_user = ossn_user_by_guid($guid);
            }
        }

        // Set OSSN session if not already set or if it's a different user.
        if ($ossn_user) {
            $current = isset($_SESSION['OSSN_USER']) ? forceObject($_SESSION['OSSN_USER']) : null;
            if (!$current || !($current instanceof OssnUser) || $current->guid != $ossn_user->guid) {
                OssnUser::setLogin($wp_user->user_email);
            }
        }
    } else {
        // WP user not logged in — clear OSSN session without session_destroy().
        if (isset($_SESSION['OSSN_USER']) && $_SESSION['OSSN_USER'] !== false) {
            unset($_SESSION['OSSN_USER']);
            $_SESSION['OSSN_USER'] = false;
        }
    }
}

/**
 * Override OSSN login/register/logout page handlers.
 *
 * - login → redirect to WP login
 * - registered → redirect to WP registration
 * - logout action → delegate to wp_logout()
 */
function wp_ossn_override_auth_pages() {
    // Replace OSSN's login page with a redirect to WP login.
    ossn_unregister_page('login');
    ossn_register_page('login', 'wp_ossn_login_page_handler');

    // Replace OSSN's registration page with a redirect to WP registration.
    ossn_unregister_page('registered');
    ossn_register_page('registered', 'wp_ossn_register_page_handler');

    // Replace OSSN's logout action.
    ossn_unregister_action('user/logout');
    ossn_register_action(
        'user/logout',
        WP_OSSN_BRIDGE_DIR . 'includes/actions/logout.php'
    );
}

function wp_ossn_login_page_handler($pages) {
    $prefix = wp_ossn_get_prefix();
    $redirect_to = site_url('/' . $prefix . '/home');
    wp_redirect(wp_login_url($redirect_to));
    exit;
}

function wp_ossn_register_page_handler($pages) {
    wp_redirect(wp_registration_url());
    exit;
}

/**
 * Hook into OSSN init to override auth pages.
 * Must run after OSSN's own init callbacks register the pages.
 */
function wp_ossn_auth_init() {
    if (function_exists('ossn_register_page')) {
        wp_ossn_override_auth_pages();
    }
}
