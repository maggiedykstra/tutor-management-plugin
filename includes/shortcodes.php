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


/* function gtp_admin_dashboard_shortcode() {
    // Check if user is logged in via session
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'admin') {
        return '<p>You do not have access to this page.</p>';
    }

    $name = esc_html($_SESSION['gtp_user']['first_name']);

    ob_start();
    ?>
   <!--  <div style="max-width:600px; margin:30px auto; padding:20px; background:#f1f1f1; border-radius:10px;">
        <h2>Welcome, <?php echo $name; ?>!</h2>

        <div style="margin-top:20px;">
            <form action="<?php echo esc_url(site_url('/index.php/new-ta-registration')); ?>" method="get" style="display:inline-block; margin-right:15px;">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Validate TAs
                </button>
            </form>

            <form action="<?php echo esc_url(site_url('/index.php/ta-session-filter')); ?>" method="get" style="display:inline-block;">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Filter TA Sessions and Hours
                </button>
            </form>
        </div>
    </div>
    <?php -->
    return ob_get_clean();
} */
#add_shortcode('gtp_admin_dashboard', 'gtp_admin_dashboard_shortcode');


function gtp_TA_dashboard_shortcode() {
    // Check if user is logged in via session
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        return '<p>You do not have access to this page.</p>';
    }

    $name = esc_html($_SESSION['gtp_user']['first_name']);

    ob_start();
    ?>
    <div style="max-width:600px; margin:30px auto; padding:20px; background:#f1f1f1; border-radius:10px;">
        <h2>Welcome, <?php echo $name; ?>!</h2>

        
        <form action="<?php echo esc_url(site_url('/index.php/TA-profile')); ?>" method="get" style="display:inline-block; margin-right:15px;">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    My profile
                </button>
        </form>


        <form action="<?php echo esc_url(site_url('/index.php/log-session')); ?>" method="get" style="display:inline-block; margin-right:15px;">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Log My Sessions
                </button>
        </form>


        <form action="<?php echo esc_url(site_url('/index.php/log-substitute')); ?>" method="get" style="display:inline-block; margin-right:15px;">
                <button type="submit" style="padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;">
                    Log Substitute Sessions
                </button>
        </form>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_TA_dashboard', 'gtp_TA_dashboard_shortcode');


function gtp_log_session_shortcode() {
    global $wpdb;

    // Check login and role
    if (!isset($_SESSION['gtp_user']) || $_SESSION['gtp_user']['role'] !== 'tutor') {
        return '<p>You do not have access to this page.</p>';
    }

    $tutor_id = $_SESSION['gtp_user']['id'];
    $first_name = $_SESSION['gtp_user']['first_name'];
    $last_name = $_SESSION['gtp_user']['last_name'];
    $username = $_SESSION['gtp_user']['username'];
    // Get classroom info
    $classroom = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gtp_classrooms WHERE id = %d", $classroom_id)
    );


    // Handle form submission
    if (isset($_POST['gtp_submit_session'])) {
        $classroom_id = intval($_POST['classroom_id']);
        $attendance = sanitize_textarea_field($_POST['attendance']);
        $topic = sanitize_textarea_field($_POST['topic']);
        $comments = sanitize_textarea_field($_POST['comments']);
        $is_sub = isset($_POST['is_substitute']) ? 1 : 0;
        $classroom = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gtp_classrooms WHERE id = %d", $classroom_id)
        );

        if ($classroom) {
            $wpdb->insert($wpdb->prefix . 'gtp_sessions', [
                'tutor_username' => $username,
                'first_name'     => $first_name,
                'last_name'      => $last_name,
                'school'         => $classroom->school,
                'subject'        => $classroom->subject,
                'teacher_name'   => $classroom->teacher_name,
                'session_date'   => current_time('mysql'),
                'attendance'     => $attendance,
                'topic'          => $topic,
                'comments'       => $comments,
                'is_substitute'  => $is_sub
            ]);
            if ($wpdb->last_error) {
                echo '<p style="color:red;">DB Error: ' . esc_html($wpdb->last_error) . '</p>';
            }
            echo '<p style="color: green;">Session logged successfully!</p>';
        }
    }

    // Get assigned classrooms
    $classes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT c.* FROM {$wpdb->prefix}gtp_classrooms c
             JOIN {$wpdb->prefix}gtp_class_assignments a
             ON c.id = a.classroom_id
             WHERE a.tutor_id = %d",
            $tutor_id
        )
    );

    ob_start();
    ?>
    <form method="post" style="max-width:600px; margin:20px auto; padding:20px; background:#f9f9f9; border-radius:8px;">
        <h2>Log a Session</h2>

        <label>Select Class:</label><br>
        <select name="classroom_id" required style="width:100%; padding:8px; margin-bottom:10px;">
            <?php foreach ($classes as $class): ?>
                <option value="<?php echo $class->id; ?>">
                    <?php echo esc_html("{$class->school} – {$class->subject} with {$class->teacher_name}"); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Attendance:</label>
        <textarea name="attendance" required style="width:100%; height:60px; margin-bottom:10px;"></textarea>

        <label>Topic Covered:</label>
        <textarea name="topic" required style="width:100%; height:60px; margin-bottom:10px;"></textarea>

        <label>Comments (optional):</label>
        <textarea name="comments" style="width:100%; height:60px; margin-bottom:10px;"></textarea>

        <label><input type="checkbox" name="is_substitute"> Substitute Session</label><br><br>

        <input type="submit" name="gtp_submit_session" value="Log Session" class="button button-primary">
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('gtp_log_session', 'gtp_log_session_shortcode');
