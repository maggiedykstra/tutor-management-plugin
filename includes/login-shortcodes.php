<?php
function gtp_login_shortcode() {
    $message = '';

    if (isset($_POST['gtp_login_submit'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'gtp_users';

        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE username = %s", $username));

        if ($user && isset($user->password_set) && (int) $user->password_set === 0) {
            $message = '<p class="gtp-msg is-error gtp-persist">Please set your password using the invite link sent to your email before logging in.</p>';
        } elseif ($user && password_verify($password, $user->password)) {
            $_SESSION['gtp_user'] = [
                'id'         => $user->id,
                'username'   => $user->username,
                'email'      => $user->email,
                'role'       => $user->role,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
            ];
            if ($user->role === 'admin') {
                wp_redirect(site_url('/index.php/admin-dashboard'));
                exit;
            }
            if ($user->role === 'tutor' || $user->role === 'pending') {
                if ($user->validated) {
                    wp_redirect(site_url('/index.php/TA-dashboard'));
                    exit;
                }
                $message = '<p class="gtp-msg is-error gtp-persist">Your account is pending approval. Please wait for an administrator to validate your account.</p>';
            }
        } else {
            $message = '<p class="gtp-msg is-error gtp-persist">Sign-in failed. Check your username and password, or register for an account.</p>';
        }
    }

    if (isset($_POST['gtp_register_submit'])) {
        wp_redirect(site_url('/index.php/registration-page'));
        exit;
    }

    ob_start();
    $logo_url = plugins_url('assets/images/gtp-logo.png', dirname(__DIR__) . '/tutor-management-plugin.php');
    ?>
    <div class="gtp-login">
        <div class="gtp-login-main">
            <div class="gtp-login-main-inner">
                <h1>Welcome back</h1>
                <p class="gtp-login-sub">Please enter your details</p>
                <?php echo $message; ?>

                <form method="post" class="gtp-form-stack">
                    <label class="gtp-field">
                        <span>Username</span>
                        <input type="text" name="username" required autocomplete="username" autofocus>
                    </label>
                    <label class="gtp-field">
                        <span>Password</span>
                        <input type="password" name="password" required autocomplete="current-password">
                    </label>
                    <div class="gtp-form-actions">
                        <button type="submit" name="gtp_login_submit" value="1" class="button button-primary gtp-login-submit">Sign in</button>
                    </div>
                </form>

                <p class="gtp-login-alt">
                    Don’t have an account?
                    <button type="submit" form="gtp-register-redirect" name="gtp_register_submit" value="1" class="gtp-login-text-btn">Sign up</button>
                </p>
                <form id="gtp-register-redirect" method="post" hidden></form>
            </div>
        </div>

        <aside class="gtp-login-aside" aria-hidden="true">
            <img class="gtp-login-aside-logo" src="<?php echo esc_url($logo_url); ?>" alt="">
        </aside>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_login', 'gtp_login_shortcode');

function gtp_registration_shortcode() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_users';
    $message = '';
    $requested_role = 'tutor';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gtp_register_submit'])) {
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $username = sanitize_user($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $requested_role = sanitize_text_field($_POST['requested_role'] ?? 'tutor');
        if (!in_array($requested_role, ['tutor', 'admin'], true)) {
            $requested_role = 'tutor';
        }

        if ($password !== $confirm) {
            $message = '<p class="gtp-msg is-error gtp-persist">Passwords do not match.</p>';
        } elseif ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE username = %s", $username))) {
            $message = '<p class="gtp-msg is-error gtp-persist">Username already exists.</p>';
        } elseif ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE email = %s", $email))) {
            $message = '<p class="gtp-msg is-error gtp-persist">Email already registered.</p>';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $wpdb->insert($table, [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'school' => null,
                'username' => $username,
                'password' => $hashed_password,
                'subject_preferences' => null,
                'role' => 'pending',
                'requested_role' => $requested_role,
                'validated' => 0,
                'password_set' => 1,
            ]);

            wp_redirect(site_url('/index.php/registration-confirmation'));
            exit;
        }
    }

    $login_url = site_url('/index.php/Welcome-to-GTP/');
    $logo_url = plugins_url('assets/images/gtp-logo.png', dirname(__DIR__) . '/tutor-management-plugin.php');

    ob_start();
    ?>
    <div class="gtp-login gtp-login--wide">
        <div class="gtp-login-main">
            <div class="gtp-login-main-inner gtp-login-main-inner--wide">
                <p class="gtp-page-back">
                    <a class="gtp-back-link" href="<?php echo esc_url($login_url); ?>">Back to sign in</a>
                </p>
                <h1>Create an account</h1>
                <p class="gtp-login-sub">Please enter your details</p>
                <?php echo $message; ?>

                <form method="post" class="gtp-form-stack">
                    <fieldset class="gtp-role-pick">
                        <legend>I want to register as</legend>
                        <div class="gtp-role-pick-options">
                            <label class="gtp-role-pick-option">
                                <input type="radio" name="requested_role" value="tutor" <?php checked($requested_role, 'tutor'); ?> required>
                                <span>
                                    <strong>TA / Tutor</strong>
                                    <em>Teach sessions and manage your classes</em>
                                </span>
                            </label>
                            <label class="gtp-role-pick-option">
                                <input type="radio" name="requested_role" value="admin" <?php checked($requested_role, 'admin'); ?>>
                                <span>
                                    <strong>Admin</strong>
                                    <em>Manage people, classes, and site settings</em>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="gtp-field-row">
                        <label class="gtp-field">
                            <span>First name</span>
                            <input type="text" name="first_name" required autocomplete="given-name" value="<?php echo isset($_POST['first_name']) ? esc_attr(sanitize_text_field(wp_unslash($_POST['first_name']))) : ''; ?>">
                        </label>
                        <label class="gtp-field">
                            <span>Last name</span>
                            <input type="text" name="last_name" required autocomplete="family-name" value="<?php echo isset($_POST['last_name']) ? esc_attr(sanitize_text_field(wp_unslash($_POST['last_name']))) : ''; ?>">
                        </label>
                    </div>

                    <label class="gtp-field">
                        <span>Email</span>
                        <input type="email" name="email" required autocomplete="email" value="<?php echo isset($_POST['email']) ? esc_attr(sanitize_email(wp_unslash($_POST['email']))) : ''; ?>">
                    </label>

                    <label class="gtp-field">
                        <span>Username</span>
                        <input type="text" name="username" required autocomplete="username" value="<?php echo isset($_POST['username']) ? esc_attr(sanitize_user(wp_unslash($_POST['username']))) : ''; ?>">
                    </label>

                    <div class="gtp-field-row">
                        <label class="gtp-field">
                            <span>Password</span>
                            <input type="password" name="password" required autocomplete="new-password">
                        </label>
                        <label class="gtp-field">
                            <span>Confirm password</span>
                            <input type="password" name="confirm_password" required autocomplete="new-password">
                        </label>
                    </div>

                    <div class="gtp-form-actions">
                        <button type="submit" name="gtp_register_submit" value="1" class="button button-primary gtp-login-submit">Submit registration</button>
                    </div>
                </form>
            </div>
        </div>

        <aside class="gtp-login-aside" aria-hidden="true">
            <img class="gtp-login-aside-logo" src="<?php echo esc_url($logo_url); ?>" alt="">
        </aside>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_registration_page', 'gtp_registration_shortcode');

function gtp_registration_confirmation_shortcode() {
    $login_url = site_url('/index.php/Welcome-to-GTP/');
    $logo_url = plugins_url('assets/images/gtp-logo.png', dirname(__DIR__) . '/tutor-management-plugin.php');
    ob_start();
    ?>
    <div class="gtp-login">
        <div class="gtp-login-main">
            <div class="gtp-login-main-inner">
                <h1>Registration received</h1>
                <p class="gtp-login-sub">Administrators are reviewing your application. Once approved, you can sign in with your username and password.</p>
                <p class="gtp-form-actions">
                    <a class="button button-primary gtp-login-submit" href="<?php echo esc_url($login_url); ?>">Back to sign in</a>
                </p>
            </div>
        </div>
        <aside class="gtp-login-aside" aria-hidden="true">
            <img class="gtp-login-aside-logo" src="<?php echo esc_url($logo_url); ?>" alt="">
        </aside>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_registration_confirmation', 'gtp_registration_confirmation_shortcode');

/**
 * When a WordPress administrator account is created, mirror/validate them in gtp_users.
 */
function gtp_validate_admin_user($user_id) {
    $user = get_userdata($user_id);
    if (!$user || !in_array('administrator', (array) $user->roles, true)) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'gtp_users';
    $user_login = $user->user_login;
    $user_email = $user->user_email;

    $existing_user = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE username = %s",
        $user_login
    ));

    if ($existing_user) {
        $wpdb->update(
            $table_name,
            ['validated' => 1],
            ['username' => $user_login]
        );
        return;
    }

    $wpdb->insert(
        $table_name,
        [
            'username' => $user_login,
            'email' => $user_email,
            'role' => 'admin',
            'requested_role' => 'admin',
            'validated' => 1,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'password' => $user->user_pass,
            'password_set' => 1,
        ]
    );
}
add_action('user_register', 'gtp_validate_admin_user');
