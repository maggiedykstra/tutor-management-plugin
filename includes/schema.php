<?php
/**
 * Table creation SQL definitions using dbDelta-compatible syntax
 */

function gtp_create_users_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_users';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        username varchar(255) NOT NULL,
        email varchar(255) DEFAULT NULL,
        password varchar(255) NOT NULL,
        first_name varchar(255) NOT NULL,
        last_name varchar(255) NOT NULL,
        school varchar(255) DEFAULT NULL,
        bio text,
        headshot_url varchar(255) DEFAULT NULL,
        subject_preferences text,
        role varchar(20) NOT NULL DEFAULT 'pending',
        validated TINYINT(1) DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset;";

    dbDelta($sql);
}

function gtp_create_classrooms_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_classrooms';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        school varchar(255) NOT NULL,
        subject varchar(255) NOT NULL,
        teacher_first_name varchar(255) NOT NULL,
        teacher_last_name varchar(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    dbDelta($sql);
}

function gtp_create_class_assignments_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_class_assignments';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tutor_id mediumint(9) NOT NULL,
        classroom_id mediumint(9) NOT NULL,
        first_taught datetime DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    dbDelta($sql);
}

function gtp_create_sessions_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_sessions';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tutor_id mediumint(9) NOT NULL,
        student_id mediumint(9) NOT NULL,
        session_time datetime DEFAULT NULL,
        notes text,
        PRIMARY KEY (id)
    ) $charset;";

    dbDelta($sql);
}

function gtp_create_students_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_students';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(255) NOT NULL,
        last_name varchar(255) NOT NULL,
        classroom_id mediumint(9) NOT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    dbDelta($sql);
}
