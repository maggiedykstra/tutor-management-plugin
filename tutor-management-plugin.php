<?php
/*
Plugin Name: Tutor Management Plugin
Description: A custom plugin for managing tutors and students.
Version: 1.0
Author: Maggie Dykstra
*/

defined('ABSPATH') or die('No script kiddies please!');

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

// Create custom table on plugin activation
register_activation_hook(__FILE__, 'gtp_create_users_table');
function gtp_create_users_table() {
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

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
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
            // Set session or cookie if needed
            if ($user->role === 'admin') {
                wp_redirect(site_url('/admin-dashboard'));
                exit;
            } elseif ($user->role === 'tutor') {
                wp_redirect(site_url('/tutor-dashboard'));
                exit;
            }
        } else {
            echo '<p style="color:red;">Login failed. Please check your credentials.</p>';
        }
    }

    ob_start();
    ?>
    <form method="post" style="max-width:400px; margin:40px auto; padding:20px; background:#f9f9f9; border-radius:8px;">
        <h2>Welcome GTP Tutors & Admins</h2>
        <p><input type="text" name="username" placeholder="Username" required style="width:100%; padding:10px; margin-bottom:10px;"></p>
        <p><input type="password" name="password" placeholder="Password" required style="width:100%; padding:10px; margin-bottom:10px;"></p>
        <p><input type="submit" name="gtp_login_submit" value="Sign In" style="padding:10px 20px; background:#0073aa; color:white; border:none; cursor:pointer;"></p>
    </form>
    <?php
    return ob_get_clean();
}
