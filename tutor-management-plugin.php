<?php
/*
Plugin Name: Tutor Management Plugin
Description: A custom plugin for managing tutors and students.
Version: 1.0
Author: Maggie Dykstra
*/
define('GTP_DB_VERSION', '4.4'); // Increment this when schema changes

add_action('init', 'gtp_start_session', 1);
function gtp_start_session() {
    if (!session_id()) {
        session_start();
    }
}

add_action('init', 'gtp_handle_logout', 2);
function gtp_handle_logout() {
    if (!isset($_GET['gtp_logout'])) {
        return;
    }

    if (isset($_SESSION['gtp_user'])) {
        unset($_SESSION['gtp_user']);
    }

    wp_safe_redirect(site_url('/index.php/Welcome-to-GTP/'));
    exit;
}

add_action('wp_footer', 'gtp_render_logout_button');
function gtp_render_logout_button() {
    if (is_admin() || empty($_SESSION['gtp_user'])) {
        return;
    }

    // Invite page is public; don't show app chrome there
    if (gtp_is_auth_page()) {
        return;
    }

    // Admins get Logout inside the semester top chrome
    if (($_SESSION['gtp_user']['role'] ?? '') === 'admin') {
        return;
    }

    $logout_url = site_url('/index.php/Welcome-to-GTP/?gtp_logout=1');
    ?>
    <div class="gtp-top-chrome gtp-logout-only">
        <div class="gtp-top-chrome-inner">
            <span></span>
            <div class="gtp-top-chrome-right">
                <?php echo gtp_bug_report_top_link_html(); ?>
                <a class="gtp-back-link gtp-top-logout" href="<?php echo esc_url($logout_url); ?>">Logout</a>
            </div>
        </div>
    </div>
    <script>
    document.body.classList.add('has-gtp-logout', 'has-gtp-top-chrome');
    </script>
    <?php
}

register_activation_hook(__FILE__, 'gtp_activate_plugin');
function gtp_activate_plugin() {
  delete_option('gtp_pages_created'); // regenerates pages if not already
  gtp_create_required_pages();        // ensure pages exist
  flush_rewrite_rules();              // rebuild WordPress routing
}

/* function gtp_update_db_schema() {
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
    */

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
require_once plugin_dir_path(__FILE__) . 'includes/password-invite.php';
require_once plugin_dir_path(__FILE__) . 'includes/announcements.php';
require_once plugin_dir_path(__FILE__) . 'includes/flagged-sessions.php';
require_once plugin_dir_path(__FILE__) . 'includes/manage-people.php';
require_once plugin_dir_path(__FILE__) . 'includes/tutor-resources.php';
require_once plugin_dir_path(__FILE__) . 'includes/monthly-checkins.php';
require_once plugin_dir_path(__FILE__) . 'includes/semesters.php';
require_once plugin_dir_path(__FILE__) . 'includes/site-bugs.php';
require_once plugin_dir_path(__FILE__) . 'includes/page-creation.php';
require_once plugin_dir_path(__FILE__) . 'includes/tutor-shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/helperfxns.php';
require_once plugin_dir_path(__FILE__) . 'includes/devonly-db.php';
require_once plugin_dir_path(__FILE__) . 'includes/auth.php';
require_once plugin_dir_path(__FILE__) . 'includes/GTP-admin-dash.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . 'includes/session-filter-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/spreadsheet-view-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/edit-classrooms-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/my-logged-sessions-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/my-classes-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/schema.php';
require_once plugin_dir_path(__FILE__) . 'includes/db-migrations.php';
require_once plugin_dir_path(__FILE__) . 'includes/database-updater.php';


function gtp_add_admin_menu() {
    add_menu_page(
        'GTP Database Updater',
        'GTP DB Updater',
        'manage_options',
        'gtp-database-updater',
        'gtp_database_updater_page',
        'dashicons-database',
        90
    );
}
add_action('admin_menu', 'gtp_add_admin_menu');

