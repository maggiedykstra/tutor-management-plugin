<?php
function gtp_format_student_display_name($first_name, $last_name = '') {
    return trim(trim((string) $first_name) . ' ' . trim((string) $last_name));
}

add_action('wp_ajax_gtp_get_students_for_classroom', 'gtp_get_students_for_classroom');
add_action('wp_ajax_nopriv_gtp_get_students_for_classroom', 'gtp_get_students_for_classroom');
function gtp_get_students_for_classroom() {
    global $wpdb;

    if (!isset($_SESSION['gtp_user'])) {
        wp_send_json_error('Not logged in.');
    }

    $classroom_id = intval($_POST['classroom_id']);
    $table_name = $wpdb->prefix . 'gtp_students';

    $students = $wpdb->get_results($wpdb->prepare(
        "SELECT id, first_name, last_name, student_name
         FROM $table_name
         WHERE classroom_id = %d
         ORDER BY last_name ASC, first_name ASC, student_name ASC",
        $classroom_id
    ));

    foreach ($students as $student) {
        $student->display_name = gtp_format_student_display_name(
            $student->first_name ?: $student->student_name,
            $student->last_name
        );
    }

    wp_send_json_success($students);
}

add_action('wp_ajax_gtp_add_student_to_roster', 'gtp_add_student_to_roster');
add_action('wp_ajax_nopriv_gtp_add_student_to_roster', 'gtp_add_student_to_roster');
function gtp_add_student_to_roster() {
    global $wpdb;

    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        wp_send_json_error('Not logged in or not a tutor.');
    }

    $classroom_id = intval($_POST['classroom_id']);
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['last_name'] ?? '');

    // Back-compat if a single student_name is still posted
    if ($first_name === '' && !empty($_POST['student_name'])) {
        $parts = preg_split('/\s+/', sanitize_text_field($_POST['student_name']), 2);
        $first_name = $parts[0] ?? '';
        $last_name = $parts[1] ?? '';
    }

    if ($first_name === '') {
        wp_send_json_error('Student first name cannot be empty.');
    }

    if (!$classroom_id) {
        wp_send_json_error('Please select a class first.');
    }

    $student_name = gtp_format_student_display_name($first_name, $last_name);
    $table_name = $wpdb->prefix . 'gtp_students';

    $inserted = $wpdb->insert($table_name, [
        'classroom_id' => $classroom_id,
        'first_name'   => $first_name,
        'last_name'    => $last_name !== '' ? $last_name : null,
        'student_name' => $student_name,
        'date_added'   => current_time('mysql'),
    ]);

    if ($inserted === false || !$wpdb->insert_id) {
        wp_send_json_error('Could not add student: ' . $wpdb->last_error);
    }

    wp_send_json_success([
        'id' => (int) $wpdb->insert_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'student_name' => $student_name,
        'display_name' => $student_name,
    ]);
}

add_action('wp_ajax_gtp_update_student_name', 'gtp_update_student_name');
add_action('wp_ajax_nopriv_gtp_update_student_name', 'gtp_update_student_name');
function gtp_update_student_name() {
    global $wpdb;

    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        wp_send_json_error('Not logged in or not a tutor.');
    }

    $student_id = intval($_POST['student_id']);
    $classroom_id = intval($_POST['classroom_id']);
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['last_name'] ?? '');
    $tutor_id = $_SESSION['gtp_user']['id'];

    if ($first_name === '' && !empty($_POST['student_name'])) {
        $parts = preg_split('/\s+/', sanitize_text_field($_POST['student_name']), 2);
        $first_name = $parts[0] ?? '';
        $last_name = $parts[1] ?? '';
    }

    if ($first_name === '') {
        wp_send_json_error('Student first name cannot be empty.');
    }

    if (!gtp_tutor_assigned_to_classroom($tutor_id, $classroom_id)) {
        wp_send_json_error('You are not assigned to this class.');
    }

    $student_name = gtp_format_student_display_name($first_name, $last_name);
    $table_name = $wpdb->prefix . 'gtp_students';
    $updated = $wpdb->update(
        $table_name,
        [
            'first_name' => $first_name,
            'last_name' => $last_name !== '' ? $last_name : null,
            'student_name' => $student_name,
        ],
        ['id' => $student_id, 'classroom_id' => $classroom_id]
    );

    if ($updated === false) {
        wp_send_json_error('Could not update student: ' . $wpdb->last_error);
    }

    wp_send_json_success([
        'id' => $student_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'student_name' => $student_name,
        'display_name' => $student_name,
    ]);
}

