<?php
function gtp_edit_classrooms_shortcode() {
    global $wpdb;

    // Check if user is an admin
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    $classrooms_table = $wpdb->prefix . 'gtp_classrooms';
    $tutors_table = $wpdb->prefix . 'gtp_users';
    $assignments_table = $wpdb->prefix . 'gtp_class_assignments';

    // Handle form submission
    if (isset($_POST['gtp_update_classroom'])) {
        $classroom_id = intval($_POST['classroom_id']);
        $school = sanitize_text_field($_POST['school']);
        $resolved = gtp_resolve_subject_from_post($_POST, 'subject', 'new_subject');
        $subject = $resolved['subject'] ?? '';
        $teacher_first_name = sanitize_text_field($_POST['teacher_first_name']);
        $teacher_last_name = sanitize_text_field($_POST['teacher_last_name']);
        $teacher_email = sanitize_email($_POST['teacher_email']);
        $teacher_phone = sanitize_text_field($_POST['teacher_phone']);
        $start_time = gtp_sanitize_time($_POST['start_time'] ?? '');
        $end_time = gtp_sanitize_time($_POST['end_time'] ?? '');
        $time_slot = gtp_format_time_range($start_time, $end_time);
        $meeting_days = gtp_meeting_days_to_storage($_POST['meeting_days'] ?? []);
        $is_block = !empty($_POST['is_block']) ? 1 : 0;
        $tutor_id = intval($_POST['tutor_id']);

        if (!empty($resolved['error'])) {
            echo '<p class="gtp-msg is-error gtp-persist">' . esc_html($resolved['error']) . '</p>';
        } else {
        // Update classroom details
        $wpdb->update(
            $classrooms_table,
            [
                'school' => $school,
                'subject' => $subject,
                'teacher_first_name' => $teacher_first_name,
                'teacher_last_name' => $teacher_last_name,
                'teacher_email' => $teacher_email,
                'teacher_phone' => $teacher_phone,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'time_slot' => $time_slot,
                'meeting_days' => $meeting_days,
                'is_block' => $is_block,
            ],
            ['id' => $classroom_id]
        );

        $wpdb->query($wpdb->prepare(
            "UPDATE $classrooms_table
             SET start_time = NULLIF(%s, ''), end_time = NULLIF(%s, '')
             WHERE id = %d",
            $start_time ?: '',
            $end_time ?: '',
            $classroom_id
        ));

        // Update TA assignment
        if ($tutor_id) {
            // Check if an assignment already exists
            $existing_assignment = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $assignments_table WHERE classroom_id = %d", $classroom_id)
            );

            if ($existing_assignment) {
                $wpdb->update(
                    $assignments_table,
                    ['tutor_id' => $tutor_id],
                    ['classroom_id' => $classroom_id]
                );
            } else {
                $wpdb->insert(
                    $assignments_table,
                    [
                        'classroom_id' => $classroom_id,
                        'tutor_id' => $tutor_id,
                        'first_taught' => current_time('mysql'),
                    ]
                );
            }
        } else {
            // If no tutor is selected, remove any existing assignment
            $wpdb->delete($assignments_table, ['classroom_id' => $classroom_id]);
        }

        echo '<p class="gtp-msg is-success">Classroom updated successfully!</p>';
        }
    }

    // Display edit form if a classroom is selected
    if (isset($_GET['edit_id'])) {
        $edit_id = intval($_GET['edit_id']);
        $classroom = $wpdb->get_row($wpdb->prepare("SELECT * FROM $classrooms_table WHERE id = %d", $edit_id));
        $tutors = $wpdb->get_results("SELECT id, first_name, last_name FROM $tutors_table WHERE role = 'tutor' ORDER BY last_name, first_name ASC");
        $assigned_tutor = $wpdb->get_var($wpdb->prepare("SELECT tutor_id FROM $assignments_table WHERE classroom_id = %d", $edit_id));

        ob_start();
        ?>
        <div class="gtp-page">
            <?php echo gtp_dashboard_back_link('admin'); ?>
            <h1 class="gtp-page-title">Edit Classroom</h1>
            <form method="post" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <input type="hidden" name="classroom_id" value="<?php echo $classroom->id; ?>">
                <div style="grid-column: 1 / -1;">
                    <label>School:</label>
                    <input type="text" name="school" value="<?php echo esc_attr($classroom->school); ?>" required style="width:100%; padding:8px; margin-bottom:10px;">
                </div>
                <div>
                    <label>Subject:</label>
                    <?php
                    echo gtp_render_subject_select([
                        'name' => 'subject',
                        'id' => 'gtp-edit-classroom-subject',
                        'selected' => $classroom->subject,
                        'required' => true,
                        'allow_add' => true,
                    ]);
                    echo gtp_subject_select_script();
                    ?>
                </div>
                <div>
                    <label>Start Time:</label>
                    <input type="time" name="start_time" step="60" value="<?php echo esc_attr(gtp_time_input_value($classroom->start_time)); ?>" style="width:100%; padding:8px; margin-bottom:10px;">
                </div>
                <div>
                    <label>End Time:</label>
                    <input type="time" name="end_time" step="60" value="<?php echo esc_attr(gtp_time_input_value($classroom->end_time)); ?>" style="width:100%; padding:8px; margin-bottom:10px;">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label>Days of the week:</label>
                    <?php echo gtp_render_meeting_days_checkboxes([
                        'id_prefix' => 'gtp-edit-day',
                        'selected' => $classroom->meeting_days ?? '',
                    ]); ?>
                </div>
                <div style="grid-column: 1 / -1;">
                    <label class="gtp-check-row" style="display:flex; align-items:flex-start; gap:8px; margin:0 0 10px;">
                        <input type="checkbox" name="is_block" value="1" <?php checked(!empty($classroom->is_block)); ?>>
                        <span>This class is a Block class / one semester only</span>
                    </label>
                </div>
                <div>
                    <label>Teacher First Name:</label>
                    <input type="text" name="teacher_first_name" value="<?php echo esc_attr($classroom->teacher_first_name); ?>" required style="width:100%; padding:8px; margin-bottom:10px;">
                </div>
                <div>
                    <label>Teacher Last Name:</label>
                    <input type="text" name="teacher_last_name" value="<?php echo esc_attr($classroom->teacher_last_name); ?>" required style="width:100%; padding:8px; margin-bottom:10px;">
                </div>
                <div>
                    <label>Teacher Email:</label>
                    <input type="email" name="teacher_email" value="<?php echo esc_attr($classroom->teacher_email); ?>" style="width:100%; padding:8px; margin-bottom:10px;">
                </div>
                <div>
                    <label>Teacher Phone:</label>
                    <input type="tel" name="teacher_phone" value="<?php echo esc_attr($classroom->teacher_phone); ?>" style="width:100%; padding:8px; margin-bottom:10px;">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label>Assign to TA:</label>
                    <select name="tutor_id" style="width:100%; padding:8px; margin-bottom:10px;">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($tutors as $tutor): ?>
                            <option value="<?php echo $tutor->id; ?>" <?php selected($assigned_tutor, $tutor->id); ?>><?php echo esc_html($tutor->first_name . ' ' . $tutor->last_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="grid-column: 1 / -1; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="submit" name="gtp_update_classroom" value="Save Changes" class="button button-primary">
                    <?php
                    $back_url = add_query_arg([
                        'school' => isset($_GET['school']) ? sanitize_text_field($_GET['school']) : '',
                        'subject' => isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : '',
                        'assignment' => isset($_GET['assignment']) ? sanitize_text_field($_GET['assignment']) : '',
                    ], site_url('/index.php/edit-classrooms/'));
                    ?>
                    <a href="<?php echo esc_url($back_url); ?>" class="button">Edit Classrooms</a>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // Display list of classrooms
    $base_url = site_url('/index.php/edit-classrooms/');
    $semester_id = gtp_get_working_semester_id();
    $schools = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT school FROM $classrooms_table WHERE semester_id = %d ORDER BY school ASC",
        $semester_id
    ));
    $subjects = gtp_get_subjects();

    $selected_school = isset($_GET['school']) ? sanitize_text_field($_GET['school']) : '';
    $selected_subject = isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : '';
    $selected_assignment = isset($_GET['assignment']) ? sanitize_text_field($_GET['assignment']) : '';
    if (!in_array($selected_assignment, ['', 'assigned', 'unassigned'], true)) {
        $selected_assignment = '';
    }

    $sql = "SELECT c.*,
                   u.first_name AS tutor_first_name,
                   u.last_name AS tutor_last_name,
                   a.tutor_id AS assigned_tutor_id
            FROM $classrooms_table c
            LEFT JOIN $assignments_table a ON a.classroom_id = c.id
            LEFT JOIN $tutors_table u ON u.id = a.tutor_id
            WHERE c.semester_id = %d";
    $params = [$semester_id];

    if ($selected_school !== '') {
        $sql .= ' AND c.school = %s';
        $params[] = $selected_school;
    }
    if ($selected_subject !== '') {
        $match = gtp_subject_match_values($selected_subject);
        $ph = implode(',', array_fill(0, count($match), '%s'));
        $sql .= " AND c.subject IN ($ph)";
        foreach ($match as $m) {
            $params[] = $m;
        }
    }
    if ($selected_assignment === 'unassigned') {
        $sql .= ' AND a.id IS NULL';
    } elseif ($selected_assignment === 'assigned') {
        $sql .= ' AND a.id IS NOT NULL';
    }

    $sql .= ' ORDER BY c.school ASC, c.subject ASC';
    $classrooms = $wpdb->get_results($wpdb->prepare($sql, $params));

    $working = gtp_get_semester($semester_id);

    ob_start();
    ?>
    <div class="gtp-page">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Edit Classrooms</h1>
        <p class="gtp-page-intro">Showing classes for working semester: <strong><?php echo esc_html(gtp_semester_label($working)); ?></strong></p>
        <form method="get" action="<?php echo esc_url($base_url); ?>" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:16px;">
            <select name="school">
                <option value="">-- All Schools --</option>
                <?php foreach ($schools as $school) : ?>
                    <option value="<?php echo esc_attr($school); ?>" <?php selected($selected_school, $school); ?>><?php echo esc_html($school); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="subject">
                <option value="">-- All Subjects --</option>
                <?php foreach ($subjects as $subject) : ?>
                    <option value="<?php echo esc_attr($subject); ?>" <?php selected($selected_subject, $subject); ?>><?php echo esc_html($subject); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="assignment">
                <option value="" <?php selected($selected_assignment, ''); ?>>-- All assignment statuses --</option>
                <option value="unassigned" <?php selected($selected_assignment, 'unassigned'); ?>>Not assigned to a tutor</option>
                <option value="assigned" <?php selected($selected_assignment, 'assigned'); ?>>Assigned to a tutor</option>
            </select>
            <input type="submit" value="Filter" class="button">
            <?php if ($selected_school || $selected_subject || $selected_assignment) : ?>
                <a class="button" href="<?php echo esc_url($base_url); ?>">Clear</a>
            <?php endif; ?>
        </form>
        <table class="wp-list-table widefat fixed striped" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ddd; padding: 8px;">School</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Subject</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Teacher</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Schedule</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Assigned TA</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classrooms)) : ?>
                    <tr>
                        <td colspan="6" style="border: 1px solid #ddd; padding: 8px;">No classrooms match these filters.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($classrooms as $classroom) :
                        $tutor_name = 'Unassigned';
                        if (!empty($classroom->assigned_tutor_id)) {
                            $tutor_name = trim(($classroom->tutor_first_name ?? '') . ' ' . ($classroom->tutor_last_name ?? ''));
                        }
                        $edit_url = add_query_arg([
                            'edit_id' => $classroom->id,
                            'school' => $selected_school,
                            'subject' => $selected_subject,
                            'assignment' => $selected_assignment,
                        ], $base_url);
                        ?>
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($classroom->school); ?></td>
                            <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html(gtp_format_classroom_subject($classroom)); ?></td>
                            <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($classroom->teacher_first_name . ' ' . $classroom->teacher_last_name); ?></td>
                            <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html(gtp_format_classroom_schedule($classroom)); ?></td>
                            <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($tutor_name); ?></td>
                            <td style="border: 1px solid #ddd; padding: 8px;"><a href="<?php echo esc_url($edit_url); ?>" class="button">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_edit_classrooms', 'gtp_edit_classrooms_shortcode');
