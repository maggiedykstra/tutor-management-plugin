<?php
/**
 * Announcements / reminders for admins and tutors.
 */

function gtp_announcement_subject_choices() {
    return gtp_get_subjects();
}

function gtp_announcement_subjects_for_choice($choice) {
    return gtp_subject_match_values($choice);
}

/**
 * Resolve user IDs from audience checkboxes/config.
 *
 * @param array $audience {
 *   @type bool  $everyone
 *   @type bool  $all_tutors
 *   @type bool  $all_admins
 *   @type array $subjects  Subject choice labels
 *   @type array $schools   School names
 * }
 * @return int[]
 */
function gtp_resolve_announcement_recipients($audience) {
    global $wpdb;

    $users_table = $wpdb->prefix . 'gtp_users';
    $ids = [];

    $everyone = !empty($audience['everyone']);
    $all_tutors = !empty($audience['all_tutors']) || $everyone;
    $all_admins = !empty($audience['all_admins']) || $everyone;
    $subjects = array_filter(array_map('sanitize_text_field', (array) ($audience['subjects'] ?? [])));
    $schools = array_filter(array_map('sanitize_text_field', (array) ($audience['schools'] ?? [])));

    if ($all_tutors) {
        $tutor_ids = $wpdb->get_col("SELECT id FROM $users_table WHERE role = 'tutor' AND validated = 1");
        $ids = array_merge($ids, array_map('intval', $tutor_ids));
    }

    if ($all_admins) {
        $admin_ids = $wpdb->get_col("SELECT id FROM $users_table WHERE role = 'admin'");
        $ids = array_merge($ids, array_map('intval', $admin_ids));
    }

    if (!empty($subjects) || !empty($schools)) {
        $subject_values = [];
        foreach ($subjects as $choice) {
            $subject_values = array_merge($subject_values, gtp_announcement_subjects_for_choice($choice));
        }
        $subject_values = array_values(array_unique($subject_values));

        $conditions = [];
        $params = [];

        if (!empty($subject_values)) {
            $ph = implode(',', array_fill(0, count($subject_values), '%s'));
            $conditions[] = "c.subject IN ($ph)";
            foreach ($subject_values as $sv) {
                $params[] = $sv;
            }
        }
        if (!empty($schools)) {
            $ph = implode(',', array_fill(0, count($schools), '%s'));
            $conditions[] = "c.school IN ($ph)";
            foreach ($schools as $school) {
                $params[] = $school;
            }
        }

        if (!empty($conditions)) {
            // OR within subject/school targeting so either match qualifies
            $where = implode(' OR ', $conditions);
            $sql = "SELECT DISTINCT a.tutor_id
                    FROM {$wpdb->prefix}gtp_class_assignments a
                    INNER JOIN {$wpdb->prefix}gtp_classrooms c ON c.id = a.classroom_id
                    INNER JOIN $users_table u ON u.id = a.tutor_id
                    WHERE u.role = 'tutor' AND u.validated = 1 AND ($where)";
            $matched = $wpdb->get_col($wpdb->prepare($sql, $params));
            $ids = array_merge($ids, array_map('intval', $matched));
        }
    }

    $ids = array_values(array_unique(array_filter($ids)));
    sort($ids);
    return $ids;
}

function gtp_format_announcement_audience_label($audience) {
    $parts = [];
    if (!empty($audience['everyone'])) {
        return 'Everyone';
    }
    if (!empty($audience['all_tutors'])) {
        $parts[] = 'All tutors';
    }
    if (!empty($audience['all_admins'])) {
        $parts[] = 'All admins';
    }
    $subjects = array_filter((array) ($audience['subjects'] ?? []));
    if (!empty($subjects)) {
        $parts[] = 'Subjects: ' . implode(', ', $subjects);
    }
    $schools = array_filter((array) ($audience['schools'] ?? []));
    if (!empty($schools)) {
        $parts[] = 'Schools: ' . implode(', ', $schools);
    }
    return $parts ? implode(' · ', $parts) : 'No audience selected';
}

