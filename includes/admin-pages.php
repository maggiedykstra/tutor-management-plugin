
<?php

// Register the admin menu
add_action('admin_menu', function () {
    add_menu_page(
        'Tutor Dashboard',              // Page title
        'Tutor Management',             // Menu title
        'manage_options',               // Capability
        'tutor-dashboard',              // Menu slug
        'render_tutor_dashboard',       // Callback function
        'dashicons-groups',             // Icon
        20                              // Position
    );
});

// Load the admin dashboard template
function render_tutor_dashboard() {
    include plugin_dir_path(__FILE__) . '../templates/dashboard.php';
}

// Enqueue CSS and JS for this admin page
function tutor_management_enqueue_assets($hook) {
    if ($hook !== 'toplevel_page_tutor-dashboard') return;

    wp_enqueue_style('tutor-style', plugin_dir_url(__FILE__) . '../assets/style.css');
    wp_enqueue_script('tutor-script', plugin_dir_url(__FILE__) . '../assets/script.js', array('jquery'), null, true);
}

add_action('admin_enqueue_scripts', 'tutor_management_enqueue_assets');