add_action('wp_ajax_gtp_remove_student_from_class', 'gtp_remove_student_from_class');
add_action('wp_ajax_nopriv_gtp_remove_student_from_class', 'gtp_remove_student_from_class');
function gtp_remove_student_from_class() {
    global $wpdb;

    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        wp_send_json_error('Not logged in or not a tutor.');
    }

    $student_id = intval($_POST['student_id']);
    $classroom_id = intval($_POST['classroom_id']);
    $tutor_id = $_SESSION['gtp_user']['id'];

    if (!gtp_tutor_assigned_to_classroom($tutor_id, $classroom_id)) {
        wp_send_json_error('You are not assigned to this class.');
    }

    $table_name = $wpdb->prefix . 'gtp_students';

    // Unlink from class only — keep the student row for historical session attendance
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE $table_name
         SET last_classroom_id = classroom_id,
             classroom_id = NULL,
             removed_at = %s
         WHERE id = %d AND classroom_id = %d",
        current_time('mysql'),
        $student_id,
        $classroom_id
    ));

    if ($updated === false) {
        wp_send_json_error('Could not remove student: ' . $wpdb->last_error);
    }

    wp_send_json_success(['id' => $student_id]);
}

function gtp_tutor_assigned_to_classroom($tutor_id, $classroom_id) {
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_class_assignments WHERE classroom_id = %d AND tutor_id = %d",
        $classroom_id,
        $tutor_id
    ));
}

add_action('wp_ajax_gtp_update_classroom_info', 'gtp_update_classroom_info');
add_action('wp_ajax_nopriv_gtp_update_classroom_info', 'gtp_update_classroom_info');
function gtp_update_classroom_info() {
    global $wpdb;

    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        wp_send_json_error('Not logged in or not a tutor.');
    }

    $tutor_id = $_SESSION['gtp_user']['id'];
    $classroom_id = intval($_POST['classroom_id'] ?? 0);

    if (!$classroom_id || !gtp_tutor_assigned_to_classroom($tutor_id, $classroom_id)) {
        wp_send_json_error('You are not assigned to this class.');
    }

    $school = sanitize_text_field($_POST['school'] ?? '');
    $teacher_first_name = sanitize_text_field($_POST['teacher_first_name'] ?? '');
    $teacher_last_name = sanitize_text_field($_POST['teacher_last_name'] ?? '');
    $teacher_email = sanitize_email($_POST['teacher_email'] ?? '');
    $teacher_phone = sanitize_text_field($_POST['teacher_phone'] ?? '');
    $start_time = gtp_sanitize_time($_POST['start_time'] ?? '');
    $end_time = gtp_sanitize_time($_POST['end_time'] ?? '');
    $zoom_link = esc_url_raw($_POST['zoom_link'] ?? '');
    $meeting_days = gtp_meeting_days_to_storage($_POST['meeting_days'] ?? []);
    $time_slot = gtp_format_time_range($start_time, $end_time);
    $schedule_display = gtp_format_classroom_schedule((object) [
        'meeting_days' => $meeting_days,
        'start_time' => $start_time,
        'end_time' => $end_time,
    ]);

    $updated = $wpdb->update(
        $wpdb->prefix . 'gtp_classrooms',
        [
            'school' => $school,
            'teacher_first_name' => $teacher_first_name,
            'teacher_last_name' => $teacher_last_name,
            'teacher_email' => $teacher_email,
            'teacher_phone' => $teacher_phone,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'time_slot' => $time_slot,
            'meeting_days' => $meeting_days,
            'zoom_link' => $zoom_link,
        ],
        ['id' => $classroom_id]
    );

    if ($updated === false) {
        wp_send_json_error('Could not update class information: ' . $wpdb->last_error);
    }

    // Ensure NULL is stored when times are cleared (wpdb may turn null into '')
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}gtp_classrooms
         SET start_time = NULLIF(%s, ''), end_time = NULLIF(%s, ''), meeting_days = NULLIF(%s, '')
         WHERE id = %d",
        $start_time ?: '',
        $end_time ?: '',
        $meeting_days ?: '',
        $classroom_id
    ));

    wp_send_json_success([
        'school' => $school,
        'teacher_first_name' => $teacher_first_name,
        'teacher_last_name' => $teacher_last_name,
        'teacher_email' => $teacher_email,
        'teacher_phone' => $teacher_phone,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'start_time_input' => gtp_time_input_value($start_time),
        'end_time_input' => gtp_time_input_value($end_time),
        'meeting_days' => $meeting_days,
        'time_display' => $schedule_display,
        'zoom_link' => $zoom_link,
    ]);
}

