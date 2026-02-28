<?php
/**
 * User sync: creates/updates/deletes OSSN users when WP users change.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create an OSSN user from a WP user object.
 *
 * @param WP_User $wp_user
 * @return int|false OSSN user GUID or false on failure.
 */
function wp_ossn_create_ossn_user($wp_user) {
    if (!class_exists('OssnUser')) {
        return false;
    }

    // Don't create duplicates.
    $existing = ossn_user_by_email($wp_user->user_email);
    if ($existing) {
        update_user_meta($wp_user->ID, '_ossn_guid', $existing->guid);
        return $existing->guid;
    }

    $user = new OssnUser;
    $user->username       = $wp_user->user_login;
    $user->email          = $wp_user->user_email;
    $user->first_name     = !empty($wp_user->first_name) ? $wp_user->first_name : $wp_user->display_name;
    $user->last_name      = !empty($wp_user->last_name)  ? $wp_user->last_name  : '';
    $user->password       = wp_generate_password(32, true, true);
    $user->validated       = true;
    $user->sendactiviation = false;

    // Map WP role to OSSN type.
    $user->usertype = 'normal';
    if (in_array('administrator', (array) $wp_user->roles, true)) {
        $user->usertype = 'admin';
    }

    // Required field defaults.
    $user->birthdate = '01/01/1990';
    $user->gender    = 'other';

    $guid = $user->addUser();
    if ($guid) {
        update_user_meta($wp_user->ID, '_ossn_guid', $guid);
    }
    return $guid;
}

/**
 * On WP user registration, create the OSSN user.
 */
function wp_ossn_on_user_register($user_id) {
    if (wp_ossn_option('auto_create_users', '1') !== '1') {
        return;
    }

    require_once WP_OSSN_BRIDGE_DIR . 'includes/ossn-bootstrap.php';
    if (!wp_ossn_bootstrap()) {
        return;
    }

    $wp_user = get_userdata($user_id);
    if ($wp_user) {
        wp_ossn_create_ossn_user($wp_user);
    }
}
add_action('user_register', 'wp_ossn_on_user_register');

/**
 * On WP profile update, sync name/email to OSSN.
 */
function wp_ossn_on_profile_update($user_id, $old_user_data) {
    require_once WP_OSSN_BRIDGE_DIR . 'includes/ossn-bootstrap.php';
    if (!wp_ossn_bootstrap()) {
        return;
    }

    $wp_user   = get_userdata($user_id);
    $ossn_guid = get_user_meta($user_id, '_ossn_guid', true);

    if (empty($ossn_guid)) {
        // No OSSN user linked yet — try by email from old data.
        $ossn_user = ossn_user_by_email($old_user_data->user_email);
        if (!$ossn_user) {
            return;
        }
        $ossn_guid = $ossn_user->guid;
        update_user_meta($user_id, '_ossn_guid', $ossn_guid);
    }

    $ossn_user = ossn_user_by_guid((int) $ossn_guid);
    if (!$ossn_user) {
        return;
    }

    // Update fields via direct DB update (OssnUser doesn't have a simple update method).
    $database = new OssnDatabase;
    $params = array();
    $params['table']  = 'ossn_users';
    $params['names']  = array('first_name', 'last_name', 'email');
    $params['values'] = array(
        !empty($wp_user->first_name) ? mb_substr($wp_user->first_name, 0, 30) : $wp_user->display_name,
        !empty($wp_user->last_name)  ? mb_substr($wp_user->last_name, 0, 30)  : '',
        $wp_user->user_email,
    );
    $params['wheres'] = array("guid='{$ossn_guid}'");
    $database->update($params);
}
add_action('profile_update', 'wp_ossn_on_profile_update', 10, 2);

/**
 * On WP user deletion, delete the OSSN user.
 */
function wp_ossn_on_delete_user($user_id) {
    require_once WP_OSSN_BRIDGE_DIR . 'includes/ossn-bootstrap.php';
    if (!wp_ossn_bootstrap()) {
        return;
    }

    $ossn_guid = get_user_meta($user_id, '_ossn_guid', true);
    if (empty($ossn_guid)) {
        return;
    }

    $ossn_user = ossn_user_by_guid((int) $ossn_guid);
    if ($ossn_user && method_exists($ossn_user, 'deleteUser')) {
        $ossn_user->deleteUser();
    }
}
add_action('delete_user', 'wp_ossn_on_delete_user');
