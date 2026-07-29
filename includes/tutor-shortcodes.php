<?php
function gtp_TA_dashboard_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;

    $user_id = (int) $_SESSION['gtp_user']['id'];
    $name = esc_html($_SESSION['gtp_user']['first_name']);
    $dash_url = site_url('/index.php/TA-dashboard/');

    $tutor = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gtp_users WHERE id = %d",
        $user_id
    ));

    $classrooms = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*
         FROM {$wpdb->prefix}gtp_classrooms c
         INNER JOIN {$wpdb->prefix}gtp_class_assignments a ON a.classroom_id = c.id
         WHERE a.tutor_id = %d AND c.semester_id = %d
         ORDER BY c.school ASC, c.subject ASC",
        $user_id,
        gtp_get_live_semester_id()
    ));

    // Fellow tutors who share any of this tutor's classrooms
    $peer_tutors = [];
    if (!empty($classrooms)) {
        $classroom_ids = array_map(static function ($c) {
            return (int) $c->id;
        }, $classrooms);
        $ph = implode(',', array_fill(0, count($classroom_ids), '%d'));
        $params = array_merge($classroom_ids, [$user_id]);
        $peer_tutors = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT u.id, u.first_name, u.last_name, u.headshot_url
             FROM {$wpdb->prefix}gtp_users u
             INNER JOIN {$wpdb->prefix}gtp_class_assignments a ON a.tutor_id = u.id
             WHERE a.classroom_id IN ($ph)
               AND u.id <> %d
               AND u.role = 'tutor'
             ORDER BY u.last_name ASC, u.first_name ASC
             LIMIT 12",
            ...$params
        ));
    }

    ob_start();
    ?>
    <div class="gtp-home gtp-ta-home">
        <?php if (!empty($_SESSION['gtp_session_logged_success'])) : ?>
            <div class="gtp-home-success">Session logged successfully!</div>
            <?php unset($_SESSION['gtp_session_logged_success']); ?>
        <?php endif; ?>

        <?php
        $missing_profile = gtp_tutor_incomplete_profile_parts($tutor);
        if (!empty($missing_profile)) :
            $profile_url = site_url('/index.php/TA-profile');
            $missing_list = esc_html(implode(', ', $missing_profile));
            ?>
            <div class="gtp-home-notice gtp-home-notice--profile gtp-persist" role="status">
                <strong>Complete your profile</strong>
                <span>Add your <?php echo $missing_list; ?> so students and admins can get to know you.</span>
                <a href="<?php echo esc_url($profile_url); ?>">Update My Profile</a>
            </div>
        <?php endif; ?>

        <header class="gtp-home-hero">
            <?php echo gtp_user_avatar_html($tutor ?: (object) [
                'first_name' => $_SESSION['gtp_user']['first_name'],
                'last_name' => $_SESSION['gtp_user']['last_name'],
                'headshot_url' => '',
            ]); ?>
            <div class="gtp-home-hero-text">
                <h1>Welcome, <?php echo $name; ?></h1>
                <p>TA home — classes, announcements, and quick actions.</p>
            </div>
            <nav class="gtp-home-nav" aria-label="TA shortcuts">
                <a href="<?php echo esc_url(site_url('/index.php/log-session')); ?>">Log Session</a>
                <a href="<?php echo esc_url(site_url('/index.php/log-substitute')); ?>">Log Substitute</a>
                <a href="<?php echo esc_url(site_url('/index.php/my-logged-sessions')); ?>">My Sessions</a>
                <a href="<?php echo esc_url(site_url('/index.php/my-classes/')); ?>">Manage Classes</a>
                <a href="<?php echo esc_url(site_url('/index.php/monthly-checkins/')); ?>">Monthly Check-ins</a>
                <a href="<?php echo esc_url(site_url('/index.php/tutor-resources/')); ?>">Tutor Resources</a>
                <a href="<?php echo esc_url(site_url('/index.php/TA-profile')); ?>">My Profile</a>
            </nav>
        </header>

        <?php
        $due_checkins = gtp_tutor_due_checkins($user_id);
        $due_count = count($due_checkins['items']);
        $day_of_month = (int) current_time('j');
        if ($due_count > 0 || ($day_of_month >= 25 && !empty($classrooms))) :
            $checkin_url = site_url('/index.php/monthly-checkins/');
            ?>
            <div class="gtp-home-stats">
                <?php if ($due_count > 0) : ?>
                    <a class="gtp-home-stat gtp-home-stat-checkin has-alerts" href="<?php echo esc_url($checkin_url); ?>">
                        <strong><?php echo (int) $due_count; ?></strong>
                        <span>Check-in<?php echo $due_count === 1 ? '' : 's'; ?> due · <?php echo esc_html(gtp_checkin_month_label($due_checkins['month'])); ?></span>
                    </a>
                <?php else : ?>
                    <a class="gtp-home-stat gtp-home-stat-checkin" href="<?php echo esc_url($checkin_url); ?>">
                        <strong>✓</strong>
                        <span>Monthly check-ins complete</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="gtp-home-grid">
            <div class="gtp-home-main">
                <section class="gtp-home-section">
                    <div class="gtp-home-section-header">
                        <h2>My Classes</h2>
                        <a href="<?php echo esc_url(site_url('/index.php/my-classes/')); ?>">Open full class view</a>
                    </div>
                    <?php if (empty($classrooms)) : ?>
                        <p class="gtp-home-empty">You are not assigned to any classes yet.</p>
                    <?php else : ?>
                        <div class="gtp-home-class-list">
                            <?php foreach ($classrooms as $classroom) :
                                $teacher = trim($classroom->teacher_first_name . ' ' . $classroom->teacher_last_name);
                                $time = gtp_format_classroom_schedule($classroom);
                                $roster_count = (int) $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_students WHERE classroom_id = %d",
                                    $classroom->id
                                ));
                                ?>
                                <article class="gtp-home-class-card">
                                    <h3><?php echo esc_html(gtp_format_classroom_subject($classroom)); ?> · <?php echo esc_html($classroom->school); ?></h3>
                                    <p><strong>Teacher:</strong> <?php echo esc_html($teacher !== '' ? $teacher : '—'); ?></p>
                                    <p class="gtp-home-muted"><strong>Time:</strong> <?php echo esc_html($time !== '' ? $time : '—'); ?></p>
                                    <p class="gtp-home-muted"><?php echo (int) $roster_count; ?> student<?php echo $roster_count === 1 ? '' : 's'; ?> on roster</p>
                                    <?php if (!empty($classroom->zoom_link)) : ?>
                                        <p class="gtp-home-muted"><a href="<?php echo esc_url($classroom->zoom_link); ?>" target="_blank" rel="noopener noreferrer">Zoom link</a></p>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if (!empty($peer_tutors)) : ?>
                    <section class="gtp-home-section" style="margin-top:28px;">
                        <div class="gtp-home-section-header">
                            <h2>Tutors on your classes</h2>
                        </div>
                        <div class="gtp-home-people">
                            <?php foreach ($peer_tutors as $peer) : ?>
                                <div class="gtp-home-person">
                                    <?php echo gtp_user_avatar_html($peer); ?>
                                    <div class="gtp-home-person-name"><?php echo esc_html(trim($peer->first_name . ' ' . $peer->last_name)); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="gtp-home-aside">
                <?php echo gtp_render_announcements_home_widget($user_id, $dash_url); ?>
            </aside>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_TA_dashboard', 'gtp_TA_dashboard_shortcode');


