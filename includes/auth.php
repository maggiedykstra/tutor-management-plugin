<?php
//for authorization of users and starting a session
function gtp_handle_login() {
    if (isset($_POST['gtp_login'])) {
        $username = sanitize_text_field($_POST['gtp_username']);
        $password = $_POST['gtp_password'];

        global $wpdb;
        $table = $wpdb->prefix . 'gtp_users';
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE username = %s", $username));

        if ($user && password_verify($password, $user->password)) {
            $_SESSION['gtp_user'] = [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role
            ];
            wp_redirect(home_url('/tutor-portal')); // or wherever your dashboard lives
            exit;
        } else {
            wp_redirect(add_query_arg('error', '1', home_url('/tutor-portal')));
            exit;
        }
    }
}
add_action('init', 'gtp_handle_login');
