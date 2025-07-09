<?php
/*
Plugin Name: Tutor Management Plugin
Description: A custom plugin for managing tutors and students.
Version: 1.0
Author: Maggie Dykstra
*/

add_action('init', function () {
    error_log('Running schema check...');
    gtp_check_and_update_schema();
});

defined('ABSPATH') or die('No script kiddies please!');

register_activation_hook(__FILE__, 'gtp_check_and_update_schema');
gtp_check_and_update_schema();

// Register admin menu
add_action('admin_menu', 'tutor_management_add_admin_menu');

function tutor_management_add_admin_menu() {
    add_menu_page(
        'Tutor Dashboard',
        'Tutor Dashboard',
        'manage_options',
        'tutor-dashboard',
        'tutor_management_dashboard_page',
        'dashicons-welcome-learn-more',
        6
    );
}

// Load CSS and JS
add_action('admin_enqueue_scripts', 'tutor_management_enqueue_assets');

function tutor_management_enqueue_assets($hook) {
    if ($hook !== 'toplevel_page_tutor-dashboard') return;

    wp_enqueue_style('tutor-style', plugin_dir_url(__FILE__) . 'assets/style.css');
    wp_enqueue_script('tutor-script', plugin_dir_url(__FILE__) . 'assets/script.js', array('jquery'), null, true);
}

// Dashboard Page Content
function tutor_management_dashboard_page() {
    ?>
    <div class="tutor-welcome-screen">
        <h1>Welcome to the Tutor Management Plugin</h1>
        <p>This is your admin panel.</p>
    </div>
    <?php
}

// Hook schema sync to init so it's always updated
add_action('init', 'gtp_check_and_update_schema');

function gtp_check_and_update_schema() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_users';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        username varchar(60) NOT NULL,
        password varchar(255) NOT NULL,
        role varchar(20) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY username (username)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    error_log('About to run dbDelta for gtp_users...');
    dbDelta($sql);
}

// Shortcode to show login form
add_shortcode('gtp_login', 'gtp_login_shortcode');

function gtp_login_shortcode() {
    if (isset($_POST['gtp_login_submit'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'gtp_users';

        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];

        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE username = %s", $username));

        if ($user && password_verify($password, $user->password)) {
            if ($user->role === 'admin') {
                wp_redirect(site_url('/index.php/admin-dashboard'));
                exit;
            } elseif ($user->role === 'tutor') {
                wp_redirect(site_url('/index.php/tutor-dashboard'));
                exit;
            }
        } else {
            echo '<p style="color:red;">Login failed. Please check your credentials or register through the sign up button.</p>';
        }
    }

    if (isset($_POST['gtp_register_submit'])) {
        wp_redirect(site_url('/index.php/registration-page'));
    }

    ob_start();
    ?>
    <form method="post" style="max-width:400px; margin:30px auto; padding:15px; background:#f9f9f9; border-radius:8px;">
        <h2>Login</h2>
        <p><input type="text" name="username" placeholder="Username" required style="width:90%; padding:10px; margin-bottom:10px;"></p>
        <p><input type="password" name="password" placeholder="Password" required style="width:90%; padding:10px; margin-bottom:10px;"></p>
        <p><input type="submit" name="gtp_login_submit" value="Sign In" style="padding:10px 20px; background:#0073aa; color:white; border:none; cursor:pointer;"></p>
    </form>

    <form method="post" style="max-width:400px; margin:10px auto; padding:0px; background:#ffffff; border-radius:8px;">
        <p><input type="submit" name="gtp_register_submit" value="Register Here!" style="padding:10px 20px; background:#0073aa; color:white; border:none; cursor:pointer;"></p>

    </form>
    <?php
    return ob_get_clean();
}

register_activation_hook(__FILE__, 'gtp_create_required_pages');

function gtp_create_required_pages() {
    $pages = [
        'Welcome-to-GTP' => '[gtp_login]',
        'admin-dashboard' => '<h2>Welcome Admin!</h2>',
        'tutor-dashboard' => '<h2>Welcome Teaching Assistant!</h2>',
        'registration-page' => '<h2>Register your GTP account here</h2>',
    ];

    foreach ($pages as $slug => $content) {
        if (!get_page_by_path($slug)) {
            wp_insert_post([
                'post_title'   => ucwords(str_replace('-', ' ', $slug)),
                'post_name'    => $slug,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ]);
        }
    }
}