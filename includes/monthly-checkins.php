<?php
/**
 * Monthly tutor check-ins (one form per class per month).
 */

function gtp_checkin_month_label($ym) {
    $ts = strtotime($ym . '-01');
    return $ts ? date('F Y', $ts) : $ym;
}

/** Active due month: current month from the 25th onward; otherwise previous month. */
function gtp_checkin_active_month($timestamp = null) {
    $timestamp = $timestamp ?: current_time('timestamp');
    $day = (int) date('j', $timestamp);
    if ($day >= 25) {
        return date('Y-m', $timestamp);
    }
    return date('Y-m', strtotime('first day of last month', $timestamp));
}

/** True once the 25th of that month has arrived (check-in is open/due). */
function gtp_checkin_month_is_open($ym, $timestamp = null) {
    $timestamp = $timestamp ?: current_time('timestamp');
    $open_at = strtotime($ym . '-25 00:00:00');
    return $open_at !== false && $timestamp >= $open_at;
}

function gtp_checkin_classroom_label($classroom) {
    if (!$classroom) {
        return 'Unknown class';
    }
    $teacher = trim(($classroom->teacher_first_name ?? '') . ' ' . ($classroom->teacher_last_name ?? ''));
    $parts = array_filter([
        gtp_format_classroom_subject($classroom),
        $classroom->school ?? '',
        $teacher !== '' ? ('T. ' . $teacher) : '',
    ]);
    return implode(' · ', $parts);
}

function gtp_tutor_assigned_classrooms($tutor_id, $semester_id = null) {
    global $wpdb;
    if ($semester_id === null) {
        $semester_id = gtp_context_semester_id();
    }
    $semester_id = (int) $semester_id;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT c.*
         FROM {$wpdb->prefix}gtp_classrooms c
         INNER JOIN {$wpdb->prefix}gtp_class_assignments a ON a.classroom_id = c.id
         WHERE a.tutor_id = %d AND c.semester_id = %d
         ORDER BY c.school ASC, c.subject ASC",
        (int) $tutor_id,
        $semester_id
    ));
}

function gtp_checkin_exists($tutor_id, $classroom_id, $ym) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}gtp_monthly_checkins
         WHERE tutor_id = %d AND classroom_id = %d AND checkin_month = %s",
        (int) $tutor_id,
        (int) $classroom_id,
        $ym
    ));
}

function gtp_get_checkin($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT ci.*,
                u.first_name AS tutor_first_name, u.last_name AS tutor_last_name, u.username AS tutor_username,
                c.school, c.subject, c.teacher_first_name, c.teacher_last_name, c.start_time, c.end_time, c.zoom_link
         FROM {$wpdb->prefix}gtp_monthly_checkins ci
         INNER JOIN {$wpdb->prefix}gtp_users u ON u.id = ci.tutor_id
         INNER JOIN {$wpdb->prefix}gtp_classrooms c ON c.id = ci.classroom_id
         WHERE ci.id = %d",
        (int) $id
    ));
}

/**
 * Incomplete check-ins for a tutor for open months (defaults to active due month).
 * @return array{month:string,items:array<int,object>}
 */
function gtp_tutor_due_checkins($tutor_id, $month = null) {
    $tutor_id = (int) $tutor_id;
    $month = $month ?: gtp_checkin_active_month();
    if (!gtp_checkin_month_is_open($month)) {
        return ['month' => $month, 'items' => []];
    }

    $classrooms = gtp_tutor_assigned_classrooms($tutor_id);
    $due = [];
    foreach ($classrooms as $classroom) {
        if (!gtp_checkin_exists($tutor_id, (int) $classroom->id, $month)) {
            $due[] = $classroom;
        }
    }
    return ['month' => $month, 'items' => $due];
}

function gtp_count_tutor_due_checkins($tutor_id) {
    $due = gtp_tutor_due_checkins($tutor_id);
    return count($due['items']);
}

/** Admin: count submitted check-ins with any rating <= 3 for a month (default active). */
function gtp_count_low_rating_checkins($month = null) {
    global $wpdb;
    $month = $month ?: gtp_checkin_active_month();
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_monthly_checkins
         WHERE checkin_month = %s
           AND semester_id = %d
           AND (rating_overall <= 3 OR rating_attendance <= 3
                OR rating_participation <= 3 OR rating_progression <= 3
                OR rating_teacher_mgmt <= 3 OR rating_teacher_engagement <= 3
                OR rating_teacher_communication <= 3 OR rating_teacher_content <= 3)",
        $month,
        gtp_context_semester_id()
    ));
}

