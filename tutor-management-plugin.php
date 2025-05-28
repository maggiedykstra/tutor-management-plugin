<?php
/**
 * Plugin Name: Tutor Management Plugin
 * Description: Manage schools, students, and tutor hours.
 * Version: 1.0.0
 * Author: Maggie Dykstra
 */

defined('ABSPATH') or die('No script kiddies please!');

// Include functionality
require_once plugin_dir_path(__FILE__) . 'includes/admin-pages.php';

// Enqueue CSS/JS
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('tutor-style', plugin_dir_url(__FILE__) . 'assets/style.css');
    wp_enqueue_script('tutor-js', plugin_dir_url(__FILE__) . 'assets/script.js', array('jquery'), null, true);
});
