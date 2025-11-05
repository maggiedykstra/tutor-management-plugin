<?php
function gtp_TA_dashboard_shortcode() {
    // Check if user is logged in via session
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        return '<p>You do not have access to this page.</p>';
    }

    $name = esc_html($_SESSION['gtp_user']['first_name']);

    ob_start();
    ?>
    <button onclick="history.back()">Go Back</button>
    <div style="max-width:600px; margin:30px auto; padding:20px; background:#f1f1f1; border-radius:10px;">
        <h2>Welcome, <?php echo $name; ?>!</h2>

        
        <form action="<?php echo esc_url(site_url('/index.php/TA-profile')); ?>" method="get" style="display:inline-block; margin-right:15px;">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    My profile
                </button>
        </form>


        <form action="<?php echo esc_url(site_url('/index.php/log-session')); ?>" method="get" style="display:inline-block; margin-right:15px;">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Log My Sessions
                </button>
        </form>


        <form action="<?php echo esc_url(site_url('/index.php/log-substitute')); ?>" method="get" style="display:inline-block; margin-right:15px;">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Log Substitute Sessions
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
    // Get classroom info
    $classroom = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gtp_classrooms WHERE id = %d", $classroom_id)
    );


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
            }
            wp_redirect(site_url('/index.php/ta-dashboard/'));
            exit;
        }
    }

    // Get assigned subjects for the tutor
    $subjects = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT c.subject FROM {$wpdb->prefix}gtp_classrooms c
             JOIN {$wpdb->prefix}gtp_class_assignments a ON c.id = a.classroom_id
             WHERE a.tutor_id = %d ORDER BY c.subject ASC",
            $tutor_id
        )
    );

    ob_start();
    ?>
    <button onclick="history.back()">Go Back</button>
    <form method="post" style="max-width:600px; margin:20px auto; padding:20px; background:#f9f9f9; border-radius:8px;">
        <h2>Log a Session</h2>

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
        <div style="margin-bottom: 20px;">
            <input type="text" id="new-student-name" placeholder="Enter new student name" style="width: 70%; padding: 8px; box-sizing: border-box;">
            <button type="button" id="add-student-button" class="button" style="width: 28%; float: right;">Add Student</button>
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