function gtp_count_incomplete_checkins_for_month($month = null) {
    global $wpdb;
    $month = $month ?: gtp_checkin_active_month();
    if (!gtp_checkin_month_is_open($month)) {
        return 0;
    }

    $semester_id = gtp_get_working_semester_id();
    $expected = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_class_assignments a
         INNER JOIN {$wpdb->prefix}gtp_users u ON u.id = a.tutor_id
         INNER JOIN {$wpdb->prefix}gtp_classrooms c ON c.id = a.classroom_id
         WHERE u.role = 'tutor' AND u.validated = 1 AND c.semester_id = %d",
        $semester_id
    ));
    $completed = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_monthly_checkins
         WHERE checkin_month = %s AND semester_id = %d",
        $month,
        $semester_id
    ));
    return max(0, $expected - $completed);
}

function gtp_sanitize_rating($value) {
    $n = (int) $value;
    return ($n >= 1 && $n <= 5) ? $n : 0;
}

function gtp_render_rating_field($name, $label, $selected = 0, $explanation = '', $required = true) {
    ob_start();
    ?>
    <fieldset class="gtp-checkin-rating">
        <legend><?php echo esc_html($label); ?><?php echo $required ? ' <span class="gtp-req">*</span>' : ''; ?></legend>
        <div class="gtp-checkin-stars">
            <?php for ($i = 1; $i <= 5; $i++) : ?>
                <label>
                    <input type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo $i; ?>"
                        <?php checked((int) $selected, $i); ?> <?php echo $required ? 'required' : ''; ?>>
                    <?php echo $i; ?>
                </label>
            <?php endfor; ?>
        </div>
        <label class="gtp-checkin-explain">
            <span>Optional explanation</span>
            <textarea name="<?php echo esc_attr($name); ?>_explain" rows="2" placeholder="Optional notes for this rating"><?php echo esc_textarea($explanation); ?></textarea>
        </label>
    </fieldset>
    <?php
    return ob_get_clean();
}

