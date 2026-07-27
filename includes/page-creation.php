<?php
add_action('init', 'gtp_create_required_pages');

// delete_option('gtp_pages_created');

/**
 * Slugs for all front-end pages owned by this plugin.
 * Stored lowercase — WordPress normalizes post_name to lowercase, so
 * is_page('TA-dashboard') fails against post_name 'ta-dashboard'.
 */
function gtp_plugin_page_slugs() {
    return [
        'welcome-to-gtp',
        'admin-dashboard',
        'ta-dashboard',
        'registration-page',
        'registration-confirmation',
        'ta-profile',
        'log-session',
        'log-substitute',
        'new-ta-registration',
        'ta-session-filter',
        'spreadsheet-view',
        'add-classroom',
        'edit-classrooms',
        'my-logged-sessions',
        'my-classes',
        'validate-tas',
        'add-user',
        'admin-profile',
        'set-password',
        'manage-announcements',
        'announcements',
        'flagged-sessions',
        'manage-people',
        'unassigned-tutors',
        'tutor-resources',
        'monthly-checkins',
        'admin-checkins',
        'manage-semesters',
        'reports',
        'report-site-bug',
        'site-bugs',
    ];
}

/** True on any GTP front-end page (case-insensitive slug match). */
function gtp_is_plugin_page() {
    if (is_admin() || !is_singular('page')) {
        return false;
    }
    $slug = strtolower((string) get_post_field('post_name', get_queried_object_id()));
    return $slug !== '' && in_array($slug, gtp_plugin_page_slugs(), true);
}

function gtp_is_auth_page() {
    if (!gtp_is_plugin_page()) {
        return false;
    }
    $slug = strtolower((string) get_post_field('post_name', get_queried_object_id()));
    return in_array($slug, ['welcome-to-gtp', 'registration-page', 'registration-confirmation', 'set-password'], true);
}

/**
 * Human-friendly WP page titles (front-end still hides these).
 */
function gtp_plugin_page_titles() {
    return [
        'Welcome-to-GTP' => 'Sign in',
        'admin-dashboard' => 'Admin Dashboard',
        'TA-dashboard' => 'TA Dashboard',
        'registration-page' => 'Create an account',
        'registration-confirmation' => 'Registration received',
        'TA-profile' => 'My Profile',
        'admin-profile' => 'My Profile',
        'log-session' => 'Log Session',
        'log-substitute' => 'Log Substitute Session',
        'my-logged-sessions' => 'My Logged Sessions',
        'my-classes' => 'My Classes',
        'set-password' => 'Set Password',
        'tutor-resources' => 'Tutor Resources',
        'monthly-checkins' => 'Monthly Check-ins',
        'announcements' => 'Announcements',
        'report-site-bug' => 'Report Site Bug',
        'site-bugs' => 'Site Bugs',
        'validate-tas' => 'Approve Registrations',
    ];
}

