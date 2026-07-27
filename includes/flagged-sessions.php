<?php
/**
 * Admin alerts for flagged sessions (teacher absent / class no-show).
 */

function gtp_flagged_sessions_where_sql() {
    return '(s.teacher_present = 0 OR s.no_show = 1)';
}

function gtp_get_flagged_sessions($admin_id = 0) {
    global $wpdb;
    $admin_id = (int) $admin_id;
    $flag = gtp_flagged_sessions_where_sql();

    if ($admin_id > 0) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*,
                    r.reviewed_at
             FROM {$wpdb->prefix}gtp_sessions s
             LEFT JOIN {$wpdb->prefix}gtp_flagged_session_reviews r
                ON r.session_id = s.id AND r.admin_id = %d
             WHERE $flag
             ORDER BY (r.reviewed_at IS NULL) DESC, s.session_date DESC, s.id DESC",
            $admin_id
        ));
    }

    return $wpdb->get_results(
        "SELECT s.*, NULL AS reviewed_at
         FROM {$wpdb->prefix}gtp_sessions s
         WHERE $flag
         ORDER BY s.session_date DESC, s.id DESC"
    );
}

function gtp_count_unreviewed_flagged_sessions($admin_id) {
    global $wpdb;
    $admin_id = (int) $admin_id;
    $flag = gtp_flagged_sessions_where_sql();

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$wpdb->prefix}gtp_sessions s
         LEFT JOIN {$wpdb->prefix}gtp_flagged_session_reviews r
            ON r.session_id = s.id AND r.admin_id = %d
         WHERE $flag
           AND r.id IS NULL",
        $admin_id
    ));
}

function gtp_count_flagged_sessions() {
    global $wpdb;
    $flag = gtp_flagged_sessions_where_sql();
    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_sessions s WHERE $flag"
    );
}

function gtp_mark_flagged_session_reviewed($session_id, $admin_id) {
    global $wpdb;
    $session_id = (int) $session_id;
    $admin_id = (int) $admin_id;
    if ($session_id <= 0 || $admin_id <= 0) {
        return;
    }

    $table = $wpdb->prefix . 'gtp_flagged_session_reviews';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE session_id = %d AND admin_id = %d",
        $session_id,
        $admin_id
    ));

    if ($exists) {
        return;
    }

    $wpdb->insert($table, [
        'session_id' => $session_id,
        'admin_id' => $admin_id,
        'reviewed_at' => current_time('mysql'),
    ]);
}

function gtp_flagged_session_reasons($session) {
    $reasons = [];
    if (!empty($session->no_show)) {
        $reasons[] = 'Class did not show up';
    }
    if (isset($session->teacher_present) && (int) $session->teacher_present === 0) {
        $reasons[] = 'Teacher was not present';
    }
    return $reasons;
}

function gtp_render_flagged_session_detail($session) {
    $reasons = gtp_flagged_session_reasons($session);
    $tutor = trim(($session->first_name ?? '') . ' ' . ($session->last_name ?? ''));
    ob_start();
    ?>
    <div class="gtp-home-ann-detail gtp-flag-detail">
        <h3><?php echo esc_html(date('M j, Y', strtotime($session->session_date))); ?> · <?php echo esc_html($session->school); ?></h3>
        <div class="gtp-home-ann-meta">
            <?php echo esc_html(implode(' · ', $reasons)); ?>
        </div>
        <p style="margin:10px 0 4px;"><strong>Tutor:</strong> <?php echo esc_html($tutor); ?></p>
        <p style="margin:4px 0;"><strong>Subject:</strong> <?php echo esc_html($session->subject); ?></p>
        <p style="margin:4px 0;"><strong>Teacher:</strong> <?php echo esc_html($session->teacher_name); ?></p>
        <?php if (!empty($session->topic)) : ?>
            <p style="margin:4px 0;"><strong>Topic:</strong> <?php echo esc_html($session->topic); ?></p>
        <?php endif; ?>
        <?php if (!empty($session->comments)) : ?>
            <p style="margin:4px 0;"><strong>Notes:</strong> <?php echo esc_html($session->comments); ?></p>
        <?php endif; ?>
        <?php
        $time = '';
        if (function_exists('gtp_format_time_range')) {
            $time = gtp_format_time_range($session->start_time ?? null, $session->end_time ?? null);
        }
        if ($time === '' && !empty($session->time_slot)) {
            $time = $session->time_slot;
        }
        if ($time !== '') :
            ?>
            <p style="margin:4px 0;"><strong>Time:</strong> <?php echo esc_html($time); ?></p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Dashboard widget: inbox-style flagged sessions.
 */
function gtp_render_flagged_sessions_home_widget($admin_id, $base_url = '', $limit = 8) {
    $admin_id = (int) $admin_id;
    if ($base_url === '') {
        $base_url = site_url('/index.php/admin-dashboard/');
    }

    $view_id = isset($_GET['flag']) ? intval($_GET['flag']) : 0;
    if ($view_id > 0) {
        // Only mark if this session is actually flagged
        global $wpdb;
        $flag = gtp_flagged_sessions_where_sql();
        $is_flagged = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_sessions s WHERE s.id = %d AND $flag",
            $view_id
        ));
        if ($is_flagged) {
            gtp_mark_flagged_session_reviewed($view_id, $admin_id);
        }
    }

    $items = gtp_get_flagged_sessions($admin_id);
    $selected = null;
    foreach ($items as $item) {
        if ((int) $item->id === $view_id) {
            $selected = $item;
            break;
        }
    }

    $display_items = array_slice($items, 0, max(1, (int) $limit));
    $unreviewed = gtp_count_unreviewed_flagged_sessions($admin_id);
    $total = count($items);

    ob_start();
    ?>
    <section class="gtp-home-section gtp-home-flagged" id="flagged-sessions">
        <div class="gtp-home-section-header">
            <h2>Flagged sessions<?php echo $unreviewed > 0 ? ' <span style="color:#b45309;font-size:0.9rem;">(' . (int) $unreviewed . ' new)</span>' : ''; ?></h2>
            <a href="<?php echo esc_url(site_url('/index.php/flagged-sessions/')); ?>">See all flagged sessions</a>
        </div>

        <?php if ($total === 0) : ?>
            <p class="gtp-home-empty">No flagged sessions.</p>
        <?php else : ?>
            <div class="gtp-home-ann-panel">
                <?php foreach ($display_items as $item) :
                    $is_new = empty($item->reviewed_at);
                    $is_active = ((int) $item->id === $view_id);
                    $url = add_query_arg(['flag' => $item->id], $base_url) . '#flagged-sessions';
                    $classes = 'gtp-home-ann-item';
                    if ($is_new) {
                        $classes .= ' is-unread';
                    }
                    if ($is_active) {
                        $classes .= ' is-active';
                    }
                    $reasons = gtp_flagged_session_reasons($item);
                    $label = date('M j, Y', strtotime($item->session_date)) . ' · ' . $item->school;
                    ?>
                    <div class="<?php echo esc_attr($classes); ?>">
                        <a class="gtp-home-ann-link" href="<?php echo esc_url($url); ?>">
                            <?php echo esc_html($label); ?>
                            <?php if ($is_new) : ?><span class="gtp-home-ann-new gtp-flag-new"> · New</span><?php endif; ?>
                            <span class="gtp-home-ann-meta"><?php echo esc_html(implode(' · ', $reasons)); ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>

                <?php if ($selected) : ?>
                    <?php echo gtp_render_flagged_session_detail($selected); ?>
                <?php endif; ?>
            </div>
            <p style="margin-top:10px;">
                <a class="button" href="<?php echo esc_url(site_url('/index.php/flagged-sessions/')); ?>">See all flagged sessions</a>
            </p>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

