<?php
add_action('init', 'gtp_create_pages_once');

function gtp_create_pages_once() {
    if (!get_option('gtp_pages_created')) {
        gtp_create_required_pages();
        update_option('gtp_pages_created', true); // Prevent future runs
    }
}

// // use below to reset the pages if you delete then
// delete_option('gtp_pages_created');


function gtp_create_required_pages() {
    $pages = [
        'Welcome-to-GTP'        => '<!-- wp:shortcode -->[gtp_login]<!-- /wp:shortcode -->',
        'admin-dashboard'       => '<!-- wp:shortcode -->[gtp_admin_dashboard]<!-- /wp:shortcode -->',
        'TA-dashboard'          => '<!-- wp:shortcode -->[gtp_TA_dashboard]<!-- /wp:shortcode -->',
        'registration-page'     => '<button onclick="history.back()">Go Back</button><!-- wp:shortcode -->[gtp_registration_page]<!-- /wp:shortcode -->',
        'registration-confirmation' => 'GTP administrators are reviewing your registration. Once approved, you will be able to log in!',

        // Newly added pages
        'TA-profile'            => '<button onclick="history.back()">Go Back</button><h2>My Profile (TA)</h2>',
        'log-session'           => '<!-- wp:shortcode -->[gtp_log_session]<!-- /wp:shortcode -->',
        'log-substitute'        => '<button onclick="history.back()">Go Back</button><h2>Log Substitute Session</h2>',
        'new-ta-registration'   => '<button onclick="history.back()">Go Back</button><h2>Validate or Register a New TA</h2>',
        'ta-session-filter'     => '<button onclick="history.back()">Go Back</button><h2>Filter and Review TA Sessions</h2>',
        'add-classroom'         => '<!-- wp:shortcode -->[gtp_add_classroom]<!-- /wp:shortcode -->',
    ];

    foreach ($pages as $slug => $content) {
        $existing = get_page_by_path($slug, OBJECT, 'page');
        if (!$existing || $existing->post_status === 'trash') {
            wp_insert_post([
                'post_title'   => ucwords(str_replace('-', ' ', $slug)),
                'post_name'    => $slug,
                'post_content' => $content,
                'post_status'  => 'private',
                'post_type'    => 'page'
            ]);
        }
    }
}


// Remove "Private:" prefix from page titles for cleaner UI
add_filter('the_title', 'gtp_remove_private_prefix', 10, 2);

function gtp_remove_private_prefix($title, $id) {
    if (get_post_status($id) === 'private') {
        $title = str_replace('Private: ', '', $title);
    }
    return $title;
}



