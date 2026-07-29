<?php
/**
 * Central subject catalog used across classrooms, filters, preferences, etc.
 */

/** Built-in subjects (always available). */
function gtp_default_subjects() {
    return [
        'AP Biology',
        'AP Statistics',
        'AP Physics',
        'AP CSP',
    ];
}

/** Map older stored labels → canonical names. */
function gtp_subject_legacy_map() {
    return [
        'Biology' => 'AP Biology',
        'AP Biology' => 'AP Biology',
        'Statistics' => 'AP Statistics',
        'AP Statistics' => 'AP Statistics',
        'Physics' => 'AP Physics',
        'AP Physics' => 'AP Physics',
        'AP Physics 1' => 'AP Physics',
        'AP Physics 2' => 'AP Physics',
        'CSP' => 'AP CSP',
        'AP CSP' => 'AP CSP',
        'AP Computer Science Principles' => 'AP CSP',
        'Computer Science Principles' => 'AP CSP',
    ];
}

function gtp_create_subjects_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_subjects';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        is_default tinyint(1) NOT NULL DEFAULT 0,
        sort_order int NOT NULL DEFAULT 100,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY name (name)
    ) $charset;";

    dbDelta($sql);
}

/** Ensure default rows exist in gtp_subjects (only when the catalog is empty). */
function gtp_seed_default_subjects() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_subjects';
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if (!$table_exists) {
        return;
    }

    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    if ($count > 0) {
        // Catalog already owned by admins — do not re-insert deleted defaults.
        return;
    }

    $now = current_time('mysql');
    $order = 10;
    foreach (gtp_default_subjects() as $name) {
        $wpdb->insert($table, [
            'name' => $name,
            'is_default' => 1,
            'sort_order' => $order,
            'created_at' => $now,
        ]);
        $order += 10;
    }
}

/**
 * All subject names available site-wide, sorted.
 *
 * @return string[]
 */
function gtp_get_subjects() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_subjects';

    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if (!$table_exists) {
        return gtp_default_subjects();
    }

    $rows = $wpdb->get_col(
        "SELECT name FROM $table ORDER BY sort_order ASC, name ASC"
    );
    if (empty($rows)) {
        return [];
    }

    return array_values(array_unique(array_map('strval', $rows)));
}

/**
 * Full subject rows for admin management UI.
 *
 * @return object[]
 */
function gtp_get_subject_rows() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_subjects';
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    if (!$table_exists) {
        return [];
    }
    return $wpdb->get_results(
        "SELECT * FROM $table ORDER BY sort_order ASC, name ASC"
    );
}

/** How many classrooms currently use this subject name. */
function gtp_subject_classroom_count($name) {
    global $wpdb;
    $match = gtp_subject_match_values($name);
    if (empty($match)) {
        return 0;
    }
    $ph = implode(',', array_fill(0, count($match), '%s'));
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_classrooms WHERE subject IN ($ph)",
        ...$match
    ));
}

/**
 * Rename a subject in the catalog and update classrooms/sessions/preferences.
 *
 * @return true|WP_Error
 */
function gtp_rename_subject($id, $new_name) {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_subjects';
    $id = (int) $id;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    if (!$row) {
        return new WP_Error('missing', 'Subject not found.');
    }

    $new_name = gtp_normalize_subject_name($new_name);
    if ($new_name === '') {
        return new WP_Error('empty_subject', 'Please enter a subject name.');
    }

    $old_name = $row->name;
    if (strcasecmp($old_name, $new_name) === 0 && $old_name === $new_name) {
        return true;
    }

    foreach (gtp_get_subjects() as $existing) {
        if (strcasecmp($existing, $new_name) === 0 && strcasecmp($existing, $old_name) !== 0) {
            return new WP_Error('duplicate', 'That subject name already exists.');
        }
    }

    $updated = $wpdb->update($table, ['name' => $new_name, 'is_default' => 0], ['id' => $id]);
    if ($updated === false) {
        return new WP_Error('save_failed', 'Could not rename the subject.');
    }

    if ($old_name !== $new_name) {
        $wpdb->update($wpdb->prefix . 'gtp_classrooms', ['subject' => $new_name], ['subject' => $old_name]);
        $wpdb->update($wpdb->prefix . 'gtp_sessions', ['subject' => $new_name], ['subject' => $old_name]);
        gtp_rewrite_preference_subject_key($old_name, $new_name);
    }

    return true;
}

