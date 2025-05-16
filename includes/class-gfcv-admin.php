<?php
/**
 * Admin functionality for Gravity Forms Results Custom View with ACL
 *
 * @package Gravity_Form_Custom_View
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GFCV_Admin {
    /**
     * Instance of this class.
     *
     * @var object
     */
    private static $instance = null;

    /**
     * Get an instance of this class.
     */
    public static function get_instance() {
        if (null == self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'), 999);
        
        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Handle form submissions
        add_action('admin_init', array($this, 'handle_form_submissions'));
        
        // Add AJAX handler for getting form fields
        add_action('wp_ajax_gfcv_get_form_fields', array($this, 'ajax_get_form_fields'));
    }

    /**
     * Add admin menu under Gravity Forms
     */
    public function add_admin_menu() {
        if (class_exists('GFForms')) {
            // Add submenu page under Gravity Forms
            add_submenu_page(
                'gf_edit_forms',
                __('مدیریت لیست‌های نمایشی', 'gravity-form-custom-view'),
                __('مدیریت لیست‌های نمایشی', 'gravity-form-custom-view'),
                'manage_options',
                'gf_custom_view',
                array($this, 'display_admin_page')
            );
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('forms_page_gf_custom_view' !== $hook) {
            return;
        }

        // Enqueue CodeMirror for code editing
        wp_enqueue_code_editor(array('type' => 'text/html'));
        
        // Enqueue admin styles
        wp_enqueue_style(
            'gfcv-admin-styles',
            GFCV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GFCV_PLUGIN_VERSION
        );
        
        // Enqueue admin scripts
        wp_enqueue_script(
            'gfcv-admin-scripts',
            GFCV_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            GFCV_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Display admin page
     */
    public function display_admin_page() {
        // Check if editing or adding a new view
        $view_id = isset($_GET['view_id']) ? absint($_GET['view_id']) : 0;
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        if ('edit' === $action || 'add' === $action) {
            $this->display_edit_view_page($view_id);
        } else {
            $this->display_views_list_page();
        }
    }

    /**
     * Display views list page
     */
    private function display_views_list_page() {
        $db = GFCV_DB::get_instance();
        $views = $db->get_views();
        
        // Get available forms
        $forms = array();
        if (class_exists('GFAPI')) {
            $forms = GFAPI::get_forms();
        }
        
        include GFCV_PLUGIN_PATH . 'templates/admin/views-list.php';
    }

    /**
     * Display edit view page
     *
     * @param int $view_id View ID
     */
    private function display_edit_view_page($view_id) {
        $db = GFCV_DB::get_instance();
        $view = $view_id ? $db->get_view($view_id) : null;
        
        // Get available forms
        $forms = array();
        if (class_exists('GFAPI')) {
            $forms = GFAPI::get_forms();
        }
        
        include GFCV_PLUGIN_PATH . 'templates/admin/edit-view.php';
    }

    /**
     * Handle form submissions
     */
    public function handle_form_submissions() {
        if (!isset($_POST['gfcv_action'])) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['gfcv_nonce']) || !wp_verify_nonce($_POST['gfcv_nonce'], 'gfcv_save_view')) {
            wp_die(__('Security check failed', 'gravity-form-custom-view'));
        }
        
        $action = sanitize_text_field($_POST['gfcv_action']);
        $db = GFCV_DB::get_instance();
        
        if ('save_view' === $action) {
            $view_id = isset($_POST['view_id']) ? absint($_POST['view_id']) : 0;
            
            $data = array(
                'view_title' => sanitize_text_field($_POST['view_title']),
                'form_id' => absint($_POST['form_id']),
                'field_ids' => sanitize_text_field($_POST['field_ids']),
                'admin_sms_pattern' => wp_kses_post($_POST['admin_sms_pattern']),
                'user_sms_pattern' => wp_kses_post($_POST['user_sms_pattern']),
                'kavenegar_api_key' => sanitize_text_field($_POST['kavenegar_api_key']),
                'send_to_api' => isset($_POST['send_to_api']) ? 1 : 0,
                'send_to_details' => wp_kses_post($_POST['send_to_details']),
                'details_view_html' => wp_kses_post($_POST['details_view_html']),
                'user_access_ids' => sanitize_text_field($_POST['user_access_ids']),
                'user_mobile_field_id' => sanitize_text_field($_POST['user_mobile_field_id']),
                'admin_mobile_numbers' => sanitize_text_field($_POST['admin_mobile_numbers'])
            );
            
            if ($view_id) {
                $db->update_view($view_id, $data);
                $redirect_url = admin_url('admin.php?page=gf_custom_view&action=edit&view_id=' . $view_id . '&updated=1');
            } else {
                global $wpdb;
                $db->add_view($data);
                $new_view_id = $wpdb->insert_id;
                $redirect_url = admin_url('admin.php?page=gf_custom_view&action=edit&view_id=' . $new_view_id . '&created=1');
            }
            
            wp_redirect($redirect_url);
            exit;
        }
        
        if ('delete_view' === $action) {
            $view_id = isset($_POST['view_id']) ? absint($_POST['view_id']) : 0;
            
            if ($view_id) {
                $db->delete_view($view_id);
                wp_redirect(admin_url('admin.php?page=gf_custom_view&deleted=1'));
                exit;
            }
        }
    }
    
    /**
     * AJAX handler for getting form fields
     */
    public function ajax_get_form_fields() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gfcv_save_view')) {
            wp_send_json_error(array('message' => __('Security check failed', 'gravity-form-custom-view')));
        }
        
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        
        if (!$form_id) {
            wp_send_json_error(array('message' => __('Invalid form ID', 'gravity-form-custom-view')));
        }
        
        // Get form fields
        if (!class_exists('GFAPI')) {
            wp_send_json_error(array('message' => __('Gravity Forms API not available', 'gravity-form-custom-view')));
        }
        
        $form = GFAPI::get_form($form_id);
        
        if (!$form) {
            wp_send_json_error(array('message' => __('Form not found', 'gravity-form-custom-view')));
        }
        
        // Build HTML for field list
        $html = '';
        
        if (!empty($form['fields'])) {
            foreach ($form['fields'] as $field) {
                if (!$field->displayOnly) {
                    $html .= '<div class="field-item">';
                    $html .= '<code>' . esc_html($field->id) . '</code>: ' . esc_html($field->label);
                    $html .= '</div>';
                }
            }
        } else {
            $html = '<p>' . __('No fields found in this form.', 'gravity-form-custom-view') . '</p>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
}