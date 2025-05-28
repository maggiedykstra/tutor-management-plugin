<?php

add_action('admin_menu', function () {
    add_menu_page(
        'Tutor Dashboard',
        'Tutor Management',
        'manage_options',
        'tutor-dashboard',
        'render_tutor_dashboard',
        'dashicons-groups',
        20
    );
});

function render_tutor_dashboard() {
    include plugin_dir_path(__FILE__) . '../templates/dashboard.php';
}
