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

/** Ordered weekday keys used for classroom schedules. */
function gtp_meeting_day_options() {
    return [
        'M' => 'M',
        'T' => 'T',
        'W' => 'W',
        'TH' => 'TH',
        'F' => 'F',
    ];
}

/**
 * Normalize meeting days from POST/array/string into ordered unique keys.
 *
 * @param mixed $raw
 * @return string[]
 */
function gtp_normalize_meeting_days($raw) {
    $allowed = array_keys(gtp_meeting_day_options());
    if (is_string($raw)) {
        $raw = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    }
    if (!is_array($raw)) {
        return [];
    }
    $days = [];
    foreach ($raw as $day) {
        $day = strtoupper(trim((string) $day));
        // Accept common aliases
        if ($day === 'THU' || $day === 'THURS' || $day === 'R') {
            $day = 'TH';
        }
        if (in_array($day, $allowed, true) && !in_array($day, $days, true)) {
            $days[] = $day;
        }
    }
    // Keep canonical order
    return array_values(array_filter($allowed, static function ($key) use ($days) {
        return in_array($key, $days, true);
    }));
}

/** Store-ready comma string, e.g. "M,W,F". */
function gtp_meeting_days_to_storage($raw) {
    return implode(',', gtp_normalize_meeting_days($raw));
}

/** Human label, e.g. "M, W, F". */
function gtp_format_meeting_days($raw) {
    return implode(', ', gtp_normalize_meeting_days($raw));
}

/**
 * Days + time for a classroom, e.g. "M, W · 3:00 PM – 4:00 PM".
 *
 * @param object|array|null $classroom
 */
function gtp_format_classroom_schedule($classroom) {
    if (!$classroom) {
        return '';
    }
    $classroom = (object) $classroom;
    $days = gtp_format_meeting_days($classroom->meeting_days ?? '');
    $time = gtp_format_time_range($classroom->start_time ?? null, $classroom->end_time ?? null);
    if ($days && $time) {
        return $days . ' · ' . $time;
    }
    return $days ?: $time;
}

/** Whether a classroom is marked as a Block (one-semester) class. */
function gtp_classroom_is_block($classroom) {
    if (is_object($classroom)) {
        return !empty($classroom->is_block);
    }
    if (is_array($classroom)) {
        return !empty($classroom['is_block']);
    }
    return !empty($classroom);
}

/**
 * Classroom subject/name for display, with "(Block)" when applicable.
 *
 * @param object|array|string $classroom_or_subject Classroom row or subject string
 * @param bool|null $is_block Only used when first arg is a subject string
 */
function gtp_format_classroom_subject($classroom_or_subject, $is_block = null) {
    if (is_object($classroom_or_subject) || is_array($classroom_or_subject)) {
        $row = (object) $classroom_or_subject;
        $subject = (string) ($row->subject ?? '');
        $block = gtp_classroom_is_block($row);
    } else {
        $subject = (string) $classroom_or_subject;
        $block = (bool) $is_block;
    }
    if ($subject === '') {
        return '';
    }
    return $block ? ($subject . ' (Block)') : $subject;
}

/**
 * Render M–F checkboxes for classroom forms.
 *
 * @param array $args {
 *   @type string $name     Input name (default meeting_days[])
 *   @type array|string $selected
 *   @type string $id_prefix
 * }
 */
function gtp_render_meeting_days_checkboxes($args = []) {
    $name = $args['name'] ?? 'meeting_days[]';
    $selected = gtp_normalize_meeting_days($args['selected'] ?? []);
    $id_prefix = $args['id_prefix'] ?? 'gtp-day';
    $class = $args['class'] ?? 'gtp-meeting-days';

    ob_start();
    ?>
    <div class="<?php echo esc_attr($class); ?>">
        <?php foreach (gtp_meeting_day_options() as $key => $label) :
            $id = $id_prefix . '-' . strtolower($key);
            ?>
            <label class="gtp-meeting-day" for="<?php echo esc_attr($id); ?>">
                <input type="checkbox"
                       id="<?php echo esc_attr($id); ?>"
                       name="<?php echo esc_attr($name); ?>"
                       value="<?php echo esc_attr($key); ?>"
                       <?php checked(in_array($key, $selected, true)); ?>>
                <span><?php echo esc_html($label); ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
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