add_action('wp_ajax_gtp_get_classrooms_for_subject', 'gtp_get_classrooms_for_subject');
add_action('wp_ajax_nopriv_gtp_get_classrooms_for_subject', 'gtp_get_classrooms_for_subject');
function gtp_get_classrooms_for_subject() {
    global $wpdb;
    $subject = sanitize_text_field($_POST['subject']);
    $is_substitute = isset($_POST['is_substitute']) && $_POST['is_substitute'] === 'true';

    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        wp_send_json_error('Not logged in or not a tutor.');
    }
    $tutor_id = $_SESSION['gtp_user']['id'];

    $classrooms_table = $wpdb->prefix . 'gtp_classrooms';
    $assignments_table = $wpdb->prefix . 'gtp_class_assignments';
    $match = gtp_subject_match_values($subject);
    $ph = implode(',', array_fill(0, count($match), '%s'));
    $semester_id = gtp_get_live_semester_id();

    if ($is_substitute) {
        $params = array_merge($match, [$semester_id]);
        $classrooms = $wpdb->get_results($wpdb->prepare(
            "SELECT id, school, subject, is_block, teacher_first_name, teacher_last_name, start_time, end_time
             FROM $classrooms_table
             WHERE subject IN ($ph) AND semester_id = %d",
            ...$params
        ));
    } else {
        $params = array_merge($match, [$tutor_id, $semester_id]);
        $classrooms = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.school, c.subject, c.is_block, c.teacher_first_name, c.teacher_last_name, c.start_time, c.end_time
             FROM $classrooms_table c
             JOIN $assignments_table a ON c.id = a.classroom_id
             WHERE c.subject IN ($ph) AND a.tutor_id = %d AND c.semester_id = %d",
            ...$params
        ));
    }

    foreach ($classrooms as $classroom) {
        $classroom->start_time_input = gtp_time_input_value($classroom->start_time);
        $classroom->end_time_input = gtp_time_input_value($classroom->end_time);
        $classroom->display_subject = gtp_format_classroom_subject($classroom);
    }

    wp_send_json_success($classrooms);
}

add_action('wp_ajax_gtp_get_session_roster', 'gtp_get_session_roster');
add_action('wp_ajax_nopriv_gtp_get_session_roster', 'gtp_get_session_roster');
function gtp_get_session_roster() {
    global $wpdb;

    if (!isset($_SESSION['gtp_user']) || ($_SESSION['gtp_user']['role'] ?? '') !== 'admin') {
        wp_send_json_error('Not authorized.');
    }

    $session_id = intval($_POST['session_id'] ?? 0);
    if (!$session_id) {
        wp_send_json_error('Missing session.');
    }

    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gtp_sessions WHERE id = %d",
        $session_id
    ));

    if (!$session) {
        wp_send_json_error('Session not found.');
    }

    $present_ids = json_decode($session->attendance, true);
    if (!is_array($present_ids)) {
        $present_ids = [];
    }
    $present_ids = array_map('intval', $present_ids);
    $present_lookup = array_fill_keys($present_ids, true);

    // Match classroom by school / subject / teacher name
    $classroom = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}gtp_classrooms
         WHERE school = %s AND subject = %s
           AND CONCAT(teacher_first_name, ' ', teacher_last_name) = %s
         LIMIT 1",
        $session->school,
        $session->subject,
        $session->teacher_name
    ));

    $student_map = [];

    if ($classroom) {
        $roster = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, student_name, classroom_id, last_classroom_id, removed_at
             FROM {$wpdb->prefix}gtp_students
             WHERE classroom_id = %d OR last_classroom_id = %d",
            $classroom->id,
            $classroom->id
        ));
        foreach ($roster as $student) {
            $student_map[(int) $student->id] = $student;
        }
    }

    $missing_ids = array_diff($present_ids, array_keys($student_map));
    if (!empty($missing_ids)) {
        $placeholders = implode(',', array_fill(0, count($missing_ids), '%d'));
        $extra = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, student_name, classroom_id, last_classroom_id, removed_at
             FROM {$wpdb->prefix}gtp_students
             WHERE id IN ($placeholders)",
            ...$missing_ids
        ));
        foreach ($extra as $student) {
            $student_map[(int) $student->id] = $student;
        }
    }

    $session_date = $session->session_date;
    $students_out = [];

    foreach ($student_map as $sid => $student) {
        $still_enrolled = $classroom && ((int) $student->classroom_id === (int) $classroom->id);
        $removed_on = !empty($student->removed_at) ? substr($student->removed_at, 0, 10) : null;

        // Skip students removed before this session who weren't marked present
        if (!$still_enrolled && $removed_on !== null && $session_date > $removed_on && empty($present_lookup[$sid])) {
            continue;
        }

        $name = '';
        if (!empty($student->first_name) || !empty($student->last_name)) {
            $name = gtp_format_student_display_name($student->first_name, $student->last_name);
        }
        if ($name === '') {
            $name = trim((string) ($student->student_name ?? ''));
        }

        $students_out[] = [
            'id' => $sid,
            'name' => $name,
            'status' => !empty($present_lookup[$sid]) ? 'Present' : 'Absent',
        ];
    }

    usort($students_out, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    wp_send_json_success([
        'session_id' => $session_id,
        'session_date' => $session->session_date,
        'school' => $session->school,
        'subject' => $session->subject,
        'teacher_name' => $session->teacher_name,
        'no_show' => !empty($session->no_show),
        'students' => $students_out,
    ]);
}

