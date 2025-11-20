<?php
function gtp_create_users_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_users';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        username varchar(255) NOT NULL,
        password varchar(255) NOT NULL,
        first_name varchar(255) NOT NULL,
        last_name varchar(255) NOT NULL,
        school varchar(255) DEFAULT NULL,
        bio text,
        headshot_url varchar(255) DEFAULT NULL,
        subject_preferences text,
        role varchar(20) NOT NULL DEFAULT 'tutor',
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function gtp_create_sessions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_sessions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tutor_username varchar(255) NOT NULL,
        first_name varchar(255) NOT NULL,
        last_name varchar(255) NOT NULL,
        school varchar(255) NOT NULL,
        subject varchar(255) NOT NULL,
        teacher_name varchar(255) NOT NULL,
        session_date date NOT NULL,
        attendance text,
        topic text,
        comments text,
        is_substitute tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function gtp_create_classrooms_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_classrooms';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        school varchar(255) NOT NULL,
        subject varchar(255) NOT NULL,
        teacher_first_name varchar(255) NOT NULL,
        teacher_last_name varchar(255) NOT NULL,
        teacher_email varchar(255) DEFAULT NULL,
        teacher_phone varchar(255) DEFAULT NULL,
        time_slot varchar(255) DEFAULT NULL,
        roster text,
        zoom_link varchar(255) DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function gtp_create_class_assignments_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_class_assignments';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        classroom_id mediumint(9) NOT NULL,
        tutor_id mediumint(9) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function gtp_create_students_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_students';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(255) NOT NULL,
        last_name varchar(255) NOT NULL,
        classroom_id mediumint(9) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
