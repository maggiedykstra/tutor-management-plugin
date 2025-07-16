<?php
//=== GTP Dev Seeder: Only for Maria and Maggie ===
add_action('admin_menu', function () {
    $current_user = wp_get_current_user();
    if (in_array($current_user->user_login, ['gtpplugin'])) {
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
        ['username' => 'sampleadmin', 'password' => 'admin123', 'role' => 'admin'],
        ['username' => 'sampletutor1', 'password' => 'tutor123', 'role' => 'tutor'],
        ['username' => 'sampletutor2', 'password' => 'tutor456', 'role' => 'tutor'],
    ];

    foreach ($samples as $user) {
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE username = %s", $user['username'])
        );

        $hashed = password_hash($user['password'], PASSWORD_DEFAULT);

        if ($existing) {
            $wpdb->update($table, [
                'password' => $hashed,
                'role'     => $user['role']
            ], ['id' => $existing]);
        } else {
            $wpdb->insert($table, [
                'username' => $user['username'],
                'password' => $hashed,
                'role'     => $user['role']
            ]);
        }
    }
}


// // Remove "Private:" prefix from page titles for cleaner UI
// add_filter('the_title', 'gtp_remove_private_prefix', 10, 2);

// function gtp_remove_private_prefix($title, $id) {
//     if (get_post_status($id) === 'private') {
//         $title = str_replace('Private: ', '', $title);
//     }
//     return $title;
// }

// // adds test users

// function gtp_create_test_users() {
//     global $wpdb;
//     $table = $wpdb->prefix . 'gtp_users';

//     // Check and insert test admin user
//     $admin_exists = $wpdb->get_var(
//         $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE username = %s", 'testadmin')
//     );
//     if (!$admin_exists) {
//         $wpdb->insert($table, [
//             'username' => 'testadmin',
//             'password' => password_hash('adminpass123', PASSWORD_DEFAULT),
//             'role'     => 'admin',
//         ]);
//     }

//     // Check and insert test TA user
//     $ta_exists = $wpdb->get_var(
//         $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE username = %s", 'testta')
//     );
//     if (!$ta_exists) {
//         $wpdb->insert($table, [
//             'username' => 'testta',
//             'password' => password_hash('tapass123', PASSWORD_DEFAULT),
//             'role'     => 'tutor',
//         ]);
//     }
// }
