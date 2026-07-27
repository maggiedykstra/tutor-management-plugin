<?php
/**
 * Site bug reports: TA/admin submit form, admin review list, email notify.
 */

function gtp_create_site_bugs_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_site_bugs';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        reporter_id mediumint(9) NOT NULL,
        reporter_name varchar(255) NOT NULL,
        reporter_role varchar(20) NOT NULL DEFAULT '',
        page_key text NOT NULL,
        page_label text NOT NULL,
        page_other text,
        description text NOT NULL,
        page_url varchar(500) DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'open',
        resolved_at datetime DEFAULT NULL,
        resolved_by mediumint(9) DEFAULT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY status (status),
        KEY reporter_id (reporter_id),
        KEY created_at (created_at)
    ) $charset;";

    dbDelta($sql);
}

/** Pages TAs/admins can pick when reporting a bug. */
function gtp_bug_report_page_options() {
    return [
        'ta-dashboard' => 'TA Dashboard',
        'admin-dashboard' => 'Admin Dashboard',
        'log-session' => 'Log Session',
        'log-substitute' => 'Log Substitute Session',
        'my-logged-sessions' => 'My Logged Sessions',
        'my-classes' => 'My Classes',
        'monthly-checkins' => 'Monthly Check-ins',
        'admin-checkins' => 'Tutor Check-ins (admin)',
        'tutor-resources' => 'Tutor Resources',
        'announcements' => 'Announcements',
        'ta-profile' => 'My Profile (TA)',
        'admin-profile' => 'My Profile (admin)',
        'spreadsheet-view' => 'Spreadsheet View',
        'ta-session-filter' => 'Filter Sessions',
        'edit-classrooms' => 'Edit Classrooms',
        'add-classroom' => 'Add Classroom',
        'manage-people' => 'Manage People',
        'unassigned-tutors' => 'Unassigned Tutors',
        'validate-tas' => 'Approve Registrations',
        'flagged-sessions' => 'Flagged Sessions',
        'manage-semesters' => 'Manage Semesters',
        'reports' => 'Reports',
        'manage-announcements' => 'Manage Announcements',
        'welcome-to-gtp' => 'Sign in',
        'other' => 'Other',
    ];
}

/** Human-readable page list for a stored bug (supports multi-select). */
function gtp_bug_pages_display_label($bug) {
    $page = (string) ($bug->page_label ?? '');
    $keys = array_filter(array_map('trim', explode(',', (string) ($bug->page_key ?? ''))));
    if (!empty($bug->page_other) && (in_array('other', $keys, true) || ($bug->page_key ?? '') === 'other')) {
        $page .= ' — ' . $bug->page_other;
    }
    return $page;
}

function gtp_bug_report_url() {
    return site_url('/index.php/report-site-bug/');
}

function gtp_site_bugs_admin_url($args = []) {
    return add_query_arg($args, site_url('/index.php/site-bugs/'));
}

function gtp_bug_report_top_link_html() {
    return '<a class="gtp-top-report" href="' . esc_url(gtp_bug_report_url()) . '">Report Site Bug</a>';
}

/** Email all GTP admins about a new bug report. */
function gtp_notify_admins_new_bug($bug_id) {
    global $wpdb;
    $bug = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gtp_site_bugs WHERE id = %d",
        (int) $bug_id
    ));
    if (!$bug) {
        return;
    }

    $email = 'mdolan047@gmail.com';

    $page = gtp_bug_pages_display_label($bug);

    $subject = '[GTP] New site bug report #' . (int) $bug->id;
    $body = "A new site bug was reported.\n\n"
        . "Reporter: {$bug->reporter_name} ({$bug->reporter_role})\n"
        . "Page: {$page}\n"
        . "Submitted: {$bug->created_at}\n\n"
        . "Description:\n{$bug->description}\n\n"
        . "Review: " . gtp_site_bugs_admin_url() . "\n";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    wp_mail($email, $subject, $body, $headers);
}

