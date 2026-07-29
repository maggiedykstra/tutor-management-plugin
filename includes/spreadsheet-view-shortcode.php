<?php
/**
 * Admin Spreadsheet View — overview of sessions by subject/class.
 */

function gtp_spreadsheet_subject_choices() {
    // Keys are the selectable subjects; values are SQL match variants (canonical + legacy).
    $choices = [];
    foreach (gtp_get_subjects() as $subject) {
        $choices[$subject] = gtp_subject_match_values($subject);
    }
    return $choices;
}

function gtp_spreadsheet_subjects_for_choice($choice) {
    return gtp_subject_match_values($choice);
}

function gtp_spreadsheet_student_display_name($student) {
    if (!empty($student->first_name) || !empty($student->last_name)) {
        return trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''));
    }
    return trim((string) ($student->student_name ?? ''));
}

function gtp_spreadsheet_view_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;

    $selected_subject = isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : '';
    $selected_classroom_id = isset($_GET['classroom_id']) ? intval($_GET['classroom_id']) : 0;
    $subject_choices = array_keys(gtp_spreadsheet_subject_choices());

    $classrooms = [];
    if ($selected_subject && in_array($selected_subject, $subject_choices, true)) {
        $subjects = gtp_spreadsheet_subjects_for_choice($selected_subject);
        $placeholders = implode(',', array_fill(0, count($subjects), '%s'));
        $classrooms = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*,
                    (SELECT u.first_name
                     FROM {$wpdb->prefix}gtp_class_assignments a
                     INNER JOIN {$wpdb->prefix}gtp_users u ON u.id = a.tutor_id
                     WHERE a.classroom_id = c.id
                     ORDER BY a.id ASC
                     LIMIT 1) AS tutor_first_name,
                    (SELECT u.last_name
                     FROM {$wpdb->prefix}gtp_class_assignments a
                     INNER JOIN {$wpdb->prefix}gtp_users u ON u.id = a.tutor_id
                     WHERE a.classroom_id = c.id
                     ORDER BY a.id ASC
                     LIMIT 1) AS tutor_last_name
             FROM {$wpdb->prefix}gtp_classrooms c
             WHERE c.subject IN ($placeholders)
               AND c.semester_id = %d
             ORDER BY c.school ASC, c.teacher_last_name ASC, c.teacher_first_name ASC",
            ...array_merge($subjects, [gtp_get_working_semester_id()])
        ));

        if ($selected_classroom_id) {
            $valid_ids = array_map(static function ($c) {
                return (int) $c->id;
            }, $classrooms);
            if (!in_array($selected_classroom_id, $valid_ids, true)) {
                $selected_classroom_id = 0;
            }
        }

        if (!$selected_classroom_id && !empty($classrooms)) {
            $selected_classroom_id = (int) $classrooms[0]->id;
        }
    } else {
        $selected_subject = '';
        $selected_classroom_id = 0;
    }

    $selected_classroom = null;
    $sessions = [];
    $roster_students = [];
    $matrix = [];

    if ($selected_classroom_id) {
        foreach ($classrooms as $classroom) {
            if ((int) $classroom->id === $selected_classroom_id) {
                $selected_classroom = $classroom;
                break;
            }
        }
    }

    if ($selected_classroom) {
        $teacher_name = trim($selected_classroom->teacher_first_name . ' ' . $selected_classroom->teacher_last_name);

        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}gtp_sessions
             WHERE school = %s AND subject = %s AND teacher_name = %s
             ORDER BY session_date ASC, id ASC",
            $selected_classroom->school,
            $selected_classroom->subject,
            $teacher_name
        ));

        // Current roster + former students (unlinked) + anyone who appeared in attendance
        $current_roster = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, student_name, classroom_id, last_classroom_id, removed_at, date_added
             FROM {$wpdb->prefix}gtp_students
             WHERE classroom_id = %d OR last_classroom_id = %d
             ORDER BY last_name ASC, first_name ASC, student_name ASC",
            $selected_classroom->id,
            $selected_classroom->id
        ));

        $student_map = [];
        foreach ($current_roster as $student) {
            $student_map[(int) $student->id] = $student;
        }

        $attendance_ids_all = [];
        foreach ($sessions as $session) {
            $ids = json_decode($session->attendance, true);
            if (!is_array($ids)) {
                continue;
            }
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $attendance_ids_all[$id] = true;
                }
            }
        }

        $missing_ids = array_diff(array_keys($attendance_ids_all), array_keys($student_map));
        if (!empty($missing_ids)) {
            $id_placeholders = implode(',', array_fill(0, count($missing_ids), '%d'));
            $former_students = $wpdb->get_results($wpdb->prepare(
                "SELECT id, first_name, last_name, student_name, classroom_id, last_classroom_id, removed_at, date_added
                 FROM {$wpdb->prefix}gtp_students
                 WHERE id IN ($id_placeholders)",
                ...$missing_ids
            ));
            foreach ($former_students as $student) {
                $student_map[(int) $student->id] = $student;
            }
        }

        // Sort roster: last name, first name
        $roster_students = array_values($student_map);
        usort($roster_students, static function ($a, $b) {
            $a_last = strtolower($a->last_name ?: '');
            $b_last = strtolower($b->last_name ?: '');
            if ($a_last === $b_last) {
                return strcasecmp(
                    $a->first_name ?: ($a->student_name ?: ''),
                    $b->first_name ?: ($b->student_name ?: '')
                );
            }
            return strcasecmp($a_last, $b_last);
        });

        // Fallback window for legacy removals without removed_at
        $first_present_date = [];
        $last_present_date = [];
        foreach ($sessions as $session) {
            $ids = json_decode($session->attendance, true);
            if (!is_array($ids)) {
                continue;
            }
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id <= 0) {
                    continue;
                }
                if (!isset($first_present_date[$id])) {
                    $first_present_date[$id] = $session->session_date;
                }
                $last_present_date[$id] = $session->session_date;
            }
        }

        foreach ($roster_students as $student) {
            $sid = (int) $student->id;
            $still_enrolled = ((int) $student->classroom_id === (int) $selected_classroom->id);
            $removed_on = !empty($student->removed_at) ? substr($student->removed_at, 0, 10) : null;
            $row = [];

            foreach ($sessions as $session) {
                // Dash only for students no longer in the class (after they left).
                // No-show sessions count as absent (0) for students still on the roster.
                if (!$still_enrolled) {
                    if ($removed_on !== null) {
                        if ($session->session_date > $removed_on) {
                            $row[] = '-';
                            continue;
                        }
                    } else {
                        // Legacy removals (no removed_at): dash outside first/last presence window
                        $first = $first_present_date[$sid] ?? null;
                        $last = $last_present_date[$sid] ?? null;
                        if ($first === null || $last === null
                            || $session->session_date < $first
                            || $session->session_date > $last) {
                            $row[] = '-';
                            continue;
                        }
                    }
                }

                $ids = json_decode($session->attendance, true);
                if (!is_array($ids)) {
                    $ids = [];
                }
                $ids = array_map('intval', $ids);
                $present = in_array($sid, $ids, true);
                $row[] = $present ? '1' : '0';
            }

            $matrix[$sid] = $row;
        }
    }

    $base_url = site_url('/index.php/spreadsheet-view/');

    ob_start();
    ?>
    <div class="gtp-spreadsheet-wrap">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Spreadsheet View</h1>

        <form method="get" action="<?php echo esc_url($base_url); ?>" class="gtp-spreadsheet-subject-form">
            <p><strong>Select a subject:</strong></p>
            <div class="gtp-spreadsheet-subjects">
                <?php foreach ($subject_choices as $choice) : ?>
                    <label class="gtp-spreadsheet-subject-option">
                        <input type="radio"
                               name="subject"
                               value="<?php echo esc_attr($choice); ?>"
                               <?php checked($selected_subject, $choice); ?>
                               onchange="this.form.submit()">
                        <?php echo esc_html($choice); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </form>

        <?php if (!$selected_subject) : ?>
            <p class="gtp-spreadsheet-empty">Please select a subject.</p>
        <?php elseif (empty($classrooms)) : ?>
            <p class="gtp-spreadsheet-empty">No classes found for this subject.</p>
        <?php else : ?>
            <div class="gtp-spreadsheet-main">
                <?php if ($selected_classroom) :
                    $ta_name = '';
                    if (!empty($selected_classroom->tutor_first_name) || !empty($selected_classroom->tutor_last_name)) {
                        $ta_name = trim($selected_classroom->tutor_first_name . ' ' . $selected_classroom->tutor_last_name);
                    }
                    $class_times = gtp_format_time_range($selected_classroom->start_time, $selected_classroom->end_time);
                    ?>
                    <div class="gtp-spreadsheet-meta">
                        <span><strong>School:</strong> <?php echo esc_html($selected_classroom->school); ?></span>
                        <span class="gtp-ss-meta-sep">|</span>
                        <span><strong>Teacher:</strong> <?php echo esc_html(trim($selected_classroom->teacher_first_name . ' ' . $selected_classroom->teacher_last_name)); ?></span>
                        <span class="gtp-ss-meta-sep">|</span>
                        <span><strong>Teaching Assistant:</strong> <?php echo esc_html($ta_name); ?></span>
                        <span class="gtp-ss-meta-sep">|</span>
                        <span><strong>Class Times:</strong> <?php echo esc_html($class_times); ?></span>
                        <span class="gtp-ss-meta-sep">|</span>
                        <span class="gtp-ss-meta-zoom"><strong>Zoom Link:</strong>
                            <?php if (!empty($selected_classroom->zoom_link)) : ?>
                                <a href="<?php echo esc_url($selected_classroom->zoom_link); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($selected_classroom->zoom_link); ?></a>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="gtp-spreadsheet-scroll">
                        <table class="gtp-spreadsheet-table">
                            <tbody>
                                <tr>
                                    <th class="gtp-ss-label">Date:</th>
                                    <?php if (empty($sessions)) : ?>
                                        <td class="gtp-ss-empty-msg" colspan="1">No sessions logged yet.</td>
                                    <?php else : ?>
                                        <?php foreach ($sessions as $session) : ?>
                                            <td class="gtp-ss-cell gtp-ss-date"><?php echo esc_html(date('m/d/y', strtotime($session->session_date))); ?></td>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tr>
                                <tr>
                                    <th class="gtp-ss-label">Topics Covered:</th>
                                    <?php foreach ($sessions as $session) : ?>
                                        <td class="gtp-ss-cell gtp-ss-topic"><?php echo esc_html($session->topic); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <th class="gtp-ss-label">NOTES:</th>
                                    <?php foreach ($sessions as $session) : ?>
                                        <td class="gtp-ss-cell gtp-ss-notes"><?php echo esc_html($session->comments); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr class="gtp-ss-roster-header">
                                    <th class="gtp-ss-label">Student Names</th>
                                    <?php foreach ($sessions as $session) : ?>
                                        <td class="gtp-ss-cell"></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php foreach ($roster_students as $student) :
                                    $sid = (int) $student->id;
                                    $name = gtp_spreadsheet_student_display_name($student);
                                    ?>
                                    <tr>
                                        <th class="gtp-ss-label gtp-ss-student"><?php echo esc_html($name); ?></th>
                                        <?php
                                        $cells = $matrix[$sid] ?? [];
                                        foreach ($cells as $cell) :
                                            ?>
                                            <td class="gtp-ss-cell gtp-ss-att"><?php echo esc_html($cell); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="gtp-spreadsheet-tabs" role="tablist">
                <?php foreach ($classrooms as $classroom) :
                    $tab_label = $classroom->school;
                    if (!empty($classroom->tutor_first_name) && !empty($classroom->tutor_last_name)) {
                        $initial = strtoupper(substr($classroom->tutor_first_name, 0, 1));
                        $tab_label .= ' (' . $initial . '. ' . $classroom->tutor_last_name . ')';
                    }
                    $tab_url = add_query_arg([
                        'subject' => $selected_subject,
                        'classroom_id' => $classroom->id,
                    ], $base_url);
                    $is_active = ((int) $classroom->id === $selected_classroom_id);
                    ?>
                    <a class="gtp-spreadsheet-tab<?php echo $is_active ? ' is-active' : ''; ?>"
                       href="<?php echo esc_url($tab_url); ?>"
                       role="tab"
                       aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
                        <?php echo esc_html($tab_label); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_spreadsheet_view', 'gtp_spreadsheet_view_shortcode');