function gtp_checkin_classroom_meta_html($classroom) {
    if (!$classroom) {
        return '';
    }
    $teacher = trim(($classroom->teacher_first_name ?? '') . ' ' . ($classroom->teacher_last_name ?? ''));
    $time = gtp_format_classroom_schedule($classroom);
    ob_start();
    ?>
    <div class="gtp-checkin-class-meta">
        <p><strong>Subject:</strong> <?php echo esc_html(gtp_format_classroom_subject($classroom) ?: '—'); ?></p>
        <p><strong>School:</strong> <?php echo esc_html($classroom->school ?? '—'); ?></p>
        <p><strong>Teacher:</strong> <?php echo esc_html($teacher !== '' ? $teacher : '—'); ?></p>
        <p><strong>Time:</strong> <?php echo esc_html($time !== '' ? $time : '—'); ?></p>
        <?php if (!empty($classroom->zoom_link)) : ?>
            <p><strong>Zoom:</strong> <a href="<?php echo esc_url($classroom->zoom_link); ?>" target="_blank" rel="noopener noreferrer">Link</a></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/* -------------------------------------------------------------------------- */
/* Tutor: list + complete forms                                               */
/* -------------------------------------------------------------------------- */

function gtp_monthly_checkins_tutor_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;
    $tutor_id = (int) $_SESSION['gtp_user']['id'];
    $tutor_name = trim(($_SESSION['gtp_user']['first_name'] ?? '') . ' ' . ($_SESSION['gtp_user']['last_name'] ?? ''));
    $table = $wpdb->prefix . 'gtp_monthly_checkins';
    $message = '';
    $page_url = site_url('/index.php/monthly-checkins/');
    $active_month = gtp_checkin_active_month();
    $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : 'due';
    $fill_classroom = isset($_GET['classroom_id']) ? (int) $_GET['classroom_id'] : 0;
    $fill_month = isset($_GET['month']) ? sanitize_text_field(wp_unslash($_GET['month'])) : $active_month;
    if (!preg_match('/^\d{4}-\d{2}$/', $fill_month)) {
        $fill_month = $active_month;
    }

    $classrooms = gtp_tutor_assigned_classrooms($tutor_id);
    $classroom_map = [];
    foreach ($classrooms as $c) {
        $classroom_map[(int) $c->id] = $c;
    }

    // Submit
    if (isset($_POST['gtp_submit_checkin']) && check_admin_referer('gtp_submit_checkin', 'gtp_checkin_nonce')) {
        $classroom_id = (int) ($_POST['classroom_id'] ?? 0);
        $month = sanitize_text_field(wp_unslash($_POST['checkin_month'] ?? ''));
        $overall = gtp_sanitize_rating($_POST['rating_overall'] ?? 0);
        $attendance = gtp_sanitize_rating($_POST['rating_attendance'] ?? 0);
        $participation = gtp_sanitize_rating($_POST['rating_participation'] ?? 0);
        $progression = gtp_sanitize_rating($_POST['rating_progression'] ?? 0);
        $teacher_mgmt = gtp_sanitize_rating($_POST['rating_teacher_mgmt'] ?? 0);
        $teacher_engagement = gtp_sanitize_rating($_POST['rating_teacher_engagement'] ?? 0);
        $teacher_communication = gtp_sanitize_rating($_POST['rating_teacher_communication'] ?? 0);
        $teacher_content = gtp_sanitize_rating($_POST['rating_teacher_content'] ?? 0);

        if (!isset($classroom_map[$classroom_id])) {
            $message = '<p class="gtp-checkin-msg is-error gtp-persist">Please select one of your assigned classes.</p>';
        } elseif (!preg_match('/^\d{4}-\d{2}$/', $month) || !gtp_checkin_month_is_open($month)) {
            $message = '<p class="gtp-checkin-msg is-error gtp-persist">That check-in month is not open yet.</p>';
        } elseif (!$overall || !$attendance || !$participation || !$progression) {
            $message = '<p class="gtp-checkin-msg is-error gtp-persist">Please provide all four Class/Students ratings (1–5).</p>';
        } elseif (!$teacher_mgmt || !$teacher_engagement || !$teacher_communication || !$teacher_content) {
            $message = '<p class="gtp-checkin-msg is-error gtp-persist">Please provide all four Teacher ratings (1–5).</p>';
        } elseif (gtp_checkin_exists($tutor_id, $classroom_id, $month)) {
            $message = '<p class="gtp-checkin-msg is-error gtp-persist">You already submitted a check-in for this class and month.</p>';
        } else {
            $wpdb->insert($table, [
                'tutor_id' => $tutor_id,
                'classroom_id' => $classroom_id,
                'checkin_month' => $month,
                'rating_overall' => $overall,
                'rating_attendance' => $attendance,
                'rating_participation' => $participation,
                'rating_progression' => $progression,
                'explain_overall' => sanitize_textarea_field(wp_unslash($_POST['rating_overall_explain'] ?? '')),
                'explain_attendance' => sanitize_textarea_field(wp_unslash($_POST['rating_attendance_explain'] ?? '')),
                'explain_participation' => sanitize_textarea_field(wp_unslash($_POST['rating_participation_explain'] ?? '')),
                'explain_progression' => sanitize_textarea_field(wp_unslash($_POST['rating_progression_explain'] ?? '')),
                'rating_teacher_mgmt' => $teacher_mgmt,
                'explain_teacher_mgmt' => sanitize_textarea_field(wp_unslash($_POST['rating_teacher_mgmt_explain'] ?? '')),
                'rating_teacher_engagement' => $teacher_engagement,
                'explain_teacher_engagement' => sanitize_textarea_field(wp_unslash($_POST['rating_teacher_engagement_explain'] ?? '')),
                'rating_teacher_communication' => $teacher_communication,
                'explain_teacher_communication' => sanitize_textarea_field(wp_unslash($_POST['rating_teacher_communication_explain'] ?? '')),
                'rating_teacher_content' => $teacher_content,
                'explain_teacher_content' => sanitize_textarea_field(wp_unslash($_POST['rating_teacher_content_explain'] ?? '')),
                'class_no_show' => !empty($_POST['class_no_show']) ? 1 : 0,
                'teacher_no_show' => !empty($_POST['teacher_no_show']) ? 1 : 0,
                'comments' => sanitize_textarea_field(wp_unslash($_POST['comments'] ?? '')),
                'submitted_at' => current_time('mysql'),
                'semester_id' => (int) ($classroom_map[$classroom_id]->semester_id ?? gtp_get_live_semester_id()),
            ]);
            if ($wpdb->insert_id) {
                $message = '<p class="gtp-checkin-msg is-success">Check-in submitted for '
                    . esc_html(gtp_checkin_month_label($month)) . '.</p>';
                $view = 'history';
                $fill_classroom = 0;
            } else {
                $message = '<p class="gtp-checkin-msg is-error">Could not save check-in. Please try again.</p>';
            }
        }
    }

    $due = gtp_tutor_due_checkins($tutor_id, $active_month);
    $history = $wpdb->get_results($wpdb->prepare(
        "SELECT ci.*, c.school, c.subject, c.teacher_first_name, c.teacher_last_name
         FROM $table ci
         INNER JOIN {$wpdb->prefix}gtp_classrooms c ON c.id = ci.classroom_id
         WHERE ci.tutor_id = %d
         ORDER BY ci.checkin_month DESC, c.school ASC, c.subject ASC",
        $tutor_id
    ));

    $selected_classroom = ($fill_classroom && isset($classroom_map[$fill_classroom]))
        ? $classroom_map[$fill_classroom]
        : null;

    // Classroom JSON for auto-fill
    $class_payload = [];
    foreach ($classrooms as $c) {
        $class_payload[(int) $c->id] = [
            'subject' => gtp_format_classroom_subject($c),
            'school' => $c->school,
            'teacher' => trim($c->teacher_first_name . ' ' . $c->teacher_last_name),
            'time' => gtp_format_classroom_schedule($c),
            'zoom' => $c->zoom_link ?: '',
        ];
    }

    ob_start();
    ?>
    <div class="gtp-home gtp-checkin-page">
        <?php echo gtp_dashboard_back_link('tutor'); ?>
        <h1 class="gtp-page-title">Monthly check-ins</h1>
        <p class="gtp-checkin-intro">Complete one check-in per class each month. Forms open on the 25th.</p>
        <?php echo $message; ?>

        <nav class="gtp-checkin-tabs" aria-label="Check-in views">
            <a class="<?php echo $view === 'due' || $view === 'form' ? 'is-active' : ''; ?>"
               href="<?php echo esc_url(add_query_arg('view', 'due', $page_url)); ?>">
                Due<?php echo count($due['items']) ? ' (' . count($due['items']) . ')' : ''; ?>
            </a>
            <a class="<?php echo $view === 'history' ? 'is-active' : ''; ?>"
               href="<?php echo esc_url(add_query_arg('view', 'history', $page_url)); ?>">Past check-ins</a>
            <?php if (!empty($classrooms) && gtp_checkin_month_is_open($active_month)) : ?>
                <a class="<?php echo $view === 'form' ? 'is-active' : ''; ?>"
                   href="<?php echo esc_url(add_query_arg(['view' => 'form', 'month' => $active_month], $page_url)); ?>">New form</a>
            <?php endif; ?>
        </nav>

        <?php if ($view === 'history') : ?>
            <section class="gtp-checkin-section">
                <h2>Past check-ins</h2>
                <?php if (empty($history)) : ?>
                    <p>You have not submitted any monthly check-ins yet.</p>
                <?php else : ?>
                    <div class="gtp-checkin-table-wrap">
                        <table class="gtp-checkin-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Class</th>
                                    <th>Overall</th>
                                    <th>Att.</th>
                                    <th>Part.</th>
                                    <th>Prog.</th>
                                    <th>T.Mgmt</th>
                                    <th>T.Engage</th>
                                    <th>T.Comm</th>
                                    <th>T.Content</th>
                                    <th>Submitted</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $row) :
                                    $low = ($row->rating_overall <= 3 || $row->rating_attendance <= 3
                                        || $row->rating_participation <= 3 || $row->rating_progression <= 3
                                        || ($row->rating_teacher_mgmt && $row->rating_teacher_mgmt <= 3)
                                        || ($row->rating_teacher_engagement && $row->rating_teacher_engagement <= 3)
                                        || ($row->rating_teacher_communication && $row->rating_teacher_communication <= 3)
                                        || ($row->rating_teacher_content && $row->rating_teacher_content <= 3));
                                    ?>
                                    <tr class="<?php echo $low ? 'is-low' : ''; ?>">
                                        <td><?php echo esc_html(gtp_checkin_month_label($row->checkin_month)); ?></td>
                                        <td><?php echo esc_html(gtp_checkin_classroom_label($row)); ?></td>
                                        <td><?php echo (int) $row->rating_overall; ?>/5</td>
                                        <td><?php echo (int) $row->rating_attendance; ?>/5</td>
                                        <td><?php echo (int) $row->rating_participation; ?>/5</td>
                                        <td><?php echo (int) $row->rating_progression; ?>/5</td>
                                        <td><?php echo $row->rating_teacher_mgmt ? ((int) $row->rating_teacher_mgmt . '/5') : '—'; ?></td>
                                        <td><?php echo $row->rating_teacher_engagement ? ((int) $row->rating_teacher_engagement . '/5') : '—'; ?></td>
                                        <td><?php echo $row->rating_teacher_communication ? ((int) $row->rating_teacher_communication . '/5') : '—'; ?></td>
                                        <td><?php echo $row->rating_teacher_content ? ((int) $row->rating_teacher_content . '/5') : '—'; ?></td>
                                        <td><?php echo esc_html(date('M j, Y', strtotime($row->submitted_at))); ?></td>
                                        <td><a href="<?php echo esc_url(add_query_arg(['view' => 'detail', 'id' => (int) $row->id], $page_url)); ?>">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        <?php elseif ($view === 'detail' && !empty($_GET['id'])) : ?>
            <?php
            $detail = gtp_get_checkin((int) $_GET['id']);
            if (!$detail || (int) $detail->tutor_id !== $tutor_id) {
                echo '<p>Check-in not found.</p>';
            } else {
                echo gtp_render_checkin_detail($detail);
            }
            ?>

        <?php elseif ($view === 'form' || ($view === 'due' && $fill_classroom)) : ?>
            <section class="gtp-checkin-section">
                <h2>Check-in form · <?php echo esc_html(gtp_checkin_month_label($fill_month)); ?></h2>
                <?php if (empty($classrooms)) : ?>
                    <p>You are not assigned to any classes yet.</p>
                <?php elseif (!gtp_checkin_month_is_open($fill_month)) : ?>
                    <p>Check-ins for this month open on the 25th.</p>
                <?php else : ?>
                    <form method="post" class="gtp-checkin-form" id="gtp-checkin-form">
                        <?php wp_nonce_field('gtp_submit_checkin', 'gtp_checkin_nonce'); ?>
                        <input type="hidden" name="checkin_month" value="<?php echo esc_attr($fill_month); ?>">

                        <p class="gtp-checkin-tutor-name"><strong>Tutor:</strong> <?php echo esc_html($tutor_name); ?></p>

                        <label class="gtp-checkin-field">
                            <span>Class <span class="gtp-req">*</span></span>
                            <select name="classroom_id" id="gtp-checkin-classroom" required>
                                <option value="">Select a class…</option>
                                <?php foreach ($classrooms as $c) :
                                    $already = gtp_checkin_exists($tutor_id, (int) $c->id, $fill_month);
                                    if ($already) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?php echo (int) $c->id; ?>"
                                        <?php selected($fill_classroom, (int) $c->id); ?>>
                                        <?php echo esc_html(gtp_checkin_classroom_label($c)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <div id="gtp-checkin-class-details" class="gtp-checkin-class-meta" <?php echo $selected_classroom ? '' : 'hidden'; ?>>
                            <?php
                            if ($selected_classroom) {
                                echo gtp_checkin_classroom_meta_html($selected_classroom);
                            }
                            ?>
                        </div>

                        <h3 class="gtp-checkin-section-heading">Class / Students</h3>
                        <?php
                        echo gtp_render_rating_field('rating_overall', 'Overall month / sessions');
                        echo gtp_render_rating_field('rating_attendance', 'Attendance');
                        echo gtp_render_rating_field('rating_participation', 'Participation');
                        echo gtp_render_rating_field('rating_progression', 'Progression through content');
                        ?>

                        <div class="gtp-checkin-checks">
                            <label>
                                <input type="checkbox" name="class_no_show" value="1">
                                Class did not show up for one or more sessions
                            </label>
                            <label>
                                <input type="checkbox" name="teacher_no_show" value="1">
                                Teacher did not show up for one or more sessions
                            </label>
                        </div>

                        <h3 class="gtp-checkin-section-heading">Teacher</h3>
                        <?php
                        echo gtp_render_rating_field('rating_teacher_mgmt', 'Teacher Management of Class');
                        echo gtp_render_rating_field('rating_teacher_engagement', 'Teacher Engagement / Presence During Sessions');
                        echo gtp_render_rating_field('rating_teacher_communication', 'Teacher Communication');
                        echo gtp_render_rating_field('rating_teacher_content', 'Movement Through Content on Non-Session Days');
                        ?>

                        <label class="gtp-checkin-field">
                            <span>Comments</span>
                            <textarea name="comments" rows="4" placeholder="Anything else we should know?"></textarea>
                        </label>

                        <button type="submit" name="gtp_submit_checkin" value="1" class="button button-primary">Submit check-in</button>
                    </form>
                    <script type="application/json" id="gtp-checkin-class-data"><?php echo wp_json_encode($class_payload); ?></script>
                <?php endif; ?>
            </section>

        <?php else : /* due list */ ?>
            <section class="gtp-checkin-section">
                <h2>Due for <?php echo esc_html(gtp_checkin_month_label($due['month'])); ?></h2>
                <?php if (!gtp_checkin_month_is_open($active_month)) : ?>
                    <p>Monthly check-ins open on the 25th. Nothing is due right now.</p>
                <?php elseif (empty($due['items'])) : ?>
                    <p class="gtp-checkin-msg is-success gtp-persist">You're all caught up for this month.</p>
                    <p><a href="<?php echo esc_url(add_query_arg('view', 'history', $page_url)); ?>">View past check-ins</a></p>
                <?php else : ?>
                    <p>Complete one form for each class below.</p>
                    <ul class="gtp-checkin-due-list">
                        <?php foreach ($due['items'] as $c) : ?>
                            <li>
                                <div>
                                    <strong><?php echo esc_html(gtp_checkin_classroom_label($c)); ?></strong>
                                    <div class="gtp-checkin-muted"><?php echo esc_html(gtp_format_classroom_schedule($c) ?: 'Time TBD'); ?></div>
                                </div>
                                <a class="button button-primary"
                                   href="<?php echo esc_url(add_query_arg([
                                       'view' => 'form',
                                       'month' => $due['month'],
                                       'classroom_id' => (int) $c->id,
                                   ], $page_url)); ?>">Complete</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_monthly_checkins', 'gtp_monthly_checkins_tutor_shortcode');

