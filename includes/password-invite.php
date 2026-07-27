<?php
/**
 * Secure invite / set-password helpers and shortcode.
 * Tokens are random; only SHA-256 hashes are stored. Passwords are hashed with password_hash().
 */

function gtp_password_token_table() {
    global $wpdb;
    return $wpdb->prefix . 'gtp_password_tokens';
}

/**
 * Create a one-time invite token for a user. Returns the raw token (for the email link only).
 */
function gtp_create_password_invite_token($user_id, $hours_valid = 72) {
    global $wpdb;

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $table = gtp_password_token_table();

    // Invalidate unused prior invite tokens for this user
    $wpdb->query($wpdb->prepare(
        "UPDATE $table SET used_at = %s
         WHERE user_id = %d AND token_type = 'invite' AND used_at IS NULL",
        current_time('mysql'),
        $user_id
    ));

    $raw_token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $raw_token);
    $now = current_time('mysql');
    $expires_local = date('Y-m-d H:i:s', current_time('timestamp') + ((int) $hours_valid * HOUR_IN_SECONDS));

    $inserted = $wpdb->insert($table, [
        'user_id' => $user_id,
        'token_hash' => $token_hash,
        'token_type' => 'invite',
        'expires_at' => $expires_local,
        'used_at' => null,
        'created_at' => $now,
    ]);

    if (!$inserted) {
        return false;
    }

    return $raw_token;
}

function gtp_set_password_url($raw_token) {
    return add_query_arg(
        ['token' => $raw_token],
        site_url('/index.php/set-password/')
    );
}

/**
 * Email the invite link. Does not include any password.
 */
function gtp_send_password_invite_email($user, $raw_token) {
    if (empty($user->email) || empty($raw_token)) {
        return false;
    }

    $link = gtp_set_password_url($raw_token);
    $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
    if ($name === '') {
        $name = $user->username;
    }

    $subject = 'Set your GTP password';
    $message = "Hi {$name},\n\n"
        . "An account has been created for you on the GTP Tutor Management system.\n\n"
        . "Username: {$user->username}\n\n"
        . "Click the link below to choose your password. This link expires in 72 hours and can only be used once:\n\n"
        . "{$link}\n\n"
        . "If you did not expect this email, you can ignore it.\n";

    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    return wp_mail($user->email, $subject, $message, $headers);
}

/**
 * Look up a valid (unused, unexpired) token. Returns object with token row + user, or null.
 */
function gtp_find_valid_password_token($raw_token) {
    global $wpdb;

    $raw_token = trim((string) $raw_token);
    if ($raw_token === '' || strlen($raw_token) < 32) {
        return null;
    }

    $token_hash = hash('sha256', $raw_token);
    $table = gtp_password_token_table();
    $users = $wpdb->prefix . 'gtp_users';
    $now = current_time('mysql');

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT t.id AS token_id, t.user_id, t.expires_at, t.used_at, t.token_type,
                u.username, u.email, u.first_name, u.last_name, u.role
         FROM $table t
         INNER JOIN $users u ON u.id = t.user_id
         WHERE t.token_hash = %s
           AND t.used_at IS NULL
           AND t.expires_at >= %s
         LIMIT 1",
        $token_hash,
        $now
    ));

    return $row ?: null;
}

function gtp_mark_password_token_used($token_id) {
    global $wpdb;
    $table = gtp_password_token_table();
    $wpdb->update(
        $table,
        ['used_at' => current_time('mysql')],
        ['id' => (int) $token_id]
    );
}

/**
 * Persist a new password (hashed) and mark the account as password_set.
 */
function gtp_set_user_password($user_id, $plain_password) {
    global $wpdb;

    $user_id = (int) $user_id;
    $plain_password = (string) $plain_password;
    if ($user_id <= 0 || $plain_password === '') {
        return false;
    }

    $updated = $wpdb->update(
        $wpdb->prefix . 'gtp_users',
        [
            'password' => password_hash($plain_password, PASSWORD_DEFAULT),
            'password_set' => 1,
        ],
        ['id' => $user_id]
    );

    return $updated !== false;
}