/**
 * Full-page list of all flagged sessions.
 */
function gtp_flagged_sessions_shortcode() {
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    $admin_id = (int) $_SESSION['gtp_user']['id'];
    $base = site_url('/index.php/flagged-sessions/');

    $view_id = isset($_GET['flag']) ? intval($_GET['flag']) : 0;
    if ($view_id > 0) {
        global $wpdb;
        $flag = gtp_flagged_sessions_where_sql();
        $is_flagged = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gtp_sessions s WHERE s.id = %d AND $flag",
            $view_id
        ));
        if ($is_flagged) {
            gtp_mark_flagged_session_reviewed($view_id, $admin_id);
        }
    }

    $items = gtp_get_flagged_sessions($admin_id);
    $selected = null;
    foreach ($items as $item) {
        if ((int) $item->id === $view_id) {
            $selected = $item;
            break;
        }
    }

    ob_start();
    ?>
    <div class="gtp-home" style="max-width:1000px;">
        <?php echo gtp_dashboard_back_link('admin'); ?>
        <h1 class="gtp-page-title">All flagged sessions</h1>
        <p>Sessions where the teacher was not present and/or the class did not show up. New items appear in bold until you open them.</p>

        <?php if (empty($items)) : ?>
            <p>No flagged sessions.</p>
        <?php else : ?>
            <div class="gtp-ann-inbox-layout" style="display:flex; flex-wrap:wrap; gap:24px; align-items:flex-start;">
                <div style="flex:1 1 280px; min-width:240px; max-width:420px;">
                    <div class="gtp-home-ann-panel">
                        <?php foreach ($items as $item) :
                            $is_new = empty($item->reviewed_at);
                            $is_active = ((int) $item->id === $view_id);
                            $url = add_query_arg(['flag' => $item->id], $base);
                            $classes = 'gtp-home-ann-item';
                            if ($is_new) {
                                $classes .= ' is-unread';
                            }
                            if ($is_active) {
                                $classes .= ' is-active';
                            }
                            $reasons = gtp_flagged_session_reasons($item);
                            $label = date('M j, Y', strtotime($item->session_date)) . ' · ' . $item->school;
                            ?>
                            <div class="<?php echo esc_attr($classes); ?>">
                                <a class="gtp-home-ann-link" href="<?php echo esc_url($url); ?>">
                                    <?php echo esc_html($label); ?>
                                    <?php if ($is_new) : ?><span class="gtp-home-ann-new gtp-flag-new"> · New</span><?php endif; ?>
                                    <span class="gtp-home-ann-meta"><?php echo esc_html(trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? '')) . ' · ' . implode(' · ', $reasons)); ?></span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div style="flex:2 1 320px; min-width:280px;">
                    <?php if ($selected) : ?>
                        <?php echo gtp_render_flagged_session_detail($selected); ?>
                    <?php else : ?>
                        <p style="color:#666;">Select a flagged session to review it. Unread items appear in bold.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_flagged_sessions', 'gtp_flagged_sessions_shortcode');
