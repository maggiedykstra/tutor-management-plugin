<?php
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


add_action('template_redirect', function () {
    // List of page slugs you want to protect
    $protected_pages = ['admin-dashboard', 'tutor-dashboard', 'registration-page'];

    if (is_page($protected_pages)) {
        // Check if user is logged in via GTP (custom DB)

    }
});