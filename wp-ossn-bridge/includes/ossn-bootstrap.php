<?php
/**
 * Custom OSSN bootstrap for WP-OSSN Bridge.
 *
 * Replicates system/start.php but lets us set $Ossn->url BEFORE
 * ossn_trigger_callback('ossn', 'init') fires, which is critical
 * to prevent ossn_redirect_absolute_url() from 301-redirecting requests.
 *
 * This file must only be included once per request via wp_ossn_bootstrap().
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstrap OSSN into the current PHP process.
 *
 * @return bool True on success, false if OSSN path is invalid.
 */
function wp_ossn_bootstrap() {
    static $booted = false;
    if ($booted) {
        return true;
    }

    $ossn_path = wp_ossn_get_ossn_path();
    if (empty($ossn_path) || !is_file($ossn_path . 'system/start.php')) {
        return false;
    }

    // Save WP's exception/error handlers so we can restore them later.
    $wp_exception_handler = set_exception_handler(function ($e) {});
    restore_exception_handler();

    global $Ossn;
    if (!isset($Ossn)) {
        $Ossn = new stdClass;
    }

    // 1. Security gate bypass — same constant OSSN checks in start.php:12
    if (!defined('OSSN_ALLOW_SYSTEM_START')) {
        define('OSSN_ALLOW_SYSTEM_START', true);
    }

    // 2. Route helper (uses dirname to locate OSSN root)
    require_once $ossn_path . 'libraries/ossn.lib.route.php';

    // 3. Configuration files
    $configs = ossn_route()->configs;
    if (!is_file($configs . 'ossn.config.site.php') || !is_file($configs . 'ossn.config.db.php')) {
        return false;
    }

    require_once $configs . 'libraries.php';
    require_once $configs . 'classes.php';
    require_once $configs . 'ossn.config.site.php';
    require_once $configs . 'ossn.config.db.php';
    require_once $configs . 'ossn.config.dcache.php';

    // 4. Override $Ossn->url to point to the WP-routed prefix BEFORE init fires.
    //    This prevents ossn_redirect_absolute_url() from issuing 301s
    //    and makes ossn_site_url() generate /social/* URLs.
    $prefix = wp_ossn_get_prefix();
    $Ossn->url = site_url('/' . $prefix . '/');

    // 5. Session — guard against double session_start
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        session_start();
    }

    // 6. Load all OSSN libraries
    foreach ($Ossn->libraries as $lib) {
        $lib_file = ossn_route()->libs . "ossn.lib.{$lib}.php";
        if (!include_once($lib_file)) {
            throw new Exception("WP-OSSN Bridge: cannot include OSSN library: {$lib}");
        }
    }

    // 7. Fire init callbacks (page handlers, components, etc.)
    ossn_trigger_callback('ossn', 'init');

    // 8. Update user last activity
    update_last_activity();

    // Restore WP's exception handler if OSSN overrode it.
    if ($wp_exception_handler !== null) {
        set_exception_handler($wp_exception_handler);
    }

    $booted = true;
    return true;
}
