<?php
/**
 * Admin session filter / payroll report shortcode.
 */

function gtp_session_filter_subject_choices() {
    return gtp_get_subjects();
}

function gtp_session_filter_subjects_for_choice($choice) {
    return gtp_subject_match_values($choice);
}

/**
 * Build SQL + params for session log filtering from request args.
 *
 * @param array $args Typically $_GET
 * @return array{0:string,1:array} SQL (without ORDER BY) and prepare params
 */
function gtp_session_filter_build_log_query($args) {
    global $wpdb;

    $sessions_table = $wpdb->prefix . 'gtp_sessions';
    $sql = "SELECT * FROM $sessions_table WHERE 1=1";
    $params = [];

    $semester_id = isset($args['semester_id']) ? (int) $args['semester_id'] : gtp_get_working_semester_id();
    if ($semester_id > 0) {
        $sql .= ' AND semester_id = %d';
        $params[] = $semester_id;
    }

    if (!empty($args['start_date'])) {
        $sql .= ' AND session_date >= %s';
        $params[] = sanitize_text_field($args['start_date']);
    }
    if (!empty($args['end_date'])) {
        $sql .= ' AND session_date <= %s';
        $params[] = sanitize_text_field($args['end_date']);
    }
    if (!empty($args['school'])) {
        $sql .= ' AND school = %s';
        $params[] = sanitize_text_field($args['school']);
    }
    if (!empty($args['tutor_id'])) {
        $tutor_user = $wpdb->get_row($wpdb->prepare(
            "SELECT username FROM {$wpdb->prefix}gtp_users WHERE id = %d",
            intval($args['tutor_id'])
        ));
        if ($tutor_user) {
            $sql .= ' AND tutor_username = %s';
            $params[] = $tutor_user->username;
        }
    }
    if (!empty($args['classroom_id'])) {
        $classroom_details = $wpdb->get_row($wpdb->prepare(
            "SELECT school, subject, teacher_first_name, teacher_last_name
             FROM {$wpdb->prefix}gtp_classrooms WHERE id = %d",
            intval($args['classroom_id'])
        ));
        if ($classroom_details) {
            $teacher_name = $classroom_details->teacher_first_name . ' ' . $classroom_details->teacher_last_name;
            $sql .= ' AND school = %s AND subject = %s AND teacher_name = %s';
            $params[] = $classroom_details->school;
            $params[] = $classroom_details->subject;
            $params[] = $teacher_name;
        }
    }

    if (!empty($args['subject'])) {
        $choice = sanitize_text_field($args['subject']);
        $subjects = gtp_session_filter_subjects_for_choice($choice);
        $placeholders = implode(',', array_fill(0, count($subjects), '%s'));
        $sql .= " AND subject IN ($placeholders)";
        foreach ($subjects as $subject) {
            $params[] = $subject;
        }
    }

    if (!empty($args['teacher_absent'])) {
        $sql .= ' AND teacher_present = 0';
    }

    if (!empty($args['name_search'])) {
        $name = sanitize_text_field($args['name_search']);
        $like = '%' . $wpdb->esc_like($name) . '%';
        $name_clauses = [];

        // Tutor who logged the session
        $name_clauses[] = '(first_name LIKE %s OR last_name LIKE %s OR CONCAT(first_name, \' \', last_name) LIKE %s OR tutor_username LIKE %s)';
        array_push($params, $like, $like, $like, $like);

        // Classroom teacher on the session
        $name_clauses[] = '(teacher_name LIKE %s)';
        $params[] = $like;

        // Students in attendance JSON
        $student_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gtp_students
             WHERE first_name LIKE %s
                OR last_name LIKE %s
                OR student_name LIKE %s
                OR CONCAT(IFNULL(first_name, ''), ' ', IFNULL(last_name, '')) LIKE %s",
            $like,
            $like,
            $like,
            $like
        ));

        foreach ($student_ids as $sid) {
            $sid = (int) $sid;
            if ($sid <= 0) {
                continue;
            }
            // Match student id as a JSON array element without matching longer numbers
            $name_clauses[] = '(attendance = %s OR attendance LIKE %s OR attendance LIKE %s OR attendance LIKE %s OR attendance LIKE %s)';
            array_push(
                $params,
                '[' . $sid . ']',
                '%[' . $sid . ',%',
                '%,' . $sid . ',%',
                '%,' . $sid . ']%',
                '%[' . $sid . ']%'
            );
        }

        $sql .= ' AND (' . implode(' OR ', $name_clauses) . ')';
    }

    return [$sql, $params];
}