function gtp_create_required_pages() {
    $pages = [
        'Welcome-to-GTP'        => '<!-- wp:shortcode -->[gtp_login]<!-- /wp:shortcode -->',
        'admin-dashboard'       => '<!-- wp:shortcode -->[gtp_admin_dashboard]<!-- /wp:shortcode -->',
        'TA-dashboard'          => '<!-- wp:shortcode -->[gtp_TA_dashboard]<!-- /wp:shortcode -->',
        'registration-page'     => '<!-- wp:shortcode -->[gtp_registration_page]<!-- /wp:shortcode -->',
        'registration-confirmation' => '<!-- wp:shortcode -->[gtp_registration_confirmation]<!-- /wp:shortcode -->',

        // Newly added pages
        'TA-profile'            => '<!-- wp:shortcode -->[gtp_ta_profile]<!-- /wp:shortcode -->',
        'log-session'           => '<!-- wp:shortcode -->[gtp_log_session]<!-- /wp:shortcode -->',
        'log-substitute'        => '<!-- wp:shortcode -->[gtp_log_substitute_session]<!-- /wp:shortcode -->',
        'new-ta-registration'   => '<!-- wp:shortcode -->[gtp_add_ta]<!-- /wp:shortcode -->',
        'ta-session-filter'     => '<!-- wp:shortcode -->[gtp_session_filter]<!-- /wp:shortcode -->',
        'spreadsheet-view'      => '<!-- wp:shortcode -->[gtp_spreadsheet_view]<!-- /wp:shortcode -->',
        'add-classroom'         => '<!-- wp:shortcode -->[gtp_add_classroom]<!-- /wp:shortcode -->',
        'edit-classrooms'       => '<!-- wp:shortcode -->[gtp_edit_classrooms]<!-- /wp:shortcode -->',
        'my-logged-sessions'    => '<!-- wp:shortcode -->[gtp_my_logged_sessions]<!-- /wp:shortcode -->',
        'my-classes'            => '<!-- wp:shortcode -->[gtp_my_classes]<!-- /wp:shortcode -->',
        'validate-tas'            => '<!-- wp:shortcode -->[gtp_validate_tas]<!-- /wp:shortcode -->',
        'add-user'            => '<!-- wp:shortcode -->[gtp_add_user]<!-- /wp:shortcode -->',
        'admin-profile'       => '<!-- wp:shortcode -->[gtp_admin_profile]<!-- /wp:shortcode -->',
        'set-password'        => '<!-- wp:shortcode -->[gtp_set_password]<!-- /wp:shortcode -->',
        'manage-announcements'=> '<!-- wp:shortcode -->[gtp_manage_announcements]<!-- /wp:shortcode -->',
        'announcements'       => '<!-- wp:shortcode -->[gtp_announcements_inbox]<!-- /wp:shortcode -->',
        'flagged-sessions'    => '<!-- wp:shortcode -->[gtp_flagged_sessions]<!-- /wp:shortcode -->',
        'manage-people'       => '<!-- wp:shortcode -->[gtp_manage_people]<!-- /wp:shortcode -->',
        'unassigned-tutors'   => '<!-- wp:shortcode -->[gtp_unassigned_tutors]<!-- /wp:shortcode -->',
        'tutor-resources'     => '<!-- wp:shortcode -->[gtp_tutor_resources]<!-- /wp:shortcode -->',
        'monthly-checkins'    => '<!-- wp:shortcode -->[gtp_monthly_checkins]<!-- /wp:shortcode -->',
        'admin-checkins'      => '<!-- wp:shortcode -->[gtp_admin_checkins]<!-- /wp:shortcode -->',
        'manage-semesters'    => '<!-- wp:shortcode -->[gtp_manage_semesters]<!-- /wp:shortcode -->',
        'reports'             => '<!-- wp:shortcode -->[gtp_reports]<!-- /wp:shortcode -->',
        'report-site-bug'     => '<!-- wp:shortcode -->[gtp_report_site_bug]<!-- /wp:shortcode -->',
        'site-bugs'           => '<!-- wp:shortcode -->[gtp_site_bugs]<!-- /wp:shortcode -->',
    ];

    $titles = gtp_plugin_page_titles();

    foreach ($pages as $slug => $content) {
        $existing = get_page_by_path($slug, OBJECT, 'page');
        $title = $titles[$slug] ?? ucwords(str_replace('-', ' ', $slug));
        if (!$existing || $existing->post_status === 'trash') {
            // Invite links must work without a logged-in WordPress user
            $status = ($slug === 'set-password' || $slug === 'Welcome-to-GTP' || $slug === 'registration-page' || $slug === 'registration-confirmation')
                ? 'publish'
                : 'private';
            wp_insert_post([
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => $content,
                'post_status'  => $status,
                'post_type'    => 'page'
            ]);
        } else {
            $update = [];
            if ($slug === 'set-password' && $existing->post_status !== 'publish') {
                $update['post_status'] = 'publish';
                $update['post_content'] = $content;
            }
            if ($slug === 'registration-confirmation' && $existing->post_content !== $content) {
                $update['post_content'] = $content;
                $update['post_status'] = 'publish';
            }
            // Keep WP titles clean (TA Profile → My Profile, etc.)
            if (isset($titles[$slug]) && $existing->post_title !== $titles[$slug]) {
                $update['post_title'] = $titles[$slug];
            }
            if ($update) {
                $update['ID'] = $existing->ID;
                wp_update_post($update);
            }
        }
    }
}

