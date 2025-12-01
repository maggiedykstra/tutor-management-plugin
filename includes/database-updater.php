<?php

/**
 * GTP Database Updater
 * 
 * Handles table creation & migrations for all plugin tables.
 * Runs on plugin load when version changes OR when manually triggered
 */



/**
 * Run updates if version changed or tables missing
 */
function gtp_update_db_schema() {
    $installed_ver = get_option('gtp_db_version');

    if ($installed_ver != GTP_DB_VERSION) {

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create fresh tables (dbDelta is safe on existing ones)
        gtp_create_users_table();
        gtp_create_classrooms_table();
        gtp_create_class_assignments_table();
        gtp_create_sessions_table();
        gtp_create_students_table();

        // Apply incremental schema updates
        gtp_migrate_users_table();
        gtp_migrate_classrooms_table();
        gtp_migrate_assignments_table();
        gtp_migrate_students_table();

        update_option('gtp_db_version', GTP_DB_VERSION);
    }
}
add_action('plugins_loaded', 'gtp_update_db_schema');
register_activation_hook(__FILE__, 'gtp_update_db_schema');


/**
 * Manual DB updater admin page
 */
function gtp_database_updater_page() {
    if (isset($_POST['gtp_update_db'])) {
        gtp_update_db_schema();
        echo '<div class="updated"><p>Database schema updated successfully!</p></div>';
    } ?>

    <div class="wrap">
        <h1>GTP Database Updater</h1>
        <p>Click below to apply schema updates to all GTP plugin tables.</p>
        <form method="post">
            <input type="hidden" name="gtp_update_db" value="1">
            <?php submit_button('Update Database'); ?>
        </form>
    </div>
<?php
}
