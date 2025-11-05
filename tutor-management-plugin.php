<?php
/*
Plugin Name: Tutor Management Plugin
Description: A custom plugin for managing tutors and students.
Version: 1.0
Author: Maggie Dykstra
*/
define('GTP_DB_VERSION', '1.3'); // Increment this when schema changes


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
    gtp_create_users_table();             // Already exists
    gtp_create_sessions_table();          // From earlier
    gtp_create_classrooms_table();        // ✅ New
    gtp_create_class_assignments_table(); // ✅ New

    update_option('gtp_db_version', '1.3');
}

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


