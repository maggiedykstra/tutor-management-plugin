<?php
/*
Plugin Name: Tutor Management Plugin
Description: A custom plugin for managing tutors and students.
Version: 1.0
Author: Maggie Dykstra
*/
define('GTP_DB_VERSION', '1.8'); // Increment this when schema changes


add_action('init', 'gtp_start_session', 1);
function gtp_start_session() {
    if (!session_id()) {
        session_start();
    }
}

register_activation_hook(__FILE__, 'gtp_activate_plugin');
function gtp_activate_plugin() {
  delete_option('gtp_pages_created'); // regenerates pages if not already
  gtp_create_required_pages();        // ensure pages exist
  flush_rewrite_rules();              // rebuild WordPress routing
}

function gtp_update_db_schema() {
    $installed_ver = get_option('gtp_db_version');
    if ($installed_ver != GTP_DB_VERSION) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        gtp_create_users_table();
        gtp_create_sessions_table();
        gtp_create_classrooms_table();
        gtp_create_class_assignments_table();
        gtp_create_students_table();
        update_option('gtp_db_version', GTP_DB_VERSION);
    }
}

add_action('plugins_loaded', 'gtp_update_db_schema');

register_activation_hook(__FILE__, 'gtp_update_db_schema');

add_action('plugins_loaded', function () {
    $installed_ver = get_option('gtp_db_version');
    if ($installed_ver !== GTP_DB_VERSION) {
        gtp_update_db_schema();
    }
});


defined('ABSPATH') or die('No script kiddies please!');


// Include plugin modules
require_once plugin_dir_path(__FILE__) . 'includes/schema.php';
require_once plugin_dir_path(__FILE__) . 'includes/WP-admin-pages.php';
require_once plugin_dir_path(__FILE__) . 'includes/login-shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/page-creation.php';
require_once plugin_dir_path(__FILE__) . 'includes/tutor-shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/helperfxns.php';
require_once plugin_dir_path(__FILE__) . 'includes/devonly-db.php';
require_once plugin_dir_path(__FILE__) . 'includes/auth.php';
require_once plugin_dir_path(__FILE__) . 'includes/GTP-admin-dash.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . 'includes/session-filter-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/edit-classrooms-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/my-logged-sessions-shortcode.php';

function gtp_enqueue_log_session_scripts() {
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'gtp_log_session') || has_shortcode($post->post_content, 'gtp_log_substitute_session'))) {
        wp_enqueue_script(
            'gtp-log-session',
            plugin_dir_url(__FILE__) . 'assets/js/log-session.js',
            [],
            '1.0',
            true
        );
        wp_localize_script(
            'gtp-log-session',
            'gtp_ajax',
            ['ajax_url' => admin_url('admin-ajax.php')]
        );
    }
}
add_action('wp_enqueue_scripts', 'gtp_enqueue_log_session_scripts');

function gtp_enqueue_classroom_filter_scripts() {
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'gtp_log_session') || has_shortcode($post->post_content, 'gtp_log_substitute_session'))) {
        wp_enqueue_script(
            'gtp-classroom-filter',
            plugin_dir_url(__FILE__) . 'assets/js/classroom-filter.js',
            [],
            '1.0',
            true
        );
        wp_localize_script(
            'gtp-classroom-filter',
            'gtp_ajax',
            ['ajax_url' => admin_url('admin-ajax.php')]
        );
    }
}
add_action('wp_enqueue_scripts', 'gtp_enqueue_classroom_filter_scripts');

function gtp_enqueue_admin_dashboard_scripts() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'gtp_TA_dashboard')) {
        wp_enqueue_script(
            'gtp-admin-dashboard',
            plugin_dir_url(__FILE__) . 'assets/js/admin-dashboard.js',
            [],
            '1.0',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'gtp_enqueue_admin_dashboard_scripts');