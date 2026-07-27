<?php
// Runs early on every page load
add_action('template_redirect', 'gtp_handle_page_access');

function gtp_handle_page_access() {
    if (!is_page()) return;

    $page_slug = get_post_field('post_name', get_post());

    // Public pages shouldn't require login
    $public_pages = ['welcome-to-gtp', 'registration-page', 'registration-confirmation', 'set-password'];
    if (in_array($page_slug, $public_pages, true)) return;

    // Now only dashboard pages should be locked:
    if ($page_slug === 'tutor-dashboard' && $role !== 'tutor') {
        wp_redirect(site_url('/welcome-to-gtp'));
        exit;
    }
}

function gtp_is_gtp_admin($username) {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_users';
    $role = $wpdb->get_var($wpdb->prepare("SELECT role FROM $table WHERE username = %s", $username));
    return $role === 'admin';
}

function gtp_is_gtp_tutor($username) {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_users';
    $role = $wpdb->get_var($wpdb->prepare("SELECT role FROM $table WHERE username = %s", $username));
    return $role === 'tutor';
}

/**
 * Normalize a time input to MySQL TIME (HH:MM:SS) or null.
 * Accepts HH:MM or HH:MM:SS from <input type="time">.
 */
function gtp_sanitize_time($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
        return null;
    }

    $hour = (int) $matches[1];
    $minute = (int) $matches[2];
    $second = isset($matches[3]) ? (int) $matches[3] : 0;

    if ($hour > 23 || $minute > 59 || $second > 59) {
        return null;
    }

    return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

/** Value for <input type="time"> (HH:MM). */
function gtp_time_input_value($time) {
    if ($time === null || $time === '') {
        return '';
    }

    if (preg_match('/^(\d{2}):(\d{2})/', (string) $time, $matches)) {
        return $matches[1] . ':' . $matches[2];
    }

    $timestamp = strtotime((string) $time);
    return $timestamp ? date('H:i', $timestamp) : '';
}

/** Display a single time as 3:05 PM. */
function gtp_format_time_display($time) {
    if ($time === null || $time === '') {
        return '';
    }

    $timestamp = strtotime((string) $time);
    return $timestamp ? date('g:i A', $timestamp) : '';
}

/** Display a start/end range, e.g. 3:00 PM – 4:15 PM. */
function gtp_format_time_range($start_time, $end_time) {
    $start = gtp_format_time_display($start_time);
    $end = gtp_format_time_display($end_time);

    if ($start && $end) {
        return $start . ' – ' . $end;
    }

    return $start ?: $end;
}

/**
 * Dashboard home URL for the current (or given) GTP role.
 */
function gtp_dashboard_url($role = null) {
    if ($role === null) {
        $role = $_SESSION['gtp_user']['role'] ?? '';
    }
    if ($role === 'admin') {
        return site_url('/index.php/admin-dashboard/');
    }
    return site_url('/index.php/TA-dashboard/');
}

/**
 * Label for dashboard back control.
 */
function gtp_dashboard_back_label($role = null) {
    if ($role === null) {
        $role = $_SESSION['gtp_user']['role'] ?? '';
    }
    return $role === 'admin' ? 'Admin Dashboard' : 'TA Dashboard';
}

/**
 * Standardized back-to-dashboard link HTML.
 */
function gtp_dashboard_back_link($role = null) {
    $role = $role ?: ($_SESSION['gtp_user']['role'] ?? '');
    $url = gtp_dashboard_url($role);
    $label = gtp_dashboard_back_label($role);
    return '<p class="gtp-page-back"><a class="gtp-back-link" href="' . esc_url($url) . '">' . esc_html($label) . '</a></p>';
}

/**
 * Compact page title markup used on subpages.
 */
function gtp_page_title($title) {
    return '<h1 class="gtp-page-title">' . esc_html($title) . '</h1>';
}

/**
 * Flash banner HTML. Success/errors auto-dismiss unless $persist is true
 * (use persist for "please fill this out" style validation).
 */
function gtp_msg_html($text, $type = 'success', $persist = false) {
    $type = in_array($type, ['success', 'error', 'warn'], true) ? $type : 'success';
    $classes = 'gtp-msg is-' . $type;
    if ($persist) {
        $classes .= ' gtp-persist';
    }
    return '<p class="' . esc_attr($classes) . '">' . $text . '</p>';
}

/**
 * Which TA profile pieces are still missing (college, photo, bio, subject preferences).
 * Returns a list of human-readable labels, or empty array if complete.
 */
function gtp_tutor_incomplete_profile_parts($tutor) {
    if (!$tutor) {
        return ['college', 'profile picture', 'bio', 'subject preferences'];
    }

    $missing = [];
    if (trim((string) ($tutor->school ?? '')) === '') {
        $missing[] = 'college';
    }
    if (empty($tutor->headshot_url)) {
        $missing[] = 'profile picture';
    }
    if (trim((string) ($tutor->bio ?? '')) === '') {
        $missing[] = 'bio';
    }

    $prefs = json_decode((string) ($tutor->subject_preferences ?? ''), true);
    if (!is_array($prefs) || empty($prefs)) {
        $missing[] = 'subject preferences';
    }

    return $missing;
}

