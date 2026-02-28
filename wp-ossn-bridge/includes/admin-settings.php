<?php
/**
 * WP admin settings page for WP-OSSN Bridge.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the settings page under Settings menu.
 */
function wp_ossn_admin_menu() {
    add_options_page(
        'OSSN Bridge Settings',
        'OSSN Bridge',
        'manage_options',
        'wp-ossn-bridge',
        'wp_ossn_settings_page'
    );
}
add_action('admin_menu', 'wp_ossn_admin_menu');

/**
 * Register settings.
 */
function wp_ossn_register_settings() {
    register_setting('wp_ossn_bridge', 'wp_ossn_bridge_settings', 'wp_ossn_sanitize_settings');

    add_settings_section(
        'wp_ossn_main',
        'OSSN Configuration',
        '__return_false',
        'wp-ossn-bridge'
    );

    add_settings_field('ossn_path', 'OSSN Installation Path', 'wp_ossn_field_ossn_path', 'wp-ossn-bridge', 'wp_ossn_main');
    add_settings_field('url_prefix', 'URL Prefix', 'wp_ossn_field_url_prefix', 'wp-ossn-bridge', 'wp_ossn_main');
    add_settings_field('auto_create_users', 'Auto-create OSSN Users', 'wp_ossn_field_auto_create', 'wp-ossn-bridge', 'wp_ossn_main');
}
add_action('admin_init', 'wp_ossn_register_settings');

/**
 * Sanitize settings on save.
 */
function wp_ossn_sanitize_settings($input) {
    $clean = array();
    $clean['ossn_path']         = isset($input['ossn_path']) ? sanitize_text_field($input['ossn_path']) : '';
    $clean['url_prefix']        = isset($input['url_prefix']) ? sanitize_title($input['url_prefix']) : 'social';
    $clean['auto_create_users'] = !empty($input['auto_create_users']) ? '1' : '0';
    return $clean;
}

// --- Field renderers ---

function wp_ossn_field_ossn_path() {
    $val = wp_ossn_option('ossn_path', '');
    echo '<input type="text" name="wp_ossn_bridge_settings[ossn_path]" value="' . esc_attr($val) . '" class="regular-text" />';
    echo '<p class="description">Absolute filesystem path to the OSSN installation directory (e.g. <code>/var/www/ossn</code>).</p>';

    if (!empty($val)) {
        $start_file = rtrim($val, '/') . '/system/start.php';
        if (is_file($start_file)) {
            echo '<p style="color:green;">&#10003; OSSN installation found.</p>';
        } else {
            echo '<p style="color:red;">&#10007; Could not find <code>system/start.php</code> at this path.</p>';
        }
    }
}

function wp_ossn_field_url_prefix() {
    $val = wp_ossn_option('url_prefix', 'social');
    echo '<input type="text" name="wp_ossn_bridge_settings[url_prefix]" value="' . esc_attr($val) . '" class="regular-text" />';
    echo '<p class="description">URL prefix for OSSN pages (e.g. <code>social</code> → <code>yoursite.com/social/</code>).</p>';
}

function wp_ossn_field_auto_create() {
    $val = wp_ossn_option('auto_create_users', '1');
    echo '<label><input type="checkbox" name="wp_ossn_bridge_settings[auto_create_users]" value="1" ' . checked($val, '1', false) . ' /> ';
    echo 'Automatically create OSSN users when new WP users register.</label>';
}

/**
 * Render the settings page.
 */
function wp_ossn_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>OSSN Bridge Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wp_ossn_bridge');
            do_settings_sections('wp-ossn-bridge');
            submit_button();
            ?>
        </form>

        <hr />
        <h2>Bulk Sync Users</h2>
        <p>Create OSSN users for all existing WordPress users who don't have one yet.</p>
        <form method="post" action="">
            <?php wp_nonce_field('wp_ossn_bulk_sync', 'wp_ossn_bulk_sync_nonce'); ?>
            <input type="submit" name="wp_ossn_bulk_sync" class="button button-secondary" value="Sync All WP Users to OSSN" />
        </form>
        <?php wp_ossn_handle_bulk_sync(); ?>

        <hr />
        <h2>Web Server Configuration</h2>
        <p>Add these aliases to your Apache or Nginx config so static OSSN assets (theme images, vendor libraries) are served directly:</p>
        <?php
        $ossn_path = wp_ossn_get_ossn_path();
        $prefix = wp_ossn_get_prefix();
        if (!empty($ossn_path)):
        ?>
        <h3>Apache</h3>
        <pre style="background:#f0f0f0;padding:10px;">
Alias /<?php echo esc_html($prefix); ?>/themes/ <?php echo esc_html($ossn_path); ?>themes/
Alias /<?php echo esc_html($prefix); ?>/vendors/ <?php echo esc_html($ossn_path); ?>vendors/

&lt;Directory <?php echo esc_html($ossn_path); ?>themes/&gt;
    Require all granted
&lt;/Directory&gt;
&lt;Directory <?php echo esc_html($ossn_path); ?>vendors/&gt;
    Require all granted
&lt;/Directory&gt;</pre>

        <h3>Nginx</h3>
        <pre style="background:#f0f0f0;padding:10px;">
location /<?php echo esc_html($prefix); ?>/themes/ {
    alias <?php echo esc_html($ossn_path); ?>themes/;
}
location /<?php echo esc_html($prefix); ?>/vendors/ {
    alias <?php echo esc_html($ossn_path); ?>vendors/;
}</pre>
        <?php else: ?>
        <p><em>Set the OSSN path above to see the server configuration snippets.</em></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Handle the bulk sync form submission.
 */
function wp_ossn_handle_bulk_sync() {
    if (!isset($_POST['wp_ossn_bulk_sync'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['wp_ossn_bulk_sync_nonce'], 'wp_ossn_bulk_sync')) {
        echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
        return;
    }

    require_once WP_OSSN_BRIDGE_DIR . 'includes/ossn-bootstrap.php';
    if (!wp_ossn_bootstrap()) {
        echo '<div class="notice notice-error"><p>Could not bootstrap OSSN. Check the installation path.</p></div>';
        return;
    }

    $wp_users = get_users(array('fields' => 'all'));
    $created  = 0;
    $skipped  = 0;

    foreach ($wp_users as $wp_user) {
        $existing = ossn_user_by_email($wp_user->user_email);
        if ($existing) {
            $skipped++;
            continue;
        }
        $guid = wp_ossn_create_ossn_user($wp_user);
        if ($guid) {
            $created++;
        }
    }

    echo '<div class="notice notice-success"><p>';
    echo "Bulk sync complete: {$created} users created, {$skipped} already existed.";
    echo '</p></div>';
}
