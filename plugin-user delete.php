<?php
/*
Plugin Name: Delete Fake Subscribers
Description: Delete fake subscriber accounts with suspicious names and email addresses.
Version: 1.1
Author: ChatGPT
*/

if (!defined('ABSPATH')) exit;

class DeleteFakeSubscribers {
    private $page_slug = 'delete-fake-subscribers';
    private $settings_option = 'dfs_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_admin_menu() {
        add_management_page('Delete Fake Subscribers', 'Delete Fake Subscribers', 'manage_options', $this->page_slug, [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting($this->settings_option, $this->settings_option);
        add_settings_section('dfs_main_section', 'Detection Settings', null, $this->page_slug);

        add_settings_field('dfs_vowel_limit', 'Max Vowels Allowed', [$this, 'vowel_limit_field'], $this->page_slug, 'dfs_main_section');
        add_settings_field('dfs_disposable_domains', 'Block Disposable Domains', [$this, 'disposable_domains_field'], $this->page_slug, 'dfs_main_section');
    }

    public function vowel_limit_field() {
        $options = get_option($this->settings_option);
        $value = isset($options['vowel_limit']) ? intval($options['vowel_limit']) : 5;
        echo "<input type='number' name='{$this->settings_option}[vowel_limit]' value='{$value}' min='1' max='20' /> (default 5)";
    }

    public function disposable_domains_field() {
        $options = get_option($this->settings_option);
        $value = isset($options['disposable_domains']) ? esc_attr($options['disposable_domains']) : 'mailinator.com,tempmail.com,10minutemail.com';
        echo "<textarea name='{$this->settings_option}[disposable_domains]' rows='3' cols='50'>{$value}</textarea><br>Comma-separated domains to block.";
    }

    private function is_suspicious_name($name, $vowel_limit) {
        $vowels = preg_match_all('/[aeiou]/i', $name);
        return $vowels <= $vowel_limit;
    }

    private function is_suspicious_email($email, $disposable_domains) {
        if (!is_email($email)) return true;
        $domain = substr(strrchr($email, "@"), 1);
        foreach ($disposable_domains as $d) {
            if (stripos($domain, trim($d)) !== false) return true;
        }
        return false;
    }

    public function settings_page() {
        if (isset($_POST['dfs_delete'])) {
            check_admin_referer('dfs_delete_action', 'dfs_delete_nonce');
            $this->delete_fake_subscribers();
        }

        echo '<div class="wrap"><h1>Delete Fake Subscribers</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields($this->settings_option);
        do_settings_sections($this->page_slug);
        submit_button('Save Settings');
        echo '</form>';

        echo '<hr><h2>Run Deletion</h2>';
        echo '<form method="post">';
        wp_nonce_field('dfs_delete_action', 'dfs_delete_nonce');
        submit_button('Delete Fake Subscribers', 'delete', 'dfs_delete');
        echo '</form></div>';
    }

    private function delete_fake_subscribers() {
        $options = get_option($this->settings_option);
        $vowel_limit = isset($options['vowel_limit']) ? intval($options['vowel_limit']) : 5;
        $disposable_domains = isset($options['disposable_domains']) ? explode(',', $options['disposable_domains']) : [];

        $args = [
            'role' => 'Subscriber',
            'fields' => 'all',
            'number' => -1
        ];
        $users = get_users($args);

        $deleted = 0;
        foreach ($users as $user) {
            $name_fields = [$user->user_login, $user->display_name, $user->user_nicename];
            $suspicious_name = false;
            foreach ($name_fields as $name) {
                if ($this->is_suspicious_name($name, $vowel_limit)) {
                    $suspicious_name = true;
                    break;
                }
            }

            $suspicious_email = $this->is_suspicious_email($user->user_email, $disposable_domains);

            if ($suspicious_name && $suspicious_email) {
                require_once(ABSPATH.'wp-admin/includes/user.php');
                wp_delete_user($user->ID);
                $deleted++;
            }
        }

        echo '<div class="updated notice"><p>Deleted ' . $deleted . ' fake subscribers.</p></div>';
    }
}

new DeleteFakeSubscribers();