function gtp_render_checkin_detail($row, $include_back = true) {
    $low = ($row->rating_overall <= 3 || $row->rating_attendance <= 3
        || $row->rating_participation <= 3 || $row->rating_progression <= 3
        || ($row->rating_teacher_mgmt && $row->rating_teacher_mgmt <= 3)
        || ($row->rating_teacher_engagement && $row->rating_teacher_engagement <= 3)
        || ($row->rating_teacher_communication && $row->rating_teacher_communication <= 3)
        || ($row->rating_teacher_content && $row->rating_teacher_content <= 3));
    ob_start();
    ?>
    <section class="gtp-checkin-section">
        <?php if ($include_back) {
            echo gtp_dashboard_back_link();
        } ?>
        <h2 class="gtp-page-title"><?php echo esc_html(gtp_checkin_month_label($row->checkin_month)); ?> · <?php echo esc_html(gtp_checkin_classroom_label($row)); ?></h2>
        <?php if ($low) : ?>
            <p class="gtp-checkin-msg is-warn gtp-persist">Includes one or more ratings of 3 or below.</p>
        <?php endif; ?>
        <p><strong>Tutor:</strong> <?php echo esc_html(trim(($row->tutor_first_name ?? '') . ' ' . ($row->tutor_last_name ?? ''))); ?></p>
        <?php echo gtp_checkin_classroom_meta_html($row); ?>
        <h3 class="gtp-checkin-section-heading">Class / Students</h3>
        <ul class="gtp-checkin-detail-ratings">
            <li><strong>Overall:</strong> <?php echo (int) $row->rating_overall; ?>/5
                <?php if ($row->explain_overall) : ?><div class="gtp-checkin-muted"><?php echo esc_html($row->explain_overall); ?></div><?php endif; ?></li>
            <li><strong>Attendance:</strong> <?php echo (int) $row->rating_attendance; ?>/5
                <?php if ($row->explain_attendance) : ?><div class="gtp-checkin-muted"><?php echo esc_html($row->explain_attendance); ?></div><?php endif; ?></li>
            <li><strong>Participation:</strong> <?php echo (int) $row->rating_participation; ?>/5
                <?php if ($row->explain_participation) : ?><div class="gtp-checkin-muted"><?php echo esc_html($row->explain_participation); ?></div><?php endif; ?></li>
            <li><strong>Progression:</strong> <?php echo (int) $row->rating_progression; ?>/5
                <?php if ($row->explain_progression) : ?><div class="gtp-checkin-muted"><?php echo esc_html($row->explain_progression); ?></div><?php endif; ?></li>
        </ul>
        <p>
            <strong>Class no-show (1+ sessions):</strong> <?php echo !empty($row->class_no_show) ? 'Yes' : 'No'; ?><br>
            <strong>Teacher no-show (1+ sessions):</strong> <?php echo !empty($row->teacher_no_show) ? 'Yes' : 'No'; ?>
        </p>
        <?php if ($row->rating_teacher_mgmt) : ?>
        <h3 class="gtp-checkin-section-heading">Teacher</h3>
        <ul class="gtp-checkin-detail-ratings">
            <li><strong>Teacher Management of Class:</strong> <?php echo (int) $row->rating_teacher_mgmt; ?>/5
                <?php if ($row->explain_teacher_mgmt) : ?><div class="gtp-checkin-muted"><?php echo esc_html($row->explain_teacher_mgmt); ?></div><?php endif; ?></li>
            <li><strong>Teacher Engagement / Presence:</strong> <?php echo (int) $row->rating_teacher_engagement; ?>/5
                <?php if ($row->explain_teacher_engagement) : ?><div class="gtp-checkin-muted"><?php echo esc_html($row->explain_teacher_engagement); ?></div><?php endif; ?></li>
            <li><strong>Teacher Communication:</strong> <?php echo (int) $row->rating_teacher_communication; ?>/5
                <?php if ($row->explain_teacher_communication) : ?><div class="gtp-checkin-muted"><?php echo esc_html($row->explain_teacher_communication); ?></div><?php endif; ?></li>
            <li><strong>Movement Through Content (non-session days):</strong> <?php echo (int) $row->rating_teacher_content; ?>/5
                <?php if ($row->explain_teacher_content) : ?><div class="gtp-checkin-muted"><?php echo esc_html($row->explain_teacher_content); ?></div><?php endif; ?></li>
        </ul>
        <?php endif; ?>
        <?php if (!empty($row->comments)) : ?>
            <p><strong>Comments:</strong></p>
            <div class="gtp-checkin-comments"><?php echo nl2br(esc_html($row->comments)); ?></div>
        <?php endif; ?>
        <p class="gtp-checkin-muted">Submitted <?php echo esc_html(date('M j, Y g:i A', strtotime($row->submitted_at))); ?></p>
    </section>
    <?php
    return ob_get_clean();
}