function gtp_save_announcement_recipients($announcement_id, array $user_ids) {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_announcement_recipients';
    $announcement_id = (int) $announcement_id;

    foreach ($user_ids as $user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            continue;
        }
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $table (announcement_id, user_id, read_at) VALUES (%d, %d, NULL)",
            $announcement_id,
            $user_id
        ));
    }
}

function gtp_count_unread_announcements($user_id) {
    global $wpdb;
    $user_id = (int) $user_id;
    $now = current_time('mysql');

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$wpdb->prefix}gtp_announcement_recipients r
         INNER JOIN {$wpdb->prefix}gtp_announcements a ON a.id = r.announcement_id
         WHERE r.user_id = %d
           AND r.read_at IS NULL
           AND a.send_at <= %s",
        $user_id,
        $now
    ));
}

function gtp_mark_announcement_read($announcement_id, $user_id) {
    global $wpdb;
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}gtp_announcement_recipients
         SET read_at = %s
         WHERE announcement_id = %d AND user_id = %d AND read_at IS NULL",
        current_time('mysql'),
        (int) $announcement_id,
        (int) $user_id
    ));
}

function gtp_get_user_announcements($user_id) {
    global $wpdb;
    $user_id = (int) $user_id;
    $now = current_time('mysql');

    return $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, r.read_at,
                u.first_name AS author_first_name,
                u.last_name AS author_last_name
         FROM {$wpdb->prefix}gtp_announcement_recipients r
         INNER JOIN {$wpdb->prefix}gtp_announcements a ON a.id = r.announcement_id
         LEFT JOIN {$wpdb->prefix}gtp_users u ON u.id = a.author_id
         WHERE r.user_id = %d
           AND a.send_at <= %s
         ORDER BY a.send_at DESC, a.id DESC",
        $user_id,
        $now
    ));
}

/**
 * Render announcements panel for dashboard home (marks read when ?ann=ID).
 */
