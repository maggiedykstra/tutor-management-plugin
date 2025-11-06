<?php
add_action('init', 'gtp_create_required_pages');

// delete_option('gtp_pages_created');


function gtp_create_required_pages() {
    $pages = [
        'Welcome-to-GTP'        => '<!-- wp:shortcode -->[gtp_login]<!-- /wp:shortcode -->',
        'admin-dashboard'       => '<!-- wp:shortcode -->[gtp_admin_dashboard]<!-- /wp:shortcode -->',
        'TA-dashboard'          => '<!-- wp:shortcode -->[gtp_TA_dashboard]<!-- /wp:shortcode -->',
        'registration-page'     => '<button onclick="history.back()">Go Back</button><!-- wp:shortcode -->[gtp_registration_page]<!-- /wp:shortcode -->',
        'registration-confirmation' => 'GTP administrators are reviewing your registration. Once approved, you will be able to log in!',

        // Newly added pages
        'TA-profile'            => '<!-- wp:shortcode -->[gtp_ta_profile]<!-- /wp:shortcode -->',
        'log-session'           => '<!-- wp:shortcode -->[gtp_log_session]<!-- /wp:shortcode -->',
        'log-substitute'        => '<!-- wp:shortcode -->[gtp_log_substitute_session]<!-- /wp:shortcode -->',
        'new-ta-registration'   => '<button onclick="history.back()">Go Back</button><h2>Validate or Register a New TA</h2>',
        'new-ta-registration'   => '<!-- wp:shortcode -->[gtp_add_ta]<!-- /wp:shortcode -->',
        'ta-session-filter'     => '<!-- wp:shortcode -->[gtp_session_filter]<!-- /wp:shortcode -->',
        'add-classroom'         => '<!-- wp:shortcode -->[gtp_add_classroom]<!-- /wp:shortcode -->',
        'edit-classrooms'       => '<!-- wp:shortcode -->[gtp_edit_classrooms]<!-- /wp:shortcode -->',
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
    if (is_page('ta-session-filter') && !is_admin()) {
        return '';
    }
    if (get_post_status($id) === 'private') {
        $title = str_replace('Private: ', '', $title);
    }
    return $title;
}



