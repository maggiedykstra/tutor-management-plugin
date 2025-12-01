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
}

function gtp_migrate_classrooms_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_classrooms';

    // Example: Add new columns if needed
    // if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'new_column'")) {
    //     $wpdb->query("ALTER TABLE $table ADD new_column VARCHAR(255) DEFAULT NULL");
    // }
}

function gtp_migrate_assignments_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_class_assignments';

    // Add new assignment columns here if needed
}

function gtp_migrate_students_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_students';

    // Add new student table columns here if needed
}