function gtp_render_announcements_home_widget($user_id, $base_url = '', $limit = 8) {
    $user_id = (int) $user_id;
    if ($base_url === '') {
        $base_url = site_url('/index.php/announcements/');
    }

    $view_id = isset($_GET['ann']) ? intval($_GET['ann']) : 0;
    if ($view_id > 0) {
        gtp_mark_announcement_read($view_id, $user_id);
    }

    $items = gtp_get_user_announcements($user_id);
    $selected = null;
    foreach ($items as $item) {
        if ((int) $item->id === $view_id) {
            $selected = $item;
            break;
        }
    }

    $display_items = array_slice($items, 0, max(1, (int) $limit));
    $unread = gtp_count_unread_announcements($user_id);

    ob_start();
    ?>
    <section class="gtp-home-section gtp-home-announcements">
        <div class="gtp-home-ann-panel">
            <div class="gtp-home-ann-panel-header">
                <h2>Announcements<?php echo $unread > 0 ? ' <span class="gtp-home-ann-count">(' . (int) $unread . ' new)</span>' : ''; ?></h2>
                <div class="gtp-home-ann-panel-actions">
                    <?php if (!empty($_SESSION['gtp_user']['role']) && $_SESSION['gtp_user']['role'] === 'admin') : ?>
                        <a href="<?php echo esc_url(site_url('/index.php/manage-announcements/')); ?>">New announcement</a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(site_url('/index.php/announcements/')); ?>">View all</a>
                </div>
            </div>

            <?php if (empty($items)) : ?>
                <p class="gtp-home-empty gtp-home-ann-empty">No announcements yet.</p>
            <?php else : ?>
                <?php foreach ($display_items as $item) :
                    $is_unread = empty($item->read_at);
                    $is_active = ((int) $item->id === $view_id);
                    $url = add_query_arg(['ann' => $item->id], $base_url);
                    $classes = 'gtp-home-ann-item';
                    if ($is_unread) {
                        $classes .= ' is-unread';
                    }
                    if ($is_active) {
                        $classes .= ' is-active';
                    }
                    ?>
                    <div class="<?php echo esc_attr($classes); ?>">
                        <a class="gtp-home-ann-link" href="<?php echo esc_url($url); ?>">
                            <?php echo esc_html($item->title); ?>
                            <?php if ($is_unread) : ?><span class="gtp-home-ann-new"> · New</span><?php endif; ?>
                            <span class="gtp-home-ann-meta"><?php echo esc_html(date('M j, Y g:i A', strtotime($item->send_at))); ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>

                <?php if ($selected) : ?>
                    <div class="gtp-home-ann-detail">
                        <h3><?php echo esc_html($selected->title); ?></h3>
                        <div class="gtp-home-ann-meta">
                            <?php
                            $author = trim(($selected->author_first_name ?? '') . ' ' . ($selected->author_last_name ?? ''));
                            echo esc_html(($author !== '' ? $author : 'Admin') . ' · ' . date('M j, Y g:i A', strtotime($selected->send_at)));
                            ?>
                        </div>
                        <div class="gtp-home-ann-body"><?php echo esc_html($selected->body); ?></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function gtp_user_avatar_html($user, $class = 'gtp-home-avatar') {
    $first = trim((string) ($user->first_name ?? ''));
    $last = trim((string) ($user->last_name ?? ''));
    $initials = strtoupper(substr($first !== '' ? $first : 'U', 0, 1) . substr($last !== '' ? $last : '', 0, 1));
    $url = trim((string) ($user->headshot_url ?? ''));

    if ($url !== '') {
        return '<div class="' . esc_attr($class) . '"><img src="' . esc_url($url) . '" alt="' . esc_attr(trim($first . ' ' . $last)) . '"></div>';
    }
    return '<div class="' . esc_attr($class) . '" aria-hidden="true">' . esc_html($initials) . '</div>';
}

/**
 * Admin manage page: create / edit / delete announcements + list previous.
 */
function gtp_manage_announcements_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    global $wpdb;
    $message = '';
    if (!empty($_SESSION['gtp_announcement_flash'])) {
        $message = $_SESSION['gtp_announcement_flash'];
        unset($_SESSION['gtp_announcement_flash']);
    }
    $author_id = (int) $_SESSION['gtp_user']['id'];
    $table = $wpdb->prefix . 'gtp_announcements';
    $recipients_table = $wpdb->prefix . 'gtp_announcement_recipients';
    $schools = $wpdb->get_col("SELECT DISTINCT school FROM {$wpdb->prefix}gtp_classrooms WHERE school IS NOT NULL AND school <> '' ORDER BY school ASC");
    $subject_choices = gtp_announcement_subject_choices();
    $manage_url = site_url('/index.php/manage-announcements/');

    // Delete
    if (isset($_POST['gtp_delete_announcement']) && check_admin_referer('gtp_delete_announcement_' . (int) ($_POST['announcement_id'] ?? 0), 'gtp_announcement_nonce')) {
        $delete_id = (int) ($_POST['announcement_id'] ?? 0);
        if ($delete_id > 0) {
            $wpdb->delete($recipients_table, ['announcement_id' => $delete_id]);
            $wpdb->delete($table, ['id' => $delete_id]);
            $_SESSION['gtp_announcement_flash'] = '<p class="gtp-msg is-success">Announcement deleted.</p>';
            wp_safe_redirect($manage_url);
            exit;
        }
    }

    $editing = null;
    $edit_id = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
    if ($edit_id > 0) {
        $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $edit_id));
        if (!$editing) {
            $message = '<p class="gtp-msg is-error gtp-persist">Announcement not found.</p>';
            $edit_id = 0;
        }
    }

    // Create or update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['gtp_create_announcement']) || isset($_POST['gtp_update_announcement']))) {
        $is_update = isset($_POST['gtp_update_announcement']);
        $nonce_action = $is_update
            ? 'gtp_update_announcement_' . (int) ($_POST['announcement_id'] ?? 0)
            : 'gtp_create_announcement';

        if (!isset($_POST['gtp_announcement_nonce']) || !wp_verify_nonce($_POST['gtp_announcement_nonce'], $nonce_action)) {
            $message = '<p class="gtp-msg is-error gtp-persist">Security check failed. Please try again.</p>';
        } else {
            $title = sanitize_text_field($_POST['title'] ?? '');
            $body = sanitize_textarea_field($_POST['body'] ?? '');
            $send_mode = sanitize_text_field($_POST['send_mode'] ?? 'now');
            $update_id = $is_update ? (int) ($_POST['announcement_id'] ?? 0) : 0;

            $audience = [
                'everyone' => !empty($_POST['audience_everyone']),
                'all_tutors' => !empty($_POST['audience_all_tutors']),
                'all_admins' => !empty($_POST['audience_all_admins']),
                'subjects' => isset($_POST['audience_subjects']) ? array_map('sanitize_text_field', (array) $_POST['audience_subjects']) : [],
                'schools' => isset($_POST['audience_schools']) ? array_map('sanitize_text_field', (array) $_POST['audience_schools']) : [],
            ];

            if ($title === '' || $body === '') {
                $message = '<p class="gtp-msg is-error gtp-persist">Title and message are required.</p>';
            } elseif (
                empty($audience['everyone'])
                && empty($audience['all_tutors'])
                && empty($audience['all_admins'])
                && empty($audience['subjects'])
                && empty($audience['schools'])
            ) {
                $message = '<p class="gtp-msg is-error gtp-persist">Select at least one audience option.</p>';
            } else {
                $send_at = null;
                if ($send_mode === 'schedule') {
                    $raw = sanitize_text_field($_POST['send_at'] ?? '');
                    $ts = strtotime($raw);
                    if (!$ts) {
                        $message = '<p class="gtp-msg is-error gtp-persist">Please choose a valid send date and time.</p>';
                    } else {
                        $send_at = date('Y-m-d H:i:s', $ts);
                    }
                } else {
                    $send_at = current_time('mysql');
                }

                if ($message === '' && $send_at) {
                    $recipient_ids = gtp_resolve_announcement_recipients($audience);
                    if (empty($recipient_ids)) {
                        $message = '<p class="gtp-msg is-error gtp-persist">No matching recipients found for that audience.</p>';
                    } else {
                        $label = gtp_format_announcement_audience_label($audience);
                        $payload = [
                            'title' => $title,
                            'body' => $body,
                            'audience_json' => wp_json_encode($audience),
                            'audience_label' => $label,
                            'send_at' => $send_at,
                        ];

                        if ($is_update && $update_id > 0) {
                            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $update_id));
                            if (!$existing) {
                                $message = '<p class="gtp-msg is-error gtp-persist">Announcement not found.</p>';
                            } else {
                                // If still scheduled / not emailed, keep emailed_at null so emails go out at send time.
                                // If already emailed, do not re-send on edit.
                                $already_emailed = !empty($existing->emailed_at);
                                $wpdb->update($table, $payload, ['id' => $update_id]);
                                if (!$already_emailed) {
                                    $wpdb->query($wpdb->prepare(
                                        "UPDATE $table SET emailed_at = NULL WHERE id = %d",
                                        $update_id
                                    ));
                                }
                                $wpdb->delete($recipients_table, ['announcement_id' => $update_id]);
                                gtp_save_announcement_recipients($update_id, $recipient_ids);

                                if (!$already_emailed && $send_at <= current_time('mysql')) {
                                    gtp_email_announcement_recipients($update_id);
                                }

                                $when = ($send_mode === 'schedule' && $send_at > current_time('mysql'))
                                    ? 'Scheduled for ' . date('M j, Y g:i A', strtotime($send_at))
                                    : 'Visible now';
                                $message = '<p class="gtp-msg is-success">Announcement updated. '
                                    . esc_html($when)
                                    . ' · '
                                    . count($recipient_ids)
                                    . ' recipient(s).</p>';
                                $_SESSION['gtp_announcement_flash'] = $message;
                                wp_safe_redirect($manage_url);
                                exit;
                            }
                        } else {
                            $payload['author_id'] = $author_id;
                            $payload['created_at'] = current_time('mysql');

                            $inserted = $wpdb->insert($table, $payload);
                            if ($inserted) {
                                $announcement_id = (int) $wpdb->insert_id;
                                gtp_save_announcement_recipients($announcement_id, $recipient_ids);

                                if ($send_at <= current_time('mysql')) {
                                    gtp_email_announcement_recipients($announcement_id);
                                }

                                $when = ($send_mode === 'schedule' && $send_at > current_time('mysql'))
                                    ? 'Scheduled for ' . date('M j, Y g:i A', strtotime($send_at))
                                    : 'Sent now';
                                $message = '<p class="gtp-msg is-success">Announcement saved. '
                                    . esc_html($when)
                                    . ' to '
                                    . count($recipient_ids)
                                    . ' recipient(s). Recipients with email notifications on were emailed.</p>';
                            } else {
                                $message = '<p class="gtp-msg is-error">Could not save announcement: ' . esc_html($wpdb->last_error) . '</p>';
                            }
                        }
                    }
                }
            }
        }
    }

    // Reload editing row after failed update attempts
    if ($edit_id > 0 && !$editing) {
        $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $edit_id));
    }

    $form_audience = [
        'everyone' => false,
        'all_tutors' => false,
        'all_admins' => false,
        'subjects' => [],
        'schools' => [],
    ];
    $form_title = '';
    $form_body = '';
    $form_send_mode = 'now';
    $form_send_at = '';

    if ($editing) {
        $form_title = $editing->title;
        $form_body = $editing->body;
        $decoded = json_decode((string) $editing->audience_json, true);
        if (is_array($decoded)) {
            $form_audience = array_merge($form_audience, $decoded);
        }
        if ($editing->send_at > current_time('mysql')) {
            $form_send_mode = 'schedule';
            $form_send_at = date('Y-m-d\TH:i', strtotime($editing->send_at));
        }
    }

    $previous = $wpdb->get_results(
        "SELECT a.*,
                (SELECT COUNT(*) FROM $recipients_table r WHERE r.announcement_id = a.id) AS recipient_count,
                (SELECT COUNT(*) FROM $recipients_table r WHERE r.announcement_id = a.id AND r.read_at IS NOT NULL) AS read_count
         FROM $table a
         ORDER BY a.created_at DESC, a.id DESC"
    );

    $now = current_time('mysql');

    ob_start();
    ?>
    <div class="gtp-announcements-wrap">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <p style="margin:-6px 0 14px;"><a href="<?php echo esc_url(site_url('/index.php/announcements/')); ?>">My Announcements Inbox</a></p>
        <h1 class="gtp-page-title">Announcements</h1>
        <?php echo $message; ?>

        <h3><?php echo $editing ? 'Edit Announcement' : 'New Announcement'; ?></h3>
        <?php if ($editing) : ?>
            <p class="gtp-people-muted" style="margin-top:-6px;">
                Editing #<?php echo (int) $editing->id; ?>.
                <a href="<?php echo esc_url($manage_url); ?>">Cancel edit</a>
            </p>
        <?php endif; ?>

        <form method="post" class="gtp-announcement-form">
            <?php if ($editing) :
                wp_nonce_field('gtp_update_announcement_' . (int) $editing->id, 'gtp_announcement_nonce');
                ?>
                <input type="hidden" name="announcement_id" value="<?php echo (int) $editing->id; ?>">
            <?php else :
                wp_nonce_field('gtp_create_announcement', 'gtp_announcement_nonce');
            endif; ?>

            <p>
                <label for="gtp_ann_title"><strong>Title</strong></label><br>
                <input type="text" id="gtp_ann_title" name="title" required value="<?php echo esc_attr($form_title); ?>" style="width:100%; max-width:640px; padding:8px;">
            </p>
            <p>
                <label for="gtp_ann_body"><strong>Message</strong></label><br>
                <textarea id="gtp_ann_body" name="body" rows="6" required style="width:100%; max-width:640px; padding:8px;"><?php echo esc_textarea($form_body); ?></textarea>
            </p>

            <fieldset style="border:1px solid #ccc; padding:12px 16px; margin:16px 0; max-width:640px;">
                <legend><strong>Send to</strong></legend>
                <label style="display:block; margin-bottom:6px;">
                    <input type="checkbox" name="audience_everyone" value="1" id="gtp_aud_everyone" <?php checked(!empty($form_audience['everyone'])); ?>> Everyone
                </label>
                <label style="display:block; margin-bottom:6px;">
                    <input type="checkbox" name="audience_all_tutors" value="1" class="gtp-aud-opt" <?php checked(!empty($form_audience['all_tutors'])); ?>> All tutors
                </label>
                <label style="display:block; margin-bottom:10px;">
                    <input type="checkbox" name="audience_all_admins" value="1" class="gtp-aud-opt" <?php checked(!empty($form_audience['all_admins'])); ?>> All admins
                </label>

                <p style="margin:8px 0 4px;"><strong>Tutors in subjects</strong></p>
                <?php foreach ($subject_choices as $choice) : ?>
                    <label style="display:inline-block; margin:0 14px 8px 0;">
                        <input type="checkbox" name="audience_subjects[]" value="<?php echo esc_attr($choice); ?>" class="gtp-aud-opt"
                            <?php checked(in_array($choice, (array) ($form_audience['subjects'] ?? []), true)); ?>>
                        <?php echo esc_html($choice); ?>
                    </label>
                <?php endforeach; ?>

                <p style="margin:12px 0 4px;"><strong>Tutors at schools</strong></p>
                <?php if (empty($schools)) : ?>
                    <p style="color:#666; margin:0;">No schools found yet.</p>
                <?php else : ?>
                    <?php foreach ($schools as $school) : ?>
                        <label style="display:block; margin-bottom:4px;">
                            <input type="checkbox" name="audience_schools[]" value="<?php echo esc_attr($school); ?>" class="gtp-aud-opt"
                                <?php checked(in_array($school, (array) ($form_audience['schools'] ?? []), true)); ?>>
                            <?php echo esc_html($school); ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </fieldset>

            <fieldset style="border:1px solid #ccc; padding:12px 16px; margin:16px 0; max-width:640px;">
                <legend><strong>When to send</strong></legend>
                <label style="display:block; margin-bottom:8px;">
                    <input type="radio" name="send_mode" value="now" <?php checked($form_send_mode, 'now'); ?>> Send now
                </label>
                <label style="display:block; margin-bottom:8px;">
                    <input type="radio" name="send_mode" value="schedule" id="gtp_send_schedule" <?php checked($form_send_mode, 'schedule'); ?>> Schedule for later
                </label>
                <p id="gtp_schedule_fields" style="<?php echo $form_send_mode === 'schedule' ? '' : 'display:none;'; ?> margin:8px 0 0;">
                    <label for="gtp_send_at">Date &amp; time</label><br>
                    <input type="datetime-local" id="gtp_send_at" name="send_at" step="60" value="<?php echo esc_attr($form_send_at); ?>">
                </p>
            </fieldset>

            <p>
                <?php if ($editing) : ?>
                    <button type="submit" name="gtp_update_announcement" value="1" class="button button-primary">Save changes</button>
                    <a class="button" href="<?php echo esc_url($manage_url); ?>">Cancel</a>
                <?php else : ?>
                    <button type="submit" name="gtp_create_announcement" class="button button-primary">Create Announcement</button>
                <?php endif; ?>
            </p>
        </form>

        <script>
        (function () {
            const scheduleFields = document.getElementById('gtp_schedule_fields');
            const sendAt = document.getElementById('gtp_send_at');
            const everyone = document.getElementById('gtp_aud_everyone');
            const opts = document.querySelectorAll('.gtp-aud-opt');

            function toggleSchedule() {
                const scheduled = document.querySelector('input[name="send_mode"]:checked').value === 'schedule';
                scheduleFields.style.display = scheduled ? '' : 'none';
                if (sendAt) {
                    sendAt.required = scheduled;
                }
            }
            document.querySelectorAll('input[name="send_mode"]').forEach(function (el) {
                el.addEventListener('change', toggleSchedule);
            });
            toggleSchedule();

            function syncEveryone() {
                if (!everyone) return;
                if (everyone.checked) {
                    opts.forEach(function (cb) { cb.checked = false; cb.disabled = true; });
                } else {
                    opts.forEach(function (cb) { cb.disabled = false; });
                }
            }
            if (everyone) {
                everyone.addEventListener('change', syncEveryone);
                syncEveryone();
            }
        })();
        </script>

        <h3 style="margin-top:36px;">Previous Announcements</h3>
        <?php if (empty($previous)) : ?>
            <p>No announcements yet.</p>
        <?php else : ?>
            <div style="overflow-x:auto;">
                <table class="gtp-announcements-table" style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="border:1px solid #ccc; padding:8px; background:#f2f2f2; text-align:left;">Title</th>
                            <th style="border:1px solid #ccc; padding:8px; background:#f2f2f2; text-align:left;">Audience</th>
                            <th style="border:1px solid #ccc; padding:8px; background:#f2f2f2; text-align:left;">Send time</th>
                            <th style="border:1px solid #ccc; padding:8px; background:#f2f2f2; text-align:left;">Status</th>
                            <th style="border:1px solid #ccc; padding:8px; background:#f2f2f2; text-align:left;">Recipients</th>
                            <th style="border:1px solid #ccc; padding:8px; background:#f2f2f2; text-align:left;">Read</th>
                            <th style="border:1px solid #ccc; padding:8px; background:#f2f2f2; text-align:left;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previous as $row) :
                            $is_future = $row->send_at > $now;
                            $status = $is_future ? 'Scheduled' : 'Sent';
                            if (!$is_future && empty($row->emailed_at)) {
                                $status .= ' (email pending)';
                            }
                            ?>
                            <tr>
                                <td style="border:1px solid #ccc; padding:8px; vertical-align:top;">
                                    <strong><?php echo esc_html($row->title); ?></strong>
                                    <div style="margin-top:6px; white-space:pre-wrap; color:#444;"><?php echo esc_html($row->body); ?></div>
                                </td>
                                <td style="border:1px solid #ccc; padding:8px; vertical-align:top;"><?php echo esc_html($row->audience_label); ?></td>
                                <td style="border:1px solid #ccc; padding:8px; vertical-align:top; white-space:nowrap;"><?php echo esc_html(date('M j, Y g:i A', strtotime($row->send_at))); ?></td>
                                <td style="border:1px solid #ccc; padding:8px; vertical-align:top;"><?php echo esc_html($status); ?></td>
                                <td style="border:1px solid #ccc; padding:8px; vertical-align:top;"><?php echo (int) $row->recipient_count; ?></td>
                                <td style="border:1px solid #ccc; padding:8px; vertical-align:top;"><?php echo (int) $row->read_count; ?> / <?php echo (int) $row->recipient_count; ?></td>
                                <td style="border:1px solid #ccc; padding:8px; vertical-align:top; white-space:nowrap;">
                                    <a class="button" href="<?php echo esc_url(add_query_arg('edit_id', (int) $row->id, $manage_url)); ?>">Edit</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this announcement?');">
                                        <?php wp_nonce_field('gtp_delete_announcement_' . (int) $row->id, 'gtp_announcement_nonce'); ?>
                                        <input type="hidden" name="announcement_id" value="<?php echo (int) $row->id; ?>">
                                        <button type="submit" name="gtp_delete_announcement" value="1" class="button">Delete</button>
                                    </form>
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
add_shortcode('gtp_manage_announcements', 'gtp_manage_announcements_shortcode');

