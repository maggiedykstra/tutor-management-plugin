<?php
function gtp_login_shortcode() {
    if (isset($_POST['gtp_login_submit'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'gtp_users';

        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];

        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE username = %s", $username));

        if ($user && password_verify($password, $user->password)) {
            $_SESSION['gtp_user'] = [
                'id'         => $user->id,
                'username'   => $user->username,
                'email'      => $user->email,
                'role'       => $user->role,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name
            ];
            if ($user->role === 'admin') {
                wp_redirect(site_url('/index.php/admin-dashboard'));
                exit;
            } elseif ($user->role === 'tutor') {
                wp_redirect(site_url('/index.php/TA-dashboard'));
                exit;
            }
        } else {
            echo '<p style="color:red;">Login failed. Please check your credentials or register through the sign up button.</p>';
        }
    }

    if (isset($_POST['gtp_register_submit'])) {
        wp_redirect(site_url('/index.php/registration-page'));
    }


    ob_start();
    ?>
    <form method="post" style="max-width:400px; margin:30px auto; padding:15px; background:#f9f9f9; border-radius:8px;">
        <h2>Login</h2>
        <p><input type="text" name="username" placeholder="Username" required style="width:90%; padding:10px; margin-bottom:10px;"></p>
        <p><input type="password" name="password" placeholder="Password" required style="width:90%; padding:10px; margin-bottom:10px;"></p>
        <p><input type="submit" name="gtp_login_submit" value="Sign In" style="padding:10px 20px; background:#0073aa; color:white; border:none; cursor:pointer;"></p>
    </form>

    <form method="post" style="max-width:400px; margin:10px auto; padding:0px; background:#ffffff; border-radius:8px;">
        <p><input type="submit" name="gtp_register_submit" value="Register Here!" style="padding:10px 20px; background:#0073aa; color:white; border:none; cursor:pointer;"></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_login', 'gtp_login_shortcode');

//register here shortcode

function gtp_registration_shortcode() {
    global $wpdb;
    $table = $wpdb->prefix . 'gtp_users';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gtp_register_submit'])) {
        // Sanitize input
        $first_name  = sanitize_text_field($_POST['first_name']);
        $last_name   = sanitize_text_field($_POST['last_name']);
        $email       = sanitize_email($_POST['email']);
        $college     = sanitize_text_field($_POST['college']);
        $username    = sanitize_user($_POST['username']);
        $password    = $_POST['password'];
        $confirm     = $_POST['confirm_password'];

        $subject_preferences = [];
        if (isset($_POST['subject_preferences'])) {
            foreach ($_POST['subject_preferences'] as $subject => $preference) {
                $subject_preferences[sanitize_text_field($subject)] = sanitize_text_field($preference);
            }
        }
        $subject_preferences_json = json_encode($subject_preferences);

        // Basic validation
        if ($password !== $confirm) {
            echo "<p style='color:red;'>Passwords do not match.</p>";
        } elseif ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE username = %s", $username))) {
            echo "<p style='color:red;'>Username already exists.</p>";
        } elseif ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE email = %s", $email))) {
            echo "<p style='color:red;'>Email already registered.</p>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $wpdb->insert($table, [
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => $email,
                'college'    => $college,
                'username'   => $username,
                'password'   => $hashed_password,
                'subject_preferences'   => $subject_preferences_json,
                'role'       => 'tutor',
                // 'verified'   => 0 NEED TO ADD THIS FIELD TO DB
            ]);

            wp_redirect(site_url('/index.php/registration-confirmation'));
            exit;
        }
    }

    $all_subjects = ['AP Computer Science Principles', 'AP Biology', 'AP Statistics', 'AP Physics 1'];
    $preference_levels = [
        'cannot_tutor' => 'Cannot Tutor',
        'willing_to_tutor' => 'Willing to Tutor',
        'excited_to_tutor' => 'Would be Excited to Tutor'
    ];

    ob_start(); ?>
    <form method="post" style="max-width:600px; margin:30px auto; padding:20px; background:#f9f9f9; border-radius:8px;">
        <p><a href="<?php echo esc_url('/tutor-site/index.php/welcome-to-gtp/'); ?>" class="button">← Back to Login</a></p>


        <label>First Name:</label><br>
        <input type="text" name="first_name" required style="width:60%; height:25px; padding:8px;"><br><br>

        <label>Last Name:</label><br>
        <input type="text" name="last_name" required style="width:60%; height:25px; padding:8px;"><br><br>

        <label>Email:</label><br>
        <input type="email" name="email" required style="width:60%; height:25px; padding:8px;"><br><br>

        <label>College:</label><br>
        <input type="text" name="college" required style="width:60%; height:25px; padding:8px;"><br><br>

        <label>Username:</label><br>
        <input type="text" name="username" required style="width:60%; height:25px; padding:8px;"><br><br>

        <label>Password:</label><br>
        <input type="password" name="password" required style="width:60%; height:25px; padding:8px;"><br><br>

        <label>Confirm Password:</label><br>
        <input type="password" name="confirm_password" required style="width:60%; height:25px; padding:8px;"><br><br>

        <h3>Subject Preferences:</h3>
        <table class="form-table" border="1" style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ccc; padding: 8px;">Subject</th>
                    <?php foreach ($preference_levels as $level_key => $level_label) : ?>
                        <th style="text-align:center; border: 1px solid #ccc; padding: 8px;"><?php echo esc_html($level_label); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_subjects as $subject) : ?>
                    <tr>
                        <th style="border: 1px solid #ccc; padding: 8px;"><?php echo esc_html($subject); ?></th>
                        <?php foreach ($preference_levels as $level_key => $level_label) : ?>
                            <td style="text-align:center; border: 1px solid #ccc; padding: 8px;">
                                <input type="radio" name="subject_preferences[<?php echo esc_attr($subject); ?>]" value="<?php echo esc_attr($level_key); ?>" <?php checked('cannot_tutor', $level_key); ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <br>

        <input type="submit" name="gtp_register_submit" value="Submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; cursor:pointer;">
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_registration_page', 'gtp_registration_shortcode');



