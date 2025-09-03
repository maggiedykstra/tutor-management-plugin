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