/**
 * Shared inbox for tutors and admins.
 */
function gtp_announcements_inbox_shortcode() {
    if (!isset($_SESSION['gtp_user'])) {
        return '<p>You do not have access to this page.</p>';
    }

    $role = $_SESSION['gtp_user']['role'] ?? '';
    if ($role !== 'admin' && $role !== 'tutor') {
        return '<p>You do not have access to this page.</p>';
    }

    $user_id = (int) $_SESSION['gtp_user']['id'];
    $dash = ($role === 'admin')
        ? site_url('/index.php/admin-dashboard/')
        : site_url('/index.php/TA-dashboard/');

    $view_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
    if ($view_id > 0) {
        gtp_mark_announcement_read($view_id, $user_id);
    }

    $items = gtp_get_user_announcements($user_id);
    $selected = null;
    foreach ($items as $item) {
        if ((int) $item->id === $view_id) {
            $selected = $item;
            break;
        }
    }

    ob_start();
    ?>
    <div class="gtp-announcements-inbox">
        <?php echo gtp_dashboard_back_link(); ?>
        <?php if ($role === 'admin') : ?>
            <p style="margin:-6px 0 14px;"><a href="<?php echo esc_url(site_url('/index.php/manage-announcements/')); ?>">Manage / Create Announcements</a></p>
        <?php endif; ?>

        <h1 class="gtp-page-title">Announcements</h1>

        <div class="gtp-ann-inbox-layout" style="display:flex; flex-wrap:wrap; gap:24px; align-items:flex-start;">
            <div class="gtp-ann-list" style="flex:1 1 280px; min-width:240px; max-width:420px;">
                <h3 style="margin-top:0;">All announcements</h3>
                <?php if (empty($items)) : ?>
                    <p>No announcements yet.</p>
                <?php else : ?>
                    <ul style="list-style:none; margin:0; padding:0;">
                        <?php foreach ($items as $item) :
                            $unread = empty($item->read_at);
                            $url = add_query_arg(['view' => $item->id], site_url('/index.php/announcements/'));
                            $weight = $unread ? '700' : '400';
                            $bg = ((int) $item->id === $view_id) ? '#e8f4fc' : ($unread ? '#fff' : '#fafafa');
                            ?>
                            <li style="border:1px solid #ddd; margin-bottom:8px; background:<?php echo esc_attr($bg); ?>;">
                                <a href="<?php echo esc_url($url); ?>" style="display:block; padding:10px 12px; text-decoration:none; color:#222; font-weight:<?php echo esc_attr($weight); ?>;">
                                    <div><?php echo esc_html($item->title); ?><?php echo $unread ? ' <span style="color:#0073aa;">• New</span>' : ''; ?></div>
                                    <div style="font-size:12px; color:#666; font-weight:400; margin-top:4px;">
                                        <?php echo esc_html(date('M j, Y g:i A', strtotime($item->send_at))); ?>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="gtp-ann-detail" style="flex:2 1 320px; min-width:280px;">
                <?php if ($selected) : ?>
                    <article style="border:1px solid #ccc; padding:16px 18px;">
                        <h3 style="margin-top:0;"><?php echo esc_html($selected->title); ?></h3>
                        <p style="color:#666; font-size:13px; margin-top:0;">
                            <?php
                            $author = trim(($selected->author_first_name ?? '') . ' ' . ($selected->author_last_name ?? ''));
                            echo esc_html(($author !== '' ? $author : 'Admin') . ' · ' . date('M j, Y g:i A', strtotime($selected->send_at)));
                            ?>
                        </p>
                        <div style="white-space:pre-wrap; line-height:1.5;"><?php echo esc_html($selected->body); ?></div>
                    </article>
                <?php else : ?>
                    <p style="color:#666;">Select an announcement to read it. Unread items appear in bold.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_announcements_inbox', 'gtp_announcements_inbox_shortcode');
