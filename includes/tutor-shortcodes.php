<?php
function gtp_TA_dashboard_shortcode() {
    // Check if user is logged in via session
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        return '<p>You do not have access to this page.</p>';
    }

    $name = esc_html($_SESSION['gtp_user']['first_name']);

    ob_start();
    ?>
    <div style="max-width:600px; margin:30px auto; padding:20px; background:#f1f1f1; border-radius:10px;">
        <?php
        if (isset($_SESSION['gtp_session_logged_success']) && $_SESSION['gtp_session_logged_success']) {
            echo '<div id="gtp-success-banner" style="color: green; font-weight: bold; margin-bottom: 15px;">Session logged successfully!</div>';
            unset($_SESSION['gtp_session_logged_success']);
        }
        ?>
        <h2>Welcome, <?php echo $name; ?>!</h2>

        <div style="margin-top:20px; display: flex; gap: 15px;">
            <form action="<?php echo esc_url(site_url('/index.php/TA-profile')); ?>" method="get">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    My profile
                </button>
        </form>


            <form action="<?php echo esc_url(site_url('/index.php/log-session')); ?>" method="get">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Log My Sessions
                </button>
        </form>


            <form action="<?php echo esc_url(site_url('/index.php/log-substitute')); ?>" method="get">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Log Substitute Sessions
                </button>
        </form>

        <form action="<?php echo esc_url(site_url('/index.php/my-logged-sessions')); ?>" method="get">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    My Logged Sessions
                </button>
        </form>

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
        $attendance = isset($_POST['attendance']) ? json_encode(array_map('intval', $_POST['attendance'])) : '';
        $topic = sanitize_textarea_field($_POST['topic']);
        $comments = sanitize_textarea_field($_POST['comments']);
        $is_sub = isset($_POST['is_substitute']) ? 1 : 0;
        $classroom = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gtp_classrooms WHERE id = %d", $classroom_id)
        );

        if ($classroom) {
            $wpdb->insert($wpdb->prefix . 'gtp_sessions', [
                'tutor_username' => $username,
                'first_name'     => $first_name,
                'last_name'      => $last_name,
                'school'         => $classroom->school,
                'subject'        => $classroom->subject,
                'teacher_name'   => $classroom->teacher_first_name . ' ' . $classroom->teacher_last_name,
                'session_date'   => $session_date,
                'attendance'     => $attendance,
                'topic'          => $topic,
                'comments'       => $comments,
                'is_substitute'  => $is_sub
            ]);
            if ($wpdb->last_error) {
                echo '<p style="color:red;">DB Error: ' . esc_html($wpdb->last_error) . '</p>';
            } else {
                $_SESSION['gtp_session_logged_success'] = true;
                wp_redirect(site_url('/index.php/ta-dashboard/'));
                exit;
            }
        }
    }

    // Get assigned classrooms for the tutor
    $classrooms = $wpdb->get_results($wpdb->prepare(
        "SELECT c.id, c.subject, c.school, c.teacher_first_name, c.teacher_last_name FROM {$wpdb->prefix}gtp_classrooms c
         JOIN {$wpdb->prefix}gtp_class_assignments a ON c.id = a.classroom_id
         WHERE a.tutor_id = %d ORDER BY c.subject ASC",
        $tutor_id
    ));

    ob_start();
    ?>
    <form method="post" style="max-width:600px; margin:20px auto; padding:20px; background:#f9f9f9; border-radius:8px;">


        <p><a href="<?php echo esc_url(site_url('/index.php/ta-dashboard/')); ?>" class="button">← Back to Dashboard</a></p>

        <label>Select Class:</label><br>
        <select name="classroom_id" id="gtp-classroom-select" required style="width:100%; padding:8px; margin-bottom:10px; box-sizing: border-box;">
            <option value="">-- Select a Class --</option>
            <?php foreach ($classrooms as $classroom): ?>
                <option value="<?php echo esc_attr($classroom->id); ?>"><?php echo esc_html($classroom->subject . ', ' . $classroom->school . ' - ' . $classroom->teacher_first_name . ' ' . $classroom->teacher_last_name); ?></option>
            <?php endforeach; ?>
        </select>

        <label>Date:</label><br>
        <input type="date" name="session_date" required style="width:100%; padding:8px; margin-bottom:10px; box-sizing: border-box;">

        <label>Attendance:</label>
        <div id="attendance-checklist-container" style="margin-bottom: 10px; border: 1px solid #ccc; padding: 10px; max-height: 200px; overflow-y: auto;">
            <!-- Student checklist will be loaded here -->
        </div>
        <div style="margin-bottom: 20px; display: flex; align-items: center;">
            <label for="new-student-name" style="margin-right: 10px; white-space: nowrap;">Add student:</label>
            <input type="text" id="new-student-name" placeholder="Enter new student name" style="flex-grow: 1; padding: 8px; box-sizing: border-box; height: 38px;">
            <button type="button" id="add-student-button" class="button" style="margin-left: 10px; height: 38px; line-height: 1; padding: 0 15px;">Add</button>
        </div>

        <label>Topic Covered:</label>
        <textarea name="topic" required style="width:100%; height:60px; margin-bottom:10px; box-sizing: border-box;"></textarea>

        <label>Comments (optional):</label>
        <textarea name="comments" style="width:100%; height:60px; margin-bottom:10px; box-sizing: border-box;"></textarea>

        <label><input type="checkbox" name="is_substitute"> Substitute Session</label><br><br>

        <input type="submit" name="gtp_submit_session" value="Log Session" class="button button-primary">
    </form>
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
        $attendance = isset($_POST['attendance']) ? json_encode(array_map('intval', $_POST['attendance'])) : '';
        $topic = sanitize_textarea_field($_POST['topic']);
        $comments = sanitize_textarea_field($_POST['comments']);
        $is_sub = 1; // Always a substitute session
        $classroom = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gtp_classrooms WHERE id = %d", $classroom_id)
        );

        if ($classroom) {
            $wpdb->insert($wpdb->prefix . 'gtp_sessions', [
                'tutor_username' => $username,
                'first_name'     => $first_name,
                'last_name'      => $last_name,
                'school'         => $classroom->school,
                'subject'        => $classroom->subject,
                'teacher_name'   => $classroom->teacher_first_name . ' ' . $classroom->teacher_last_name,
                'session_date'   => $session_date,
                'attendance'     => $attendance,
                'topic'          => $topic,
                'comments'       => $comments,
                'is_substitute'  => $is_sub
            ]);
            if ($wpdb->last_error) {
                echo '<p style="color:red;">DB Error: ' . esc_html($wpdb->last_error) . '</p>';
            } else {
                $_SESSION['gtp_session_logged_success'] = true;
                wp_redirect(site_url('/index.php/ta-dashboard/'));
                exit;
            }
        }
    }

    // Get all subjects
    $subjects = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT subject FROM {$wpdb->prefix}gtp_classrooms ORDER BY subject ASC"
        )
    );

    ob_start();
    ?>
    <form method="post" style="max-width:600px; margin:20px auto; padding:20px; background:#f9f9f9; border-radius:8px;">


        <p><a href="<?php echo esc_url(site_url('/index.php/ta-dashboard/')); ?>" class="button">← Back to Dashboard</a></p>

        <label>Select Subject:</label><br>
        <select id="gtp-subject-select" name="subject" required style="width:100%; padding:8px; margin-bottom:10px; box-sizing: border-box;">
            <option value="">-- Select a Subject --</option>
            <?php foreach ($subjects as $subject): ?>
                <option value="<?php echo esc_attr($subject); ?>"><?php echo esc_html($subject); ?></option>
            <?php endforeach; ?>
        </select>

        <label>Select Class:</label><br>
        <select name="classroom_id" id="gtp-classroom-select" required style="width:100%; padding:8px; margin-bottom:10px; box-sizing: border-box;" disabled>
            <option value="">-- Select a subject first --</option>
        </select>

        <label>Date:</label><br>
        <input type="date" name="session_date" required style="width:100%; padding:8px; margin-bottom:10px; box-sizing: border-box;">

        <label>Attendance:</label>
        <div id="attendance-checklist-container" style="margin-bottom: 10px; border: 1px solid #ccc; padding: 10px; max-height: 200px; overflow-y: auto;">
            <!-- Student checklist will be loaded here -->
        </div>
        <div style="margin-bottom: 20px; display: flex; align-items: center;">
            <label for="new-student-name" style="margin-right: 10px; white-space: nowrap;">Add student:</label>
            <input type="text" id="new-student-name" placeholder="Enter new student name" style="flex-grow: 1; padding: 8px; box-sizing: border-box; height: 38px;">
            <button type="button" id="add-student-button" class="button" style="margin-left: 10px; height: 38px; line-height: 1; padding: 0 15px;">Add</button>
        </div>

        <label>Topic Covered:</label>
        <textarea name="topic" required style="width:100%; height:60px; margin-bottom:10px; box-sizing: border-box;"></textarea>

        <label>Comments (optional):</label>
        <textarea name="comments" style="width:100%; height:60px; margin-bottom:10px; box-sizing: border-box;"></textarea>

        <input type="hidden" name="is_substitute" value="1">

        <input type="submit" name="gtp_submit_session" value="Log Session" class="button button-primary">
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_log_substitute_session', 'gtp_log_substitute_session_shortcode');