/**
 * Remove a subject from the catalog. Does not delete classrooms that used it.
 *
 * @return true|WP_Error
 */
function gtp_delete_subject($id) {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_subjects';
    $id = (int) $id;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    if (!$row) {
        return new WP_Error('missing', 'Subject not found.');
    }

    $deleted = $wpdb->delete($table, ['id' => $id]);
    if (!$deleted) {
        return new WP_Error('delete_failed', 'Could not remove the subject.');
    }
    return true;
}

/** Rewrite subject_preferences JSON keys when a subject is renamed. */
function gtp_rewrite_preference_subject_key($old_key, $new_key) {
    global $wpdb;
    $users = $wpdb->prefix . 'gtp_users';
    $tutors = $wpdb->get_results(
        "SELECT id, subject_preferences FROM $users
         WHERE subject_preferences IS NOT NULL AND subject_preferences <> ''"
    );
    foreach ((array) $tutors as $tutor) {
        $prefs = json_decode((string) $tutor->subject_preferences, true);
        if (!is_array($prefs) || !array_key_exists($old_key, $prefs)) {
            continue;
        }
        $level = $prefs[$old_key];
        unset($prefs[$old_key]);
        if (!isset($prefs[$new_key]) || $prefs[$new_key] === 'cannot_tutor') {
            $prefs[$new_key] = $level;
        }
        $wpdb->update(
            $users,
            ['subject_preferences' => wp_json_encode($prefs)],
            ['id' => (int) $tutor->id]
        );
    }
}

/** Normalize a subject title for storage/comparison. */
function gtp_normalize_subject_name($name) {
    return trim(preg_replace('/\s+/', ' ', (string) $name));
}

/**
 * Add a subject to the catalog. Returns canonical name or WP_Error.
 *
 * @param string $name
 * @return string|WP_Error
 */
function gtp_add_custom_subject($name) {
    global $wpdb;

    $name = gtp_normalize_subject_name($name);
    if ($name === '') {
        return new WP_Error('empty_subject', 'Please enter a subject name.');
    }

    $map = gtp_subject_legacy_map();
    if (isset($map[$name])) {
        $name = $map[$name];
    }

    foreach (gtp_get_subjects() as $existing) {
        if (strcasecmp($existing, $name) === 0) {
            return $existing;
        }
    }

    $table = $wpdb->prefix . 'gtp_subjects';
    $inserted = $wpdb->insert($table, [
        'name' => $name,
        'is_default' => 0,
        'sort_order' => 500,
        'created_at' => current_time('mysql'),
    ]);

    if (!$inserted) {
        // Race / unique key — try to return existing
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $table WHERE name = %s",
            $name
        ));
        if ($existing) {
            return $existing;
        }
        return new WP_Error('subject_save_failed', 'Could not save the new subject.');
    }

    return $name;
}

/**
 * Resolve subject from a POST request (select value, or new subject when adding).
 *
 * @param array $post Typically $_POST
 * @param string $field Select field name
 * @param string $new_field New-subject text field name
 * @return array{subject?:string,error?:string}
 */
function gtp_resolve_subject_from_post($post, $field = 'subject', $new_field = 'new_subject') {
    $raw = sanitize_text_field($post[$field] ?? '');
    if ($raw === '' || $raw === '__add_subject__') {
        $new = gtp_normalize_subject_name(sanitize_text_field($post[$new_field] ?? ''));
        if ($new === '') {
            return ['error' => 'Please select a subject or add a new one.'];
        }
        $result = gtp_add_custom_subject($new);
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message()];
        }
        return ['subject' => $result];
    }

    $map = gtp_subject_legacy_map();
    if (isset($map[$raw])) {
        $raw = $map[$raw];
    }

    $catalog = gtp_get_subjects();
    foreach ($catalog as $name) {
        if (strcasecmp($name, $raw) === 0) {
            return ['subject' => $name];
        }
    }

    // Unknown but non-empty — treat as new custom subject so old free-text edits still work.
    $result = gtp_add_custom_subject($raw);
    if (is_wp_error($result)) {
        return ['error' => $result->get_error_message()];
    }
    return ['subject' => $result];
}

