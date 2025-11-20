<?php
function gtp_my_classes_shortcode() {
    global $wpdb;

    // Check if user is a tutor
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        return '<p>You do not have access to this page.</p>';
    }

    $tutor_id = $_SESSION['gtp_user']['id'];
    $classrooms_table = $wpdb->prefix . 'gtp_classrooms';
    $assignments_table = $wpdb->prefix . 'gtp_class_assignments';
    $students_table = $wpdb->prefix . 'gtp_students';

    // Handle Zoom link update
    if (isset($_POST['gtp_update_zoom_link'])) {
        $classroom_id = intval($_POST['classroom_id']);
        $zoom_link = esc_url_raw($_POST['zoom_link']);

        // Verify that the tutor is assigned to this classroom
        $is_assigned = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $assignments_table WHERE classroom_id = %d AND tutor_id = %d",
            $classroom_id,
            $tutor_id
        ));

        if ($is_assigned) {
            $wpdb->update(
                $classrooms_table,
                ['zoom_link' => $zoom_link],
                ['id' => $classroom_id]
            );
            echo '<p style="color:green;">Zoom link updated successfully!</p>';
        }
    }

    // Get assigned classrooms
    $assigned_classrooms = $wpdb->get_results($wpdb->prepare(
        "SELECT c.* FROM $classrooms_table c
         JOIN $assignments_table a ON c.id = a.classroom_id
         WHERE a.tutor_id = %d",
        $tutor_id
    ));

    ob_start();
    ?>
    <div class="wrap">

        <p><a href="<?php echo esc_url(site_url('/index.php/ta-dashboard/')); ?>" class="button">← Back to Dashboard</a></p>

        <?php if (empty($assigned_classrooms)) : ?>
            <p>You are not assigned to any classes.</p>
        <?php else : ?>
            <?php foreach ($assigned_classrooms as $classroom) : ?>
                <div style="margin-bottom: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 5px;">
                    <h2><?php echo esc_html($classroom->subject); ?></h2>
                    <p><strong>Teacher:</strong> <?php echo esc_html($classroom->teacher_first_name . ' ' . $classroom->teacher_last_name); ?></p>
                    <p><strong>Teacher Email:</strong> <?php echo esc_html($classroom->teacher_email); ?></p>
                    <p><strong>Teacher Phone:</strong> <?php echo esc_html($classroom->teacher_phone); ?></p>
                    <p><strong>Time:</strong> <?php echo esc_html($classroom->time_slot); ?></p>

                    <details>
                        <summary>View Roster</summary>
                        <?php
                        $roster = $wpdb->get_col($wpdb->prepare(
                            "SELECT student_name FROM $students_table WHERE classroom_id = %d ORDER BY student_name ASC",
                            $classroom->id
                        ));
                        if (empty($roster)) {
                            echo '<p>No students in this roster.</p>';
                        } else {
                            echo '<ul>';
                            foreach ($roster as $student_name) {
                                echo '<li>' . esc_html($student_name) . '</li>';
                            }
                            echo '</ul>';
                        }
                        ?>
                    </details>

                    <form method="post" style="margin-top: 10px;">
                        <input type="hidden" name="classroom_id" value="<?php echo $classroom->id; ?>">
                        <label for="zoom_link_<?php echo $classroom->id; ?>">Zoom Link:</label>
                        <input type="url" id="zoom_link_<?php echo $classroom->id; ?>" name="zoom_link" value="<?php echo esc_attr($classroom->zoom_link); ?>" style="width: 100%; max-width: 400px;">
                        <input type="submit" name="gtp_update_zoom_link" value="Save Link" class="button">
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_my_classes', 'gtp_my_classes_shortcode');