/**
 * Hide the huge WordPress page title on plugin screens (content has its own headings).
 */
add_filter('the_title', 'gtp_hide_plugin_page_titles', 10, 2);
function gtp_hide_plugin_page_titles($title, $id = 0) {
    if (is_admin()) {
        return $title;
    }

    // Don't blank titles in menus/widgets — only the main page heading
    if (in_the_loop() && is_main_query() && gtp_is_plugin_page()) {
        return '';
    }

    if ($id && get_post_status($id) === 'private') {
        $title = str_replace(['Private: ', 'Private:'], '', $title);
    }

    return $title;
}

/** Browser tab: site name only — never show WP page titles as chrome. */
add_filter('pre_get_document_title', 'gtp_plugin_document_title', 20);
function gtp_plugin_document_title($title) {
    if (gtp_is_plugin_page()) {
        return get_bloginfo('name', 'display');
    }
    return $title;
}

add_filter('body_class', 'gtp_plugin_page_body_class');
function gtp_plugin_page_body_class($classes) {
    if (gtp_is_plugin_page()) {
        $classes[] = 'gtp-plugin-page';
        $classes[] = 'gtp-hide-page-title';
        $classes[] = 'gtp-hide-header';
        if (gtp_is_auth_page()) {
            $classes[] = 'gtp-auth-screen';
        }
    }
    return $classes;
}

