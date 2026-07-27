<?php

/**
 * Incremental schema migrations (add new columns to existing tables)
 */

function gtp_migrate_users_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_users';

    // Add email column
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'email'")) {
        $wpdb->query("ALTER TABLE $table ADD email VARCHAR(255) DEFAULT NULL AFTER username");
    }

    // Add school column
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'school'")) {
        $wpdb->query("ALTER TABLE $table ADD school VARCHAR(255) DEFAULT NULL AFTER last_name");
    }

    // Add validated column
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'validated'")) {
        $wpdb->query("ALTER TABLE $table ADD validated TINYINT(1) DEFAULT 0 AFTER role");
    }

    // Existing users already have passwords; new invites start with password_set = 0
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'password_set'")) {
        $wpdb->query("ALTER TABLE $table ADD password_set TINYINT(1) NOT NULL DEFAULT 1 AFTER validated");
    }

    // What the person applied for at registration (tutor | admin)
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'requested_role'")) {
        $wpdb->query("ALTER TABLE $table ADD requested_role VARCHAR(20) NOT NULL DEFAULT 'tutor' AFTER role");
    }
}

function gtp_migrate_classrooms_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_classrooms';

    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'start_time'")) {
        $wpdb->query("ALTER TABLE $table ADD start_time TIME DEFAULT NULL");
    }

    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'end_time'")) {
        $wpdb->query("ALTER TABLE $table ADD end_time TIME DEFAULT NULL");
    }
}

function gtp_migrate_assignments_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_class_assignments';

    // Add new assignment columns here if needed
}

function gtp_migrate_students_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_students';

    // Add student_name if missing
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'student_name'")) {
        $wpdb->query("ALTER TABLE $table ADD student_name VARCHAR(255) NOT NULL DEFAULT '' AFTER id");
    }

    // Backfill student_name from legacy first_name/last_name if those columns exist
    if ($wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'first_name'")) {
        $wpdb->query(
            "UPDATE $table
             SET student_name = TRIM(CONCAT(IFNULL(first_name, ''), ' ', IFNULL(last_name, '')))
             WHERE (student_name IS NULL OR student_name = '')
               AND (first_name IS NOT NULL OR last_name IS NOT NULL)"
        );

        // Backfill first_name/last_name from student_name when only the combined name exists
        $wpdb->query(
            "UPDATE $table
             SET first_name = SUBSTRING_INDEX(student_name, ' ', 1),
                 last_name = CASE
                     WHEN student_name LIKE '% %' THEN TRIM(SUBSTRING(student_name, LOCATE(' ', student_name) + 1))
                     ELSE NULL
                 END
             WHERE (first_name IS NULL OR first_name = '')
               AND student_name IS NOT NULL
               AND student_name <> ''"
        );
    }

    // Allow NULL classroom_id so students can be removed from a class without deleting the row
    $classroom_col = $wpdb->get_row("SHOW COLUMNS FROM $table LIKE 'classroom_id'");
    if ($classroom_col && strtoupper($classroom_col->Null) === 'NO') {
        $wpdb->query("ALTER TABLE $table MODIFY classroom_id mediumint(9) DEFAULT NULL");
    }

    // Optional date_added column used by roster inserts
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'date_added'")) {
        $wpdb->query("ALTER TABLE $table ADD date_added datetime DEFAULT NULL");
    }

    // Remember previous class after unlink so spreadsheet history stays complete
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'last_classroom_id'")) {
        $wpdb->query("ALTER TABLE $table ADD last_classroom_id mediumint(9) DEFAULT NULL");
    }
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'removed_at'")) {
        $wpdb->query("ALTER TABLE $table ADD removed_at datetime DEFAULT NULL");
    }

    // Legacy first_name/last_name must be nullable so student_name-only inserts work
    if ($wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'first_name'")) {
        $wpdb->query("ALTER TABLE $table MODIFY first_name VARCHAR(255) NULL DEFAULT NULL");
    }
    if ($wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'last_name'")) {
        $wpdb->query("ALTER TABLE $table MODIFY last_name VARCHAR(255) NULL DEFAULT NULL");
    }
}

function gtp_migrate_sessions_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_sessions';

    // teacher_present: 1 = teacher was present, 0 = teacher was not present on Zoom
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'teacher_present'")) {
        $wpdb->query("ALTER TABLE $table ADD teacher_present TINYINT(1) NOT NULL DEFAULT 1");
    }

    // no_show: 1 = class did not show up, 0 = class showed up
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'no_show'")) {
        $wpdb->query("ALTER TABLE $table ADD no_show TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Editable session time text (legacy); prefer start_time/end_time
    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'time_slot'")) {
        $wpdb->query("ALTER TABLE $table ADD time_slot VARCHAR(255) DEFAULT NULL");
    }

    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'start_time'")) {
        $wpdb->query("ALTER TABLE $table ADD start_time TIME DEFAULT NULL");
    }

    if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'end_time'")) {
        $wpdb->query("ALTER TABLE $table ADD end_time TIME DEFAULT NULL");
    }
}
