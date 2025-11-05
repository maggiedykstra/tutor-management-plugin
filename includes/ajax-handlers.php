<?php
add_action('wp_ajax_gtp_get_students_for_classroom', 'gtp_get_students_for_classroom');
function gtp_get_students_for_classroom() {
    global $wpdb;
    $classroom_id = intval($_POST['classroom_id']);
    $table_name = $wpdb->prefix . 'gtp_students';

    $students = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE classroom_id = %d ORDER BY student_name ASC", $classroom_id));

    wp_send_json_success($students);
}

add_action('wp_ajax_gtp_add_student_to_roster', 'gtp_add_student_to_roster');
function gtp_add_student_to_roster() {
    global $wpdb;
    $classroom_id = intval($_POST['classroom_id']);
    $student_name = sanitize_text_field($_POST['student_name']);

    if (empty($student_name)) {
        wp_send_json_error('Student name cannot be empty.');
    }

    $table_name = $wpdb->prefix . 'gtp_students';

    $wpdb->insert($table_name, [
        'classroom_id' => $classroom_id,
        'student_name' => $student_name,
        'date_added'   => current_time('mysql')
    ]);

    $new_student_id = $wpdb->insert_id;

    $new_student = [
        'id' => $new_student_id,
        'student_name' => $student_name
    ];

    wp_send_json_success($new_student);
}

add_action('wp_ajax_gtp_get_classrooms_for_subject', 'gtp_get_classrooms_for_subject');
function gtp_get_classrooms_for_subject() {
    global $wpdb;
    $subject = sanitize_text_field($_POST['subject']);

    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        wp_send_json_error('Not logged in or not a tutor.');
    }
    $tutor_id = $_SESSION['gtp_user']['id'];

    $classrooms_table = $wpdb->prefix . 'gtp_classrooms';
    $assignments_table = $wpdb->prefix . 'gtp_class_assignments';

    $classrooms = $wpdb->get_results($wpdb->prepare(
        "SELECT c.id, c.school, c.teacher_first_name, c.teacher_last_name FROM $classrooms_table c
         JOIN $assignments_table a ON c.id = a.classroom_id
         WHERE a.tutor_id = %d AND c.subject = %s",
        $tutor_id,
        $subject
    ));

    wp_send_json_success($classrooms);
}