function gtp_enqueue_log_session_scripts() {
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'gtp_log_session') || has_shortcode($post->post_content, 'gtp_log_substitute_session'))) {
        wp_enqueue_script(
            'gtp-log-session',
            plugin_dir_url(__FILE__) . 'assets/js/log-session.js',
            [],
            '1.7',
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
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'gtp_log_substitute_session')) {
        wp_enqueue_script(
            'gtp-classroom-filter',
            plugin_dir_url(__FILE__) . 'assets/js/classroom-filter.js',
            [],
            '1.2',
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
    if (!is_a($post, 'WP_Post')) {
        return;
    }

    $is_ta_dash = has_shortcode($post->post_content, 'gtp_TA_dashboard');
    $is_admin_dash = has_shortcode($post->post_content, 'gtp_admin_dashboard');
    $is_flagged = has_shortcode($post->post_content, 'gtp_flagged_sessions');
    $is_people = has_shortcode($post->post_content, 'gtp_manage_people')
        || has_shortcode($post->post_content, 'gtp_unassigned_tutors');
    $is_profile = has_shortcode($post->post_content, 'gtp_ta_profile')
        || has_shortcode($post->post_content, 'gtp_admin_profile');

    if ($is_ta_dash || $is_admin_dash || $is_flagged || $is_people || $is_profile) {
        wp_enqueue_style(
            'gtp-dashboard-home',
            plugin_dir_url(__FILE__) . 'assets/css/dashboard-home.css',
            [],
            '2.0'
        );
    }

    if ($is_admin_dash) {
        wp_enqueue_script(
            'gtp-admin-home',
            plugin_dir_url(__FILE__) . 'assets/js/admin-home.js',
            [],
            '1.0',
            true
        );
        wp_localize_script(
            'gtp-admin-home',
            'gtp_ajax',
            ['ajax_url' => admin_url('admin-ajax.php')]
        );
    }

    if ($is_ta_dash) {
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

function gtp_enqueue_tutor_resources_assets() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'gtp_tutor_resources')) {
        wp_enqueue_style(
            'gtp-dashboard-home',
            plugin_dir_url(__FILE__) . 'assets/css/dashboard-home.css',
            [],
            '2.0'
        );
        wp_enqueue_style(
            'gtp-tutor-resources',
            plugin_dir_url(__FILE__) . 'assets/css/tutor-resources.css',
            ['gtp-dashboard-home'],
            '1.1'
        );
    }
}
add_action('wp_enqueue_scripts', 'gtp_enqueue_tutor_resources_assets');

function gtp_enqueue_monthly_checkin_assets() {
    global $post;
    if (!is_a($post, 'WP_Post')) {
        return;
    }

    $is_tutor = has_shortcode($post->post_content, 'gtp_monthly_checkins');
    $is_admin = has_shortcode($post->post_content, 'gtp_admin_checkins');
    $is_ta_dash = has_shortcode($post->post_content, 'gtp_TA_dashboard');
    $is_admin_dash = has_shortcode($post->post_content, 'gtp_admin_dashboard');

    if ($is_tutor || $is_admin || $is_ta_dash || $is_admin_dash) {
        wp_enqueue_style(
            'gtp-monthly-checkins',
            plugin_dir_url(__FILE__) . 'assets/css/monthly-checkins.css',
            [],
            '1.2'
        );
    }

    if ($is_tutor) {
        wp_enqueue_script(
            'gtp-monthly-checkins',
            plugin_dir_url(__FILE__) . 'assets/js/monthly-checkins.js',
            [],
            '1.0',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'gtp_enqueue_monthly_checkin_assets');

function gtp_enqueue_my_classes_scripts() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'gtp_my_classes')) {
        wp_enqueue_style(
            'gtp-my-classes-roster',
            plugin_dir_url(__FILE__) . 'assets/css/my-classes-roster.css',
            [],
            '1.9'
        );
        wp_enqueue_script(
            'gtp-my-classes-roster',
            plugin_dir_url(__FILE__) . 'assets/js/my-classes-roster.js',
            [],
            '1.8',
            true
        );
        wp_localize_script(
            'gtp-my-classes-roster',
            'gtp_ajax',
            ['ajax_url' => admin_url('admin-ajax.php')]
        );
    }
}
add_action('wp_enqueue_scripts', 'gtp_enqueue_my_classes_scripts');
function gtp_enqueue_spreadsheet_view_assets() {
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'gtp_spreadsheet_view')) {
        wp_enqueue_style(
            'gtp-spreadsheet-view',
            plugin_dir_url(__FILE__) . 'assets/css/spreadsheet-view.css',
            [],
            '1.5'
        );
    }
}
add_action('wp_enqueue_scripts', 'gtp_enqueue_spreadsheet_view_assets');