function gtp_ta_profile_shortcode() {
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
                echo '<p style="color:red;">' . esc_html($movefile['error']) . '</p>';
            }
        }

        // Update bio, name, and subject preferences
        $update_data['bio'] = sanitize_textarea_field($_POST['bio']);
        $update_data['first_name'] = sanitize_text_field($_POST['first_name']);
        $update_data['last_name'] = sanitize_text_field($_POST['last_name']);
        $subject_preferences = isset($_POST['subject_preferences']) ? array_map('sanitize_text_field', $_POST['subject_preferences']) : [];
        $update_data['subject_preferences'] = json_encode($subject_preferences);

        if (!empty($update_data)) {
            $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $tutor_id]
            );

            if ($wpdb->last_error) {
                echo '<p style="color:red;">DB Error: ' . esc_html($wpdb->last_error) . '</p>';
            } else {
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
    $school = $tutor->school;
    $bio = $tutor->bio;
    $headshot_url = $tutor->headshot_url;
    $subject_preferences = json_decode($tutor->subject_preferences, true) ?: [];

    $all_subjects = ['AP Computer Science Principles', 'AP Biology', 'AP Statistics', 'AP Physics 1'];

    ob_start();
    ?>
    <div style="max-width:600px; margin:20px auto; padding:20px; background:#f9f9f9; border-radius:8px;">
        <p><a href="<?php echo esc_url(site_url('/index.php/ta-dashboard/')); ?>" class="button">← Back to Dashboard</a></p>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('gtp_update_profile', 'gtp_profile_nonce'); ?>

            <?php if ($headshot_url): ?>
                <img src="<?php echo esc_url($headshot_url); ?>" alt="Your headshot" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">
            <?php endif; ?>

            <h3>First Name:</h3>
            <p>
                <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($first_name); ?>" style="width:100%; padding:8px; box-sizing: border-box;">
            </p>

            <h3>Last Name:</h3>
            <p>
                <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($last_name); ?>" style="width:100%; padding:8px; box-sizing: border-box;">
            </p>

            <h3>Update Headshot:</h3>
            <p>
                <input type="file" id="headshot" name="headshot" accept="image/*">
            </p>

            <h3>Website Bio:</h3>
            <p>
                <textarea name="bio" style="width:100%; height:150px; box-sizing: border-box;"><?php echo esc_textarea($bio); ?></textarea>
            </p>

            <h3>Subject Preferences:</h3>
            <div style="margin-bottom: 20px;">
                <?php foreach ($all_subjects as $subject): ?>
                    <div>
                        <input type="checkbox" name="subject_preferences[]" value="<?php echo esc_attr($subject); ?>" <?php checked(in_array($subject, $subject_preferences)); ?>>
                        <?php echo esc_html($subject); ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <input type="submit" name="gtp_update_profile" value="Save Profile" class="button button-primary">
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_ta_profile', 'gtp_ta_profile_shortcode');