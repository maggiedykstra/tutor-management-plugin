<?php
/**
 * School years + semesters: global live semester, per-admin working semester,
 * copy classrooms on create, and Reports exports.
 */

function gtp_create_school_years_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_school_years';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        label varchar(100) NOT NULL,
        start_date date DEFAULT NULL,
        end_date date DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset;";
    dbDelta($sql);
}

function gtp_create_semesters_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_semesters';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        school_year_id mediumint(9) NOT NULL,
        label varchar(100) NOT NULL,
        start_date date DEFAULT NULL,
        end_date date DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'planning',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY school_year_id (school_year_id),
        KEY status (status)
    ) $charset;";
    dbDelta($sql);
}

/**
 * Ensure semester_id columns exist and seed a default live semester for legacy rows.
 */
function gtp_migrate_semester_columns() {
    global $wpdb;

    $tables = [
        $wpdb->prefix . 'gtp_classrooms' => 'semester_id',
        $wpdb->prefix . 'gtp_sessions' => 'semester_id',
        $wpdb->prefix . 'gtp_monthly_checkins' => 'semester_id',
    ];

    foreach ($tables as $table => $col) {
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) {
            continue;
        }
        if (!$wpdb->get_var("SHOW COLUMNS FROM $table LIKE '$col'")) {
            $wpdb->query("ALTER TABLE $table ADD $col mediumint(9) DEFAULT NULL");
            $wpdb->query("ALTER TABLE $table ADD KEY $col ($col)");
        }
    }

    gtp_bootstrap_default_semester();
}

function gtp_bootstrap_default_semester() {
    global $wpdb;
    $semesters = $wpdb->prefix . 'gtp_semesters';
    $years = $wpdb->prefix . 'gtp_school_years';

    if (!(int) $wpdb->get_var("SELECT COUNT(*) FROM $semesters")) {
        $now = current_time('mysql');
        $year_label = (int) date('n') >= 7
            ? date('Y') . '–' . (date('Y') + 1)
            : (date('Y') - 1) . '–' . date('Y');

        $wpdb->insert($years, [
            'label' => $year_label,
            'start_date' => null,
            'end_date' => null,
            'created_at' => $now,
        ]);
        $year_id = (int) $wpdb->insert_id;

        $wpdb->insert($semesters, [
            'school_year_id' => $year_id,
            'label' => 'Current',
            'start_date' => null,
            'end_date' => null,
            'status' => 'live',
            'created_at' => $now,
        ]);
    }

    $live_id = (int) $wpdb->get_var("SELECT id FROM $semesters WHERE status = 'live' ORDER BY id DESC LIMIT 1");
    if (!$live_id) {
        $live_id = (int) $wpdb->get_var("SELECT id FROM $semesters ORDER BY id ASC LIMIT 1");
        if ($live_id) {
            $wpdb->update($semesters, ['status' => 'live'], ['id' => $live_id]);
        }
    }
    if (!$live_id) {
        return;
    }

    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}gtp_classrooms SET semester_id = %d WHERE semester_id IS NULL OR semester_id = 0",
        $live_id
    ));
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}gtp_sessions SET semester_id = %d WHERE semester_id IS NULL OR semester_id = 0",
        $live_id
    ));
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}gtp_monthly_checkins SET semester_id = %d WHERE semester_id IS NULL OR semester_id = 0",
        $live_id
    ));
}

/* -------------------------------------------------------------------------- */
/* Context                                                                    */
/* -------------------------------------------------------------------------- */

function gtp_get_semesters($status = null) {
    global $wpdb;
    $sql = "SELECT s.*, y.label AS year_label
            FROM {$wpdb->prefix}gtp_semesters s
            INNER JOIN {$wpdb->prefix}gtp_school_years y ON y.id = s.school_year_id";
    $params = [];
    if ($status !== null && $status !== '') {
        $sql .= ' WHERE s.status = %s';
        $params[] = $status;
    }
    $sql .= ' ORDER BY y.label DESC, s.id DESC';
    return $params ? $wpdb->get_results($wpdb->prepare($sql, ...$params)) : $wpdb->get_results($sql);
}

