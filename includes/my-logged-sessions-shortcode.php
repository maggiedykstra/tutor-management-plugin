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

        echo '<p class="gtp-msg is-success">Session updated successfully!</p>';
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
                $students = $wpdb->get_results($wpdb->prepare("SELECT first_name, last_name, student_name FROM {$wpdb->prefix}gtp_students WHERE id IN ($student_ids_placeholders)", $attendance_ids));
            }

            ob_start();
            ?>
            <div class="gtp-page">
                <?php echo gtp_dashboard_back_link('tutor'); ?>
                <h1 class="gtp-page-title">Edit Session</h1>
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
                            <p><?php echo esc_html(trim(($student->first_name ?: $student->student_name) . ' ' . ($student->last_name ?: ''))); ?></p>
                        <?php endforeach; ?>
                    </div>

                    <input type="submit" name="gtp_update_session" value="Save Changes" class="button button-primary">
                    <p style="margin-top: 10px;"><a href="<?php echo esc_url(remove_query_arg('edit_session')); ?>" class="button">My Logged Sessions</a></p>
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

    $sql = "SELECT * FROM $sessions_table WHERE tutor_username = %s";
    $params = [$_SESSION['gtp_user']['username']];
    if ($selected_subject) {
        $match = gtp_subject_match_values($selected_subject);
        $ph = implode(',', array_fill(0, count($match), '%s'));
        $sql .= " AND subject IN ($ph)";
        foreach ($match as $m) {
            $params[] = $m;
        }
    }
    if ($start_date && $end_date) {
        $sql .= ' AND session_date BETWEEN %s AND %s';
        $params[] = $start_date;
        $params[] = $end_date;
    }
    $sql .= ' ORDER BY session_date DESC';
    $sessions = $wpdb->get_results($wpdb->prepare($sql, $params));

    // Get subjects for filters
    $subjects = gtp_get_subjects();

    ob_start();
    ?>
    <div class="gtp-page">
        <?php echo gtp_dashboard_back_link('tutor'); ?>
        <h1 class="gtp-page-title">My logged sessions</h1>
        <p class="gtp-page-intro">Filter and edit sessions you have logged.</p>

        <form method="get" class="gtp-filter-bar">
            <input type="hidden" name="page_id" value="<?php echo (int) get_the_ID(); ?>">
            <label class="gtp-field">
                <span>Subject</span>
                <select name="subject">
                    <option value="">All subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo esc_attr($subject); ?>" <?php selected($selected_subject, $subject); ?>><?php echo esc_html($subject); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="gtp-field">
                <span>Start date</span>
                <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
            </label>
            <label class="gtp-field">
                <span>End date</span>
                <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
            </label>
            <div class="gtp-form-actions">
                <button type="submit" class="button">Filter</button>
            </div>
        </form>

        <div class="gtp-checkin-table-wrap">
            <table class="gtp-data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Classroom</th>
                        <th>Topic</th>
                        <th>Comments</th>
                        <th>Attendance</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessions)) : ?>
                        <tr>
                            <td colspan="6">No sessions found for these filters.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($sessions as $session): ?>
                            <?php
                            $attendance_ids = json_decode($session->attendance);
                            $students = [];
                            if (!empty($attendance_ids)) {
                                $student_ids_placeholders = implode(',', array_fill(0, count($attendance_ids), '%d'));
                                $students = $wpdb->get_results($wpdb->prepare("SELECT first_name, last_name, student_name FROM {$wpdb->prefix}gtp_students WHERE id IN ($student_ids_placeholders)", $attendance_ids));
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html($session->session_date); ?></td>
                                <td><?php echo esc_html($session->subject . ', ' . $session->school . ' - ' . $session->teacher_name); ?></td>
                                <td><?php echo esc_html($session->topic); ?></td>
                                <td><?php echo esc_html($session->comments); ?></td>
                                <td>
                                    <?php foreach ($students as $student): ?>
                                        <?php echo esc_html(trim(($student->first_name ?: $student->student_name) . ' ' . ($student->last_name ?: ''))); ?><br>
                                    <?php endforeach; ?>
                                </td>
                                <td><a href="<?php echo esc_url(add_query_arg('edit_session', $session->id)); ?>" class="button">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_my_logged_sessions', 'gtp_my_logged_sessions_shortcode');