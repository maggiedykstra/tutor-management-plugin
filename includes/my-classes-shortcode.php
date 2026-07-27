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

    // Get assigned classrooms
    $assigned_classrooms = $wpdb->get_results($wpdb->prepare(
        "SELECT c.* FROM $classrooms_table c
         JOIN $assignments_table a ON c.id = a.classroom_id
         WHERE a.tutor_id = %d AND c.semester_id = %d",
        $tutor_id,
        gtp_get_live_semester_id()
    ));

    $display_value = static function ($value) {
        return trim((string) $value);
    };

    ob_start();
    ?>
    <div class="gtp-page gtp-my-classes">

        <?php echo gtp_dashboard_back_link('tutor'); ?>
        <h1 class="gtp-page-title">My Classes</h1>
        <p class="gtp-page-intro">Your assigned classes for the live semester.</p>
        <?php if (empty($assigned_classrooms)) : ?>
            <p>You are not assigned to any classes.</p>
        <?php else : ?>
            <?php foreach ($assigned_classrooms as $classroom) :
                $roster = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, first_name, last_name, student_name
                     FROM $students_table
                     WHERE classroom_id = %d
                     ORDER BY last_name ASC, first_name ASC, student_name ASC",
                    $classroom->id
                ));
                $teacher_display = trim($classroom->teacher_first_name . ' ' . $classroom->teacher_last_name);
                ?>
                <div class="gtp-class-card"
                     data-classroom-id="<?php echo esc_attr($classroom->id); ?>"
                     data-school="<?php echo esc_attr($classroom->school); ?>"
                     data-teacher-first-name="<?php echo esc_attr($classroom->teacher_first_name); ?>"
                     data-teacher-last-name="<?php echo esc_attr($classroom->teacher_last_name); ?>"
                     data-teacher-email="<?php echo esc_attr($classroom->teacher_email); ?>"
                     data-teacher-phone="<?php echo esc_attr($classroom->teacher_phone); ?>"
                     data-start-time="<?php echo esc_attr(gtp_time_input_value($classroom->start_time)); ?>"
                     data-end-time="<?php echo esc_attr(gtp_time_input_value($classroom->end_time)); ?>"
                     data-zoom-link="<?php echo esc_attr($classroom->zoom_link); ?>">

                    <div class="gtp-class-card-header">
                        <h2><?php echo esc_html($classroom->subject); ?></h2>
                        <button type="button" class="button gtp-edit-class-info-btn">Edit Class Information</button>
                    </div>

                    <div class="gtp-class-info-view">
                        <p><strong>School:</strong> <span class="gtp-info-school"><?php echo esc_html($display_value($classroom->school)); ?></span></p>
                        <p><strong>Teacher:</strong> <span class="gtp-info-teacher"><?php echo esc_html($display_value($teacher_display)); ?></span></p>
                        <p><strong>Teacher Email:</strong> <span class="gtp-info-teacher-email"><?php echo esc_html($display_value($classroom->teacher_email)); ?></span></p>
                        <p><strong>Teacher Phone:</strong> <span class="gtp-info-teacher-phone"><?php echo esc_html($display_value($classroom->teacher_phone)); ?></span></p>
                        <p><strong>Time:</strong> <span class="gtp-info-time"><?php echo esc_html(gtp_format_time_range($classroom->start_time, $classroom->end_time)); ?></span></p>
                        <p class="gtp-info-zoom-row">
                            <strong>Zoom Link:</strong>
                            <span class="gtp-info-zoom">
                                <?php if (!empty($classroom->zoom_link)) : ?>
                                    <a href="<?php echo esc_url($classroom->zoom_link); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($classroom->zoom_link); ?></a>
                                <?php endif; ?>
                            </span>
                        </p>
                    </div>

                    <div class="gtp-class-info-edit" hidden>
                        <div class="gtp-class-info-fields">
                            <label>
                                <span>School</span>
                                <input type="text" class="gtp-edit-school" value="<?php echo esc_attr($classroom->school); ?>">
                            </label>
                            <div class="gtp-class-info-two-col">
                                <label>
                                    <span>Teacher First Name</span>
                                    <input type="text" class="gtp-edit-teacher-first" value="<?php echo esc_attr($classroom->teacher_first_name); ?>">
                                </label>
                                <label>
                                    <span>Teacher Last Name</span>
                                    <input type="text" class="gtp-edit-teacher-last" value="<?php echo esc_attr($classroom->teacher_last_name); ?>">
                                </label>
                            </div>
                            <label>
                                <span>Teacher Email</span>
                                <input type="email" class="gtp-edit-teacher-email" value="<?php echo esc_attr($classroom->teacher_email); ?>">
                            </label>
                            <label>
                                <span>Teacher Phone</span>
                                <input type="text" class="gtp-edit-teacher-phone" value="<?php echo esc_attr($classroom->teacher_phone); ?>">
                            </label>
                            <div class="gtp-class-info-two-col">
                                <label>
                                    <span>Start Time</span>
                                    <input type="time" class="gtp-edit-start-time" step="60" value="<?php echo esc_attr(gtp_time_input_value($classroom->start_time)); ?>">
                                </label>
                                <label>
                                    <span>End Time</span>
                                    <input type="time" class="gtp-edit-end-time" step="60" value="<?php echo esc_attr(gtp_time_input_value($classroom->end_time)); ?>">
                                </label>
                            </div>
                            <label>
                                <span>Zoom Link</span>
                                <input type="url" class="gtp-edit-zoom-link" value="<?php echo esc_attr($classroom->zoom_link); ?>" placeholder="https://zoom.us/...">
                            </label>
                        </div>
                        <div class="gtp-class-info-edit-actions">
                            <button type="button" class="button gtp-cancel-class-info-btn">Cancel</button>
                            <button type="button" class="button button-primary gtp-save-class-info-btn">Save Changes</button>
                        </div>
                        <p class="gtp-class-info-message"></p>
                    </div>

                    <details class="gtp-roster-details">
                        <summary>Class Roster <span class="gtp-roster-count">(<?php echo count($roster); ?>)</span></summary>
                        <div class="gtp-roster-panel">
                            <ul class="gtp-roster-list">
                                <?php if (empty($roster)) : ?>
                                    <li class="gtp-roster-empty">No students in this roster yet.</li>
                                <?php else : ?>
                                    <?php foreach ($roster as $student) :
                                        $display = trim(($student->first_name ?: $student->student_name) . ' ' . ($student->last_name ?: ''));
                                        ?>
                                        <li class="gtp-roster-name"
                                            data-student-id="<?php echo esc_attr($student->id); ?>"
                                            data-first-name="<?php echo esc_attr($student->first_name ?: $student->student_name); ?>"
                                            data-last-name="<?php echo esc_attr($student->last_name ?: ''); ?>">
                                            <?php echo esc_html($display); ?>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            <button type="button" class="button gtp-open-roster-modal">Edit/Add Students</button>
                        </div>
                    </details>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="gtp-roster-modal" class="gtp-roster-modal" hidden aria-hidden="true">
        <div class="gtp-roster-modal-backdrop" data-close-modal></div>
        <div class="gtp-roster-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="gtp-roster-modal-title">
            <div class="gtp-roster-modal-header">
                <h3 id="gtp-roster-modal-title">Edit/Add Students</h3>
                <button type="button" class="gtp-roster-modal-close" data-close-modal aria-label="Close">&times;</button>
            </div>
            <p class="gtp-roster-modal-subtitle">Choose a student to edit, or tap + to add a new student. Click Save Changes when you are done.</p>

            <div class="gtp-modal-student-list" id="gtp-modal-student-list"></div>

            <div class="gtp-modal-list-actions">
                <button type="button" class="button gtp-plus-student-btn" id="gtp-show-add-student-btn" title="Add student" aria-label="Add student">+</button>
            </div>

            <div id="gtp-modal-edit-panel" class="gtp-modal-edit-panel" hidden>
                <h4>Edit Student</h4>
                <input type="hidden" id="gtp-edit-student-id" value="">
                <div class="gtp-modal-fields">
                    <input type="text" id="gtp-edit-first-name" placeholder="First name">
                    <input type="text" id="gtp-edit-last-name" placeholder="Last name (optional)">
                </div>
                <button type="button" class="button gtp-remove-btn" id="gtp-remove-student-btn">Remove from Class</button>
            </div>

            <div id="gtp-modal-add-panel" class="gtp-modal-add-panel" hidden>
                <h4>Add Student</h4>
                <div class="gtp-modal-fields">
                    <input type="text" id="gtp-add-first-name" placeholder="First name">
                    <input type="text" id="gtp-add-last-name" placeholder="Last name (optional)">
                </div>
                <div class="gtp-modal-add-actions">
                    <button type="button" class="button" id="gtp-cancel-add-student-btn">Cancel</button>
                    <button type="button" class="button button-primary" id="gtp-add-student-btn">Add to List</button>
                </div>
            </div>

            <p id="gtp-roster-modal-message" class="gtp-roster-modal-message"></p>

            <div class="gtp-roster-modal-footer">
                <button type="button" class="button button-primary" id="gtp-save-student-btn">Save Changes</button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_my_classes', 'gtp_my_classes_shortcode');