function gtp_get_semester($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT s.*, y.label AS year_label
         FROM {$wpdb->prefix}gtp_semesters s
         INNER JOIN {$wpdb->prefix}gtp_school_years y ON y.id = s.school_year_id
         WHERE s.id = %d",
        (int) $id
    ));
}

function gtp_get_school_years() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}gtp_school_years ORDER BY label DESC, id DESC"
    );
}

function gtp_semester_label($semester) {
    if (!$semester) {
        return '—';
    }
    $year = $semester->year_label ?? '';
    $label = $semester->label ?? '';
    return trim($year . ' · ' . $label, ' ·');
}

function gtp_get_live_semester_id() {
    global $wpdb;
    $id = (int) $wpdb->get_var(
        "SELECT id FROM {$wpdb->prefix}gtp_semesters WHERE status = 'live' ORDER BY id DESC LIMIT 1"
    );
    if (!$id) {
        gtp_bootstrap_default_semester();
        $id = (int) $wpdb->get_var(
            "SELECT id FROM {$wpdb->prefix}gtp_semesters WHERE status = 'live' ORDER BY id DESC LIMIT 1"
        );
    }
    return $id;
}

function gtp_get_working_semester_id() {
    if (!empty($_SESSION['gtp_working_semester_id'])) {
        $id = (int) $_SESSION['gtp_working_semester_id'];
        if (gtp_get_semester($id)) {
            return $id;
        }
    }
    $live = gtp_get_live_semester_id();
    $_SESSION['gtp_working_semester_id'] = $live;
    return $live;
}

function gtp_set_working_semester_id($id) {
    $id = (int) $id;
    if ($id > 0 && gtp_get_semester($id)) {
        $_SESSION['gtp_working_semester_id'] = $id;
        return true;
    }
    return false;
}

/** Semester scope for the current user (admin = working, tutor = live). */
function gtp_context_semester_id() {
    $role = $_SESSION['gtp_user']['role'] ?? '';
    if ($role === 'admin') {
        return gtp_get_working_semester_id();
    }
    return gtp_get_live_semester_id();
}

add_action('init', 'gtp_handle_working_semester_switch', 3);
function gtp_handle_working_semester_switch() {
    if (!isset($_POST['gtp_set_working_semester'])) {
        return;
    }
    if (empty($_SESSION['gtp_user']) || ($_SESSION['gtp_user']['role'] ?? '') !== 'admin') {
        return;
    }
    if (!isset($_POST['gtp_semester_nonce']) || !wp_verify_nonce($_POST['gtp_semester_nonce'], 'gtp_set_working_semester')) {
        return;
    }
    gtp_set_working_semester_id((int) ($_POST['working_semester_id'] ?? 0));

    $redirect = wp_get_referer();
    if (!$redirect) {
        $redirect = site_url('/index.php/admin-dashboard/');
    }
    wp_safe_redirect($redirect);
    exit;
}

/* -------------------------------------------------------------------------- */
/* Create / copy / make live                                                  */
/* -------------------------------------------------------------------------- */

