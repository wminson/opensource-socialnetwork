<?php
/**
 * WP rewrite rules that map /social/* URLs to OSSN handlers.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register rewrite rules for the OSSN URL prefix.
 */
function wp_ossn_register_rewrite_rules() {
    $prefix = wp_ossn_get_prefix();

    // Order matters — more specific rules first.
    // Actions: /social/action/user/login  → ossn_action=user/login
    add_rewrite_rule(
        "^{$prefix}/action/(.+)$",
        'index.php?ossn_action=$matches[1]',
        'top'
    );

    // Dynamic CSS: /social/css/view/name.css
    add_rewrite_rule(
        "^{$prefix}/css/(.+)$",
        'index.php?ossn_handler=css&ossn_page=$matches[1]',
        'top'
    );

    // Dynamic JS: /social/js/view/name.js
    add_rewrite_rule(
        "^{$prefix}/js/(.+)$",
        'index.php?ossn_handler=js&ossn_page=$matches[1]',
        'top'
    );

    // Cached assets: /social/cache/css/123/view/name.css
    add_rewrite_rule(
        "^{$prefix}/cache/(.+)$",
        'index.php?ossn_handler=cache&ossn_page=$matches[1]',
        'top'
    );

    // Handler + subpage: /social/home, /social/u/username/friends
    add_rewrite_rule(
        "^{$prefix}/([^/]+)/(.+)$",
        'index.php?ossn_handler=$matches[1]&ossn_page=$matches[2]',
        'top'
    );

    // Handler only: /social/home
    add_rewrite_rule(
        "^{$prefix}/([^/]+)/?$",
        'index.php?ossn_handler=$matches[1]',
        'top'
    );

    // Index: /social/ (landing page)
    add_rewrite_rule(
        "^{$prefix}/?$",
        'index.php?ossn_handler=index',
        'top'
    );
}
add_action('init', 'wp_ossn_register_rewrite_rules');

/**
 * Register custom query vars so WP doesn't strip them.
 */
function wp_ossn_query_vars($vars) {
    $vars[] = 'ossn_handler';
    $vars[] = 'ossn_page';
    $vars[] = 'ossn_action';
    return $vars;
}
add_filter('query_vars', 'wp_ossn_query_vars');

/**
 * Intercept requests early for action/asset endpoints that must bypass
 * the WP template system (they output raw content and exit).
 */
function wp_ossn_template_redirect() {
    $action  = get_query_var('ossn_action', '');
    $handler = get_query_var('ossn_handler', '');

    if (empty($action) && empty($handler)) {
        return;
    }

    require_once WP_OSSN_BRIDGE_DIR . 'includes/ossn-bootstrap.php';
    if (!wp_ossn_bootstrap()) {
        return;
    }

    // Sync WP auth → OSSN session
    wp_ossn_sync_auth();

    // --- Action requests: /social/action/* ---
    if (!empty($action)) {
        $_REQUEST['action'] = $action;
        ossn_action($action);
        exit;
    }

    // --- Asset handlers that output raw content and exit ---
    $raw_handlers = array('css', 'js', 'cache');
    if (in_array($handler, $raw_handlers, true)) {
        $page = get_query_var('ossn_page', '');
        ossn_load_page($handler, $page);
        exit;
    }

    // Page requests fall through to the template system (ossn-page.php).
}
add_action('template_redirect', 'wp_ossn_template_redirect', 1);
