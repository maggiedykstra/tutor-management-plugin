<?php
function gtp_admin_dashboard_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;

    $user_id = (int) $_SESSION['gtp_user']['id'];
    $name = esc_html($_SESSION['gtp_user']['first_name']);
    $dash_url = site_url('/index.php/admin-dashboard/');

    $admin = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gtp_users WHERE id = %d",
        $user_id
    ));

    $pending_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gtp_users WHERE validated = 0");
    $semester_id = gtp_get_working_semester_id();
    $classroom_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_classrooms WHERE semester_id = %d",
        $semester_id
    ));
    $unassigned_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$wpdb->prefix}gtp_users u
         WHERE u.role = 'tutor' AND u.validated = 1
           AND u.id NOT IN (
             SELECT a.tutor_id
             FROM {$wpdb->prefix}gtp_class_assignments a
             INNER JOIN {$wpdb->prefix}gtp_classrooms c ON c.id = a.classroom_id
             WHERE c.semester_id = %d
           )",
        $semester_id
    ));
    $unreviewed_flags = gtp_count_unreviewed_flagged_sessions($user_id);
    $low_checkins = gtp_count_low_rating_checkins();
    $incomplete_checkins = gtp_count_incomplete_checkins_for_month();
    $checkin_month_label = gtp_checkin_month_label(gtp_checkin_active_month());

    $tutors = $wpdb->get_results(
        "SELECT id, first_name, last_name, headshot_url
         FROM {$wpdb->prefix}gtp_users
         WHERE role = 'tutor' AND validated = 1
         ORDER BY last_name ASC, first_name ASC
         LIMIT 16"
    );

    ob_start();
    ?>
    <div class="gtp-home gtp-admin-home">
        <header class="gtp-home-hero">
            <?php echo gtp_user_avatar_html($admin ?: (object) [
                'first_name' => $_SESSION['gtp_user']['first_name'],
                'last_name' => $_SESSION['gtp_user']['last_name'],
                'headshot_url' => '',
            ]); ?>
            <div class="gtp-home-hero-text">
                <h1>Welcome, <?php echo $name; ?></h1>
                <p>Admin home — announcements, people, and tools.</p>
            </div>
            <nav class="gtp-home-nav" aria-label="Admin shortcuts">
                <a href="<?php echo esc_url(site_url('/index.php/spreadsheet-view')); ?>">Spreadsheet View</a>
                <a href="<?php echo esc_url(site_url('/index.php/ta-session-filter')); ?>">Filter Sessions</a>
                <a href="<?php echo esc_url(site_url('/index.php/reports/')); ?>">Reports</a>
                <a href="<?php echo esc_url(site_url('/index.php/flagged-sessions/')); ?>">Flagged Sessions<?php echo $unreviewed_flags ? ' (' . $unreviewed_flags . ')' : ''; ?></a>
                <a href="<?php echo esc_url(site_url('/index.php/admin-checkins/')); ?>">Tutor Check-ins<?php echo $low_checkins ? ' (' . $low_checkins . ')' : ''; ?></a>
                <a href="<?php echo esc_url(site_url('/index.php/tutor-resources/')); ?>">Tutor Resources</a>
                <a href="<?php echo esc_url(site_url('/index.php/manage-semesters/')); ?>">Semesters</a>
                <a href="<?php echo esc_url(site_url('/index.php/site-bugs/')); ?>">Site Bugs</a>
                <a href="<?php echo esc_url(site_url('/index.php/add-classroom')); ?>">Add Classroom</a>
                <a href="<?php echo esc_url(site_url('/index.php/validate-tas')); ?>">Approve registrations<?php echo $pending_count ? ' (' . $pending_count . ')' : ''; ?></a>
                <a href="<?php echo esc_url(site_url('/index.php/add-user')); ?>">Add User</a>
                <a href="<?php echo esc_url(site_url('/index.php/admin-profile/')); ?>">My Profile</a>
            </nav>
        </header>

        <div class="gtp-home-grid">
            <div class="gtp-home-main">
                <div class="gtp-home-stats">
                    <a class="gtp-home-stat<?php echo $unassigned_count > 0 ? ' has-alerts' : ''; ?>" href="<?php echo esc_url(site_url('/index.php/unassigned-tutors/')); ?>">
                        <strong><?php echo (int) $unassigned_count; ?></strong>
                        <span>Unassigned tutors</span>
                    </a>
                    <a class="gtp-home-stat" href="<?php echo esc_url(site_url('/index.php/edit-classrooms/')); ?>">
                        <strong><?php echo (int) $classroom_count; ?></strong>
                        <span>Classrooms</span>
                    </a>
                    <a class="gtp-home-stat" href="<?php echo esc_url(site_url('/index.php/validate-tas/')); ?>">
                        <strong><?php echo (int) $pending_count; ?></strong>
                        <span>Pending approvals</span>
                    </a>
                    <?php
                    $flag_label = $unreviewed_flags === 1 ? 'Flagged session' : 'Flagged sessions';
                    $flag_url = site_url('/index.php/flagged-sessions/');
                    ?>
                    <a class="gtp-home-stat gtp-home-stat-alert<?php echo $unreviewed_flags > 0 ? ' has-alerts' : ''; ?>" href="<?php echo esc_url($flag_url); ?>">
                        <strong><?php echo (int) $unreviewed_flags; ?></strong>
                        <span><?php echo esc_html($flag_label); ?></span>
                    </a>
                    <?php
                    $checkin_filter_url = add_query_arg(
                        ['month' => gtp_checkin_active_month(), 'low_rating' => 1, 'status' => 'complete'],
                        site_url('/index.php/admin-checkins/')
                    );
                    ?>
                    <a class="gtp-home-stat gtp-home-stat-checkin<?php echo $low_checkins > 0 ? ' has-alerts' : ''; ?>"
                       href="<?php echo esc_url($low_checkins > 0 ? $checkin_filter_url : site_url('/index.php/admin-checkins/')); ?>">
                        <strong><?php echo (int) $low_checkins; ?></strong>
                        <span><?php echo $low_checkins === 1 ? 'Low-rated check-in' : 'Low-rated check-ins'; ?> · <?php echo esc_html($checkin_month_label); ?></span>
                    </a>
                    <?php if ($incomplete_checkins > 0 && (int) current_time('j') >= 25) : ?>
                        <a class="gtp-home-stat" href="<?php echo esc_url(add_query_arg(['month' => gtp_checkin_active_month(), 'status' => 'incomplete'], site_url('/index.php/admin-checkins/'))); ?>">
                            <strong><?php echo (int) $incomplete_checkins; ?></strong>
                            <span>Check-ins incomplete</span>
                        </a>
                    <?php endif; ?>
                </div>

                <section class="gtp-home-section">
                    <div class="gtp-home-section-header">
                        <h2>Tutors</h2>
                        <a href="<?php echo esc_url(site_url('/index.php/manage-people/')); ?>">Manage people</a>
                    </div>
                    <?php if (empty($tutors)) : ?>
                        <p class="gtp-home-empty">No validated tutors yet.</p>
                    <?php else : ?>
                        <div class="gtp-home-people">
                            <?php foreach ($tutors as $tutor) :
                                $display_name = trim($tutor->first_name . ' ' . $tutor->last_name);
                                ?>
                                <button type="button"
                                        class="gtp-home-person gtp-tutor-profile-trigger"
                                        data-tutor-id="<?php echo (int) $tutor->id; ?>"
                                        aria-label="View profile for <?php echo esc_attr($display_name); ?>">
                                    <?php echo gtp_user_avatar_html($tutor); ?>
                                    <div class="gtp-home-person-name"><?php echo esc_html($display_name); ?></div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="gtp-home-aside">
                <?php echo gtp_render_announcements_home_widget($user_id, $dash_url); ?>
            </aside>
        </div>
    </div>

    <div id="gtp-tutor-profile-modal" class="gtp-tutor-profile-modal" hidden aria-hidden="true">
        <div class="gtp-tutor-profile-backdrop" data-close-tutor-modal></div>
        <div class="gtp-tutor-profile-dialog" role="dialog" aria-modal="true" aria-labelledby="gtp-tutor-profile-title">
            <button type="button" class="gtp-tutor-profile-close" data-close-tutor-modal aria-label="Close">&times;</button>
            <div id="gtp-tutor-profile-body">
                <p>Loading…</p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_admin_dashboard', 'gtp_admin_dashboard_shortcode');