function gtp_session_filter_shortcode() {
    global $wpdb;

    if (isset($_GET['export_csv'])) {
        $report_type = $_GET['report_type'] ?? 'payroll';
        $results = [];
        $sessions_table = $wpdb->prefix . 'gtp_sessions';
        $filename = 'session_export_' . date('Y-m-d') . '.csv';

        if ($report_type === 'payroll') {
            $start_date = sanitize_text_field($_GET['start_date'] ?? '');
            $end_date = sanitize_text_field($_GET['end_date'] ?? '');
            if (!empty($start_date) && !empty($end_date)) {
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT u.first_name, u.last_name, COUNT(*) as session_count
                     FROM $sessions_table s
                     JOIN {$wpdb->prefix}gtp_users u ON s.tutor_username = u.username
                     WHERE s.session_date BETWEEN %s AND %s
                     GROUP BY s.tutor_username
                     ORDER BY u.last_name, u.first_name",
                    $start_date,
                    $end_date
                ), ARRAY_A);
            }
        } else {
            list($sql, $params) = gtp_session_filter_build_log_query($_GET);
            $sql .= ' ORDER BY session_date DESC';
            $results = empty($params)
                ? $wpdb->get_results($sql, ARRAY_A)
                : $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');

        if (!empty($results)) {
            if ($report_type === 'payroll') {
                fputcsv($output, ['Tutor Name', 'Session Count', 'Invoice Date']);
                $invoice_date = date('Y-m-d');
                foreach ($results as $row) {
                    fputcsv($output, [
                        $row['first_name'] . ' ' . $row['last_name'],
                        $row['session_count'],
                        $invoice_date,
                    ]);
                }
            } else {
                fputcsv($output, array_keys($results[0]));
                foreach ($results as $row) {
                    fputcsv($output, $row);
                }
            }
        }
        fclose($output);
        exit;
    }

    $semester_id = gtp_get_working_semester_id();
    $schools = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT school FROM {$wpdb->prefix}gtp_classrooms WHERE semester_id = %d ORDER BY school ASC",
        $semester_id
    ));
    $classrooms = $wpdb->get_results($wpdb->prepare(
        "SELECT id, school, subject, teacher_first_name, teacher_last_name
         FROM {$wpdb->prefix}gtp_classrooms
         WHERE semester_id = %d
         ORDER BY school, subject ASC",
        $semester_id
    ));
    $tutors = $wpdb->get_results("SELECT id, first_name, last_name FROM {$wpdb->prefix}gtp_users WHERE role = 'tutor' ORDER BY last_name, first_name ASC");
    $subject_choices = gtp_session_filter_subject_choices();

    $selected_report = $_GET['report_type'] ?? 'payroll';
    $name_search = isset($_GET['name_search']) ? sanitize_text_field($_GET['name_search']) : '';
    $selected_subject = isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : '';
    $teacher_absent = !empty($_GET['teacher_absent']);

    ob_start();
    ?>
    <div class="gtp-session-filter-wrap">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Filter TA Sessions</h1>

        <form method="get" action="<?php echo esc_url(site_url('/index.php/ta-session-filter/')); ?>">
            <fieldset style="margin-bottom: 20px;">
                <legend><h3>Select Report Type</h3></legend>
                <p>
                    <label><input type="radio" name="report_type" value="payroll" <?php checked($selected_report === 'payroll'); ?>> <strong>Payroll Report</strong> (Total sessions per tutor)</label><br>
                    <label><input type="radio" name="report_type" value="session_log" <?php checked($selected_report === 'session_log'); ?>> <strong>Session Log Search</strong> (Detailed list of sessions)</label><br>
                </p>
            </fieldset>

            <fieldset id="filters-area">
                <legend><h3>Filters</h3></legend>
                <table class="form-table">
                    <tr class="date-range-filter">
                        <th scope="row"><label>Date Range</label></th>
                        <td>
                            <input type="date" name="start_date" value="<?php echo esc_attr($_GET['start_date'] ?? ''); ?>">
                            <span>to</span>
                            <input type="date" name="end_date" value="<?php echo esc_attr($_GET['end_date'] ?? ''); ?>">
                        </td>
                    </tr>
                    <tr class="name-search-filter">
                        <th scope="row"><label for="name_search">Search by Name</label></th>
                        <td>
                            <input type="text"
                                   id="name_search"
                                   name="name_search"
                                   value="<?php echo esc_attr($name_search); ?>"
                                   placeholder="Student, tutor, or teacher name"
                                   style="width: 100%; max-width: 320px;">
                            <p class="description" style="margin: 6px 0 0;">Finds sessions involving that person as a student, tutor, or teacher.</p>
                        </td>
                    </tr>
                    <tr class="subject-filter">
                        <th scope="row"><label for="subject">Subject</label></th>
                        <td>
                            <select name="subject" id="subject">
                                <option value="">-- All Subjects --</option>
                                <?php foreach ($subject_choices as $choice) : ?>
                                    <option value="<?php echo esc_attr($choice); ?>" <?php selected($selected_subject, $choice); ?>>
                                        <?php echo esc_html($choice); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr class="teacher-absent-filter">
                        <th scope="row"><label for="teacher_absent">Teacher Absent</label></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       id="teacher_absent"
                                       name="teacher_absent"
                                       value="1"
                                       <?php checked($teacher_absent); ?>>
                                Only sessions where the teacher was not present on Zoom
                            </label>
                        </td>
                    </tr>
                    <tr class="school-filter">
                        <th scope="row"><label for="school">School</label></th>
                        <td>
                            <select name="school">
                                <option value="">-- All Schools --</option>
                                <?php foreach ($schools as $school) : ?>
                                    <option value="<?php echo esc_attr($school); ?>" <?php selected(isset($_GET['school']) ? $_GET['school'] : '', $school); ?>><?php echo esc_html($school); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr class="tutor-filter">
                        <th scope="row"><label for="tutor_id">Tutor</label></th>
                        <td>
                            <select name="tutor_id">
                                <option value="">-- All Tutors --</option>
                                <?php foreach ($tutors as $tutor) : ?>
                                    <option value="<?php echo (int) $tutor->id; ?>" <?php selected(isset($_GET['tutor_id']) ? $_GET['tutor_id'] : '', $tutor->id); ?>><?php echo esc_html("{$tutor->first_name} {$tutor->last_name}"); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr class="classroom-filter">
                        <th scope="row"><label for="classroom_id">Classroom</label></th>
                        <td>
                            <select name="classroom_id">
                                <option value="">-- All Classrooms --</option>
                                <?php foreach ($classrooms as $classroom) : ?>
                                    <option value="<?php echo (int) $classroom->id; ?>" <?php selected(isset($_GET['classroom_id']) ? $_GET['classroom_id'] : '', $classroom->id); ?>>
                                        <?php echo esc_html("{$classroom->school} - {$classroom->subject} ({$classroom->teacher_first_name} {$classroom->teacher_last_name})"); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </fieldset>

            <p class="submit">
                <input type="submit" name="filter_sessions" value="Generate Report" class="button button-primary">
                <input type="submit" name="export_csv" value="Export to CSV" class="button">
            </p>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const reportTypeRadios = document.querySelectorAll('input[name="report_type"]');
                const sessionLogFilters = document.querySelectorAll(
                    '.school-filter, .tutor-filter, .classroom-filter, .name-search-filter, .subject-filter, .teacher-absent-filter'
                );

                function toggleFilters() {
                    const selected = document.querySelector('input[name="report_type"]:checked').value;
                    const showLogFilters = selected === 'session_log';
                    sessionLogFilters.forEach(function (row) {
                        row.style.display = showLogFilters ? '' : 'none';
                    });
                }

                reportTypeRadios.forEach(function (radio) {
                    radio.addEventListener('change', toggleFilters);
                });
                toggleFilters();
            });
        </script>

        <style>
            .gtp-session-filter-wrap {
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 10px 0 24px;
                background: transparent;
                box-sizing: border-box;
            }
            .spreadsheet-style {
                border-collapse: collapse;
                width: 100%;
                min-width: 720px;
                table-layout: auto;
            }
            .spreadsheet-style th,
            .spreadsheet-style td {
                border: 1px solid #ccc;
                padding: 8px;
                vertical-align: top;
                word-break: break-word;
            }
            .spreadsheet-style th { background-color: #f2f2f2; }
            #session-results {
                width: 100%;
                max-width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin-top: 16px;
            }
            .gtp-view-roster-btn {
                padding: 4px 10px;
                font-size: 12px;
                cursor: pointer;
                white-space: nowrap;
            }
            .gtp-roster-modal-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.45);
                z-index: 100000;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .gtp-roster-modal-overlay.is-open {
                display: flex;
            }
            .gtp-roster-modal {
                background: #fff;
                border-radius: 8px;
                max-width: 480px;
                width: 100%;
                max-height: 80vh;
                overflow: auto;
                box-shadow: 0 8px 28px rgba(0, 0, 0, 0.25);
                padding: 20px 22px;
            }
            .gtp-roster-modal h3 {
                margin: 0 0 6px;
            }
            .gtp-roster-modal-meta {
                font-size: 13px;
                color: #555;
                margin-bottom: 14px;
            }
            .gtp-roster-modal table {
                width: 100%;
                border-collapse: collapse;
            }
            .gtp-roster-modal th,
            .gtp-roster-modal td {
                border: 1px solid #ddd;
                padding: 6px 8px;
                text-align: left;
            }
            .gtp-roster-modal th {
                background: #f2f2f2;
            }
            .gtp-roster-status-present { color: #1a7f37; font-weight: 600; }
            .gtp-roster-status-absent { color: #b42318; }
            .gtp-roster-modal-actions {
                margin-top: 16px;
                text-align: right;
            }
        </style>

        <div id="gtp-roster-modal-overlay" class="gtp-roster-modal-overlay" aria-hidden="true">
            <div class="gtp-roster-modal" role="dialog" aria-modal="true" aria-labelledby="gtp-roster-modal-title">
                <h3 id="gtp-roster-modal-title">Session Roster</h3>
                <div class="gtp-roster-modal-meta" id="gtp-roster-modal-meta"></div>
                <div id="gtp-roster-modal-body"><p>Loading…</p></div>
                <div class="gtp-roster-modal-actions">
                    <button type="button" class="button" id="gtp-roster-modal-close">Close</button>
                </div>
            </div>
        </div>

        <div id="session-results">
            <?php
            if (isset($_GET['filter_sessions'])) {
                $report_type = $_GET['report_type'] ?? 'payroll';
                $results = [];
                $sessions_table = $wpdb->prefix . 'gtp_sessions';

                if ($report_type === 'payroll') {
                    $start_date = sanitize_text_field($_GET['start_date'] ?? '');
                    $end_date = sanitize_text_field($_GET['end_date'] ?? '');
                    if (!empty($start_date) && !empty($end_date)) {
                        $results = $wpdb->get_results($wpdb->prepare(
                            "SELECT u.first_name, u.last_name, COUNT(*) as session_count
                             FROM $sessions_table s
                             JOIN {$wpdb->prefix}gtp_users u ON s.tutor_username = u.username
                             WHERE s.session_date BETWEEN %s AND %s
                             GROUP BY s.tutor_username
                             ORDER BY u.last_name, u.first_name",
                            $start_date,
                            $end_date
                        ));
                        echo '<table class="spreadsheet-style">';
                        echo '<thead><tr><th>Tutor Name</th><th>Session Count</th></tr></thead><tbody>';
                        foreach ($results as $row) {
                            echo '<tr><td>' . esc_html($row->first_name . ' ' . $row->last_name) . '</td><td>' . esc_html($row->session_count) . '</td></tr>';
                        }
                        echo '</tbody></table>';
                    }
                } else {
                    list($sql, $params) = gtp_session_filter_build_log_query($_GET);
                    $sql .= ' ORDER BY session_date DESC';
                    $results = empty($params)
                        ? $wpdb->get_results($sql)
                        : $wpdb->get_results($wpdb->prepare($sql, $params));

                    echo '<table class="spreadsheet-style">';
                    echo '<thead><tr><th>Date</th><th>Tutor</th><th>School</th><th>Subject</th><th>Teacher</th><th>Teacher Present</th><th>Topic</th><th>Notes</th><th></th></tr></thead><tbody>';
                    foreach ($results as $row) {
                        $teacher_present_label = (isset($row->teacher_present) && (int) $row->teacher_present === 0) ? 'No' : 'Yes';
                        echo '<tr>';
                        echo '<td>' . esc_html($row->session_date) . '</td>';
                        echo '<td>' . esc_html($row->first_name . ' ' . $row->last_name) . '</td>';
                        echo '<td>' . esc_html($row->school) . '</td>';
                        echo '<td>' . esc_html($row->subject) . '</td>';
                        echo '<td>' . esc_html($row->teacher_name) . '</td>';
                        echo '<td>' . esc_html($teacher_present_label) . '</td>';
                        echo '<td>' . esc_html($row->topic) . '</td>';
                        echo '<td>' . esc_html($row->comments ?? '') . '</td>';
                        echo '<td><button type="button" class="button gtp-view-roster-btn" data-session-id="' . (int) $row->id . '">View Roster</button></td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    ?>
                    <script>
                    (function () {
                        const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
                        const overlay = document.getElementById('gtp-roster-modal-overlay');
                        const metaEl = document.getElementById('gtp-roster-modal-meta');
                        const bodyEl = document.getElementById('gtp-roster-modal-body');
                        const closeBtn = document.getElementById('gtp-roster-modal-close');

                        function openModal() {
                            overlay.classList.add('is-open');
                            overlay.setAttribute('aria-hidden', 'false');
                        }

                        function closeModal() {
                            overlay.classList.remove('is-open');
                            overlay.setAttribute('aria-hidden', 'true');
                        }

                        function renderRoster(data) {
                            metaEl.textContent = [
                                data.session_date,
                                data.school,
                                data.subject,
                                data.teacher_name
                            ].filter(Boolean).join(' · ');

                            let html = '';
                            if (data.no_show) {
                                html += '<p><em>This session was logged as a class no-show.</em></p>';
                            }

                            if (!data.students || !data.students.length) {
                                html += '<p>No roster found for this session.</p>';
                                bodyEl.innerHTML = html;
                                return;
                            }

                            html += '<table><thead><tr><th>Student</th><th>Attendance</th></tr></thead><tbody>';
                            data.students.forEach(function (student) {
                                const cls = student.status === 'Present' ? 'gtp-roster-status-present' : 'gtp-roster-status-absent';
                                html += '<tr><td>' + escapeHtml(student.name) + '</td><td class="' + cls + '">' + escapeHtml(student.status) + '</td></tr>';
                            });
                            html += '</tbody></table>';
                            bodyEl.innerHTML = html;
                        }

                        function escapeHtml(str) {
                            return String(str == null ? '' : str)
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;');
                        }

                        document.querySelectorAll('.gtp-view-roster-btn').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                const sessionId = btn.getAttribute('data-session-id');
                                bodyEl.innerHTML = '<p>Loading…</p>';
                                metaEl.textContent = '';
                                openModal();

                                const formData = new FormData();
                                formData.append('action', 'gtp_get_session_roster');
                                formData.append('session_id', sessionId);

                                fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                                    .then(function (res) { return res.json(); })
                                    .then(function (json) {
                                        if (!json || !json.success) {
                                            bodyEl.innerHTML = '<p>' + escapeHtml((json && json.data) || 'Could not load roster.') + '</p>';
                                            return;
                                        }
                                        renderRoster(json.data);
                                    })
                                    .catch(function () {
                                        bodyEl.innerHTML = '<p>Could not load roster.</p>';
                                    });
                            });
                        });

                        closeBtn.addEventListener('click', closeModal);
                        overlay.addEventListener('click', function (e) {
                            if (e.target === overlay) {
                                closeModal();
                            }
                        });
                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
                                closeModal();
                            }
                        });
                    })();
                    </script>
                    <?php
                }

                if (empty($results)) {
                    echo '<p>No sessions found for the selected criteria.</p>';
                }
            }
            ?>
        </div>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_session_filter', 'gtp_session_filter_shortcode');
