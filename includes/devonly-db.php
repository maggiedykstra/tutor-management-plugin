<?php
//=== GTP Dev Seeder: Only for Maria and Maggie ===
add_action('admin_menu', function () {
    $current_user = wp_get_current_user();
    if (in_array($current_user->user_login, ['gtpplugin', 'globalteachingproject_mdmd'])) {
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
        ['username' => 'sampleadmin', 'password' => 'admin123', 'role' => 'admin', 'first_name' => 'SampleA1'],
        ['username' => 'sampletutor1', 'password' => 'tutor123', 'role' => 'tutor', 'first_name' => 'SampleT1', 'last_name' => 'last', 'email' => 'gmail'],
        ['username' => 'sampletutor2', 'password' => 'tutor456', 'role' => 'tutor', 'first_name' => 'SampleT2'],
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
            'teacher_name' => 'Mrs. Frizzle'
        ],
        [
            'school' => 'Riverdale Academy',
            'subject' => 'Algebra',
            'teacher_name' => 'Mr. Baxter'
        ]
    ];

    // Insert classrooms if not exist
    foreach ($sample_classrooms as $classroom) {
        $existing_id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $classrooms_table WHERE school = %s AND subject = %s AND teacher_name = %s",
                $classroom['school'], $classroom['subject'], $classroom['teacher_name'])
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



