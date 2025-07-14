<?php
function gtp_check_and_update_schema() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_users';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        username varchar(60) NOT NULL,
        password varchar(255) NOT NULL,
        role varchar(20) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY username (username)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

add_action('init', 'gtp_check_and_update_schema');
register_activation_hook(__FILE__, 'gtp_check_and_update_schema');