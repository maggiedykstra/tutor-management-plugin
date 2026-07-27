<?php
/**
 * Admin: manage people directory + unassigned tutors.
 */

function gtp_preference_level_labels() {
    return [
        'cannot_tutor' => 'Cannot Tutor',
        'willing_to_tutor' => 'Willing to Tutor',
        'excited_to_tutor' => 'Would be Excited to Tutor',
    ];
}

function gtp_preference_label($key) {
    $labels = gtp_preference_level_labels();
    $key = (string) $key;
    return $labels[$key] ?? $key;
}

function gtp_format_subject_preferences_list($json_or_array) {
    $prefs = is_array($json_or_array) ? $json_or_array : json_decode((string) $json_or_array, true);
    if (!is_array($prefs) || empty($prefs)) {
        return '<p class="gtp-people-muted">No subject preferences listed.</p>';
    }

    $html = '<ul class="gtp-people-prefs">';
    foreach ($prefs as $subject => $level) {
        $html .= '<li><strong>' . esc_html($subject) . ':</strong> ' . esc_html(gtp_preference_label($level)) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

/**
 * Unassigned tutors with expandable preference details.
 */
function gtp_unassigned_tutors_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;

    $semester_id = gtp_get_working_semester_id();
    $tutors = $wpdb->get_results($wpdb->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.username, u.school, u.subject_preferences, u.headshot_url
         FROM {$wpdb->prefix}gtp_users u
         WHERE u.role = 'tutor' AND u.validated = 1
           AND u.id NOT IN (
             SELECT a.tutor_id
             FROM {$wpdb->prefix}gtp_class_assignments a
             INNER JOIN {$wpdb->prefix}gtp_classrooms c ON c.id = a.classroom_id
             WHERE c.semester_id = %d
           )
         ORDER BY u.last_name ASC, u.first_name ASC",
        $semester_id
    ));

    ob_start();
    ?>
    <div class="gtp-home gtp-people-page">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Unassigned tutors</h1>
        <p class="gtp-people-muted">Validated tutors with no classroom in <strong><?php echo esc_html(gtp_semester_label(gtp_get_semester($semester_id))); ?></strong>. Open a row to see subject preferences.</p>

        <?php if (empty($tutors)) : ?>
            <p>All validated tutors currently have at least one classroom assignment.</p>
        <?php else : ?>
            <div class="gtp-unassigned-list">
                <?php foreach ($tutors as $tutor) :
                    $name = trim($tutor->first_name . ' ' . $tutor->last_name);
                    ?>
                    <details class="gtp-unassigned-item">
                        <summary>
                            <span class="gtp-unassigned-summary-main">
                                <?php echo gtp_user_avatar_html($tutor, 'gtp-home-avatar gtp-unassigned-avatar'); ?>
                                <span>
                                    <strong><?php echo esc_html($name); ?></strong>
                                    <span class="gtp-people-muted">@<?php echo esc_html($tutor->username); ?></span>
                                </span>
                            </span>
                            <span class="gtp-unassigned-chevron" aria-hidden="true">▾</span>
                        </summary>
                        <div class="gtp-unassigned-body">
                            <p><strong>Email:</strong>
                                <?php if (!empty($tutor->email)) : ?>
                                    <a href="mailto:<?php echo esc_attr($tutor->email); ?>"><?php echo esc_html($tutor->email); ?></a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </p>
                            <p><strong>School (profile):</strong> <?php echo esc_html($tutor->school ?: '—'); ?></p>
                            <p><strong>Subject preferences:</strong></p>
                            <?php echo gtp_format_subject_preferences_list($tutor->subject_preferences); ?>
                            <p style="margin-top:12px;">
                                <a href="<?php echo esc_url(site_url('/index.php/edit-classrooms/')); ?>">Assign on Edit Classrooms →</a>
                            </p>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_unassigned_tutors', 'gtp_unassigned_tutors_shortcode');

/**
 * Directory of all users with search + filters.
 */
function gtp_manage_people_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;
    $users_table = $wpdb->prefix . 'gtp_users';
    $assign_table = $wpdb->prefix . 'gtp_class_assignments';
    $class_table = $wpdb->prefix . 'gtp_classrooms';

    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    $role = isset($_GET['role']) ? sanitize_text_field(wp_unslash($_GET['role'])) : '';
    $subject = isset($_GET['subject']) ? sanitize_text_field(wp_unslash($_GET['subject'])) : '';
    $school = isset($_GET['school']) ? sanitize_text_field(wp_unslash($_GET['school'])) : '';

    $allowed_roles = ['admin', 'tutor'];
    if ($role !== '' && !in_array($role, $allowed_roles, true)) {
        $role = '';
    }

    $subjects = $wpdb->get_col("SELECT DISTINCT subject FROM $class_table WHERE subject IS NOT NULL AND subject <> '' ORDER BY subject ASC");
    $schools = $wpdb->get_col("SELECT DISTINCT school FROM $class_table WHERE school IS NOT NULL AND school <> '' ORDER BY school ASC");

    $sql = "SELECT DISTINCT u.id, u.username, u.email, u.first_name, u.last_name, u.role, u.validated, u.school, u.subject_preferences, u.headshot_url
            FROM $users_table u";
    $joins = [];
    $wheres = ["u.role IN ('admin', 'tutor')", 'u.validated = 1'];
    $params = [];

    if ($subject !== '' || $school !== '') {
        $joins[] = "INNER JOIN $assign_table a ON a.tutor_id = u.id";
        $joins[] = "INNER JOIN $class_table c ON c.id = a.classroom_id";
    }

    if ($role !== '') {
        $wheres[] = 'u.role = %s';
        $params[] = $role;
    }

    if ($q !== '') {
        $like = '%' . $wpdb->esc_like($q) . '%';
        $wheres[] = '(u.first_name LIKE %s OR u.last_name LIKE %s OR CONCAT(u.first_name, \' \', u.last_name) LIKE %s OR u.username LIKE %s OR u.email LIKE %s)';
        array_push($params, $like, $like, $like, $like, $like);
    }

    if ($subject !== '') {
        $wheres[] = 'c.subject = %s';
        $params[] = $subject;
    }

    if ($school !== '') {
        $wheres[] = 'c.school = %s';
        $params[] = $school;
    }

    $sql .= $joins ? (' ' . implode(' ', $joins)) : '';
    $sql .= ' WHERE ' . implode(' AND ', $wheres);
    $sql .= ' ORDER BY u.role ASC, u.last_name ASC, u.first_name ASC';

    if (!empty($params)) {
        $users = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    } else {
        $users = $wpdb->get_results($sql);
    }

    // Prefetch assignment summaries for listed users
    $assignment_map = [];
    if (!empty($users)) {
        $ids = array_map(static function ($u) {
            return (int) $u->id;
        }, $users);
        $ph = implode(',', array_fill(0, count($ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.tutor_id, c.subject, c.school
             FROM $assign_table a
             INNER JOIN $class_table c ON c.id = a.classroom_id
             WHERE a.tutor_id IN ($ph)
             ORDER BY c.school ASC, c.subject ASC",
            ...$ids
        ));
        foreach ($rows as $row) {
            $tid = (int) $row->tutor_id;
            if (!isset($assignment_map[$tid])) {
                $assignment_map[$tid] = [];
            }
            $assignment_map[$tid][] = $row->subject . ' · ' . $row->school;
        }
    }

    $action_url = site_url('/index.php/manage-people/');

    ob_start();
    ?>
    <div class="gtp-home gtp-people-page">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Manage people</h1>
        <p class="gtp-people-muted">Search and filter all validated admins and tutors.</p>

        <form class="gtp-people-filters" method="get" action="<?php echo esc_url($action_url); ?>">
            <label>
                <span>Search name</span>
                <input type="search" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Name, username, or email">
            </label>
            <label>
                <span>User type</span>
                <select name="role">
                    <option value="">All</option>
                    <option value="admin" <?php selected($role, 'admin'); ?>>Admin</option>
                    <option value="tutor" <?php selected($role, 'tutor'); ?>>Tutor</option>
                </select>
            </label>
            <label>
                <span>Subject taught</span>
                <select name="subject">
                    <option value="">All</option>
                    <?php foreach ($subjects as $subj) : ?>
                        <option value="<?php echo esc_attr($subj); ?>" <?php selected($subject, $subj); ?>><?php echo esc_html($subj); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>School</span>
                <select name="school">
                    <option value="">All</option>
                    <?php foreach ($schools as $sch) : ?>
                        <option value="<?php echo esc_attr($sch); ?>" <?php selected($school, $sch); ?>><?php echo esc_html($sch); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="gtp-people-filter-actions">
                <button type="submit" class="button button-primary">Apply</button>
                <a class="button" href="<?php echo esc_url($action_url); ?>">Clear</a>
            </div>
        </form>

        <p class="gtp-people-muted"><?php echo count($users); ?> user<?php echo count($users) === 1 ? '' : 's'; ?></p>

        <?php if (empty($users)) : ?>
            <p>No users match these filters.</p>
        <?php else : ?>
            <div class="gtp-people-table-wrap">
                <table class="gtp-people-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Email</th>
                            <th>Classes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user) :
                            $name = trim($user->first_name . ' ' . $user->last_name);
                            $classes = $assignment_map[(int) $user->id] ?? [];
                            ?>
                            <tr>
                                <td>
                                    <div class="gtp-people-name-cell">
                                        <?php echo gtp_user_avatar_html($user, 'gtp-home-avatar gtp-people-avatar'); ?>
                                        <div>
                                            <strong><?php echo esc_html($name); ?></strong>
                                            <div class="gtp-people-muted">@<?php echo esc_html($user->username); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo esc_html(ucfirst($user->role)); ?></td>
                                <td>
                                    <?php if (!empty($user->email)) : ?>
                                        <a href="mailto:<?php echo esc_attr($user->email); ?>"><?php echo esc_html($user->email); ?></a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (empty($classes)) : ?>
                                        <span class="gtp-people-muted"><?php echo $user->role === 'tutor' ? 'Unassigned' : '—'; ?></span>
                                    <?php else : ?>
                                        <?php echo esc_html(implode('; ', $classes)); ?>
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
add_shortcode('gtp_manage_people', 'gtp_manage_people_shortcode');