function gtp_report_site_bug_shortcode() {
    if (empty($_SESSION['gtp_user'])) {
        return '<p>Please sign in to report a site bug.</p>';
    }

    global $wpdb;
    $message = '';
    $show_form = true;
    $user = $_SESSION['gtp_user'];
    $options = gtp_bug_report_page_options();
    $role = $user['role'] ?? '';
    $back_role = $role === 'admin' ? 'admin' : 'tutor';
    $report_url = gtp_bug_report_url();

    if (!empty($_SESSION['gtp_bug_submitted'])) {
        unset($_SESSION['gtp_bug_submitted']);
        $show_form = false;
        $message = '<div class="gtp-bug-success">'
            . '<p class="gtp-msg is-success gtp-persist">Thanks — your report was submitted.</p>'
            . '<p class="gtp-bug-another"><a href="' . esc_url($report_url) . '">Report another bug</a></p>'
            . '</div>';
    }

    if ($show_form && isset($_POST['gtp_submit_bug']) && check_admin_referer('gtp_report_bug', 'gtp_bug_nonce')) {
        $raw_keys = isset($_POST['page_keys']) ? (array) $_POST['page_keys'] : [];
        $page_keys = [];
        foreach ($raw_keys as $raw) {
            $key = sanitize_text_field($raw);
            if (isset($options[$key])) {
                $page_keys[] = $key;
            }
        }
        $page_keys = array_values(array_unique($page_keys));
        $page_other = sanitize_textarea_field(wp_unslash($_POST['page_other'] ?? ''));
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $page_url = esc_url_raw($_POST['page_url'] ?? '');
        $includes_other = in_array('other', $page_keys, true);

        if (empty($page_keys)) {
            $message = '<p class="gtp-msg is-error gtp-persist">Please select at least one page where the bug happened.</p>';
        } elseif ($includes_other && trim($page_other) === '') {
            $message = '<p class="gtp-msg is-error gtp-persist">Please describe where the error occurs when choosing Other.</p>';
        } elseif (trim($description) === '') {
            $message = '<p class="gtp-msg is-error gtp-persist">Please describe the issue.</p>';
        } else {
            $labels = [];
            foreach ($page_keys as $key) {
                $labels[] = $options[$key];
            }
            $wpdb->insert($wpdb->prefix . 'gtp_site_bugs', [
                'reporter_id' => (int) $user['id'],
                'reporter_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'Unknown'),
                'reporter_role' => $role,
                'page_key' => implode(',', $page_keys),
                'page_label' => implode(', ', $labels),
                'page_other' => $includes_other ? $page_other : null,
                'description' => $description,
                'page_url' => $page_url ?: null,
                'status' => 'open',
                'created_at' => current_time('mysql'),
            ]);
            $new_id = (int) $wpdb->insert_id;
            if ($new_id) {
                gtp_notify_admins_new_bug($new_id);
                $_SESSION['gtp_bug_submitted'] = 1;
                wp_safe_redirect($report_url);
                exit;
            }
            $message = '<p class="gtp-msg is-error">Could not save your report. Please try again.</p>';
        }
    }

    // Only repopulate fields after a failed validation submit.
    $selected_keys = [];
    $selected_other = '';
    $selected_description = '';
    if ($show_form && isset($_POST['gtp_submit_bug'])) {
        if (isset($_POST['page_keys']) && is_array($_POST['page_keys'])) {
            $selected_keys = array_map('sanitize_text_field', $_POST['page_keys']);
        }
        $selected_other = isset($_POST['page_other']) ? sanitize_text_field(wp_unslash($_POST['page_other'])) : '';
        $selected_description = isset($_POST['description']) ? wp_unslash($_POST['description']) : '';
    }
    $other_checked = in_array('other', $selected_keys, true);

    ob_start();
    ?>
    <div class="gtp-page gtp-bug-report-page">
        <?php echo gtp_dashboard_back_link($back_role); ?>
        <h1 class="gtp-page-title">Report a site bug</h1>
        <p class="gtp-page-intro">Tell us which page(s) broke and what went wrong.</p>
        <?php echo $message; ?>

        <?php if ($show_form) : ?>
        <form method="post" class="gtp-form-stack gtp-bug-report-form" id="gtp-bug-report-form">
            <?php wp_nonce_field('gtp_report_bug', 'gtp_bug_nonce'); ?>
            <input type="hidden" name="page_url" id="gtp-bug-page-url" value="">

            <fieldset class="gtp-bug-page-pick">
                <legend>Where did the bug happen? <span class="gtp-bug-page-hint">(select all that apply)</span></legend>
                <ul class="gtp-bug-page-options">
                    <?php foreach ($options as $key => $label) :
                        if ($key === 'other') {
                            continue;
                        }
                        ?>
                        <li>
                            <label class="gtp-bug-page-option">
                                <input type="checkbox" name="page_keys[]" value="<?php echo esc_attr($key); ?>"
                                    <?php checked(in_array($key, $selected_keys, true)); ?>>
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                    <li class="gtp-bug-page-other-row<?php echo $other_checked ? ' is-active' : ''; ?>">
                        <label class="gtp-bug-page-option gtp-bug-page-option--other">
                            <input type="checkbox" name="page_keys[]" id="gtp-bug-page-other-check" value="other"
                                <?php checked($other_checked); ?>>
                            <span>Other</span>
                        </label>
                        <input type="text"
                               class="gtp-bug-other-input"
                               name="page_other"
                               id="gtp-bug-page-other"
                               value="<?php echo esc_attr($selected_other); ?>"
                               placeholder="Describe where the error occurs"
                               autocomplete="off"
                               <?php echo $other_checked ? '' : ' disabled'; ?>>
                    </li>
                </ul>
            </fieldset>

            <label class="gtp-field">
                <span>Describe the issue</span>
                <textarea name="description" rows="5" required placeholder="What happened? What did you expect?"><?php
                    echo esc_textarea($selected_description);
                ?></textarea>
            </label>

            <div class="gtp-form-actions">
                <button type="submit" name="gtp_submit_bug" value="1" class="button button-primary">Submit report</button>
            </div>
        </form>
        <script>
        (function () {
            var form = document.getElementById('gtp-bug-report-form');
            var otherCheck = document.getElementById('gtp-bug-page-other-check');
            var otherInput = document.getElementById('gtp-bug-page-other');
            var otherRow = otherInput && otherInput.closest('.gtp-bug-page-other-row');
            var urlField = document.getElementById('gtp-bug-page-url');
            if (urlField) {
                urlField.value = document.referrer || window.location.href;
            }

            function syncOtherField() {
                if (!otherCheck || !otherInput || !otherRow) {
                    return;
                }
                var isOther = otherCheck.checked;
                otherRow.classList.toggle('is-active', isOther);
                otherInput.disabled = !isOther;
                otherInput.required = isOther;
                if (!isOther) {
                    otherInput.value = '';
                }
            }

            if (form) {
                if (otherCheck) {
                    otherCheck.addEventListener('change', function () {
                        syncOtherField();
                        if (otherCheck.checked && otherInput) {
                            otherInput.focus();
                        }
                    });
                }
                syncOtherField();

                form.addEventListener('submit', function (e) {
                    var checked = form.querySelectorAll('input[name="page_keys[]"]:checked');
                    if (!checked.length) {
                        e.preventDefault();
                        alert('Please select at least one page where the bug happened.');
                    }
                });
            }
        })();
        </script>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_report_site_bug', 'gtp_report_site_bug_shortcode');

function gtp_site_bugs_admin_shortcode() {
    if (empty($_SESSION['gtp_user']) || ($_SESSION['gtp_user']['role'] ?? '') !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'gtp_site_bugs';
    $message = '';
    $admin_id = (int) $_SESSION['gtp_user']['id'];

    if (isset($_POST['gtp_resolve_bug']) && check_admin_referer('gtp_manage_bugs', 'gtp_bugs_nonce')) {
        $bug_id = (int) ($_POST['bug_id'] ?? 0);
        if ($bug_id > 0) {
            $wpdb->update($table, [
                'status' => 'resolved',
                'resolved_at' => current_time('mysql'),
                'resolved_by' => $admin_id,
            ], ['id' => $bug_id, 'status' => 'open']);
            $message = '<p class="gtp-msg is-success">Marked as resolved.</p>';
        }
    }

    if (isset($_POST['gtp_reopen_bug']) && check_admin_referer('gtp_manage_bugs', 'gtp_bugs_nonce')) {
        $bug_id = (int) ($_POST['bug_id'] ?? 0);
        if ($bug_id > 0) {
            $wpdb->update($table, [
                'status' => 'open',
                'resolved_at' => null,
                'resolved_by' => null,
            ], ['id' => $bug_id]);
            $message = '<p class="gtp-msg is-success">Reopened.</p>';
        }
    }

    $open = $wpdb->get_results("SELECT * FROM $table WHERE status = 'open' ORDER BY created_at DESC");
    $resolved = $wpdb->get_results("SELECT * FROM $table WHERE status = 'resolved' ORDER BY resolved_at DESC, created_at DESC LIMIT 100");

    ob_start();
    ?>
    <div class="gtp-page gtp-site-bugs-page">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">Site bugs</h1>
        <p class="gtp-page-intro">Reports from TAs and admins. Mark items resolved when fixed.</p>
        <?php echo $message; ?>

        <section class="gtp-bug-section">
            <h2>Open <?php if ($open) : ?><span class="gtp-bug-count"><?php echo count($open); ?></span><?php endif; ?></h2>
            <?php if (empty($open)) : ?>
                <p class="gtp-people-muted">No open reports.</p>
            <?php else : ?>
                <ul class="gtp-bug-list">
                    <?php foreach ($open as $bug) : ?>
                        <li class="gtp-bug-card">
                            <div class="gtp-bug-card-main">
                                <div class="gtp-bug-card-meta">
                                    <strong><?php echo esc_html(gtp_bug_pages_display_label($bug)); ?></strong>
                                    <span class="gtp-people-muted">
                                        · <?php echo esc_html($bug->reporter_name); ?>
                                        (<?php echo esc_html($bug->reporter_role); ?>)
                                        · <?php echo esc_html(date('M j, Y g:i A', strtotime($bug->created_at))); ?>
                                    </span>
                                </div>
                                <p class="gtp-bug-desc"><?php echo nl2br(esc_html($bug->description)); ?></p>
                            </div>
                            <form method="post" class="gtp-bug-card-actions">
                                <?php wp_nonce_field('gtp_manage_bugs', 'gtp_bugs_nonce'); ?>
                                <input type="hidden" name="bug_id" value="<?php echo (int) $bug->id; ?>">
                                <button type="submit" name="gtp_resolve_bug" value="1" class="button button-primary">Mark resolved</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="gtp-bug-section">
            <h2>Past reports</h2>
            <?php if (empty($resolved)) : ?>
                <p class="gtp-people-muted">No resolved reports yet.</p>
            <?php else : ?>
                <ul class="gtp-bug-list gtp-bug-list--past">
                    <?php foreach ($resolved as $bug) : ?>
                        <li class="gtp-bug-card is-resolved">
                            <div class="gtp-bug-card-main">
                                <div class="gtp-bug-card-meta">
                                    <strong><?php echo esc_html(gtp_bug_pages_display_label($bug)); ?></strong>
                                    <span class="gtp-sem-status gtp-sem-status--closed">Resolved</span>
                                    <span class="gtp-people-muted">
                                        · <?php echo esc_html($bug->reporter_name); ?>
                                        · resolved <?php echo esc_html($bug->resolved_at ? date('M j, Y', strtotime($bug->resolved_at)) : ''); ?>
                                    </span>
                                </div>
                                <p class="gtp-bug-desc"><?php echo nl2br(esc_html($bug->description)); ?></p>
                            </div>
                            <form method="post" class="gtp-bug-card-actions">
                                <?php wp_nonce_field('gtp_manage_bugs', 'gtp_bugs_nonce'); ?>
                                <input type="hidden" name="bug_id" value="<?php echo (int) $bug->id; ?>">
                                <button type="submit" name="gtp_reopen_bug" value="1" class="gtp-sem-action">Reopen</button>
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
add_shortcode('gtp_site_bugs', 'gtp_site_bugs_admin_shortcode');
