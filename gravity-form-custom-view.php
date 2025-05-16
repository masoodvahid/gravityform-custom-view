<?php
/**
 * Plugin Name: نمایش رکوردهای گرویتی فرم با تعیین سطح دسترسی
 * Description: ایجاد لیست قابل مدیریت در گرویتی فرم با توجه به سطح دسترسی کاربران
 * Plugin URI: https://rahkar-digital.ir
 * Version: 1.2
 * Author: مسعود وحید
 * Author URI: https://rahkar-digital.ir
 * Text Domain: gravity-form-custom-view
 * License: GPL2
 * Requires PHP: 7.4
 * 
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GFCV_PLUGIN_FILE', __FILE__);
define('GFCV_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GFCV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GFCV_PLUGIN_VERSION', '1.3');

// Check if Gravity Forms is active
add_action('admin_init', 'gfcv_check_gravity_forms');
function gfcv_check_gravity_forms() {
    if (!class_exists('GFForms')) {
        add_action('admin_notices', 'gfcv_gravity_forms_notice');
        deactivate_plugins(plugin_basename(__FILE__));
    }
}

function gfcv_gravity_forms_notice() {
    echo '<div class="error"><p>' . __('Gravity Forms Results Custom View with ACL requires Gravity Forms to be installed and activated.', 'gravity-form-custom-view') . '</p></div>';
}

// Include required files
require_once GFCV_PLUGIN_PATH . 'includes/class-gfcv-db.php';
require_once GFCV_PLUGIN_PATH . 'includes/class-gfcv-admin.php';
require_once GFCV_PLUGIN_PATH . 'includes/class-gfcv-shortcode.php';

// Initialize the plugin
function gfcv_init() {
    // Initialize database
    GFCV_DB::get_instance();
    
    // Initialize admin
    GFCV_Admin::get_instance();
    
    // Initialize shortcode
    GFCV_Shortcode::get_instance();
}
add_action('plugins_loaded', 'gfcv_init');

// Register activation hook
register_activation_hook(__FILE__, array('GFCV_DB', 'create_tables'));

// Load plugin text domain
function gfcv_load_textdomain() {
    load_plugin_textdomain('gravity-form-custom-view', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'gfcv_load_textdomain');

// Add admin notice about button customization
function gfcv_admin_notices() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if the notice has been dismissed
    if (get_option('gfcv_button_notice_dismissed')) {
        return;
    }
    
    echo '<div class="notice notice-info is-dismissible gfcv-button-notice">
        <p>' . __('Gravity Forms Custom View buttons can now be customized! Check the docs/button-customization.md file for instructions.', 'gravity-form-custom-view') . '</p>
    </div>';
    
    // Add script to handle notice dismissal
    echo '<script>
        jQuery(document).ready(function($) {
            $(document).on("click", ".gfcv-button-notice .notice-dismiss", function() {
                $.ajax({
                    url: ajaxurl,
                    data: {
                        action: "gfcv_dismiss_button_notice",
                        nonce: "' . wp_create_nonce('gfcv_dismiss_notice') . '"
                    }
                });
            });
        });
    </script>';
}
add_action('admin_notices', 'gfcv_admin_notices');

// AJAX handler for dismissing the notice
function gfcv_dismiss_button_notice() {
    check_ajax_referer('gfcv_dismiss_notice', 'nonce');
    update_option('gfcv_button_notice_dismissed', true);
    wp_die();
}
add_action('wp_ajax_gfcv_dismiss_button_notice', 'gfcv_dismiss_button_notice');