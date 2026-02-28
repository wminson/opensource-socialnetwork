<?php
/**
 * Custom logout action for WP-OSSN Bridge.
 *
 * Delegates to wp_logout() instead of OssnUser::Logout() to avoid
 * calling session_destroy() which would break WP's session handling.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Clear OSSN session data without destroying the PHP session.
unset($_SESSION['OSSN_USER']);
$_SESSION['OSSN_USER'] = false;

// Log out of WordPress.
wp_logout();

// Redirect to home page.
wp_redirect(home_url('/'));
exit;
