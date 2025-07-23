<?php
// Runs early on every page load
add_action('template_redirect', 'gtp_handle_page_access');

function gtp_handle_page_access() {
    if (!is_page()) return;

    $page_slug = get_post_field('post_name', get_post());

    // Public pages shouldn't require login
    $public_pages = ['welcome-to-gtp', 'registration-page'];
    if (in_array($page_slug, $public_pages)) return;

    // Now only dashboard pages should be locked:
    if ($page_slug === 'tutor-dashboard' && $role !== 'tutor') {
        wp_redirect(site_url('/welcome-to-gtp'));
        exit;
    }
}

function gtp_is_gtp_admin($username) {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_users';
    $role = $wpdb->get_var($wpdb->prepare("SELECT role FROM $table WHERE username = %s", $username));
    return $role === 'admin';
}

function gtp_is_gtp_tutor($username) {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_users';
    $role = $wpdb->get_var($wpdb->prepare("SELECT role FROM $table WHERE username = %s", $username));
    return $role === 'tutor';
}