function gtp_copy_classrooms_between_semesters($from_semester_id, $to_semester_id, $copy_assignments = false, $copy_rosters = false) {
    global $wpdb;
    $from_semester_id = (int) $from_semester_id;
    $to_semester_id = (int) $to_semester_id;
    if ($from_semester_id <= 0 || $to_semester_id <= 0 || $from_semester_id === $to_semester_id) {
        return 0;
    }

    $classrooms = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gtp_classrooms WHERE semester_id = %d ORDER BY id ASC",
        $from_semester_id
    ));
    $copied = 0;

    foreach ($classrooms as $c) {
        $wpdb->insert($wpdb->prefix . 'gtp_classrooms', [
            'school' => $c->school,
            'subject' => $c->subject,
            'teacher_first_name' => $c->teacher_first_name,
            'teacher_last_name' => $c->teacher_last_name,
            'teacher_email' => $c->teacher_email,
            'teacher_phone' => $c->teacher_phone,
            'time_slot' => $c->time_slot,
            'start_time' => $c->start_time,
            'end_time' => $c->end_time,
            'meeting_days' => $c->meeting_days ?? null,
            'is_block' => !empty($c->is_block) ? 1 : 0,
            'zoom_link' => $c->zoom_link,
            'semester_id' => $to_semester_id,
        ]);
        $new_id = (int) $wpdb->insert_id;
        if (!$new_id) {
            continue;
        }
        $copied++;

        if ($copy_assignments) {
            $assignments = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gtp_class_assignments WHERE classroom_id = %d",
                (int) $c->id
            ));
            foreach ($assignments as $a) {
                $wpdb->insert($wpdb->prefix . 'gtp_class_assignments', [
                    'tutor_id' => $a->tutor_id,
                    'classroom_id' => $new_id,
                    'first_taught' => null,
                ]);
            }
        }

        if ($copy_rosters) {
            $students = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gtp_students
                 WHERE classroom_id = %d AND (removed_at IS NULL OR removed_at = '')",
                (int) $c->id
            ));
            foreach ($students as $s) {
                $wpdb->insert($wpdb->prefix . 'gtp_students', [
                    'first_name' => $s->first_name,
                    'last_name' => $s->last_name,
                    'student_name' => $s->student_name,
                    'classroom_id' => $new_id,
                    'date_added' => current_time('mysql'),
                ]);
            }
        }
    }

    return $copied;
}

function gtp_make_semester_live($semester_id) {
    global $wpdb;
    $semester_id = (int) $semester_id;
    if (!$semester_id || !gtp_get_semester($semester_id)) {
        return false;
    }
    $table = $wpdb->prefix . 'gtp_semesters';
    // Close previous live semester(s)
    $wpdb->query("UPDATE $table SET status = 'closed' WHERE status = 'live'");
    $wpdb->update($table, ['status' => 'live'], ['id' => $semester_id]);
    return true;
}

/* -------------------------------------------------------------------------- */
/* Admin top bar switcher                                                     */
/* -------------------------------------------------------------------------- */