/**
 * Values to match in SQL for a chosen subject (canonical + legacy aliases).
 *
 * @param string $subject
 * @return string[]
 */
function gtp_subject_match_values($subject) {
    $canonical = gtp_normalize_subject_name($subject);
    $map = gtp_subject_legacy_map();
    if (isset($map[$canonical])) {
        $canonical = $map[$canonical];
    }

    $values = [$canonical];
    foreach ($map as $old => $new) {
        if ($new === $canonical) {
            $values[] = $old;
        }
    }
    return array_values(array_unique($values));
}

/**
 * Render a subject <select>. When $allow_add is true, includes “Add a subject…”.
 *
 * @param array $args {
 *   @type string $name
 *   @type string $id
 *   @type string $selected
 *   @type bool   $required
 *   @type bool   $allow_add
 *   @type string $class
 *   @type string $empty_label
 * }
 * @return string
 */
function gtp_render_subject_select($args = []) {
    $name = $args['name'] ?? 'subject';
    $id = $args['id'] ?? 'gtp-subject-select';
    $selected = (string) ($args['selected'] ?? '');
    $required = !empty($args['required']);
    $allow_add = !empty($args['allow_add']);
    $class = $args['class'] ?? '';
    $empty_label = $args['empty_label'] ?? '— Select subject —';
    $new_field = $args['new_field'] ?? 'new_subject';
    $new_id = $args['new_id'] ?? ($id . '-new');
    $wrap_class = $args['wrap_class'] ?? 'gtp-subject-field';

    $subjects = gtp_get_subjects();
    $is_add = ($selected === '__add_subject__');
    $selected_in_list = false;
    foreach ($subjects as $subj) {
        if (strcasecmp($subj, $selected) === 0) {
            $selected_in_list = true;
            $selected = $subj;
            break;
        }
    }

    ob_start();
    ?>
    <div class="<?php echo esc_attr($wrap_class); ?>" data-gtp-subject-field>
        <select name="<?php echo esc_attr($name); ?>"
                id="<?php echo esc_attr($id); ?>"
                class="gtp-subject-select <?php echo esc_attr($class); ?>"
                <?php echo $required ? 'required' : ''; ?>>
            <option value=""><?php echo esc_html($empty_label); ?></option>
            <?php foreach ($subjects as $subj) : ?>
                <option value="<?php echo esc_attr($subj); ?>" <?php selected($selected_in_list && $selected === $subj); ?>>
                    <?php echo esc_html($subj); ?>
                </option>
            <?php endforeach; ?>
            <?php if ($allow_add) : ?>
                <option value="__add_subject__" <?php selected($is_add); ?>>+ Add a subject…</option>
            <?php endif; ?>
            <?php if ($selected !== '' && !$selected_in_list && !$is_add && !$allow_add) : ?>
                <option value="<?php echo esc_attr($selected); ?>" selected><?php echo esc_html($selected); ?></option>
            <?php endif; ?>
        </select>
        <?php if ($allow_add) : ?>
            <div class="gtp-new-subject-wrap" id="<?php echo esc_attr($new_id); ?>-wrap" <?php echo $is_add ? '' : 'hidden'; ?>>
                <label class="gtp-field" for="<?php echo esc_attr($new_id); ?>">
                    <span>New subject name</span>
                    <input type="text"
                           name="<?php echo esc_attr($new_field); ?>"
                           id="<?php echo esc_attr($new_id); ?>"
                           class="gtp-new-subject-input"
                           value=""
                           placeholder="e.g. AP Calculus"
                           autocomplete="off">
                </label>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/** Inline JS once per page for subject “add new” toggles. */
function gtp_subject_select_script() {
    static $printed = false;
    if ($printed) {
        return '';
    }
    $printed = true;
    return <<<'HTML'
<script>
(function () {
    function syncField(root) {
        var select = root.querySelector('.gtp-subject-select');
        var wrap = root.querySelector('.gtp-new-subject-wrap');
        var input = root.querySelector('.gtp-new-subject-input');
        if (!select || !wrap || !input) {
            return;
        }
        var isAdd = select.value === '__add_subject__';
        wrap.hidden = !isAdd;
        input.required = isAdd && !!select.required;
        if (!isAdd) {
            input.value = '';
        } else {
            input.focus();
        }
    }
    function bind(root) {
        var select = root.querySelector('.gtp-subject-select');
        if (!select || select.getAttribute('data-gtp-bound')) {
            return;
        }
        select.setAttribute('data-gtp-bound', '1');
        select.addEventListener('change', function () {
            syncField(root);
        });
        syncField(root);
    }
    document.querySelectorAll('[data-gtp-subject-field]').forEach(bind);
})();
</script>
HTML;
}

/**
 * One-time migration: rename legacy subject strings in classrooms/sessions/prefs,
 * and register any unknown classroom subjects as custom catalog entries.
 */
function gtp_migrate_subject_catalog() {
    global $wpdb;

    gtp_create_subjects_table();
    gtp_seed_default_subjects();

    $map = gtp_subject_legacy_map();
    $classrooms = $wpdb->prefix . 'gtp_classrooms';
    $sessions = $wpdb->prefix . 'gtp_sessions';
    $users = $wpdb->prefix . 'gtp_users';

    foreach ($map as $old => $new) {
        if ($old === $new) {
            continue;
        }
        $wpdb->query($wpdb->prepare(
            "UPDATE $classrooms SET subject = %s WHERE subject = %s",
            $new,
            $old
        ));
        $wpdb->query($wpdb->prepare(
            "UPDATE $sessions SET subject = %s WHERE subject = %s",
            $new,
            $old
        ));
    }

    // Register any remaining classroom subjects not yet in the catalog.
    $distinct = $wpdb->get_col("SELECT DISTINCT subject FROM $classrooms WHERE subject IS NOT NULL AND subject <> ''");
    $catalog = gtp_get_subjects();
    $catalog_lower = array_map('strtolower', $catalog);
    foreach ((array) $distinct as $subj) {
        $subj = gtp_normalize_subject_name($subj);
        if ($subj === '') {
            continue;
        }
        if (isset($map[$subj])) {
            $subj = $map[$subj];
        }
        if (!in_array(strtolower($subj), $catalog_lower, true)) {
            gtp_add_custom_subject($subj);
            $catalog_lower[] = strtolower($subj);
        }
    }

    // Rewrite subject_preferences JSON keys to canonical names.
    $tutors = $wpdb->get_results(
        "SELECT id, subject_preferences FROM $users
         WHERE subject_preferences IS NOT NULL AND subject_preferences <> ''"
    );
    foreach ((array) $tutors as $tutor) {
        $prefs = json_decode((string) $tutor->subject_preferences, true);
        if (!is_array($prefs) || empty($prefs)) {
            continue;
        }
        $updated = [];
        $changed = false;
        foreach ($prefs as $key => $level) {
            $new_key = $map[$key] ?? $key;
            if ($new_key !== $key) {
                $changed = true;
            }
            // Prefer non-cannot over cannot if duplicate after remap
            if (!isset($updated[$new_key]) || $updated[$new_key] === 'cannot_tutor') {
                $updated[$new_key] = $level;
            }
        }
        if ($changed) {
            $wpdb->update(
                $users,
                ['subject_preferences' => wp_json_encode($updated)],
                ['id' => (int) $tutor->id]
            );
        }
    }
}

/**
 * Admin page: add, rename, and remove subjects in the shared catalog.
 */
function gtp_manage_subjects_shortcode() {
    if (empty($_SESSION['gtp_user']) || ($_SESSION['gtp_user']['role'] ?? '') !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    gtp_create_subjects_table();
    gtp_seed_default_subjects();

    $message = '';

    if (isset($_POST['gtp_add_subject']) && check_admin_referer('gtp_manage_subjects', 'gtp_subjects_nonce')) {
        $result = gtp_add_custom_subject($_POST['subject_name'] ?? '');
        if (is_wp_error($result)) {
            $message = '<p class="gtp-msg is-error gtp-persist">' . esc_html($result->get_error_message()) . '</p>';
        } else {
            $message = '<p class="gtp-msg is-success">Added <strong>' . esc_html($result) . '</strong>.</p>';
        }
    }

    if (isset($_POST['gtp_rename_subject']) && check_admin_referer('gtp_manage_subjects', 'gtp_subjects_nonce')) {
        $result = gtp_rename_subject((int) ($_POST['subject_id'] ?? 0), $_POST['subject_name'] ?? '');
        if (is_wp_error($result)) {
            $message = '<p class="gtp-msg is-error gtp-persist">' . esc_html($result->get_error_message()) . '</p>';
        } else {
            $message = '<p class="gtp-msg is-success">Subject renamed.</p>';
        }
    }

    if (isset($_POST['gtp_delete_subject']) && check_admin_referer('gtp_manage_subjects', 'gtp_subjects_nonce')) {
        $id = (int) ($_POST['subject_id'] ?? 0);
        $row = null;
        foreach (gtp_get_subject_rows() as $candidate) {
            if ((int) $candidate->id === $id) {
                $row = $candidate;
                break;
            }
        }
        $in_use = $row ? gtp_subject_classroom_count($row->name) : 0;
        $result = gtp_delete_subject($id);
        if (is_wp_error($result)) {
            $message = '<p class="gtp-msg is-error gtp-persist">' . esc_html($result->get_error_message()) . '</p>';
        } else {
            $message = '<p class="gtp-msg is-success">Removed <strong>' . esc_html($row->name ?? 'subject') . '</strong> from the catalog.'
                . ($in_use ? ' Note: ' . (int) $in_use . ' classroom(s) still use that name in historical data.' : '')
                . '</p>';
        }
    }

    $rows = gtp_get_subject_rows();

    ob_start();
    ?>
    <div class="gtp-page gtp-manage-subjects-page">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Manage Subjects</h1>
        <p class="gtp-page-intro">These subjects appear everywhere — classroom create/edit, filters, spreadsheet view, announcements, and TA preferences. Add or remove as the program changes.</p>
        <?php echo $message; ?>

        <section class="gtp-profile-card" style="margin-bottom:20px;">
            <div class="gtp-profile-card-title">
                <h2>Add a subject</h2>
            </div>
            <form method="post" class="gtp-form-stack" style="max-width:420px;">
                <?php wp_nonce_field('gtp_manage_subjects', 'gtp_subjects_nonce'); ?>
                <label class="gtp-field">
                    <span>Subject name</span>
                    <input type="text" name="subject_name" required placeholder="e.g. AP Calculus" autocomplete="off">
                </label>
                <div class="gtp-form-actions">
                    <button type="submit" name="gtp_add_subject" value="1" class="button button-primary">Add subject</button>
                </div>
            </form>
        </section>

        <section class="gtp-profile-card">
            <div class="gtp-profile-card-title">
                <h2>Current subjects</h2>
                <p>Rename updates classrooms and session logs that used the old name. Remove only hides it from future dropdowns.</p>
            </div>
            <?php if (empty($rows)) : ?>
                <p class="gtp-people-muted">No subjects yet. Add one above.</p>
            <?php else : ?>
                <ul class="gtp-subject-manage-list">
                    <?php foreach ($rows as $row) :
                        $usage = gtp_subject_classroom_count($row->name);
                        ?>
                        <li class="gtp-subject-manage-row">
                            <form method="post" class="gtp-subject-manage-form">
                                <?php wp_nonce_field('gtp_manage_subjects', 'gtp_subjects_nonce'); ?>
                                <input type="hidden" name="subject_id" value="<?php echo (int) $row->id; ?>">
                                <input type="text" name="subject_name" value="<?php echo esc_attr($row->name); ?>" required>
                                <span class="gtp-people-muted"><?php echo (int) $usage; ?> classroom<?php echo $usage === 1 ? '' : 's'; ?></span>
                                <button type="submit" name="gtp_rename_subject" value="1" class="button">Rename</button>
                                <button type="submit"
                                        name="gtp_delete_subject"
                                        value="1"
                                        class="button"
                                        onclick="return confirm('Remove this subject from dropdowns site-wide? Existing classrooms keep their subject text.');">
                                    Remove
                                </button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_manage_subjects', 'gtp_manage_subjects_shortcode');
