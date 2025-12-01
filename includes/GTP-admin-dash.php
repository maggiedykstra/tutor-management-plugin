<?php
function gtp_admin_dashboard_shortcode() {
    // Check if user is logged in via session
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    $name = esc_html($_SESSION['gtp_user']['first_name']);

    ob_start();
    ?>
   <!-- <button onclick="history.back()">Go Back</button> -->
   <div style="max-width:600px; margin:30px auto; padding:20px; background:#f1f1f1; border-radius:10px;">
        <h2>Welcome, <?php echo $name; ?>!</h2>

        <div style="margin-top:20px; display: flex; gap: 15px;">
            <form action="<?php echo esc_url(site_url('/index.php/validate-tas')); ?>" method="get">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Validate TAs
                </button>
            </form>

            <form action="<?php echo esc_url(site_url('/index.php/ta-session-filter')); ?>" method="get">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Filter TA Sessions and Hours
                </button>
            </form>
            <form action="<?php echo esc_url(site_url('/index.php/add-classroom')); ?>" method="get">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Add Classrooms
                </button>
            </form>
            <form action="<?php echo esc_url(site_url('/index.php/edit-classrooms')); ?>" method="get">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Edit Classrooms
                </button>
            </form>
            <form action="<?php echo esc_url(site_url('/index.php/add-user')); ?>" method="get">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Add User
                </button>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_admin_dashboard', 'gtp_admin_dashboard_shortcode');



function gtp_add_classroom_shortcode() {
    // Restrict access to admin users
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;
    $message = '';



    // Handle form submission
    if (isset($_POST['gtp_add_classroom'])) {
        $school = sanitize_text_field($_POST['school']);
        $subject = sanitize_text_field($_POST['subject']);
        $teacher_first_name = sanitize_text_field($_POST['teacher_first_name']);
        $teacher_last_name = sanitize_text_field($_POST['teacher_last_name']);
        $teacher_email = sanitize_email($_POST['teacher_email']);
        $teacher_phone = sanitize_text_field($_POST['teacher_phone']);
        $time_slot = sanitize_text_field($_POST['time_slot']);
        $roster = sanitize_textarea_field($_POST['roster']);

        // If "Other" is selected, override with custom subject
        if ($subject === 'Other' && !empty($_POST['custom_subject'])) {
            $subject = sanitize_text_field($_POST['custom_subject']);
        }

        if ($school && $subject && $teacher_first_name && $teacher_last_name) {
            $wpdb->insert(
                $wpdb->prefix . 'gtp_classrooms',
                [
                    'school'       => $school,
                    'subject'      => $subject,
                    'teacher_first_name' => $teacher_first_name,
                    'teacher_last_name' => $teacher_last_name,
                    'teacher_email' => $teacher_email,
                    'teacher_phone' => $teacher_phone,
                    'time_slot'    => $time_slot,
                ]
            );

            if ($wpdb->last_error) {
                $message = '<p style="color:red;">Database Error: ' . esc_html($wpdb->last_error) . '</p>';
            } else {
                $classroom_id = $wpdb->insert_id;
                $message = '<p style="color:green;">Classroom added successfully!</p>';

                // Handle the roster
                if (!empty($roster)) {
                    $student_names = explode("\n", $roster);
                    $students_table = $wpdb->prefix . 'gtp_students';

                    foreach ($student_names as $student_name) {
                        $student_name = trim($student_name);
                        if (!empty($student_name)) {
                            $wpdb->insert(
                                $students_table,
                                [
                                    'classroom_id' => $classroom_id,
                                    'student_name' => $student_name,
                                ]
                            );
                        }
                    }
                }
            }
        } else {
            $message = '<p style="color:red;">Please fill out all required fields.</p>';
        }
    }

    ob_start();
    ?>
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px;">
    <a href="<?php echo esc_url(site_url('/index.php/admin-dashboard/')); ?>" class="button">← Go Back to Admin Dashboard</a>
        <?php echo $message; ?>
        <form method="post">
            <label>School:</label>
            <input type="text" name="school" required style="width:100%; padding:8px; margin-bottom:10px;">

            <label>Subject:</label>
            <select name="subject" id="subject-select" required style="width:100%; padding:8px; margin-bottom:10px;">
                <option value="">-- Select Subject --</option>
                <option value="Statistics">AP Statistics</option>
                <option value="CSP">AP CSP</option>
                <option value="Biology">AP Biology</option>
                <option value="Physics">AP Physics</option>
                <option value="Other">Other</option>
            </select>

        <div id="custom-subject-wrapper" style="display:none; margin-top:10px;">
            <label>Other Subject:</label>
            <input type="text" name="custom_subject" id="custom-subject-input" style="width:100%; padding:8px;">
        </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const subjectSelect = document.getElementById('subject-select');
                const customWrapper = document.getElementById('custom-subject-wrapper');
                const customInput = document.getElementById('custom-subject-input');

                subjectSelect.addEventListener('change', function () {
                    if (this.value === 'Other') {
                        customWrapper.style.display = 'block';
                        customInput.required = true;
                    } else {
                        customWrapper.style.display = 'none';
                        customInput.required = false;
                    }
                });
            });
            </script>
            <label>Teacher First Name:</label>
            <input type="text" name="teacher_first_name" required style="width:100%; padding:8px; margin-bottom:10px;">
            
            <label>Teacher Last Name:</label>
            <input type="text" name="teacher_last_name" required style="width:100%; padding:8px; margin-bottom:10px;">

            <label>Teacher Email:</label>
            <input type="email" name="teacher_email" style="width:100%; padding:8px; margin-bottom:10px;">

            <label>Teacher Phone:</label>
            <input type="tel" name="teacher_phone" style="width:100%; padding:8px; margin-bottom:10px;">
            
            
            <label>Time Slot:</label>
            <input type="text" name="time_slot" style="width:100%; padding:8px; margin-bottom:10px;">

            <label>Roster (optional, one student name per line):</label>
            <textarea name="roster" style="width:100%; padding:8px; margin-bottom:10px;" rows="5"></textarea>

            <input type="submit" name="gtp_add_classroom" value="Add Classroom" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_add_classroom', 'gtp_add_classroom_shortcode');


