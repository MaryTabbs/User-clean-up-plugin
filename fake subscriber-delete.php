<?php
/**
 * Plugin Name: Fake Subscriber Cleaner
 * Description: Detects and deletes fake WordPress subscribers with gibberish names while keeping real names safe.
 * Version: 1.2
 * Author: Techtabbs
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Admin menu
add_action('admin_menu', function() {
    add_menu_page(
        'Fake Subscriber Cleaner',
        'Fake Subscriber Cleaner',
        'manage_options',
        'fake-subscriber-cleaner',
        'fsc_admin_page',
        'dashicons-user-slash',
        100
    );
});

function fsc_admin_page() {
    echo '<div class="wrap"><h1>Fake Subscriber Cleaner</h1>';

    if (isset($_POST['fsc_delete'])) {
        $deleted = fsc_delete_fake_subscribers();
        echo '<div class="updated"><p>Deleted ' . $deleted . ' fake subscribers.</p></div>';
    }

    $users = fsc_get_fake_subscribers();
    echo '<form method="post">';
    echo '<p><strong>Preview of suspicious subscribers:</strong></p>';
    echo '<table class="widefat fixed"><thead><tr><th>User ID</th><th>Login</th><th>Name</th><th>Email</th></tr></thead><tbody>';
    if ($users) {
        foreach ($users as $user) {
            echo '<tr><td>'.$user->ID.'</td><td>'.$user->user_login.'</td><td>'.$user->display_name.'</td><td>'.$user->user_email.'</td></tr>';
        }
    } else {
        echo '<tr><td colspan="4">No suspicious subscribers found 🎉</td></tr>';
    }
    echo '</tbody></table>';
    if ($users) {
        echo '<p><input type="submit" name="fsc_delete" class="button button-primary" value="Delete Fake Subscribers"></p>';
    }
    echo '</form></div>';
}

// Detection rules
function fsc_is_fake_name($name, $email) {
    $name = trim($name);

    // Rule 1: Real names often have spaces
    if (strpos($name, ' ') !== false) return false;

    // Rule 2: If similar to email prefix, keep
    $email_prefix = strtolower(strtok($email, '@'));
    if (similar_text(strtolower($name), $email_prefix) > strlen($name) * 0.6) return false;

    // Rule 3: Count vowels
    $vowel_count = preg_match_all('/[aeiou]/i', $name);
    if ($vowel_count > 5) return false;

    // Rule 4: Random casing & long continuous string
    if (preg_match('/[A-Z].*[A-Z].*[A-Z]/', $name) && strlen($name) > 8) return true;

    // Rule 5: Mostly gibberish (no dictionary words, no natural casing)
    if (!preg_match('/^[A-Z][a-z]+$/', $name)) {
        if (strlen($name) > 7 && strlen($name) < 15) {
            return true;
        }
    }

    return false;
}

function fsc_get_fake_subscribers() {
    $args = array(
        'role'    => 'Subscriber',
        'fields'  => array('ID', 'user_login', 'display_name', 'user_email'),
        'number'  => 500,
    );
    $users = get_users($args);

    $fake_users = array();
    foreach ($users as $user) {
        if (fsc_is_fake_name($user->user_login, $user->user_email) ||
            fsc_is_fake_name($user->display_name, $user->user_email)) {
            $fake_users[] = $user;
        }
    }
    return $fake_users;
}

function fsc_delete_fake_subscribers() {
    $fake_users = fsc_get_fake_subscribers();
    $count = 0;
    foreach ($fake_users as $user) {
        require_once(ABSPATH.'wp-admin/includes/user.php');
        wp_delete_user($user->ID);
        $count++;
    }
    return $count;
}