add_action('wp_enqueue_scripts', 'gtp_enqueue_plugin_page_chrome_css');
function gtp_enqueue_plugin_page_chrome_css() {
    if (!gtp_is_plugin_page()) {
        return;
    }

    wp_enqueue_style(
        'gtp-theme',
        plugins_url('assets/css/gtp-theme.css', dirname(__DIR__) . '/tutor-management-plugin.php'),
        [],
        '3.3'
    );

    wp_enqueue_script(
        'gtp-flash-messages',
        plugins_url('assets/js/gtp-flash-messages.js', dirname(__DIR__) . '/tutor-management-plugin.php'),
        [],
        '1.0',
        true
    );

    $css = '
        /* Hide theme chrome — Set Password should not appear in site nav */
        body.gtp-hide-header .wp-block-template-part,
        body.gtp-hide-header header.wp-block-template-part,
        body.gtp-hide-header .wp-block-site-title,
        body.gtp-hide-header header.wp-block-header,
        body.gtp-hide-header header.wp-block-group,
        body.gtp-hide-header .wp-block-navigation,
        body.gtp-hide-header footer,
        body.gtp-hide-header .wp-block-template-part[data-area="header"],
        body.gtp-hide-header .wp-block-template-part[data-area="footer"],
        body.gtp-auth-screen header,
        body.gtp-auth-screen footer,
        body.gtp-auth-screen .wp-block-navigation,
        body.gtp-auth-screen .wp-block-page-list,
        body.gtp-auth-screen nav {
            display: none !important;
        }

        body.gtp-hide-page-title .entry-title,
        body.gtp-hide-page-title .page-title,
        body.gtp-hide-page-title .wp-block-post-title,
        body.gtp-hide-page-title h1.wp-block-post-title,
        body.gtp-hide-page-title .post-title,
        body.gtp-hide-page-title header.entry-header,
        body.gtp-hide-page-title .entry-header,
        body.gtp-plugin-page .wp-block-post-title,
        body.gtp-plugin-page .wp-block-breadcrumbs,
        body.gtp-plugin-page .breadcrumb,
        body.gtp-plugin-page .breadcrumbs {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            visibility: hidden !important;
        }

        /* Pull content flush to the top (TT5 page template uses spacing|60 on main) */
        body.gtp-plugin-page {
            --wp--style--root--padding-top: 0px !important;
            --wp--style--root--padding-left: 0px !important;
            --wp--style--root--padding-right: 0px !important;
        }

        body.gtp-plugin-page .wp-site-blocks {
            padding-top: 0 !important;
            margin-top: 0 !important;
        }

        body.gtp-plugin-page .wp-site-blocks > * {
            margin-block-start: 0 !important;
        }

        body.gtp-plugin-page main,
        body.gtp-plugin-page main.wp-block-group,
        body.gtp-plugin-page .entry-content,
        body.gtp-plugin-page .wp-block-post-content,
        body.gtp-plugin-page .wp-block-group.is-layout-constrained,
        body.gtp-plugin-page .wp-block-group.alignwide,
        body.gtp-plugin-page .wp-block-group.alignfull {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        body.gtp-plugin-page .wp-block-post-featured-image {
            display: none !important;
            margin: 0 !important;
        }

        body.gtp-plugin-page .gtp-home {
            padding-top: 12px;
        }

        .gtp-logout-bar {
            display: none;
        }

        .gtp-logout-bar a {
            display: none;
        }

        .gtp-logout-bar a:hover {
            display: none;
        }

        body.gtp-plugin-page.has-gtp-logout .gtp-home,
        body.gtp-plugin-page.has-gtp-logout .entry-content {
            padding-right: 0;
        }
    ';
    wp_register_style('gtp-plugin-chrome', false, ['gtp-theme'], '1.5');
    wp_enqueue_style('gtp-plugin-chrome');
    wp_add_inline_style('gtp-plugin-chrome', $css);
}

/** Remove WP page title / header / footer blocks on plugin pages (slug-case safe). */
add_filter('render_block', 'gtp_strip_theme_chrome_blocks', 10, 2);
function gtp_strip_theme_chrome_blocks($block_content, $block) {
    if (!gtp_is_plugin_page()) {
        return $block_content;
    }
    $name = $block['blockName'] ?? '';
    if ($name === 'core/post-title') {
        return '';
    }
    if ($name === 'core/template-part') {
        $slug = $block['attrs']['slug'] ?? '';
        if (in_array($slug, ['header', 'footer'], true)) {
            return '';
        }
    }
    if ($name === 'core/site-title' || $name === 'core/site-logo' || $name === 'core/navigation') {
        return '';
    }
    return $block_content;
}

/**
 * Keep utility auth pages out of menus / page lists.
 */
function gtp_utility_page_slugs() {
    return ['set-password', 'registration-confirmation'];
}

add_filter('wp_nav_menu_objects', 'gtp_exclude_utility_pages_from_menus', 10, 2);
function gtp_exclude_utility_pages_from_menus($items, $args) {
    $blocked_slugs = gtp_utility_page_slugs();
    $blocked_titles = ['Set Password', 'Registration Confirmation', 'Registration confirmation'];
    foreach ($items as $key => $item) {
        $url = isset($item->url) ? (string) $item->url : '';
        $title = isset($item->title) ? (string) $item->title : '';
        $object_slug = '';
        if (!empty($item->object_id)) {
            $object_slug = (string) get_post_field('post_name', (int) $item->object_id);
        }
        foreach ($blocked_slugs as $slug) {
            if ($object_slug === $slug || stripos($url, $slug) !== false) {
                unset($items[$key]);
                continue 2;
            }
        }
        foreach ($blocked_titles as $blocked_title) {
            if (strcasecmp($title, $blocked_title) === 0) {
                unset($items[$key]);
                continue 2;
            }
        }
    }
    return array_values($items);
}

add_filter('wp_list_pages_excludes', 'gtp_exclude_utility_page_ids');
function gtp_exclude_utility_page_ids($exclude) {
    foreach (gtp_utility_page_slugs() as $slug) {
        $page = get_page_by_path($slug);
        if ($page) {
            $exclude[] = (int) $page->ID;
        }
    }
    return $exclude;
}

add_filter('get_pages', 'gtp_exclude_utility_pages_from_get_pages', 10, 2);
function gtp_exclude_utility_pages_from_get_pages($pages, $args) {
    if (!is_array($pages)) {
        return $pages;
    }
    $blocked = gtp_utility_page_slugs();
    return array_values(array_filter($pages, static function ($page) use ($blocked) {
        return empty($page->post_name) || !in_array($page->post_name, $blocked, true);
    }));
}