/* -------------------------------------------------------------------------- */
/* Admin review                                                               */
/* -------------------------------------------------------------------------- */

function gtp_monthly_checkins_admin_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'gtp_monthly_checkins';
    $page_url = site_url('/index.php/admin-checkins/');
    $active_month = gtp_checkin_active_month();

    $month = isset($_GET['month']) ? sanitize_text_field(wp_unslash($_GET['month'])) : $active_month;
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = $active_month;
    }
    $classroom_id = isset($_GET['classroom_id']) ? (int) $_GET['classroom_id'] : 0;
    $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
    if (!in_array($status, ['', 'complete', 'incomplete'], true)) {
        $status = '';
    }
    $low_only = !empty($_GET['low_rating']);
    $detail_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($detail_id > 0) {
        $detail = gtp_get_checkin($detail_id);
        ob_start();
        ?>
        <div class="gtp-home gtp-checkin-page">
            <?php echo gtp_dashboard_back_link('admin'); ?>
            <?php
            if ($detail) {
                echo gtp_render_checkin_detail($detail, false);
            } else {
                echo '<p>Check-in not found.</p>';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    $months = $wpdb->get_col("SELECT DISTINCT checkin_month FROM $table ORDER BY checkin_month DESC");
    if (!in_array($active_month, $months, true)) {
        array_unshift($months, $active_month);
    }
    // Also include last few months for filtering incomplete
    for ($i = 0; $i < 6; $i++) {
        $ym = date('Y-m', strtotime("first day of -{$i} month", current_time('timestamp')));
        if (!in_array($ym, $months, true)) {
            $months[] = $ym;
        }
    }
    $months = array_values(array_unique($months));
    rsort($months);

    $classrooms = $wpdb->get_results($wpdb->prepare(
        "SELECT id, school, subject, teacher_first_name, teacher_last_name
         FROM {$wpdb->prefix}gtp_classrooms
         WHERE semester_id = %d
         ORDER BY school ASC, subject ASC",
        gtp_get_working_semester_id()
    ));

    // Build expected assignment rows for incomplete tracking
    $assignments = $wpdb->get_results($wpdb->prepare(
        "SELECT a.tutor_id, a.classroom_id, u.first_name, u.last_name, u.username,
                c.school, c.subject, c.teacher_first_name, c.teacher_last_name
         FROM {$wpdb->prefix}gtp_class_assignments a
         INNER JOIN {$wpdb->prefix}gtp_users u ON u.id = a.tutor_id
         INNER JOIN {$wpdb->prefix}gtp_classrooms c ON c.id = a.classroom_id
         WHERE u.role = 'tutor' AND u.validated = 1 AND c.semester_id = %d
         ORDER BY u.last_name ASC, c.school ASC",
        gtp_get_working_semester_id()
    ));

    $completed_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT ci.*, u.first_name, u.last_name, u.username,
                c.school, c.subject, c.teacher_first_name, c.teacher_last_name
         FROM $table ci
         INNER JOIN {$wpdb->prefix}gtp_users u ON u.id = ci.tutor_id
         INNER JOIN {$wpdb->prefix}gtp_classrooms c ON c.id = ci.classroom_id
         WHERE ci.checkin_month = %s AND ci.semester_id = %d",
        $month,
        gtp_get_working_semester_id()
    ));

    $completed_map = [];
    foreach ($completed_rows as $row) {
        $completed_map[$row->tutor_id . ':' . $row->classroom_id] = $row;
    }

    $rows = [];
    if ($status !== 'incomplete') {
        foreach ($completed_rows as $row) {
            if ($classroom_id && (int) $row->classroom_id !== $classroom_id) {
                continue;
            }
            if ($low_only) {
                $is_low = ($row->rating_overall <= 3 || $row->rating_attendance <= 3
                    || $row->rating_participation <= 3 || $row->rating_progression <= 3);
                if (!$is_low) {
                    continue;
                }
            }
            $row->_status = 'complete';
            $rows[] = $row;
        }
    }

    if ($status !== 'complete' && !$low_only && gtp_checkin_month_is_open($month)) {
        foreach ($assignments as $a) {
            if ($classroom_id && (int) $a->classroom_id !== $classroom_id) {
                continue;
            }
            $key = $a->tutor_id . ':' . $a->classroom_id;
            if (isset($completed_map[$key])) {
                continue;
            }
            $rows[] = (object) [
                'id' => 0,
                'tutor_id' => $a->tutor_id,
                'classroom_id' => $a->classroom_id,
                'first_name' => $a->first_name,
                'last_name' => $a->last_name,
                'username' => $a->username,
                'school' => $a->school,
                'subject' => $a->subject,
                'teacher_first_name' => $a->teacher_first_name,
                'teacher_last_name' => $a->teacher_last_name,
                'checkin_month' => $month,
                '_status' => 'incomplete',
            ];
        }
    }

    $low_count = gtp_count_low_rating_checkins($month);
    $incomplete_count = gtp_count_incomplete_checkins_for_month($month);

    ob_start();
    ?>
    <div class="gtp-home gtp-checkin-page">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Tutor check-ins</h1>
        <p class="gtp-checkin-intro">Review monthly forms by class. Low ratings (3 or below) are highlighted.</p>

        <?php if ($low_count > 0) : ?>
            <p class="gtp-checkin-msg is-warn gtp-persist">
                <?php echo (int) $low_count; ?> submitted check-in<?php echo $low_count === 1 ? '' : 's'; ?>
                for <?php echo esc_html(gtp_checkin_month_label($month)); ?>
                include a rating of 3 or lower.
                <a href="<?php echo esc_url(add_query_arg(['month' => $month, 'low_rating' => 1, 'status' => 'complete'], $page_url)); ?>">View them</a>
            </p>
        <?php endif; ?>

        <form class="gtp-checkin-filters" method="get" action="<?php echo esc_url($page_url); ?>">
            <label>
                <span>Month</span>
                <select name="month">
                    <?php foreach ($months as $ym) : ?>
                        <option value="<?php echo esc_attr($ym); ?>" <?php selected($month, $ym); ?>>
                            <?php echo esc_html(gtp_checkin_month_label($ym)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Class</span>
                <select name="classroom_id">
                    <option value="0">All classes</option>
                    <?php foreach ($classrooms as $c) : ?>
                        <option value="<?php echo (int) $c->id; ?>" <?php selected($classroom_id, (int) $c->id); ?>>
                            <?php echo esc_html(gtp_checkin_classroom_label($c)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="" <?php selected($status, ''); ?>>All</option>
                    <option value="complete" <?php selected($status, 'complete'); ?>>Complete</option>
                    <option value="incomplete" <?php selected($status, 'incomplete'); ?>>Not complete</option>
                </select>
            </label>
            <label class="gtp-checkin-filter-check">
                <input type="checkbox" name="low_rating" value="1" <?php checked($low_only); ?>>
                Ratings ≤ 3 only
            </label>
            <div class="gtp-checkin-filter-actions">
                <button type="submit" class="button button-primary">Apply</button>
                <a class="button" href="<?php echo esc_url($page_url); ?>">Clear</a>
            </div>
        </form>

        <p class="gtp-checkin-muted">
            <?php echo count($rows); ?> row<?php echo count($rows) === 1 ? '' : 's'; ?>
            · <?php echo (int) $incomplete_count; ?> incomplete for this month
            · <?php echo (int) $low_count; ?> with low ratings
        </p>

        <?php if (empty($rows)) : ?>
            <p>No check-ins match these filters.</p>
        <?php else : ?>
            <div class="gtp-checkin-table-wrap">
                <table class="gtp-checkin-table">
                    <thead>
                        <tr>
                            <th>Tutor</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th colspan="4" class="gtp-checkin-col-group">Class / Students</th>
                            <th colspan="4" class="gtp-checkin-col-group">Teacher</th>
                            <th></th>
                        </tr>
                        <tr class="gtp-checkin-subheader">
                            <th></th><th></th><th></th>
                            <th>Overall</th><th>Att.</th><th>Part.</th><th>Prog.</th>
                            <th>Mgmt</th><th>Engage</th><th>Comm</th><th>Content</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) :
                            $is_complete = ($row->_status === 'complete');
                            $is_low = $is_complete && (
                                $row->rating_overall <= 3 || $row->rating_attendance <= 3
                                || $row->rating_participation <= 3 || $row->rating_progression <= 3
                                || ($row->rating_teacher_mgmt && $row->rating_teacher_mgmt <= 3)
                                || ($row->rating_teacher_engagement && $row->rating_teacher_engagement <= 3)
                                || ($row->rating_teacher_communication && $row->rating_teacher_communication <= 3)
                                || ($row->rating_teacher_content && $row->rating_teacher_content <= 3)
                            );
                            ?>
                            <tr class="<?php echo $is_low ? 'is-low' : ($is_complete ? '' : 'is-incomplete'); ?>">
                                <td><?php echo esc_html(trim($row->first_name . ' ' . $row->last_name)); ?></td>
                                <td><?php echo esc_html(gtp_checkin_classroom_label($row)); ?></td>
                                <td><?php echo $is_complete ? 'Complete' : 'Not complete'; ?></td>
                                <td><?php echo $is_complete ? ((int) $row->rating_overall . '/5') : '—'; ?></td>
                                <td><?php echo $is_complete ? ((int) $row->rating_attendance . '/5') : '—'; ?></td>
                                <td><?php echo $is_complete ? ((int) $row->rating_participation . '/5') : '—'; ?></td>
                                <td><?php echo $is_complete ? ((int) $row->rating_progression . '/5') : '—'; ?></td>
                                <td><?php echo ($is_complete && $row->rating_teacher_mgmt) ? ((int) $row->rating_teacher_mgmt . '/5') : '—'; ?></td>
                                <td><?php echo ($is_complete && $row->rating_teacher_engagement) ? ((int) $row->rating_teacher_engagement . '/5') : '—'; ?></td>
                                <td><?php echo ($is_complete && $row->rating_teacher_communication) ? ((int) $row->rating_teacher_communication . '/5') : '—'; ?></td>
                                <td><?php echo ($is_complete && $row->rating_teacher_content) ? ((int) $row->rating_teacher_content . '/5') : '—'; ?></td>
                                <td>
                                    <?php if ($is_complete) : ?>
                                        <a href="<?php echo esc_url(add_query_arg('id', (int) $row->id, $page_url)); ?>">View</a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_admin_checkins', 'gtp_monthly_checkins_admin_shortcode');