add_action('wp_footer', 'gtp_render_semester_switcher', 5);
function gtp_render_semester_switcher() {
    if (is_admin() || empty($_SESSION['gtp_user']) || ($_SESSION['gtp_user']['role'] ?? '') !== 'admin') {
        return;
    }
    if (gtp_is_auth_page()) {
        return;
    }

    $semesters = gtp_get_semesters();
    if (empty($semesters)) {
        return;
    }
    $working = gtp_get_working_semester_id();
    $manage_url = site_url('/index.php/manage-semesters/');
    $logout_url = site_url('/index.php/Welcome-to-GTP/?gtp_logout=1');
    ?>
    <div class="gtp-top-chrome gtp-semester-bar">
        <div class="gtp-top-chrome-inner">
            <div class="gtp-top-chrome-left">
                <form method="post" class="gtp-semester-switcher">
                    <?php wp_nonce_field('gtp_set_working_semester', 'gtp_semester_nonce'); ?>
                    <label for="gtp-working-semester">Working semester</label>
                    <select id="gtp-working-semester" name="working_semester_id" onchange="this.form.submit()">
                        <?php foreach ($semesters as $s) :
                            $status = $s->status === 'live' ? ' (live)' : ($s->status === 'planning' ? ' (planning)' : '');
                            ?>
                            <option value="<?php echo (int) $s->id; ?>" <?php selected($working, (int) $s->id); ?>>
                                <?php echo esc_html(gtp_semester_label($s) . $status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="gtp_set_working_semester" value="1">
                </form>
                <a class="gtp-semester-manage" href="<?php echo esc_url($manage_url); ?>">Manage</a>
            </div>
            <div class="gtp-top-chrome-right">
                <?php echo gtp_bug_report_top_link_html(); ?>
                <a class="gtp-back-link gtp-top-logout" href="<?php echo esc_url($logout_url); ?>">Logout</a>
            </div>
        </div>
    </div>
    <script>
    document.body.classList.add('has-gtp-semester-bar', 'has-gtp-logout', 'has-gtp-top-chrome');
    (function () {
        var sel = document.getElementById('gtp-working-semester');
        if (!sel) return;
        function sizeSelect() {
            var opt = sel.options[sel.selectedIndex];
            if (!opt) return;
            var styles = window.getComputedStyle(sel);
            var probe = document.createElement('span');
            probe.textContent = opt.text;
            probe.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap;'
                + 'font:' + styles.font + ';letter-spacing:' + styles.letterSpacing + ';';
            document.body.appendChild(probe);
            var pad = parseFloat(styles.getPropertyValue('--gtp-sem-pad')) || 6;
            var chevron = parseFloat(styles.getPropertyValue('--gtp-sem-chevron')) || 14;
            var border = (parseFloat(styles.borderLeftWidth) || 0) + (parseFloat(styles.borderRightWidth) || 0);
            sel.style.width = Math.ceil(probe.offsetWidth + pad + pad + chevron + border) + 'px';
            document.body.removeChild(probe);
        }
        sizeSelect();
        sel.addEventListener('change', sizeSelect);
    })();
    </script>
    <?php
}

/* -------------------------------------------------------------------------- */
/* Manage semesters page                                                      */
/* -------------------------------------------------------------------------- */

function gtp_manage_semesters_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;
    $message = '';
    $years_table = $wpdb->prefix . 'gtp_school_years';
    $sem_table = $wpdb->prefix . 'gtp_semesters';
    $now = current_time('mysql');

    if (isset($_POST['gtp_create_semester']) && check_admin_referer('gtp_manage_semesters', 'gtp_sem_nonce')) {
        $year_mode = sanitize_text_field($_POST['year_mode'] ?? 'existing');
        $year_id = (int) ($_POST['school_year_id'] ?? 0);
        $new_year_label = sanitize_text_field(wp_unslash($_POST['new_year_label'] ?? ''));
        $label = sanitize_text_field(wp_unslash($_POST['semester_label'] ?? ''));
        $copy_from = (int) ($_POST['copy_from_semester_id'] ?? 0);
        $copy_assignments = !empty($_POST['copy_assignments']);
        $copy_rosters = !empty($_POST['copy_rosters']);
        $set_working = !empty($_POST['set_as_working']);

        if ($year_mode === 'new') {
            if ($new_year_label === '') {
                $message = '<p class="gtp-checkin-msg is-error gtp-persist">Enter a school year label (e.g. 2026–2027).</p>';
            } else {
                $wpdb->insert($years_table, [
                    'label' => $new_year_label,
                    'created_at' => $now,
                ]);
                $year_id = (int) $wpdb->insert_id;
            }
        }

        if ($message === '' && $year_id <= 0) {
            $message = '<p class="gtp-checkin-msg is-error gtp-persist">Select or create a school year.</p>';
        } elseif ($message === '' && $label === '') {
            $message = '<p class="gtp-checkin-msg is-error gtp-persist">Enter a semester name (e.g. Fall).</p>';
        } elseif ($message === '') {
            $wpdb->insert($sem_table, [
                'school_year_id' => $year_id,
                'label' => $label,
                'status' => 'planning',
                'created_at' => $now,
            ]);
            $new_id = (int) $wpdb->insert_id;
            $copied = 0;
            if ($new_id && $copy_from > 0) {
                $copied = gtp_copy_classrooms_between_semesters($copy_from, $new_id, $copy_assignments, $copy_rosters);
            }
            if ($new_id && $set_working) {
                gtp_set_working_semester_id($new_id);
            }
            $message = '<p class="gtp-checkin-msg is-success">Semester created'
                . ($copied ? (' · copied ' . (int) $copied . ' class' . ($copied === 1 ? '' : 'es')) : '')
                . '.</p>';
        }
    }

    if (isset($_POST['gtp_make_live']) && check_admin_referer('gtp_manage_semesters', 'gtp_sem_nonce')) {
        $id = (int) ($_POST['semester_id'] ?? 0);
        if (gtp_make_semester_live($id)) {
            gtp_set_working_semester_id($id);
            $message = '<p class="gtp-checkin-msg is-success">Semester is now live for tutors. Previous live semester was closed.</p>';
        }
    }

    if (isset($_POST['gtp_set_working_from_list']) && check_admin_referer('gtp_manage_semesters', 'gtp_sem_nonce')) {
        $id = (int) ($_POST['semester_id'] ?? 0);
        if (gtp_set_working_semester_id($id)) {
            $message = '<p class="gtp-checkin-msg is-success">Working semester updated.</p>';
        }
    }

    $years = gtp_get_school_years();
    $semesters = gtp_get_semesters();
    $working = gtp_get_working_semester_id();
    $live = gtp_get_live_semester_id();

    ob_start();
    ?>
    <div class="gtp-home gtp-page">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Manage semesters</h1>
        <p class="gtp-page-intro">Create upcoming semesters in planning mode, copy class lists from a prior term, then make one live when tutors should switch.</p>
        <?php echo $message; ?>

        <section class="gtp-resources-admin-box" style="margin-bottom:24px;">
            <h3>Create semester</h3>
            <form method="post" class="gtp-resources-form">
                <?php wp_nonce_field('gtp_manage_semesters', 'gtp_sem_nonce'); ?>

                <label>
                    <span>School year</span>
                    <select name="year_mode" id="gtp-year-mode">
                        <option value="existing">Use existing year</option>
                        <option value="new">Create new year</option>
                    </select>
                </label>
                <label id="gtp-existing-year-wrap">
                    <span>Existing year</span>
                    <select name="school_year_id">
                        <?php if (empty($years)) : ?>
                            <option value="0">— none yet —</option>
                        <?php else : ?>
                            <?php foreach ($years as $y) : ?>
                                <option value="<?php echo (int) $y->id; ?>"><?php echo esc_html($y->label); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </label>
                <label id="gtp-new-year-wrap" hidden>
                    <span>New year label</span>
                    <input type="text" name="new_year_label" placeholder="2026–2027">
                </label>

                <label>
                    <span>Semester name</span>
                    <input type="text" name="semester_label" required placeholder="Fall">
                </label>

                <label>
                    <span>Copy classrooms from</span>
                    <select name="copy_from_semester_id">
                        <option value="0">— Don’t copy —</option>
                        <?php foreach ($semesters as $s) : ?>
                            <option value="<?php echo (int) $s->id; ?>"><?php echo esc_html(gtp_semester_label($s)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="gtp-checkin-filter-check" style="flex-direction:row;align-items:center;">
                    <input type="checkbox" name="copy_assignments" value="1">
                    Also copy tutor assignments
                </label>
                <label class="gtp-checkin-filter-check" style="flex-direction:row;align-items:center;">
                    <input type="checkbox" name="copy_rosters" value="1">
                    Also copy student rosters
                </label>
                <label class="gtp-checkin-filter-check" style="flex-direction:row;align-items:center;">
                    <input type="checkbox" name="set_as_working" value="1" checked>
                    Set as my working semester
                </label>

                <div class="gtp-resources-form-actions">
                    <button type="submit" name="gtp_create_semester" value="1" class="button button-primary">Create semester</button>
                </div>
            </form>
        </section>

        <section class="gtp-sem-list-section">
            <h2 class="gtp-sem-list-heading">All semesters</h2>
            <p class="gtp-sem-list-hint">
                <strong>Working</strong> is what you edit in admin.
                <strong>Live</strong> is what tutors see.
            </p>

            <?php
            $by_year = [];
            foreach ($semesters as $s) {
                $yl = $s->year_label ?: 'Unknown year';
                if (!isset($by_year[$yl])) {
                    $by_year[$yl] = [];
                }
                $by_year[$yl][] = $s;
            }
            if (empty($by_year)) :
                ?>
                <p class="gtp-people-muted">No semesters yet. Create one above.</p>
            <?php else : ?>
                <?php foreach ($by_year as $year_label => $year_semesters) : ?>
                    <div class="gtp-sem-year-group">
                        <h3 class="gtp-sem-year-label"><?php echo esc_html($year_label); ?></h3>
                        <ul class="gtp-sem-cards">
                            <?php foreach ($year_semesters as $s) :
                                $count = (int) $wpdb->get_var($wpdb->prepare(
                                    "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_classrooms WHERE semester_id = %d",
                                    (int) $s->id
                                ));
                                $is_working = (int) $s->id === $working;
                                $is_live = (int) $s->id === $live;
                                $status = $s->status;
                                ?>
                                <li class="gtp-sem-card<?php echo $is_working ? ' is-working' : ''; ?>">
                                    <div class="gtp-sem-card-main">
                                        <div class="gtp-sem-card-title-row">
                                            <span class="gtp-sem-card-name"><?php echo esc_html($s->label); ?></span>
                                            <span class="gtp-sem-status gtp-sem-status--<?php echo esc_attr($status); ?>">
                                                <?php echo esc_html($status); ?>
                                            </span>
                                            <?php if ($is_working) : ?>
                                                <span class="gtp-sem-pill gtp-sem-pill--working">Your working</span>
                                            <?php endif; ?>
                                            <?php if ($is_live) : ?>
                                                <span class="gtp-sem-pill gtp-sem-pill--live">Tutors see this</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="gtp-sem-card-meta">
                                            <?php echo (int) $count; ?> class<?php echo $count === 1 ? '' : 'es'; ?>
                                        </div>
                                    </div>
                                    <div class="gtp-sem-card-actions">
                                        <form method="post" class="gtp-sem-card-form">
                                            <?php wp_nonce_field('gtp_manage_semesters', 'gtp_sem_nonce'); ?>
                                            <input type="hidden" name="semester_id" value="<?php echo (int) $s->id; ?>">
                                            <?php if (!$is_working) : ?>
                                                <button type="submit" name="gtp_set_working_from_list" value="1" class="gtp-sem-action">
                                                    Switch working here
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($status !== 'live') : ?>
                                                <button type="submit" name="gtp_make_live" value="1" class="gtp-sem-action gtp-sem-action--primary"
                                                    onclick="return confirm('Make this the live semester for tutors? The current live semester will be closed.');">
                                                    Make live for tutors
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
    <script>
    (function () {
        var mode = document.getElementById('gtp-year-mode');
        var existing = document.getElementById('gtp-existing-year-wrap');
        var neu = document.getElementById('gtp-new-year-wrap');
        if (!mode) return;
        function sync() {
            var isNew = mode.value === 'new';
            existing.hidden = isNew;
            neu.hidden = !isNew;
        }
        mode.addEventListener('change', sync);
        sync();
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_manage_semesters', 'gtp_manage_semesters_shortcode');

/* -------------------------------------------------------------------------- */
/* Reports                                                                    */
/* -------------------------------------------------------------------------- */

function gtp_reports_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;
    $years = gtp_get_school_years();
    $semesters = gtp_get_semesters();
    $scope = isset($_GET['scope']) ? sanitize_text_field(wp_unslash($_GET['scope'])) : 'semester';
    if (!in_array($scope, ['semester', 'year'], true)) {
        $scope = 'semester';
    }
    $semester_id = isset($_GET['semester_id']) ? (int) $_GET['semester_id'] : gtp_get_working_semester_id();
    $year_id = isset($_GET['year_id']) ? (int) $_GET['year_id'] : 0;
    if (!$year_id && !empty($years)) {
        $year_id = (int) $years[0]->id;
    }

    $semester_ids = [];
    if ($scope === 'year' && $year_id > 0) {
        $semester_ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gtp_semesters WHERE school_year_id = %d",
            $year_id
        )));
    } elseif ($semester_id > 0) {
        $semester_ids = [$semester_id];
    }

    // Exports
    if (!empty($_GET['export']) && !empty($semester_ids)) {
        $type = sanitize_text_field(wp_unslash($_GET['export']));
        $ph = implode(',', array_fill(0, count($semester_ids), '%d'));

        if ($type === 'classes') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT y.label AS school_year, s.label AS semester, c.school, c.subject, c.is_block,
                        c.teacher_first_name, c.teacher_last_name, c.teacher_email,
                        c.meeting_days, c.start_time, c.end_time, c.zoom_link,
                        (SELECT GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name) SEPARATOR '; ')
                         FROM {$wpdb->prefix}gtp_class_assignments a
                         INNER JOIN {$wpdb->prefix}gtp_users u ON u.id = a.tutor_id
                         WHERE a.classroom_id = c.id) AS tutors
                 FROM {$wpdb->prefix}gtp_classrooms c
                 INNER JOIN {$wpdb->prefix}gtp_semesters s ON s.id = c.semester_id
                 INNER JOIN {$wpdb->prefix}gtp_school_years y ON y.id = s.school_year_id
                 WHERE c.semester_id IN ($ph)
                 ORDER BY y.label, s.label, c.school, c.subject",
                ...$semester_ids
            ));
            gtp_send_csv_download('gtp-classes.csv', [
                'School Year', 'Semester', 'School', 'Subject', 'Teacher First', 'Teacher Last',
                'Teacher Email', 'Days', 'Start Time', 'End Time', 'Zoom', 'Tutors',
            ], $rows, static function ($r) {
                return [
                    $r->school_year, $r->semester, $r->school, gtp_format_classroom_subject($r),
                    $r->teacher_first_name, $r->teacher_last_name, $r->teacher_email,
                    gtp_format_meeting_days($r->meeting_days ?? ''),
                    $r->start_time, $r->end_time, $r->zoom_link, $r->tutors,
                ];
            });
        }

        if ($type === 'sessions') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT y.label AS school_year, sem.label AS semester,
                        s.session_date, s.school, s.subject, s.teacher_name,
                        s.tutor_username, s.first_name, s.last_name,
                        s.start_time, s.end_time, s.topic, s.comments,
                        s.is_substitute, s.teacher_present, s.no_show, s.attendance
                 FROM {$wpdb->prefix}gtp_sessions s
                 LEFT JOIN {$wpdb->prefix}gtp_semesters sem ON sem.id = s.semester_id
                 LEFT JOIN {$wpdb->prefix}gtp_school_years y ON y.id = sem.school_year_id
                 WHERE s.semester_id IN ($ph)
                 ORDER BY s.session_date ASC, s.school ASC",
                ...$semester_ids
            ));
            gtp_send_csv_download('gtp-sessions.csv', [
                'School Year', 'Semester', 'Date', 'School', 'Subject', 'Teacher',
                'Tutor Username', 'Tutor First', 'Tutor Last', 'Start', 'End',
                'Topic', 'Comments', 'Substitute', 'Teacher Present', 'No Show', 'Attendance Count',
            ], $rows, static function ($r) {
                $att = json_decode($r->attendance ?? '[]', true);
                $count = is_array($att) ? count($att) : 0;
                return [
                    $r->school_year, $r->semester, $r->session_date, $r->school, $r->subject, $r->teacher_name,
                    $r->tutor_username, $r->first_name, $r->last_name, $r->start_time, $r->end_time,
                    $r->topic, $r->comments, $r->is_substitute ? 'yes' : 'no',
                    $r->teacher_present ? 'yes' : 'no', $r->no_show ? 'yes' : 'no', $count,
                ];
            });
        }
    }

    $page_url = site_url('/index.php/reports/');
    $export_base = add_query_arg([
        'scope' => $scope,
        'semester_id' => $semester_id,
        'year_id' => $year_id,
    ], $page_url);

    $class_count = 0;
    $session_count = 0;
    if (!empty($semester_ids)) {
        $ph = implode(',', array_fill(0, count($semester_ids), '%d'));
        $class_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_classrooms WHERE semester_id IN ($ph)",
            ...$semester_ids
        ));
        $session_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_sessions WHERE semester_id IN ($ph)",
            ...$semester_ids
        ));
    }

    ob_start();
    ?>
    <div class="gtp-home gtp-page">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Reports</h1>
        <p class="gtp-page-intro">Export class lists and session logs by semester or full school year (for investors and summaries).</p>

        <form class="gtp-checkin-filters" method="get" action="<?php echo esc_url($page_url); ?>">
            <label>
                <span>Scope</span>
                <select name="scope" id="gtp-report-scope">
                    <option value="semester" <?php selected($scope, 'semester'); ?>>One semester</option>
                    <option value="year" <?php selected($scope, 'year'); ?>>Full school year</option>
                </select>
            </label>
            <label id="gtp-report-sem-wrap" <?php echo $scope === 'year' ? 'hidden' : ''; ?>>
                <span>Semester</span>
                <select name="semester_id">
                    <?php foreach ($semesters as $s) : ?>
                        <option value="<?php echo (int) $s->id; ?>" <?php selected($semester_id, (int) $s->id); ?>>
                            <?php echo esc_html(gtp_semester_label($s)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label id="gtp-report-year-wrap" <?php echo $scope === 'semester' ? 'hidden' : ''; ?>>
                <span>School year</span>
                <select name="year_id">
                    <?php foreach ($years as $y) : ?>
                        <option value="<?php echo (int) $y->id; ?>" <?php selected($year_id, (int) $y->id); ?>>
                            <?php echo esc_html($y->label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="gtp-checkin-filter-actions">
                <button type="submit" class="button button-primary">Apply</button>
            </div>
        </form>

        <p class="gtp-people-muted"><?php echo (int) $class_count; ?> classes · <?php echo (int) $session_count; ?> sessions in this scope</p>

        <p style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="button button-primary" href="<?php echo esc_url(add_query_arg('export', 'classes', $export_base)); ?>">Download classes CSV</a>
            <a class="button button-primary" href="<?php echo esc_url(add_query_arg('export', 'sessions', $export_base)); ?>">Download sessions CSV</a>
            <a class="button" href="<?php echo esc_url(site_url('/index.php/ta-session-filter/')); ?>">Open Filter Sessions</a>
        </p>
    </div>
    <script>
    (function () {
        var scope = document.getElementById('gtp-report-scope');
        var sem = document.getElementById('gtp-report-sem-wrap');
        var year = document.getElementById('gtp-report-year-wrap');
        if (!scope) return;
        function sync() {
            var isYear = scope.value === 'year';
            sem.hidden = isYear;
            year.hidden = !isYear;
        }
        scope.addEventListener('change', sync);
        sync();
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_reports', 'gtp_reports_shortcode');

function gtp_send_csv_download($filename, $headers, $rows, $mapper) {
    if (headers_sent()) {
        return;
    }
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $mapper($row));
    }
    fclose($out);
    exit;
}
