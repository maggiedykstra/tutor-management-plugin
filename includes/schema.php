<?php
function gtp_create_users_table(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_users';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        first_name varchar(60) NOT NULL,
        last_name varchar(60) NOT NULL,
        username varchar(60) NOT NULL,
        password varchar(255) NOT NULL,
        role varchar(20) NOT NULL,
        headshot_url varchar(255) DEFAULT '' NOT NULL,
        bio text NOT NULL,
        subject_preferences text NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY username (username)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Save the current DB version
    update_option('gtp_db_version', GTP_DB_VERSION);
}

function gtp_create_sessions_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_sessions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tutor_username varchar(60) NOT NULL,
        first_name varchar(60) NOT NULL,
        last_name varchar(60) NOT NULL,
        subject varchar(100) NOT NULL,
        school varchar(100) NOT NULL,
        teacher_name varchar(100) NOT NULL,
        zoom_link varchar(100) NOT NULL,
        session_date date NOT NULL,
        attendance text NOT NULL,
        topic text NOT NULL,
        comments text,
        is_substitute tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}


function gtp_create_classrooms_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_classrooms';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        school varchar(100) NOT NULL,
        subject varchar(100) NOT NULL,
        teacher_first_name varchar(100) NOT NULL,
        teacher_last_name varchar(100) NOT NUll,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function gtp_create_class_assignments_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_class_assignments';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tutor_id mediumint(9) NOT NULL,
        classroom_id mediumint(9) NOT NULL,
        first_taught date NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function gtp_create_students_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_students';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        student_name varchar(100) NOT NULL,
        classroom_id mediumint(9) NOT NULL,
        date_added datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        KEY classroom_id (classroom_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}