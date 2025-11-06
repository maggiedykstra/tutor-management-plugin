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
        $subject = sanitize_text_field($_POST['subject']);
        $teacher_first_name = sanitize_text_field($_POST['teacher_first_name']);
        $teacher_last_name = sanitize_text_field($_POST['teacher_last_name']);
        $time_slot = sanitize_text_field($_POST['time_slot']);
        $tutor_id = intval($_POST['tutor_id']);

        // Update classroom details
        $wpdb->update(
            $classrooms_table,
            [
                'school' => $school,
                'subject' => $subject,
                'teacher_first_name' => $teacher_first_name,
                'teacher_last_name' => $teacher_last_name,
                'time_slot' => $time_slot,
            ],
            ['id' => $classroom_id]
        );

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

        echo '<p style="color:green;">Classroom updated successfully!</p>';
    }

    // Display edit form if a classroom is selected
    if (isset($_GET['edit_id'])) {
        $edit_id = intval($_GET['edit_id']);
        $classroom = $wpdb->get_row($wpdb->prepare("SELECT * FROM $classrooms_table WHERE id = %d", $edit_id));
        $tutors = $wpdb->get_results("SELECT id, first_name, last_name FROM $tutors_table WHERE role = 'tutor' ORDER BY last_name, first_name ASC");
        $assigned_tutor = $wpdb->get_var($wpdb->prepare("SELECT tutor_id FROM $assignments_table WHERE classroom_id = %d", $edit_id));

        ob_start();
        ?>
        <div style="max-width:600px; margin:20px auto; padding:20px; background:#f9f9f9; border-radius:8px;">
            <h2>Edit Classroom</h2>
            <form method="post" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <input type="hidden" name="classroom_id" value="<?php echo $classroom->id; ?>">
                <div style="grid-column: 1 / -1;">
                    <label>School:</label>
                    <input type="text" name="school" value="<?php echo esc_attr($classroom->school); ?>" required style="width:100%; padding:8px; margin-bottom:10px;">
                </div>
                <div>
                    <label>Subject:</label>
                    <input type="text" name="subject" value="<?php echo esc_attr($classroom->subject); ?>" required style="width:100%; padding:8px; margin-bottom:10px;">
                </div>
                <div>
                    <label>Time Slot:</label>
                    <input type="text" name="time_slot" value="<?php echo esc_attr($classroom->time_slot); ?>" style="width:100%; padding:8px; margin-bottom:10px;">
                </div>
                <div>
                    <label>Teacher First Name:</label>
                    <input type="text" name="teacher_first_name" value="<?php echo esc_attr($classroom->teacher_first_name); ?>" required style="width:100%; padding:8px; margin-bottom:10px;">
                </div>
                <div>
                    <label>Teacher Last Name:</label>
                    <input type="text" name="teacher_last_name" value="<?php echo esc_attr($classroom->teacher_last_name); ?>" required style="width:100%; padding:8px; margin-bottom:10px;">
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
                <div style="grid-column: 1 / -1; display: flex; justify-content: space-between;">
                    <input type="submit" name="gtp_update_classroom" value="Save Changes" class="button button-primary">
                    <a href="<?php echo esc_url(site_url('/index.php/edit-classrooms/')); ?>" class="button">← Back to Edit Classrooms</a>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    // Display list of classrooms
    $schools = $wpdb->get_col("SELECT DISTINCT school FROM $classrooms_table ORDER BY school ASC");
    $subjects = $wpdb->get_col("SELECT DISTINCT subject FROM $classrooms_table ORDER BY subject ASC");

    $selected_school = isset($_GET['school']) ? sanitize_text_field($_GET['school']) : '';
    $selected_subject = isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : '';

    $sql = "SELECT * FROM $classrooms_table WHERE 1=1";
    if ($selected_school) {
        $sql .= $wpdb->prepare(" AND school = %s", $selected_school);
    }
    if ($selected_subject) {
        $sql .= $wpdb->prepare(" AND subject = %s", $selected_subject);
    }
    $sql .= " ORDER BY school, subject ASC";
    $classrooms = $wpdb->get_results($sql);

    ob_start();
    ?>
    <div style="max-width:1000px; margin:20px auto; padding:20px; background:#f9f9f9; border-radius:8px;">
        <h2>Edit Classrooms</h2>
        <form method="get">
            <input type="hidden" name="page_id" value="<?php echo get_the_ID(); ?>">
            <select name="school">
                <option value="">-- All Schools --</option>
                <?php foreach ($schools as $school): ?>
                    <option value="<?php echo esc_attr($school); ?>" <?php selected($selected_school, $school); ?>><?php echo esc_html($school); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="subject">
                <option value="">-- All Subjects --</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?php echo esc_attr($subject); ?>" <?php selected($selected_subject, $subject); ?>><?php echo esc_html($subject); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="Filter" class="button">
        </form>
        <table class="wp-list-table widefat fixed striped" style="border-collapse: collapse; width: 100%;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ddd; padding: 8px;">School</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Subject</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Teacher</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Time Slot</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Assigned TA</th>
                    <th style="border: 1px solid #ddd; padding: 8px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classrooms as $classroom): ?>
                    <?php
                    $assigned_tutor_id = $wpdb->get_var($wpdb->prepare("SELECT tutor_id FROM $assignments_table WHERE classroom_id = %d", $classroom->id));
                    $tutor_name = 'Unassigned';
                    if ($assigned_tutor_id) {
                        $tutor = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name FROM $tutors_table WHERE id = %d", $assigned_tutor_id));
                        $tutor_name = $tutor->first_name . ' ' . $tutor->last_name;
                    }
                    ?>
                    <tr style="cursor: pointer;" onclick="window.location='?page_id=<?php echo get_the_ID(); ?>&edit_id=<?php echo $classroom->id; ?>'">
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($classroom->school); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($classroom->subject); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($classroom->teacher_first_name . ' ' . $classroom->teacher_last_name); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($classroom->time_slot); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><?php echo esc_html($tutor_name); ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><a href="?page_id=<?php echo get_the_ID(); ?>&edit_id=<?php echo $classroom->id; ?>" class="button">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top: 20px;"><a href="<?php echo esc_url(site_url('/index.php/admin-dashboard/')); ?>" class="button">← Return to Dashboard</a></p>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_edit_classrooms', 'gtp_edit_classrooms_shortcode');
