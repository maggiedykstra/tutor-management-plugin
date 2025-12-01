<?php
//=== GTP Dev Seeder: Only for Maria and Maggie ===
add_action('admin_menu', function () {
    $current_user = wp_get_current_user();
    if (in_array($current_user->user_login, ['gtpplugin', 'globalteachingproject_mdmd', 'mdolan047'])) {
        add_submenu_page(
            'tutor-dashboard',
            'Dev Seeder',
            'Seed Sample Users',
            'manage_options',
            'gtp-dev-seeder',
            'gtp_render_dev_seeder_page'
        );
    }
});

function gtp_render_dev_seeder_page() {
    if (isset($_POST['gtp_seed_users'])) {
        gtp_insert_sample_users();
        gtp_insert_sample_classrooms_and_assignments();
        echo '<div class="notice notice-success"><p>Sample users seeded successfully!</p></div>';
    }
        ?>
        <div class="wrap">
            <h1>Seed Sample Tutor Users</h1>
            <form method="post">
                <p>This will add or update test tutor/admin accounts into the GTP database. Safe to click multiple times.</p>
                <input type="submit" name="gtp_seed_users" class="button button-primary" value="Seed Sample Data" />
            </form>
        </div>
        <?php
}

function gtp_insert_sample_users() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_users';

    $samples = [
        ['username' => 'sampleadmin', 'password' => 'admin123', 'role' => 'admin', 'first_name' => 'SampleA1', 'last_name' => 'last', 'email' => 'gmail'],
        ['username' => 'sampletutor1', 'password' => 'tutor123', 'role' => 'tutor', 'first_name' => 'SampleT1', 'last_name' => 'last', 'email' => 'gmail'],
        ['username' => 'sampletutor2', 'password' => 'tutor456', 'role' => 'tutor', 'first_name' => 'SampleT2', 'last_name' => 'last', 'email' => 'gmail'],
    ];

    foreach ($samples as $user) {
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE username = %s", $user['username'])
        );

        $hashed = password_hash($user['password'], PASSWORD_DEFAULT);

        $data = [
            'email'      => $user['email'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'password'   => $hashed,
            'role'       => $user['role'],
        ];

        if ($existing) {
            $wpdb->update($table, $data, ['id' => $existing]);
        } else {
            $data['username'] = $user['username'];
            $wpdb->insert($table, $data);
        }
    }
}


function gtp_insert_sample_classrooms_and_assignments() {
    global $wpdb;

    $classrooms_table = $wpdb->prefix . 'gtp_classrooms';
    $assignments_table = $wpdb->prefix . 'gtp_class_assignments';
    $users_table = $wpdb->prefix . 'gtp_users';

    $sample_classrooms = [
        [
            'school' => 'Greenville High',
            'subject' => 'Biology',
            'teacher_first_name' => 'Mrs.',
            'teacher_last_name' => 'Frizzle'
        ],
        [
            'school' => 'Riverdale Academy',
            'subject' => 'Algebra',
              'teacher_first_name' => 'Mr.',
            'teacher_last_name' => 'Baxter'
        ]
    ];

    // Insert classrooms if not exist
    foreach ($sample_classrooms as $classroom) {
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $classrooms_table WHERE school = %s AND subject = %s AND teacher_first_name = %s AND teacher_last_name = %s",
                $classroom['school'],
                $classroom['subject'],
                $classroom['teacher_first_name'],
                $classroom['teacher_last_name']
            )
        );

        if (!$existing_id) {
            $wpdb->insert($classrooms_table, $classroom);
            $classroom_id = $wpdb->insert_id;
        } else {
            $classroom_id = $existing_id;
        }

        // Get tutor ID for sampletutor1
        $tutor_id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $users_table WHERE username = %s", 'sampletutor1')
        );

        if ($tutor_id) {
            // Check if already assigned
            $assigned = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM $assignments_table WHERE tutor_id = %d AND classroom_id = %d",
                    $tutor_id, $classroom_id)
            );

            if (!$assigned) {
                $wpdb->insert($assignments_table, [
                    'tutor_id' => $tutor_id,
                    'classroom_id' => $classroom_id,
                    'first_taught' => current_time('mysql')
                ]);
            }
        }
    }
}

//code to edit one part of the db that I changed... can use to edit/delete columns later 

function gtp_migrate_classroom_table_once() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_classrooms';

    // Only run if the old column exists
    if ($wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'teacher_name'")) {
        // Delete legacy rows
        $wpdb->query("DELETE FROM $table WHERE teacher_first_name IS NULL OR teacher_first_name = ''");

        // Drop the old column
        $wpdb->query("ALTER TABLE $table DROP COLUMN teacher_name");

        // Optional: mark this migration complete
        update_option('gtp_classroom_migration_done', true);
    }
}
function gtp_migrate_users_table_once() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_users';

    // Check if the email column exists
    $email_column = $wpdb->get_var("SHOW COLUMNS FROM $table LIKE 'email'");

    if (!$email_column) {
        // Add the email column
        $wpdb->query("ALTER TABLE $table ADD email varchar(255) DEFAULT NULL AFTER username");

        // Mark migration as complete
        update_option('gtp_users_migration_done', true);
    }
}


add_action('init', function () {
    if (!get_option('gtp_classroom_migration_done')) {
        gtp_migrate_classroom_table_once();
    }
});

add_action('init', function () {
    if (!get_option('gtp_users_migration_done')) {
        gtp_migrate_users_table_once();
    }
});


