<?php
function gtp_my_logged_sessions_shortcode() {
    global $wpdb;

    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        return '<p>You do not have access to this page.</p>';
    }

    $tutor_id = $_SESSION['gtp_user']['id'];
    $sessions_table = $wpdb->prefix . 'gtp_sessions';
    $classrooms_table = $wpdb->prefix . 'gtp_classrooms';

    // Handle session edit submission
    if (isset($_POST['gtp_update_session'])) {
        $session_id = intval($_POST['session_id']);
        $topic = sanitize_textarea_field($_POST['topic']);
        $comments = sanitize_textarea_field($_POST['comments']);

        $wpdb->update(
            $sessions_table,
            ['topic' => $topic, 'comments' => $comments],
            ['id' => $session_id, 'tutor_username' => $_SESSION['gtp_user']['username']] // Ensure tutor can only edit their own sessions
        );

        echo '<p style="color:green;">Session updated successfully!</p>';
    }

    // Display edit form
    if (isset($_GET['edit_session'])) {
        $session_id = intval($_GET['edit_session']);
        $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sessions_table WHERE id = %d AND tutor_username = %s", $session_id, $_SESSION['gtp_user']['username']));

        if ($session) {
            $attendance_ids = json_decode($session->attendance);
            $students = [];
            if (!empty($attendance_ids)) {
                $student_ids_placeholders = implode(',', array_fill(0, count($attendance_ids), '%d'));
                $students = $wpdb->get_results($wpdb->prepare("SELECT first_name, last_name FROM {$wpdb->prefix}gtp_students WHERE id IN ($student_ids_placeholders)", $attendance_ids));
            }

            ob_start();
            ?>
            <div style="max-width:600px; margin:20px auto; padding:20px; background:#f9f9f9; border-radius:8px;">
                <h2>Edit Session</h2>
                <form method="post">
                    <input type="hidden" name="session_id" value="<?php echo $session->id; ?>">
                    <p><strong>Date:</strong> <?php echo esc_html($session->session_date); ?></p>
                    <p><strong>Classroom:</strong> <?php echo esc_html($session->subject . ', ' . $session->school . ' - ' . $session->teacher_name); ?></p>
                    <label>Topic Covered:</label>
                    <textarea name="topic" required style="width:100%; height:60px; margin-bottom:10px; box-sizing: border-box;"><?php echo esc_textarea($session->topic); ?></textarea>
                    <label>Comments (optional):</label>
                    <textarea name="comments" style="width:100%; height:60px; margin-bottom:10px; box-sizing: border-box;"><?php echo esc_textarea($session->comments); ?></textarea>
                    
                    <label>Attendance:</label>
                    <div style="margin-bottom: 10px; border: 1px solid #ccc; padding: 10px; max-height: 200px; overflow-y: auto;">
                        <?php foreach ($students as $student): ?>
                            <p><?php echo esc_html($student->first_name . ' ' . $student->last_name); ?></p>
                        <?php endforeach; ?>
                    </div>

                    <input type="submit" name="gtp_update_session" value="Save Changes" class="button button-primary">
                    <p style="margin-top: 10px;"><a href="<?php echo esc_url(remove_query_arg('edit_session')); ?>" class="button">← Back to Logged Sessions</a></p>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    // Filtering logic
    $selected_subject = isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : '';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

    $sql = $wpdb->prepare("SELECT * FROM $sessions_table WHERE tutor_username = %s", $_SESSION['gtp_user']['username']);
    if ($selected_subject) {
        $sql .= $wpdb->prepare(" AND subject = %s", $selected_subject);
    }
    if ($start_date && $end_date) {
        $sql .= $wpdb->prepare(" AND session_date BETWEEN %s AND %s", $start_date, $end_date);
    }
    $sql .= " ORDER BY session_date DESC";
    $sessions = $wpdb->get_results($sql);

    // Get subjects for filters
    $subjects = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT subject FROM $sessions_table WHERE tutor_username = %s", $_SESSION['gtp_user']['username']));

    ob_start();
    ?>
    <div style="max-width:1000px; margin:20px auto; padding:20px; background:#f9f9f9; border-radius:8px;">
        <h2>My Logged Sessions</h2>
        <form method="get">
            <input type="hidden" name="page_id" value="<?php echo get_the_ID(); ?>">
            <label for="subject">Subject:</label>
            <select name="subject">
                <option value="">-- All Subjects --</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?php echo esc_attr($subject); ?>" <?php selected($selected_subject, $subject); ?>><?php echo esc_html($subject); ?></option>
                <?php endforeach; ?>
            </select>
            <label for="start_date">Start Date:</label>
            <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
            <label for="end_date">End Date:</label>
            <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
            <input type="submit" value="Filter" class="button">
        </form>
        <table class="wp-list-table widefat fixed striped" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ddd; padding: 8px;">Date</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Classroom</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Topic</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Comments</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Attendance</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                    <?php
                    $attendance_ids = json_decode($session->attendance);
                    $students = [];
                    if (!empty($attendance_ids)) {
                        $student_ids_placeholders = implode(',', array_fill(0, count($attendance_ids), '%d'));
                        $students = $wpdb->get_results($wpdb->prepare("SELECT first_name, last_name FROM {$wpdb->prefix}gtp_students WHERE id IN ($student_ids_placeholders)", $attendance_ids));
                    }
                    ?>
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($session->session_date); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($session->subject . ', ' . $session->school . ' - ' . $session->teacher_name); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($session->topic); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($session->comments); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;">
                            <?php foreach ($students as $student): ?>
                                <?php echo esc_html($student->first_name . ' ' . $student->last_name); ?><br>
                            <?php endforeach; ?>
                        </td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><a href="<?php echo esc_url(add_query_arg('edit_session', $session->id)); ?>" class="button">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top: 20px;"><a href="<?php echo esc_url(site_url('/index.php/ta-dashboard/')); ?>" class="button">← Back to Dashboard</a></p>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_my_logged_sessions', 'gtp_my_logged_sessions_shortcode');