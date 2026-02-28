<?php
/**
 * Asset manager: enqueues OSSN's CSS/JS into WP's head on OSSN pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue OSSN assets into WP's head for OSSN page requests.
 */
function wp_ossn_enqueue_assets() {
    $handler = get_query_var('ossn_handler', '');
    if (empty($handler)) {
        return;
    }

    require_once WP_OSSN_BRIDGE_DIR . 'includes/ossn-bootstrap.php';
    if (!wp_ossn_bootstrap()) {
        return;
    }

    global $Ossn;
    $prefix = wp_ossn_get_prefix();
    $base   = site_url('/' . $prefix . '/');

    // --- jQuery handling ---
    // OSSN expects jQuery available as `$` globally (not in noConflict mode).
    // Deregister WP's jQuery and let OSSN's jQuery 3.7.1 load.
    wp_deregister_script('jquery');
    wp_deregister_script('jquery-core');
    wp_deregister_script('jquery-migrate');

    // --- External CSS ---
    if (!empty($Ossn->cssheadExternal['site'])) {
        foreach ($Ossn->cssheadExternal['site'] as $name) {
            if (isset($Ossn->cssExternal[$name])) {
                wp_enqueue_style(
                    'ossn-ext-' . sanitize_key($name),
                    $Ossn->cssExternal[$name],
                    array(),
                    WP_OSSN_BRIDGE_VERSION
                );
            }
        }
    }

    // --- Internal CSS (dynamic, served via /social/css/view/*.css) ---
    if (!empty($Ossn->csshead['site'])) {
        $cache_on   = function_exists('ossn_site_settings') && ossn_site_settings('cache') == 1;
        $last_cache = $cache_on ? ossn_site_settings('last_cache') : '';

        foreach ($Ossn->csshead['site'] as $css) {
            if ($cache_on) {
                $href = $base . "cache/css/{$last_cache}/view/{$css}.css";
            } else {
                $href = $base . "css/view/{$css}.css";
            }
            wp_enqueue_style(
                'ossn-' . sanitize_key($css),
                $href,
                array(),
                WP_OSSN_BRIDGE_VERSION
            );
        }
    }

    // --- External JS ---
    if (!empty($Ossn->jsheadExternal['site'])) {
        foreach ($Ossn->jsheadExternal['site'] as $name) {
            if (isset($Ossn->jsExternal[$name])) {
                wp_enqueue_script(
                    'ossn-ext-' . sanitize_key($name),
                    $Ossn->jsExternal[$name],
                    array(),
                    WP_OSSN_BRIDGE_VERSION,
                    false
                );
            }
        }
    }

    // --- Internal JS (dynamic, served via /social/js/view/*.js) ---
    if (!empty($Ossn->jshead['site'])) {
        $cache_on   = function_exists('ossn_site_settings') && ossn_site_settings('cache') == 1;
        $last_cache = $cache_on ? ossn_site_settings('last_cache') : '';

        // Build dependency chain so JS files load in order.
        $prev_handle = '';
        foreach ($Ossn->jshead['site'] as $js) {
            if ($cache_on) {
                $src = $base . "cache/js/{$last_cache}/view/{$js}.js";
            } else {
                $src = $base . "js/view/{$js}.js";
            }
            $handle = 'ossn-' . sanitize_key($js);
            $deps   = !empty($prev_handle) ? array($prev_handle) : array();
            wp_enqueue_script($handle, $src, $deps, WP_OSSN_BRIDGE_VERSION, false);
            $prev_handle = $handle;
        }
    }

    // --- Inline JS config (Ossn.site_url, security tokens, etc.) ---
    $inline_js = wp_ossn_get_inline_js_config();
    if (!empty($inline_js)) {
        // Attach to the first loaded OSSN JS handle, or add standalone.
        $first_handle = '';
        if (!empty($Ossn->jsheadExternal['site'])) {
            $name = reset($Ossn->jsheadExternal['site']);
            $first_handle = 'ossn-ext-' . sanitize_key($name);
        }
        if (empty($first_handle) && !empty($Ossn->jshead['site'])) {
            $name = reset($Ossn->jshead['site']);
            $first_handle = 'ossn-' . sanitize_key($name);
        }
        if (!empty($first_handle)) {
            wp_add_inline_script($first_handle, $inline_js, 'before');
        }
    }

    // --- Override CSS for WP/OSSN conflicts ---
    wp_enqueue_style(
        'wp-ossn-overrides',
        WP_OSSN_BRIDGE_URL . 'assets/wp-ossn-overrides.css',
        array(),
        WP_OSSN_BRIDGE_VERSION
    );
}
add_action('wp_enqueue_scripts', 'wp_ossn_enqueue_assets', 5);

/**
 * Build the inline JS configuration that OSSN's core JS expects.
 *
 * This replicates what OSSN normally outputs in <script> tags in the <head>.
 *
 * @return string JavaScript code.
 */
function wp_ossn_get_inline_js_config() {
    if (!function_exists('ossn_site_url') || !function_exists('ossn_fetch_extend_views')) {
        return '';
    }

    // Capture the ossn/js/head extended views (contains Ossn config, token vars, etc.)
    ob_start();
    echo ossn_fetch_extend_views('ossn/js/head');
    $js_head = ob_get_clean();

    return $js_head;
}