function gtp_log_session_shortcode() {
    global $wpdb;

    // Check login and role
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        return '<p>You do not have access to this page.</p>';
    }

    $tutor_id = $_SESSION['gtp_user']['id'];
    $first_name = $_SESSION['gtp_user']['first_name'];
    $last_name = $_SESSION['gtp_user']['last_name'];
    $username = $_SESSION['gtp_user']['username'];

    // Handle form submission
    if (isset($_POST['gtp_submit_session'])) {
        $classroom_id = intval($_POST['classroom_id']);
        $session_date = sanitize_text_field($_POST['session_date']);
        $start_time = gtp_sanitize_time($_POST['start_time'] ?? '');
        $end_time = gtp_sanitize_time($_POST['end_time'] ?? '');
        $time_slot = gtp_format_time_range($start_time, $end_time);
        $no_show = isset($_POST['no_show']) ? 1 : 0;
        // teacher_present: 1 when toggle is off (teacher was present), 0 when toggle is on
        $teacher_present = isset($_POST['teacher_not_present']) ? 0 : 1;
        // If class was a no-show, force empty attendance; topic is optional only then
        if ($no_show) {
            $attendance = json_encode([]);
        } else {
            $attendance = isset($_POST['attendance']) ? json_encode(array_map('intval', $_POST['attendance'])) : '';
        }
        $topic = sanitize_textarea_field($_POST['topic'] ?? '');
        $comments = sanitize_textarea_field($_POST['comments'] ?? '');
        $classroom = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gtp_classrooms WHERE id = %d", $classroom_id)
        );

        if (!$no_show && trim($topic) === '') {
            echo '<p class="gtp-msg is-error gtp-persist">Please enter the topic covered, or mark the class as a no-show.</p>';
        } elseif ($classroom) {
            $wpdb->insert($wpdb->prefix . 'gtp_sessions', [
                'tutor_username'  => $username,
                'first_name'      => $first_name,
                'last_name'       => $last_name,
                'school'          => $classroom->school,
                'subject'         => $classroom->subject,
                'teacher_name'    => $classroom->teacher_first_name . ' ' . $classroom->teacher_last_name,
                'session_date'    => $session_date,
                'start_time'      => $start_time,
                'end_time'        => $end_time,
                'time_slot'       => $time_slot,
                'attendance'      => $attendance,
                'topic'           => $topic,
                'comments'        => $comments,
                'is_substitute'   => 0,
                'teacher_present' => $teacher_present,
                'no_show'         => $no_show,
                'semester_id'    => (int) ($classroom->semester_id ?: gtp_get_live_semester_id()),
            ]);
            if ($wpdb->last_error) {
                echo '<p class="gtp-msg is-error">DB Error: ' . esc_html($wpdb->last_error) . '</p>';
            } else {
                $_SESSION['gtp_session_logged_success'] = true;
                wp_redirect(site_url('/index.php/ta-dashboard/'));
                exit;
            }
        }
    }

    // Get assigned classrooms for the tutor
    $classrooms = $wpdb->get_results($wpdb->prepare(
        "SELECT c.id, c.subject, c.school, c.teacher_first_name, c.teacher_last_name, c.start_time, c.end_time, c.semester_id
         FROM {$wpdb->prefix}gtp_classrooms c
         JOIN {$wpdb->prefix}gtp_class_assignments a ON c.id = a.classroom_id
         WHERE a.tutor_id = %d AND c.semester_id = %d
         ORDER BY c.subject ASC",
        $tutor_id,
        gtp_get_live_semester_id()
    ));

    ob_start();
    ?>
    <div class="gtp-page">
        <?php echo gtp_dashboard_back_link('tutor'); ?>
        <h1 class="gtp-page-title">Log Session</h1>
        <p class="gtp-page-intro">Record attendance and notes for one of your assigned classes.</p>

        <form method="post" class="gtp-form-stack">
            <label class="gtp-field">
                <span>Select class</span>
                <select name="classroom_id" id="gtp-classroom-select" required>
                    <option value="">— Select a class —</option>
                    <?php foreach ($classrooms as $classroom): ?>
                        <option value="<?php echo esc_attr($classroom->id); ?>"
                                data-start-time="<?php echo esc_attr(gtp_time_input_value($classroom->start_time)); ?>"
                                data-end-time="<?php echo esc_attr(gtp_time_input_value($classroom->end_time)); ?>">
                            <?php echo esc_html(gtp_format_classroom_subject($classroom) . ', ' . $classroom->school . ' - ' . $classroom->teacher_first_name . ' ' . $classroom->teacher_last_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="gtp-field">
                <span>Date</span>
                <input type="date" name="session_date" required>
            </label>

            <div class="gtp-form-row">
                <label class="gtp-field" for="gtp-session-start-time">
                    <span>Start time</span>
                    <input type="time" name="start_time" id="gtp-session-start-time" step="60">
                </label>
                <label class="gtp-field" for="gtp-session-end-time">
                    <span>End time</span>
                    <input type="time" name="end_time" id="gtp-session-end-time" step="60">
                </label>
            </div>

            <div class="gtp-field">
                <span>Attendance</span>
                <div id="attendance-checklist-container" class="gtp-checklist-box">
                    <!-- Student checklist will be loaded here -->
                </div>
            </div>

            <div>
                <button type="button" id="gtp-show-add-student" class="button">Add student</button>
                <div id="gtp-add-student-panel" class="gtp-inline-add" hidden>
                    <input type="text" id="new-student-first-name" placeholder="First name">
                    <input type="text" id="new-student-last-name" placeholder="Last name (optional)">
                    <button type="button" id="add-student-button" class="button button-primary">Add</button>
                    <button type="button" id="gtp-cancel-add-student" class="button">Cancel</button>
                </div>
            </div>

            <label class="gtp-field" id="gtp-topic-field">
                <span id="gtp-topic-label">Topic covered</span>
                <textarea name="topic" id="gtp-session-topic" required rows="3"></textarea>
            </label>

            <label class="gtp-field">
                <span>Comments (optional)</span>
                <textarea name="comments" rows="3" placeholder="Comment if there were any issues during this session, if it was a makeup session, or anything else that you would like admin to know about this session"></textarea>
            </label>

            <label class="gtp-check-row">
                <input type="checkbox" name="teacher_not_present" id="gtp-teacher-not-present" value="1">
                <span>Teacher was not present on Zoom</span>
            </label>

            <label class="gtp-check-row">
                <input type="checkbox" name="no_show" id="gtp-no-show" value="1">
                <span>The class did not show up for the Zoom session</span>
            </label>

            <div class="gtp-form-actions">
                <button type="submit" name="gtp_submit_session" value="1" class="button button-primary">Log session</button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_log_session', 'gtp_log_session_shortcode');

function gtp_log_substitute_session_shortcode() {
    global $wpdb;

    // Check login and role
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        return '<p>You do not have access to this page.</p>';
    }

    $tutor_id = $_SESSION['gtp_user']['id'];
    $first_name = $_SESSION['gtp_user']['first_name'];
    $last_name = $_SESSION['gtp_user']['last_name'];
    $username = $_SESSION['gtp_user']['username'];

    // Handle form submission
    if (isset($_POST['gtp_submit_session'])) {
        $classroom_id = intval($_POST['classroom_id']);
        $session_date = sanitize_text_field($_POST['session_date']);
        $start_time = gtp_sanitize_time($_POST['start_time'] ?? '');
        $end_time = gtp_sanitize_time($_POST['end_time'] ?? '');
        $time_slot = gtp_format_time_range($start_time, $end_time);
        $attendance = isset($_POST['attendance']) ? json_encode(array_map('intval', $_POST['attendance'])) : '';
        $topic = sanitize_textarea_field($_POST['topic']);
        $comments = sanitize_textarea_field($_POST['comments']);
        $is_sub = 1; // Always a substitute session
        $classroom = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gtp_classrooms WHERE id = %d", $classroom_id)
        );

        if ($classroom) {
            $wpdb->insert($wpdb->prefix . 'gtp_sessions', [
                'tutor_username'  => $username,
                'first_name'      => $first_name,
                'last_name'       => $last_name,
                'school'          => $classroom->school,
                'subject'         => $classroom->subject,
                'teacher_name'    => $classroom->teacher_first_name . ' ' . $classroom->teacher_last_name,
                'session_date'    => $session_date,
                'start_time'      => $start_time,
                'end_time'        => $end_time,
                'time_slot'       => $time_slot,
                'attendance'      => $attendance,
                'topic'           => $topic,
                'comments'        => $comments,
                'is_substitute'   => $is_sub,
                'teacher_present' => 1,
                'no_show'         => 0,
                'semester_id'    => (int) ($classroom->semester_id ?: gtp_get_live_semester_id()),
            ]);
            if ($wpdb->last_error) {
                echo '<p class="gtp-msg is-error">DB Error: ' . esc_html($wpdb->last_error) . '</p>';
            } else {
                $_SESSION['gtp_session_logged_success'] = true;
                wp_redirect(site_url('/index.php/ta-dashboard/'));
                exit;
            }
        }
    }

    // Get all subjects from the shared catalog
    $subjects = gtp_get_subjects();

    ob_start();
    ?>
    <div class="gtp-page">
        <?php echo gtp_dashboard_back_link('tutor'); ?>
        <h1 class="gtp-page-title">Log substitute session</h1>
        <p class="gtp-page-intro">Log a session for a class you covered as a substitute.</p>

        <form method="post" class="gtp-form-stack">
            <label class="gtp-field">
                <span>Select subject</span>
                <select id="gtp-subject-select" name="subject" required>
                    <option value="">— Select a subject —</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo esc_attr($subject); ?>"><?php echo esc_html($subject); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="gtp-field">
                <span>Select class</span>
                <select name="classroom_id" id="gtp-classroom-select" required disabled>
                    <option value="">— Select a subject first —</option>
                </select>
            </label>

            <label class="gtp-field">
                <span>Date</span>
                <input type="date" name="session_date" required>
            </label>

            <div class="gtp-form-row">
                <label class="gtp-field" for="gtp-session-start-time">
                    <span>Start time</span>
                    <input type="time" name="start_time" id="gtp-session-start-time" step="60">
                </label>
                <label class="gtp-field" for="gtp-session-end-time">
                    <span>End time</span>
                    <input type="time" name="end_time" id="gtp-session-end-time" step="60">
                </label>
            </div>

            <div class="gtp-field">
                <span>Attendance</span>
                <div id="attendance-checklist-container" class="gtp-checklist-box">
                    <!-- Student checklist will be loaded here -->
                </div>
            </div>

            <div>
                <button type="button" id="gtp-show-add-student" class="button">Add student</button>
                <div id="gtp-add-student-panel" class="gtp-inline-add" hidden>
                    <input type="text" id="new-student-first-name" placeholder="First name">
                    <input type="text" id="new-student-last-name" placeholder="Last name (optional)">
                    <button type="button" id="add-student-button" class="button button-primary">Add</button>
                    <button type="button" id="gtp-cancel-add-student" class="button">Cancel</button>
                </div>
            </div>

            <label class="gtp-field">
                <span>Topic covered</span>
                <textarea name="topic" required rows="3"></textarea>
            </label>

            <label class="gtp-field">
                <span>Comments (optional)</span>
                <textarea name="comments" rows="3" placeholder="Comment if there were any issues during this session, if it was a makeup session, or anything else that you would like admin to know about this session"></textarea>
            </label>

            <input type="hidden" name="is_substitute" value="1">

            <div class="gtp-form-actions">
                <button type="submit" name="gtp_submit_session" value="1" class="button button-primary">Log session</button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_log_substitute_session', 'gtp_log_substitute_session_shortcode');

