<?php
/**
 * Template bridge: strips OSSN's full HTML shell and injects the body
 * content into a WP page template.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * For OSSN page requests, load the WP page template that wraps OSSN content.
 */
function wp_ossn_load_template($template) {
    $handler = get_query_var('ossn_handler', '');
    if (empty($handler)) {
        return $template;
    }

    // Skip raw-output handlers (css/js/cache/actions) — they exit early in routing.php.
    $raw_handlers = array('css', 'js', 'cache');
    if (in_array($handler, $raw_handlers, true)) {
        return $template;
    }

    return WP_OSSN_BRIDGE_DIR . 'templates/ossn-page.php';
}
add_filter('template_include', 'wp_ossn_load_template', 99);

/**
 * Register a halt:view hook on OSSN's theme/page/page to strip the
 * full HTML document and return only the inner body content.
 *
 * This hook fires when ossn_view('theme/page/page') is called via
 * ossn_plugin_view('theme/page/page', ...). Instead of the full
 * <!DOCTYPE html>..., we return just the OSSN body markup.
 */
function wp_ossn_register_template_hook() {
    if (!function_exists('ossn_add_hook')) {
        return;
    }

    ossn_add_hook('halt', 'view:theme/page/page', 'wp_ossn_strip_theme_shell');
}

/**
 * Hook callback: replaces the full OSSN page HTML with just the body content.
 *
 * @param string $hook       Hook name ('halt')
 * @param string $type       Hook type ('view:theme/page/page')
 * @param mixed  $returnvalue Current return value
 * @param array  $params     View parameters (title, contents)
 * @return string Just the OSSN inner content wrapped in a div.
 */
function wp_ossn_strip_theme_shell($hook, $type, $returnvalue, $params) {
    $contents = isset($params['contents']) ? $params['contents'] : '';
    $title    = isset($params['title']) ? $params['title'] : '';

    // Capture the parts of the OSSN page we want to keep:
    // system messages, sidebar, topbar, and the main content.
    $system_messages = '';
    if (function_exists('ossn_plugin_view')) {
        $system_messages = ossn_plugin_view('theme/page/elements/system_messages');
    }

    $sidebar = '';
    if (function_exists('ossn_plugin_view')) {
        $sidebar = ossn_plugin_view('theme/page/elements/sidebar');
    }

    $topbar = '';
    if (function_exists('ossn_plugin_view')) {
        $topbar = ossn_plugin_view('theme/page/elements/topbar');
    }

    $footer_views = '';
    if (function_exists('ossn_fetch_extend_views')) {
        $footer_views = ossn_fetch_extend_views('ossn/page/footer');
    }

    // Build the OSSN body content without the HTML document shell.
    $output  = '<div class="ossn-page-loading-annimation">';
    $output .= '<div class="ossn-page-loading-annimation-inner"><div class="ossn-loading"></div></div>';
    $output .= '</div>';
    $output .= '<div class="ossn-halt ossn-light"></div>';
    $output .= '<div class="ossn-message-box"></div>';
    $output .= '<div class="ossn-viewer" style="display:none"></div>';
    $output .= $system_messages;
    $output .= '<div class="opensource-socalnetwork">';
    $output .= $sidebar;
    $output .= '<div class="ossn-page-container">';
    $output .= $topbar;
    $output .= '<div class="ossn-inner-page">';
    $output .= $contents;
    $output .= '</div></div></div>';
    $output .= '<div id="ossn-theme-config" class="hidden" data-desktop-cover-height="300" data-minimum-cover-image-width="1200"></div>';
    $output .= $footer_views;

    // Store the title for WP's <title> tag.
    global $wp_ossn_page_title;
    $wp_ossn_page_title = $title;

    return $output;
}

/**
 * Filter WP's document title for OSSN pages.
 */
function wp_ossn_document_title($title) {
    global $wp_ossn_page_title;
    if (!empty($wp_ossn_page_title)) {
        $title['title'] = $wp_ossn_page_title;
    }
    return $title;
}
add_filter('document_title_parts', 'wp_ossn_document_title');
