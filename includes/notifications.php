<?php
/**
 * Email notification helpers (announcements + monthly check-in reminders).
 * Respects each user's email_notifications preference (default ON).
 */

/** Whether a user row should receive notification emails. */
function gtp_user_wants_email_notifications($user) {
    if (!$user || empty($user->email) || !is_email($user->email)) {
        return false;
    }
    // Default ON when column missing / null
    if (!isset($user->email_notifications)) {
        return true;
    }
    return (int) $user->email_notifications !== 0;
}

function gtp_mail_headers() {
    return ['Content-Type: text/plain; charset=UTF-8'];
}

/**
 * Send plain-text email if the user has notifications enabled.
 * @return bool
 */
function gtp_notify_user_email($user, $subject, $body) {
    if (!gtp_user_wants_email_notifications($user)) {
        return false;
    }
    return (bool) wp_mail($user->email, $subject, $body, gtp_mail_headers());
}

/**
 * Email announcement recipients who have notifications on.
 * Sets announcements.emailed_at when attempted (even if some skip).
 */
function gtp_email_announcement_recipients($announcement_id) {
    global $wpdb;
    $announcement_id = (int) $announcement_id;
    if ($announcement_id <= 0) {
        return 0;
    }

    $ann = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gtp_announcements WHERE id = %d",
        $announcement_id
    ));
    if (!$ann) {
        return 0;
    }

    // Only email once send_at has arrived
    if ($ann->send_at > current_time('mysql')) {
        return 0;
    }

    // Already emailed
    if (!empty($ann->emailed_at)) {
        return 0;
    }

    $recipients = $wpdb->get_results($wpdb->prepare(
        "SELECT u.*
         FROM {$wpdb->prefix}gtp_announcement_recipients r
         INNER JOIN {$wpdb->prefix}gtp_users u ON u.id = r.user_id
         WHERE r.announcement_id = %d",
        $announcement_id
    ));

    $inbox_url = site_url('/index.php/announcements/');
    $sent = 0;
    foreach ((array) $recipients as $user) {
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($name === '') {
            $name = $user->username;
        }
        $subject = '[GTP] ' . $ann->title;
        $body = "Hi {$name},\n\n"
            . "You have a new GTP announcement.\n\n"
            . $ann->title . "\n\n"
            . $ann->body . "\n\n"
            . "View in GTP: {$inbox_url}\n\n"
            . "You can turn off email notifications in My Profile.\n";
        if (gtp_notify_user_email($user, $subject, $body)) {
            $sent++;
        }
    }

    $wpdb->update(
        $wpdb->prefix . 'gtp_announcements',
        ['emailed_at' => current_time('mysql')],
        ['id' => $announcement_id]
    );

    return $sent;
}

/** Send emails for any due announcements that have not been emailed yet. */
function gtp_process_due_announcement_emails() {
    global $wpdb;
    $now = current_time('mysql');
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}gtp_announcements
         WHERE send_at <= %s
           AND (emailed_at IS NULL OR emailed_at = '')
         ORDER BY send_at ASC
         LIMIT 25",
        $now
    ));
    foreach ((array) $ids as $id) {
        gtp_email_announcement_recipients((int) $id);
    }
}

/**
 * Email tutors who have open monthly check-ins and have not been notified for this month.
 */
function gtp_process_checkin_reminder_emails() {
    global $wpdb;

    $month = gtp_checkin_active_month();
    if (!gtp_checkin_month_is_open($month)) {
        return;
    }

    $tutors = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gtp_users
         WHERE role = 'tutor' AND validated = 1
           AND email IS NOT NULL AND email <> ''
           AND (email_notifications IS NULL OR email_notifications = 1)
           AND (checkin_notified_month IS NULL OR checkin_notified_month = '' OR checkin_notified_month <> %s)",
        $month
    ));

    $checkin_url = site_url('/index.php/monthly-checkins/');
    $month_label = gtp_checkin_month_label($month);

    foreach ((array) $tutors as $tutor) {
        $due = gtp_tutor_due_checkins((int) $tutor->id, $month);
        $count = count($due['items']);
        if ($count <= 0) {
            // Nothing due — still mark so we don't keep querying; or skip mark so if they get a new class they get emailed?
            // Better: only mark when we actually email OR when they have zero due (no need to email).
            $wpdb->update(
                $wpdb->prefix . 'gtp_users',
                ['checkin_notified_month' => $month],
                ['id' => (int) $tutor->id]
            );
            continue;
        }

        $name = trim(($tutor->first_name ?? '') . ' ' . ($tutor->last_name ?? ''));
        if ($name === '') {
            $name = $tutor->username;
        }

        $lines = [];
        foreach ($due['items'] as $classroom) {
            $lines[] = '• ' . gtp_checkin_classroom_label($classroom);
        }

        $subject = '[GTP] Monthly check-in due — ' . $month_label;
        $body = "Hi {$name},\n\n"
            . "Your monthly check-in for {$month_label} is open. "
            . "You have {$count} class check-in"
            . ($count === 1 ? '' : 's')
            . " still to complete:\n\n"
            . implode("\n", $lines) . "\n\n"
            . "Complete them here: {$checkin_url}\n\n"
            . "You can turn off email notifications in My Profile.\n";

        if (gtp_notify_user_email($tutor, $subject, $body)) {
            $wpdb->update(
                $wpdb->prefix . 'gtp_users',
                ['checkin_notified_month' => $month],
                ['id' => (int) $tutor->id]
            );
        }
    }
}

/**
 * Periodic notification processing (announcements + check-ins).
 * Runs at most once per hour via transient (works without WP-Cron hits too).
 */
function gtp_maybe_process_notification_emails() {
    if (get_transient('gtp_notify_tick')) {
        return;
    }
    set_transient('gtp_notify_tick', 1, HOUR_IN_SECONDS);

    if (function_exists('gtp_process_due_announcement_emails')) {
        gtp_process_due_announcement_emails();
    }
    if (function_exists('gtp_process_checkin_reminder_emails') && function_exists('gtp_checkin_active_month')) {
        gtp_process_checkin_reminder_emails();
    }
}
add_action('init', 'gtp_maybe_process_notification_emails', 30);

/** HTML for the email-notifications toggle used on profile forms. */
function gtp_email_notifications_toggle_html($enabled) {
    $enabled = (int) $enabled !== 0;
    ob_start();
    ?>
    <section class="gtp-profile-card gtp-email-notify-card">
        <div class="gtp-profile-card-title">
            <h2>Email notifications</h2>
            <p>Get emails for announcements and monthly check-in reminders.</p>
        </div>
        <input type="hidden" name="email_notifications" value="0">
        <label class="gtp-toggle-row">
            <input type="checkbox" name="email_notifications" value="1" <?php checked($enabled); ?>>
            <span>Email notifications</span>
        </label>
    </section>
    <?php
    return ob_get_clean();
}
