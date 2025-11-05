<?php
function gtp_session_filter_shortcode() {
    global $wpdb;

    if (isset($_GET['export_csv'])) {
        $report_type = $_GET['report_type'];
        $results = [];
        $sessions_table = $wpdb->prefix . 'gtp_sessions';
        $filename = "session_export_" . date('Y-m-d') . ".csv";

        if ($report_type === 'payroll') {
            $start_date = sanitize_text_field($_GET['start_date']);
            $end_date = sanitize_text_field($_GET['end_date']);
            if (!empty($start_date) && !empty($end_date)) {
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT first_name, last_name, COUNT(*) as session_count FROM $sessions_table WHERE session_date BETWEEN %s AND %s GROUP BY tutor_username ORDER BY last_name, first_name",
                    $start_date, $end_date
                ), ARRAY_A);
            }
        } else { // Session Log
            $sql = "SELECT * FROM $sessions_table WHERE 1=1";
            $params = [];

            if (!empty($_GET['start_date'])) {
                $sql .= " AND session_date >= %s";
                $params[] = sanitize_text_field($_GET['start_date']);
            }
            if (!empty($_GET['end_date'])) {
                $sql .= " AND session_date <= %s";
                $params[] = sanitize_text_field($_GET['end_date']);
            }
            if (!empty($_GET['school'])) {
                $sql .= " AND school = %s";
                $params[] = sanitize_text_field($_GET['school']);
            }
            if (!empty($_GET['tutor_id'])) {
                $tutor_user = $wpdb->get_row($wpdb->prepare("SELECT username FROM {$wpdb->prefix}gtp_users WHERE id = %d", intval($_GET['tutor_id'])));
                if ($tutor_user) {
                    $sql .= " AND tutor_username = %s";
                    $params[] = $tutor_user->username;
                }
            }
            if (!empty($_GET['classroom_id'])) {
                $classroom_details = $wpdb->get_row($wpdb->prepare("SELECT school, subject, teacher_first_name, teacher_last_name FROM {$wpdb->prefix}gtp_classrooms WHERE id = %d", intval($_GET['classroom_id'])));
                if ($classroom_details) {
                    $teacher_name = $classroom_details->teacher_first_name . ' ' . $classroom_details->teacher_last_name;
                    $sql .= " AND school = %s AND subject = %s AND teacher_name = %s";
                    $params[] = $classroom_details->school;
                    $params[] = $classroom_details->subject;
                    $params[] = $teacher_name;
                }
            }

            $sql .= " ORDER BY session_date DESC";
            $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');

        if (!empty($results)) {
            fputcsv($output, array_keys($results[0])); // Add header row
            foreach ($results as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }


    // Get all schools and classrooms for dropdowns
    $schools = $wpdb->get_col("SELECT DISTINCT school FROM {$wpdb->prefix}gtp_classrooms ORDER BY school ASC");
    $classrooms = $wpdb->get_results("SELECT id, school, subject, teacher_first_name, teacher_last_name FROM {$wpdb->prefix}gtp_classrooms ORDER BY school, subject ASC");
    $tutors = $wpdb->get_results("SELECT id, first_name, last_name FROM {$wpdb->prefix}gtp_users WHERE role = 'tutor' ORDER BY last_name, first_name ASC");

    ob_start();
    ?>
    <div style="max-width: 800px; margin: 20px auto; padding: 20px; background: #f9f9f9; border-radius: 8px;">
        <h2>Filter TA Sessions</h2>

        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"> <!-- Keep page context -->

        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">

            <fieldset style="margin-bottom: 20px;">
                <legend><h3>Select Report Type</h3></legend>
                <p>
                    <label><input type="radio" name="report_type" value="payroll" <?php checked(($_GET['report_type'] ?? 'payroll') === 'payroll'); ?>> <strong>Payroll Report</strong> (Total sessions per tutor)</label><br>
                    <label><input type="radio" name="report_type" value="session_log" <?php checked(($_GET['report_type'] ?? '') === 'session_log'); ?>> <strong>Session Log Search</strong> (Detailed list of sessions)</label><br>
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
                    <tr class="school-filter">
                        <th scope="row"><label for="school">School</label></th>
                        <td>
                            <select name="school">
                                <option value="">-- All Schools --</option>
                                <?php foreach ($schools as $school): ?>
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
                                <?php foreach ($tutors as $tutor): ?>
                                    <option value="<?php echo $tutor->id; ?>" <?php selected(isset($_GET['tutor_id']) ? $_GET['tutor_id'] : '', $tutor->id); ?>><?php echo esc_html("{$tutor->first_name} {$tutor->last_name}"); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr class="classroom-filter">
                        <th scope="row"><label for="classroom_id">Classroom</label></th>
                        <td>
                            <select name="classroom_id">
                                <option value="">-- All Classrooms --</option>
                                <?php foreach ($classrooms as $classroom): ?>
                                    <option value="<?php echo $classroom->id; ?>" <?php selected(isset($_GET['classroom_id']) ? $_GET['classroom_id'] : '', $classroom->id); ?>>
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
                const dateFilter = document.querySelector('.date-range-filter');
                const schoolFilter = document.querySelector('.school-filter');
                const tutorFilter = document.querySelector('.tutor-filter');
                const classroomFilter = document.querySelector('.classroom-filter');

                function toggleFilters() {
                    const selected = document.querySelector('input[name="report_type"]:checked').value;
                    
                    schoolFilter.style.display = 'none';
                    tutorFilter.style.display = 'none';
                    classroomFilter.style.display = 'none';

                    if (selected === 'session_log') {
                        schoolFilter.style.display = '';
                        tutorFilter.style.display = '';
                        classroomFilter.style.display = '';
                    } else { // Payroll
                        dateFilter.style.display = '';
                    }
                }

                reportTypeRadios.forEach(radio => radio.addEventListener('change', toggleFilters));
                toggleFilters(); // Run on page load
            });
        </script>

        <style>
            .spreadsheet-style, .spreadsheet-style th, .spreadsheet-style td {
                border: 1px solid #ccc;
                border-collapse: collapse;
                padding: 8px;
            }
            .spreadsheet-style th { background-color: #f2f2f2; }
        </style>
        <div id="session-results">
            <?php
            if (isset($_GET['filter_sessions'])) {
                $report_type = $_GET['report_type'];
                $results = [];
                $sessions_table = $wpdb->prefix . 'gtp_sessions';

                if ($report_type === 'payroll') {
                    $start_date = sanitize_text_field($_GET['start_date']);
                    $end_date = sanitize_text_field($_GET['end_date']);
                    if (!empty($start_date) && !empty($end_date)) {
                        $results = $wpdb->get_results($wpdb->prepare(
                            "SELECT tutor_username, first_name, last_name, COUNT(*) as session_count FROM $sessions_table WHERE session_date BETWEEN %s AND %s GROUP BY tutor_username ORDER BY last_name, first_name",
                            $start_date, $end_date
                        ));
                        echo '<table class="wp-list-table widefat fixed striped posts spreadsheet-style">';
                        echo '<thead><tr><th>Tutor Name</th><th>Session Count</th></tr></thead><tbody>';
                        foreach ($results as $row) {
                            echo "<tr><td>{$row->first_name} {$row->last_name}</td><td>{$row->session_count}</td></tr>";
                        }
                        echo '</tbody></table>';
                    }
                } else { // Session Log
                    $sql = "SELECT * FROM $sessions_table WHERE 1=1";
                    $params = [];

                    if (!empty($_GET['start_date'])) {
                        $sql .= " AND session_date >= %s";
                        $params[] = sanitize_text_field($_GET['start_date']);
                    }
                    if (!empty($_GET['end_date'])) {
                        $sql .= " AND session_date <= %s";
                        $params[] = sanitize_text_field($_GET['end_date']);
                    }
                    if (!empty($_GET['school'])) {
                        $sql .= " AND school = %s";
                        $params[] = sanitize_text_field($_GET['school']);
                    }
                    if (!empty($_GET['tutor_id'])) {
                        // Need to get username from id
                        $tutor_user = $wpdb->get_row($wpdb->prepare("SELECT username FROM {$wpdb->prefix}gtp_users WHERE id = %d", intval($_GET['tutor_id'])));
                        if ($tutor_user) {
                            $sql .= " AND tutor_username = %s";
                            $params[] = $tutor_user->username;
                        }
                    }
                    if (!empty($_GET['classroom_id'])) {
                        $classroom_details = $wpdb->get_row($wpdb->prepare("SELECT school, subject, teacher_first_name, teacher_last_name FROM {$wpdb->prefix}gtp_classrooms WHERE id = %d", intval($_GET['classroom_id'])));
                        if ($classroom_details) {
                            $teacher_name = $classroom_details->teacher_first_name . ' ' . $classroom_details->teacher_last_name;
                            $sql .= " AND school = %s AND subject = %s AND teacher_name = %s";
                            $params[] = $classroom_details->school;
                            $params[] = $classroom_details->subject;
                            $params[] = $teacher_name;
                        }
                    }

                    $sql .= " ORDER BY session_date DESC";
                    $results = $wpdb->get_results($wpdb->prepare($sql, $params));

                    echo '<table class="wp-list-table widefat fixed striped posts spreadsheet-style">';
                    echo '<thead><tr><th>Date</th><th>Tutor</th><th>School</th><th>Subject</th><th>Teacher</th><th>Topic</th></tr></thead><tbody>';
                    foreach ($results as $row) {
                        echo "<tr><td>{$row->session_date}</td><td>{$row->first_name} {$row->last_name}</td><td>{$row->school}</td><td>{$row->subject}</td><td>{$row->teacher_name}</td><td>{$row->topic}</td></tr>";
                    }
                    echo '</tbody></table>';
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