function gtp_admin_profile_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;
    $admin_id = (int) $_SESSION['gtp_user']['id'];
    $table_name = $wpdb->prefix . 'gtp_users';
    $message = '';

    if (isset($_POST['gtp_update_admin_profile'])) {
        if (!isset($_POST['gtp_admin_profile_nonce']) || !wp_verify_nonce($_POST['gtp_admin_profile_nonce'], 'gtp_update_admin_profile')) {
            $message = '<p class="gtp-msg is-error gtp-persist">Security check failed. Please try again.</p>';
        } else {
            $update_data = [
                'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
                'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'bio' => sanitize_textarea_field($_POST['bio'] ?? ''),
            ];

            if ($update_data['first_name'] === '' || $update_data['last_name'] === '') {
                $message = '<p class="gtp-msg is-error gtp-persist">First and last name are required.</p>';
            } elseif ($update_data['email'] !== '' && !is_email($update_data['email'])) {
                $message = '<p class="gtp-msg is-error gtp-persist">Please enter a valid email address.</p>';
            } else {
                if ($update_data['email'] !== '') {
                    $email_taken = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE email = %s AND id <> %d",
                        $update_data['email'],
                        $admin_id
                    ));
                    if ($email_taken) {
                        $message = '<p class="gtp-msg is-error gtp-persist">That email is already used by another account.</p>';
                    }
                }

                if ($message === '' && !empty($_FILES['headshot']['name'])) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    $movefile = wp_handle_upload($_FILES['headshot'], ['test_form' => false]);
                    if ($movefile && !isset($movefile['error'])) {
                        $update_data['headshot_url'] = $movefile['url'];
                    } else {
                        $message = '<p class="gtp-msg is-error">' . esc_html($movefile['error'] ?? 'Upload failed.') . '</p>';
                    }
                }

                if ($message === '') {
                    $updated = $wpdb->update($table_name, $update_data, ['id' => $admin_id]);
                    if ($updated === false) {
                        $message = '<p class="gtp-msg is-error">Could not save profile: ' . esc_html($wpdb->last_error) . '</p>';
                    } else {
                        $_SESSION['gtp_user']['first_name'] = $update_data['first_name'];
                        $_SESSION['gtp_user']['last_name'] = $update_data['last_name'];
                        $_SESSION['gtp_user']['email'] = $update_data['email'];
                        $message = '<p class="gtp-msg is-success">Profile updated.</p>';
                    }
                }
            }
        }
    }

    $admin = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $admin_id));
    if (!$admin) {
        return '<p>Profile not found.</p>';
    }

    ob_start();
    ?>
    <div class="gtp-page" style="max-width:640px;">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">My Profile</h1>
        <?php echo $message; ?>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('gtp_update_admin_profile', 'gtp_admin_profile_nonce'); ?>

            <div style="margin-bottom:16px;">
                <?php echo gtp_user_avatar_html($admin); ?>
            </div>

            <p>
                <label><strong>Username</strong></label><br>
                <input type="text" value="<?php echo esc_attr($admin->username); ?>" disabled style="width:100%; max-width:420px; padding:8px; background:#f3f3f3;">
            </p>

            <p>
                <label for="gtp_admin_first_name"><strong>First name</strong></label><br>
                <input type="text" id="gtp_admin_first_name" name="first_name" required value="<?php echo esc_attr($admin->first_name); ?>" style="width:100%; max-width:420px; padding:8px;">
            </p>

            <p>
                <label for="gtp_admin_last_name"><strong>Last name</strong></label><br>
                <input type="text" id="gtp_admin_last_name" name="last_name" required value="<?php echo esc_attr($admin->last_name); ?>" style="width:100%; max-width:420px; padding:8px;">
            </p>

            <p>
                <label for="gtp_admin_email"><strong>Email</strong></label><br>
                <input type="email" id="gtp_admin_email" name="email" value="<?php echo esc_attr($admin->email); ?>" style="width:100%; max-width:420px; padding:8px;">
            </p>

            <p>
                <label for="gtp_admin_headshot"><strong>Profile picture</strong></label><br>
                <input type="file" id="gtp_admin_headshot" name="headshot" accept="image/*">
            </p>

            <p>
                <label for="gtp_admin_bio"><strong>Bio</strong></label><br>
                <textarea id="gtp_admin_bio" name="bio" rows="6" style="width:100%; max-width:520px; padding:8px;"><?php echo esc_textarea($admin->bio); ?></textarea>
            </p>

            <p>
                <button type="submit" name="gtp_update_admin_profile" class="button button-primary">Save Profile</button>
            </p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_admin_profile', 'gtp_admin_profile_shortcode');



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
        $start_time = gtp_sanitize_time($_POST['start_time'] ?? '');
        $end_time = gtp_sanitize_time($_POST['end_time'] ?? '');
        $time_slot = gtp_format_time_range($start_time, $end_time);
        $roster = sanitize_textarea_field($_POST['roster']);
        $tutor_id = isset($_POST['tutor_id']) ? intval($_POST['tutor_id']) : 0;

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
                    'start_time'   => $start_time,
                    'end_time'     => $end_time,
                    'time_slot'    => $time_slot,
                    'semester_id'  => gtp_get_working_semester_id(),
                ]
            );

            if ($wpdb->last_error) {
                $message = '<p class="gtp-msg is-error">Database Error: ' . esc_html($wpdb->last_error) . '</p>';
            } else {
                $classroom_id = $wpdb->insert_id;
                $message = '<p class="gtp-msg is-success">Classroom added successfully!</p>';

                if ($tutor_id > 0) {
                    $tutor_ok = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_users WHERE id = %d AND role = 'tutor'",
                        $tutor_id
                    ));
                    if ($tutor_ok) {
                        $wpdb->insert(
                            $wpdb->prefix . 'gtp_class_assignments',
                            [
                                'classroom_id' => $classroom_id,
                                'tutor_id' => $tutor_id,
                                'first_taught' => current_time('mysql'),
                            ]
                        );
                        if ($wpdb->last_error) {
                            $message .= '<p class="gtp-msg is-error">Classroom was created, but tutor assignment failed: '
                                . esc_html($wpdb->last_error) . '</p>';
                        } else {
                            $message = '<p class="gtp-msg is-success">Classroom added and tutor assigned successfully!</p>';
                        }
                    }
                }

                // Handle the roster
                if (!empty($roster)) {
                    $student_names = explode("\n", $roster);
                    $students_table = $wpdb->prefix . 'gtp_students';

                    foreach ($student_names as $student_name) {
                        $student_name = trim($student_name);
                        if (!empty($student_name)) {
                            $parts = preg_split('/\s+/', $student_name, 2);
                            $first_name = $parts[0] ?? '';
                            $last_name = $parts[1] ?? '';
                            $wpdb->insert(
                                $students_table,
                                [
                                    'classroom_id' => $classroom_id,
                                    'first_name'   => $first_name,
                                    'last_name'    => $last_name !== '' ? $last_name : null,
                                    'student_name' => trim($first_name . ' ' . $last_name),
                                ]
                            );
                        }
                    }
                }
            }
        } else {
            $message = '<p class="gtp-msg is-error gtp-persist">Please fill out all required fields.</p>';
        }
    }

    $tutors = $wpdb->get_results(
        "SELECT id, first_name, last_name
         FROM {$wpdb->prefix}gtp_users
         WHERE role = 'tutor' AND validated = 1
         ORDER BY last_name ASC, first_name ASC"
    );

    ob_start();
    ?>
    <div class="gtp-page">
    <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Add Classroom</h1>
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
            
            
            <label>Start Time:</label>
            <input type="time" name="start_time" step="60" style="width:100%; padding:8px; margin-bottom:10px;">

            <label>End Time:</label>
            <input type="time" name="end_time" step="60" style="width:100%; padding:8px; margin-bottom:10px;">

            <label>Assign Tutor (optional):</label>
            <select name="tutor_id" style="width:100%; padding:8px; margin-bottom:10px;">
                <option value="">-- Unassigned --</option>
                <?php foreach ($tutors as $tutor) : ?>
                    <option value="<?php echo (int) $tutor->id; ?>">
                        <?php echo esc_html(trim($tutor->first_name . ' ' . $tutor->last_name)); ?>
                    </option>
                <?php endforeach; ?>
            </select>

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

    if (isset($_POST['gtp_validate_user']) && check_admin_referer('gtp_validate_user_' . (int) ($_POST['user_id'] ?? 0), 'gtp_validate_nonce')) {
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $action = sanitize_text_field($_POST['gtp_validate_user']);
        $pending = $user_id > 0
            ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND validated = 0", $user_id))
            : null;

        if (!$pending) {
            $message = '<p class="gtp-msg is-error gtp-persist">That pending user was not found.</p>';
        } elseif ($action === 'deny') {
            $wpdb->delete($table_name, ['id' => $user_id]);
            $message = '<p class="gtp-msg is-success">Registration denied and removed.</p>';
        } elseif ($action === 'approve') {
            $requested = ($pending->requested_role ?? 'tutor') === 'admin' ? 'admin' : 'tutor';

            if ($requested === 'admin') {
                $approve_user = !empty($_POST['approve_user']);
                $approve_admin = !empty($_POST['approve_as_admin']);
                if (!$approve_user || !$approve_admin) {
                    $message = '<p class="gtp-msg is-error gtp-persist">Admin applicants need both confirmations: approve this user and approve them as an admin.</p>';
                } else {
                    $wpdb->update($table_name, ['role' => 'admin', 'validated' => 1], ['id' => $user_id]);
                    $message = '<p class="gtp-msg is-success">User approved as an Admin.</p>';
                }
            } else {
                $wpdb->update($table_name, ['role' => 'tutor', 'validated' => 1], ['id' => $user_id]);
                $message = '<p class="gtp-msg is-success">User approved as a TA.</p>';
            }
        }
    }

    $pending_users = $wpdb->get_results("SELECT * FROM $table_name WHERE validated = 0 ORDER BY id DESC");

    ob_start();
    ?>
    <div class="gtp-page gtp-validate-page">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Approve registrations</h1>
        <p class="gtp-page-intro">Review new sign-ups. Admin applicants need both confirmations before approval.</p>
        <?php echo $message; ?>

        <?php if (empty($pending_users)) : ?>
            <p class="gtp-people-muted">No users are currently pending approval.</p>
        <?php else : ?>
            <ul class="gtp-validate-list">
                <?php foreach ($pending_users as $user) :
                    $requested = (($user->requested_role ?? 'tutor') === 'admin') ? 'admin' : 'tutor';
                    $requested_label = $requested === 'admin' ? 'Admin' : 'TA / Tutor';
                    ?>
                    <li class="gtp-validate-card<?php echo $requested === 'admin' ? ' is-admin-request' : ''; ?>">
                        <div class="gtp-validate-card-main">
                            <div class="gtp-validate-card-meta">
                                <strong><?php echo esc_html(trim($user->first_name . ' ' . $user->last_name)); ?></strong>
                                <span class="gtp-validate-role-pill gtp-validate-role-pill--<?php echo esc_attr($requested); ?>">
                                    Registered as <?php echo esc_html($requested_label); ?>
                                </span>
                            </div>
                            <p class="gtp-people-muted">
                                @<?php echo esc_html($user->username); ?>
                                · <?php echo esc_html($user->email); ?>
                            </p>
                        </div>

                        <form method="post" class="gtp-validate-actions">
                            <?php wp_nonce_field('gtp_validate_user_' . (int) $user->id, 'gtp_validate_nonce'); ?>
                            <input type="hidden" name="user_id" value="<?php echo (int) $user->id; ?>">

                            <?php if ($requested === 'admin') : ?>
                                <div class="gtp-validate-double">
                                    <label>
                                        <input type="checkbox" name="approve_user" value="1" class="gtp-admin-approve-user">
                                        Approve this user
                                    </label>
                                    <label>
                                        <input type="checkbox" name="approve_as_admin" value="1" class="gtp-admin-approve-role">
                                        Approve this user as an admin
                                    </label>
                                </div>
                                <div class="gtp-validate-buttons">
                                    <button type="submit" name="gtp_validate_user" value="approve" class="button button-primary gtp-admin-approve-btn" disabled>
                                        Approve as Admin
                                    </button>
                                    <button type="submit" name="gtp_validate_user" value="deny" class="button">Deny</button>
                                </div>
                            <?php else : ?>
                                <div class="gtp-validate-buttons">
                                    <button type="submit" name="gtp_validate_user" value="approve" class="button button-primary">Approve as TA</button>
                                    <button type="submit" name="gtp_validate_user" value="deny" class="button">Deny</button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
            <script>
            (function () {
                document.querySelectorAll('.gtp-validate-card.is-admin-request').forEach(function (card) {
                    var userBox = card.querySelector('.gtp-admin-approve-user');
                    var roleBox = card.querySelector('.gtp-admin-approve-role');
                    var btn = card.querySelector('.gtp-admin-approve-btn');
                    if (!userBox || !roleBox || !btn) {
                        return;
                    }
                    function sync() {
                        btn.disabled = !(userBox.checked && roleBox.checked);
                    }
                    userBox.addEventListener('change', sync);
                    roleBox.addEventListener('change', sync);
                    sync();
                });
            })();
            </script>
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
        $role = sanitize_text_field($_POST['role']);

        if ($first_name === '' || $last_name === '' || $username === '' || $email === '' || $role === '') {
            $message = '<p class="gtp-msg is-error gtp-persist">Please fill out all required fields.</p>';
        } elseif (!is_email($email)) {
            $message = '<p class="gtp-msg is-error gtp-persist">Please enter a valid email address.</p>';
        } elseif ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE username = %s", $username))) {
            $message = '<p class="gtp-msg is-error gtp-persist">Username already exists.</p>';
        } elseif ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email))) {
            $message = '<p class="gtp-msg is-error gtp-persist">An account with that email already exists.</p>';
        } else {
            // Unusable placeholder until the user sets their own password via invite link
            $placeholder = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            $role = in_array($role, ['tutor', 'admin'], true) ? $role : 'tutor';

            $wpdb->insert($table_name, [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'username' => $username,
                'email' => $email,
                'password' => $placeholder,
                'role' => $role,
                'requested_role' => $role,
                'validated' => 1,
                'password_set' => 0,
            ]);

            if ($wpdb->insert_id) {
                $user_id = (int) $wpdb->insert_id;
                $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $user_id));
                $raw_token = gtp_create_password_invite_token($user_id);

                if ($raw_token && gtp_send_password_invite_email($user, $raw_token)) {
                    $message = '<p class="gtp-msg is-success">User created. An invite email was sent to <strong>'
                        . esc_html($email)
                        . '</strong> so they can set their own password.</p>';
                } elseif ($raw_token) {
                    $invite_url = gtp_set_password_url($raw_token);
                    $message = '<p class="gtp-msg is-error">User created, but the invite email could not be sent. '
                        . 'Share this one-time link with them (expires in 72 hours):<br>'
                        . '<code style="word-break:break-all;">' . esc_html($invite_url) . '</code></p>';
                } else {
                    $message = '<p class="gtp-msg is-error gtp-persist">User was created, but the invite link could not be generated. Please contact support.</p>';
                }
            } else {
                $message = '<p class="gtp-msg is-error">Error adding user.</p>';
            }
        }
    }

    ob_start();
    ?>
    <div class="gtp-page">
    <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Add User</h1>
        <p class="gtp-page-intro">Invite someone directly (skips public registration approval). They get a one-time email link to set their password — you never choose or email a password.</p>
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

            <label>Role:</label><br>
            <select name="role" required>
                <option value="tutor">Tutor</option>
                <option value="admin">Admin</option>
            </select><br><br>

            <button type="submit" name="gtp_add_user_submit" class="button button-primary">Add User &amp; Send Invite</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_add_user', 'gtp_add_user_shortcode');