function gtp_validate_shortcode() {
    // Restrict access to admin users
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }
   
    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_users';
    $message = '';

    // Handle validation/denial
    if (isset($_POST['gtp_validate_user'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['gtp_validate_user'];
    
        if ($action === 'approve') {
            $wpdb->update($table_name, ['role' => 'tutor', 'validated' => 1], ['id' => $user_id]);
            $message = '<p style="color:green;">User approved as a Tutor.</p>';
        } elseif ($action === 'deny') {
            $wpdb->delete($table_name, ['id' => $user_id]);
            $message = '<p style="color:orange;">User denied and deleted.</p>';
        }
    }

    // Get all users who are not admins or tutors (e.g., pending validation)
    $pending_users = $wpdb->get_results("SELECT * FROM  $table_name WHERE validated = 0");

    

    ob_start();
    ?>
    <a href="<?php echo site_url('index.php/admin-dashboard/'); ?>" class="button">Admin Home</a>
    <div style="max-width:800px; margin:30px auto; padding:20px; background:#f1f1f1; border-radius:10px;">
        <h2>Validate or Register a New TA</h2>
        <?php echo $message; ?>

        <h3>Pending Validation</h3>
        <?php if (!empty($pending_users)) : ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_users as $user) : ?>
                        <tr>
                            <td><?php echo esc_html($user->username); ?></td>
                            <td><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></td>
                            <td><?php echo esc_html($user->email); ?></td>
                            <td><?php echo esc_html($user->role); ?></td>
                            <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user->id; ?>">
                                <button type="submit" name="gtp_validate_user" value="approve" class="button button-primary">Approve</button>
                                <button type="submit" name="gtp_validate_user" value="deny" class="button button-secondary">Deny</button>
                            </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No users are currently pending validation.</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_validate_tas', 'gtp_validate_shortcode');

function gtp_add_user_shortcode() {
    // Restrict access to admin users
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_users';
    $message = '';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gtp_add_user_submit'])) {
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = sanitize_text_field($_POST['role']);

        // Check if username already exists
        $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE username = %s", $username));

        if ($existing > 0) {
            $message = '<p style="color:red;">Username already exists.</p>';
        } else {
            $wpdb->insert($table_name, [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'validated' => 1
            ]);

            if ($wpdb->insert_id) {
                $message = '<p style="color:green;">User added and validated successfully!</p>';
            } else {
                $message = '<p style="color:red;">Error adding user.</p>';
            }
        }
    }

    ob_start();
    ?>
    <div style="max-width:600px; margin:30px auto; padding:20px; background:#f9f9f9; border-radius:10px;">
    <a href="<?php echo esc_url(site_url('/index.php/admin-dashboard/')); ?>" class="button">← Go Back to Admin Dashboard</a>
        <h2>Add and Validate a New User</h2>
        <?php echo $message; ?>
        <form method="post">
            <label>First Name:</label><br>
            <input type="text" name="first_name" required><br><br>

            <label>Last Name:</label><br>
            <input type="text" name="last_name" required><br><br>

            <label>Username:</label><br>
            <input type="text" name="username" required><br><br>

            <label>Email:</label><br>
            <input type="email" name="email" required><br><br>

            <label>Password:</label><br>
            <input type="password" name="password" required><br><br>

            <label>Role:</label><br>
            <select name="role" required>
                <option value="tutor">Tutor</option>
                <option value="admin">Admin</option>
            </select><br><br>

            <button type="submit" name="gtp_add_user_submit" class="button button-primary">Add User</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_add_user', 'gtp_add_user_shortcode');
