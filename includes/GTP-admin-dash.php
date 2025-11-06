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
            <form action="<?php echo esc_url(site_url('/index.php/new-ta-registration')); ?>" method="get">
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
    <a href="<?php echo esc_url(site_url('/index.php/admin-dashboard/')); ?>" class="button">Go Back to Admin Dashboard</a>
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
        if (isset($_POST['approve'])) {
            $wpdb->update($table_name, ['role' => 'tutor'], ['id' => $user_id]);
            $message = '<p style="color:green;">User approved as a Tutor.</p>';
        } elseif (isset($_POST['deny'])) {
            $wpdb->delete($table_name, ['id' => $user_id]);
            $message = '<p style="color:orange;">User denied and deleted.</p>';
        }
    }

    // Get all users who are not admins or tutors (e.g., pending validation)
    $pending_users = $wpdb->get_results("SELECT * FROM  $table_name WHERE validated = 0");

    

    ob_start();
    ?>
    <button onclick="history.back()">Go Back</button>
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
                                    <button type="submit" name="approve" class="button button-primary">Approve</button>
                                    <button type="submit" name="deny" class="button button-secondary">Deny</button>
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
add_shortcode('gtp_add_ta', 'gtp_validate_shortcode');