add_action('wp_ajax_gtp_get_tutor_profile', 'gtp_get_tutor_profile');
add_action('wp_ajax_nopriv_gtp_get_tutor_profile', 'gtp_get_tutor_profile');
function gtp_get_tutor_profile() {
    global $wpdb;

    if (!isset($_SESSION['gtp_user']) || ($_SESSION['gtp_user']['role'] ?? '') !== 'admin') {
        wp_send_json_error('Not authorized.');
    }

    $tutor_id = intval($_POST['tutor_id'] ?? 0);
    if ($tutor_id <= 0) {
        wp_send_json_error('Missing tutor.');
    }

    $tutor = $wpdb->get_row($wpdb->prepare(
        "SELECT id, first_name, last_name, email, school, bio, headshot_url, subject_preferences, username
         FROM {$wpdb->prefix}gtp_users
         WHERE id = %d AND role = 'tutor'",
        $tutor_id
    ));

    if (!$tutor) {
        wp_send_json_error('Tutor not found.');
    }

    $prefs = [];
    if (!empty($tutor->subject_preferences)) {
        $decoded = json_decode($tutor->subject_preferences, true);
        if (is_array($decoded)) {
            $prefs = $decoded;
        }
    }

    $assignments = $wpdb->get_results($wpdb->prepare(
        "SELECT c.school, c.subject, c.is_block,
                c.teacher_first_name, c.teacher_last_name,
                c.start_time, c.end_time, c.meeting_days
         FROM {$wpdb->prefix}gtp_class_assignments a
         INNER JOIN {$wpdb->prefix}gtp_classrooms c ON c.id = a.classroom_id
         WHERE a.tutor_id = %d
         ORDER BY c.school ASC, c.subject ASC",
        $tutor_id
    ));

    $assigned = [];
    foreach ($assignments as $row) {
        $teacher = trim($row->teacher_first_name . ' ' . $row->teacher_last_name);
        $time = function_exists('gtp_format_classroom_schedule')
            ? gtp_format_classroom_schedule($row)
            : gtp_format_time_range($row->start_time, $row->end_time);
        $assigned[] = [
            'school' => $row->school,
            'subject' => gtp_format_classroom_subject($row),
            'teacher' => $teacher,
            'time' => $time,
        ];
    }

    wp_send_json_success([
        'id' => (int) $tutor->id,
        'name' => trim($tutor->first_name . ' ' . $tutor->last_name),
        'username' => $tutor->username,
        'email' => $tutor->email ?: '',
        'school' => $tutor->school ?: '',
        'bio' => $tutor->bio ?: '',
        'headshot_url' => $tutor->headshot_url ?: '',
        'subject_preferences' => $prefs,
        'assigned_classes' => $assigned,
    ]);
}
