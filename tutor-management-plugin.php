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
        <p>This is your admin panel. Click below to sign in.</p>
        <a href="<?php echo wp_login_url(); ?>" class="tutor-signin-button">Sign In</a>
    </div>
    <?php
}