function gtp_set_password_shortcode() {
    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
    if ($token === '' && isset($_POST['gtp_invite_token'])) {
        $token = sanitize_text_field(wp_unslash($_POST['gtp_invite_token']));
    }

    $message = '';
    $success = false;
    $token_row = $token !== '' ? gtp_find_valid_password_token($token) : null;

    if (isset($_POST['gtp_set_password_submit'])) {
        if (!isset($_POST['gtp_set_password_nonce']) || !wp_verify_nonce($_POST['gtp_set_password_nonce'], 'gtp_set_password')) {
            $message = '<p class="gtp-msg is-error gtp-persist">Security check failed. Please try again from your email link.</p>';
        } elseif (!$token_row) {
            $message = '<p class="gtp-msg is-error gtp-persist">This password link is invalid or has expired. Ask an administrator to send a new invite.</p>';
        } else {
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if (strlen($password) < 8) {
                $message = '<p class="gtp-msg is-error gtp-persist">Password must be at least 8 characters.</p>';
            } elseif ($password !== $confirm) {
                $message = '<p class="gtp-msg is-error gtp-persist">Passwords do not match.</p>';
            } elseif (!gtp_set_user_password((int) $token_row->user_id, $password)) {
                $message = '<p class="gtp-msg is-error">Could not save your password. Please try again.</p>';
            } else {
                gtp_mark_password_token_used((int) $token_row->token_id);
                $success = true;
                $message = '<p class="gtp-msg is-success">Your password has been set. You can now sign in.</p>';
                $token_row = null;
            }
        }
    }

    $login_url = site_url('/index.php/Welcome-to-GTP/');
    $logo_url = plugins_url('assets/images/gtp-logo.png', dirname(__DIR__) . '/tutor-management-plugin.php');

    ob_start();
    ?>
    <div class="gtp-login">
        <div class="gtp-login-main">
            <div class="gtp-login-main-inner">
                <div class="gtp-login-brand">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Global Teaching Project">
                </div>
                <h1>Set your password</h1>
                <?php echo $message; ?>

                <?php if ($success) : ?>
                    <p class="gtp-login-sub">You’re all set. You can now sign in.</p>
                    <p class="gtp-form-actions">
                        <a class="button button-primary gtp-login-submit" href="<?php echo esc_url($login_url); ?>">Go to sign in</a>
                    </p>
                <?php elseif (!$token_row) : ?>
                    <?php if ($message === '') : ?>
                        <p class="gtp-msg is-error gtp-persist">This password link is invalid or has expired. Ask an administrator to send a new invite.</p>
                    <?php endif; ?>
                    <p class="gtp-form-actions">
                        <a class="button button-primary gtp-login-submit" href="<?php echo esc_url($login_url); ?>">Back to sign in</a>
                    </p>
                <?php else : ?>
                    <p class="gtp-login-sub">
                        Hi <?php echo esc_html(trim($token_row->first_name . ' ' . $token_row->last_name) ?: $token_row->username); ?>,
                        choose a password for username <strong><?php echo esc_html($token_row->username); ?></strong>.
                    </p>
                    <form method="post" class="gtp-form-stack">
                        <?php wp_nonce_field('gtp_set_password', 'gtp_set_password_nonce'); ?>
                        <input type="hidden" name="gtp_invite_token" value="<?php echo esc_attr($token); ?>">

                        <label class="gtp-field">
                            <span>New password</span>
                            <input type="password" id="gtp_new_password" name="password" required minlength="8" autocomplete="new-password">
                        </label>
                        <label class="gtp-field">
                            <span>Confirm password</span>
                            <input type="password" id="gtp_confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                        </label>
                        <div class="gtp-form-actions">
                            <button type="submit" name="gtp_set_password_submit" value="1" class="button button-primary gtp-login-submit">Save password</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <aside class="gtp-login-aside" aria-hidden="true">
            <img class="gtp-login-aside-logo" src="<?php echo esc_url($logo_url); ?>" alt="">
        </aside>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_set_password', 'gtp_set_password_shortcode');
