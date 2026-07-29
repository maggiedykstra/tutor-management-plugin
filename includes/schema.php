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
        requested_role varchar(20) NOT NULL DEFAULT 'tutor',
        validated TINYINT(1) DEFAULT 0,
        password_set TINYINT(1) NOT NULL DEFAULT 1,
        email_notifications TINYINT(1) NOT NULL DEFAULT 1,
        checkin_notified_month varchar(7) DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset;";

    dbDelta($sql);
}

function gtp_create_password_tokens_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_password_tokens';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        token_hash varchar(64) NOT NULL,
        token_type varchar(20) NOT NULL DEFAULT 'invite',
        expires_at datetime NOT NULL,
        used_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY token_hash (token_hash)
    ) $charset;";

    dbDelta($sql);
}

function gtp_create_announcements_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_announcements';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        author_id mediumint(9) NOT NULL,
        title varchar(255) NOT NULL,
        body text NOT NULL,
        audience_json longtext NOT NULL,
        audience_label text,
        send_at datetime NOT NULL,
        emailed_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY send_at (send_at),
        KEY author_id (author_id)
    ) $charset;";

    dbDelta($sql);
}

function gtp_create_announcement_recipients_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_announcement_recipients';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        announcement_id mediumint(9) NOT NULL,
        user_id mediumint(9) NOT NULL,
        read_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY announcement_user (announcement_id, user_id),
        KEY user_id (user_id)
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
        teacher_email varchar(255) DEFAULT NULL,
        teacher_phone varchar(255) DEFAULT NULL,
        time_slot varchar(255) DEFAULT NULL,
        start_time time DEFAULT NULL,
        end_time time DEFAULT NULL,
        meeting_days varchar(50) DEFAULT NULL,
        is_block tinyint(1) NOT NULL DEFAULT 0,
        zoom_link varchar(255) DEFAULT NULL,
        semester_id mediumint(9) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY semester_id (semester_id)
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
        first_name varchar(255) DEFAULT NULL,
        last_name varchar(255) DEFAULT NULL,
        student_name varchar(255) NOT NULL,
        classroom_id mediumint(9) DEFAULT NULL,
        last_classroom_id mediumint(9) DEFAULT NULL,
        removed_at datetime DEFAULT NULL,
        date_added datetime DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    dbDelta($sql);
}

function gtp_create_flagged_session_reviews_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_flagged_session_reviews';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        session_id mediumint(9) NOT NULL,
        admin_id mediumint(9) NOT NULL,
        reviewed_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY session_admin (session_id, admin_id),
        KEY admin_id (admin_id)
    ) $charset;";

    dbDelta($sql);
}

function gtp_create_resource_files_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_resource_files';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description text,
        file_url varchar(500) NOT NULL,
        file_name varchar(255) DEFAULT NULL,
        file_type varchar(100) DEFAULT NULL,
        uploaded_by mediumint(9) NOT NULL,
        sort_order int NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY sort_order (sort_order)
    ) $charset;";

    dbDelta($sql);
}

function gtp_create_resource_faq_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_resource_faq';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        question text NOT NULL,
        answer text NOT NULL,
        sort_order int NOT NULL DEFAULT 0,
        updated_by mediumint(9) DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY sort_order (sort_order)
    ) $charset;";

    dbDelta($sql);
}

function gtp_create_monthly_checkins_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_monthly_checkins';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tutor_id mediumint(9) NOT NULL,
        classroom_id mediumint(9) NOT NULL,
        checkin_month char(7) NOT NULL,
        rating_overall tinyint NOT NULL,
        rating_attendance tinyint NOT NULL,
        rating_participation tinyint NOT NULL,
        rating_progression tinyint NOT NULL,
        explain_overall text,
        explain_attendance text,
        explain_participation text,
        explain_progression text,
        class_no_show tinyint(1) NOT NULL DEFAULT 0,
        teacher_no_show tinyint(1) NOT NULL DEFAULT 0,
        comments text,
        submitted_at datetime NOT NULL,
        semester_id mediumint(9) DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY tutor_class_month (tutor_id, classroom_id, checkin_month),
        KEY checkin_month (checkin_month),
        KEY classroom_id (classroom_id),
        KEY tutor_id (tutor_id),
        KEY semester_id (semester_id)
    ) $charset;";

    dbDelta($sql);
}
