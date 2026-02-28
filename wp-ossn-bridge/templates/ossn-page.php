<?php
/**
 * WP page template for rendering OSSN content inside the WP theme shell.
 *
 * Flow: WP header/nav → OSSN content → WP footer.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once WP_OSSN_BRIDGE_DIR . 'includes/ossn-bootstrap.php';

if (!wp_ossn_bootstrap()) {
    get_header();
    echo '<div class="wp-ossn-error" style="padding:2em;"><p>OSSN Bridge: unable to bootstrap OSSN. Check plugin settings.</p></div>';
    get_footer();
    return;
}

// Sync WP auth → OSSN session.
wp_ossn_sync_auth();

// Override OSSN auth pages (login/register/logout).
wp_ossn_auth_init();

// Register the template hook that strips OSSN's HTML shell.
wp_ossn_register_template_hook();

// Dispatch the OSSN page handler.
$handler = get_query_var('ossn_handler', 'index');
$page    = get_query_var('ossn_page', '');

// Handle unauthenticated access to pages that require login.
if (!ossn_isLoggedin() && in_array($handler, array('home', 'messages', 'notification'), true)) {
    $prefix = wp_ossn_get_prefix();
    wp_redirect(wp_login_url(site_url('/' . $prefix . '/' . $handler)));
    exit;
}

// For the index handler when logged in, redirect to home (OSSN convention).
if ($handler === 'index' && ossn_isLoggedin()) {
    $handler = 'home';
}

$ossn_body_content = ossn_load_page($handler, $page);

// Render WP theme shell around OSSN content.
get_header();
?>
<div id="ossn-wrapper">
    <?php echo $ossn_body_content; ?>
</div>
<?php
get_footer();