function gtp_ta_profile_shortcode()
{
    global $wpdb;
    $tutor_id = $_SESSION['gtp_user']['id'];
    $table_name = $wpdb->prefix . 'gtp_users';

    // Handle form submission
    if (isset($_POST['gtp_update_profile'])) {
        // Verify nonce
        if (!isset($_POST['gtp_profile_nonce']) || !wp_verify_nonce($_POST['gtp_profile_nonce'], 'gtp_update_profile')) {
            wp_die('Nonce verification failed!');
        }

        $update_data = [];

        // Handle headshot upload
        if (!empty($_FILES['headshot']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $uploadedfile = $_FILES['headshot'];
            $upload_overrides = ['test_form' => false];
            $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $update_data['headshot_url'] = $movefile['url'];
            } else {
                echo '<p class="gtp-msg is-error">' . esc_html($movefile['error']) . '</p>';
            }
        }

        // Update bio, name, college, and subject preferences
        $update_data['bio'] = sanitize_textarea_field($_POST['bio']);
        $update_data['first_name'] = sanitize_text_field($_POST['first_name']);
        $update_data['last_name'] = sanitize_text_field($_POST['last_name']);
        $update_data['school'] = sanitize_text_field($_POST['college'] ?? '');
        
        $subject_preferences = [];
        if (isset($_POST['subject_preferences'])) {
            foreach ($_POST['subject_preferences'] as $subject => $preference) {
                $subject_preferences[sanitize_text_field($subject)] = sanitize_text_field($preference);
            }
        }
        $update_data['subject_preferences'] = json_encode($subject_preferences);
        $update_data['email_notifications'] = !empty($_POST['email_notifications']) ? 1 : 0;

        if (!empty($update_data)) {
            $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $tutor_id]
            );

            if ($wpdb->last_error) {
                echo '<p class="gtp-msg is-error">DB Error: ' . esc_html($wpdb->last_error) . '</p>';
            } else {
                $_SESSION['gtp_user']['first_name'] = $update_data['first_name'];
                $_SESSION['gtp_user']['last_name'] = $update_data['last_name'];
                $_SESSION['gtp_profile_updated'] = true;
                wp_redirect(site_url('/index.php/ta-profile/'));
                exit;
            }
        }
    }

    // Fetch tutor data
    $tutor = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $tutor_id));

    $first_name = $tutor->first_name;
    $last_name = $tutor->last_name;
    $bio = $tutor->bio;
    $college = $tutor->school ?? '';
    $headshot_url = $tutor->headshot_url;
    $subject_preferences = json_decode($tutor->subject_preferences, true) ?: [];

    $all_subjects = gtp_get_subjects();
    $preference_levels = [
        'cannot_tutor' => 'Cannot Tutor',
        'willing_to_tutor' => 'Willing to Tutor',
        'excited_to_tutor' => 'Would be Excited to Tutor'
    ];

    ob_start();
    $message = '';
    if (!empty($_SESSION['gtp_profile_updated'])) {
        $message = '<p class="gtp-msg is-success">Profile saved.</p>';
        unset($_SESSION['gtp_profile_updated']);
    }
    ?>
    <div class="gtp-page gtp-profile-page">
        <?php echo gtp_dashboard_back_link('tutor'); ?>
        <h1 class="gtp-page-title">My Profile</h1>
        <p class="gtp-page-intro">Update your college, photo, bio, and subject preferences.</p>
        <?php echo $message; ?>

        <form method="post" enctype="multipart/form-data" class="gtp-profile-form">
            <?php wp_nonce_field('gtp_update_profile', 'gtp_profile_nonce'); ?>

            <section class="gtp-profile-card">
                <div class="gtp-profile-card-header">
                    <div class="gtp-profile-avatar-wrap">
                        <?php echo gtp_user_avatar_html($tutor, 'gtp-home-avatar gtp-profile-avatar'); ?>
                    </div>
                    <div class="gtp-profile-card-intro">
                        <h2><?php echo esc_html(trim($first_name . ' ' . $last_name)); ?></h2>
                        <p>Your photo appears on the TA dashboard and for admins.</p>
                        <label class="gtp-profile-upload">
                            <span class="button">Change photo</span>
                            <input type="file" id="headshot" name="headshot" accept="image/*">
                        </label>
                    </div>
                </div>

                <div class="gtp-form-stack">
                    <div class="gtp-field-row">
                        <label class="gtp-field">
                            <span>First name (must match BILL.com)</span>
                            <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($first_name); ?>">
                        </label>
                        <label class="gtp-field">
                            <span>Last name (must match BILL.com)</span>
                            <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($last_name); ?>">
                        </label>
                    </div>

                    <label class="gtp-field">
                        <span>College</span>
                        <input type="text" id="college" name="college" value="<?php echo esc_attr($college); ?>" placeholder="Your college or university">
                    </label>

                    <label class="gtp-field">
                        <span>Website bio</span>
                        <textarea name="bio" rows="5" placeholder="A short intro for the GTP site"><?php echo esc_textarea($bio); ?></textarea>
                    </label>
                </div>
            </section>

            <section class="gtp-profile-card">
                <div class="gtp-profile-card-title">
                    <h2>Subject preferences</h2>
                    <p>Tell admins which subjects you’re strongest in.</p>
                </div>
                <div class="gtp-prefs-table-wrap">
                    <table class="gtp-prefs-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <?php foreach ($preference_levels as $level_label) : ?>
                                    <th><?php echo esc_html($level_label); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_subjects as $subject) :
                                $current_preference = isset($subject_preferences[$subject]) ? $subject_preferences[$subject] : 'cannot_tutor';
                                ?>
                                <tr>
                                    <th scope="row"><?php echo esc_html($subject); ?></th>
                                    <?php foreach ($preference_levels as $level_key => $level_label) : ?>
                                        <td>
                                            <input type="radio"
                                                   name="subject_preferences[<?php echo esc_attr($subject); ?>]"
                                                   value="<?php echo esc_attr($level_key); ?>"
                                                   <?php checked($current_preference, $level_key); ?>
                                                   aria-label="<?php echo esc_attr($subject . ': ' . $level_label); ?>">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php echo gtp_email_notifications_toggle_html(isset($tutor->email_notifications) ? $tutor->email_notifications : 1); ?>

            <div class="gtp-form-actions">
                <button type="submit" name="gtp_update_profile" value="1" class="button button-primary">Save profile</button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_ta_profile', 'gtp_ta_profile_shortcode');