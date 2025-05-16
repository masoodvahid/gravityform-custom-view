<?php
/**
 * Shortcode functionality for Gravity Forms Results Custom View with ACL
 *
 * @package Gravity_Form_Custom_View
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GFCV_Shortcode {
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
        // Register shortcode
        add_shortcode('gf-custom-view', array($this, 'render_shortcode'));
        
        // Register frontend scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_gfcv_get_entry_details', array($this, 'ajax_get_entry_details'));
        add_action('wp_ajax_nopriv_gfcv_get_entry_details', array($this, 'ajax_get_entry_details'));
        
        add_action('wp_ajax_gfcv_get_sms_template', array($this, 'ajax_get_sms_template'));
        add_action('wp_ajax_nopriv_gfcv_get_sms_template', array($this, 'ajax_get_sms_template'));
        
        add_action('wp_ajax_gfcv_send_sms', array($this, 'ajax_send_sms'));
        add_action('wp_ajax_nopriv_gfcv_send_sms', array($this, 'ajax_send_sms'));
        
        add_action('wp_ajax_gfcv_get_api_data', array($this, 'ajax_get_api_data'));
        add_action('wp_ajax_nopriv_gfcv_get_api_data', array($this, 'ajax_get_api_data'));
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        global $post;
        
        // Only enqueue on pages with our shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'gf-custom-view')) {
            // Enqueue dashicons
            wp_enqueue_style('dashicons');
            
            // Enqueue styles
            wp_enqueue_style(
                'gfcv-frontend-styles',
                GFCV_PLUGIN_URL . 'assets/css/frontend.css',
                array('dashicons'),
                GFCV_PLUGIN_VERSION
            );
            
            // Enqueue custom button styles (can be customized by users)
            wp_enqueue_style(
                'gfcv-custom-button-styles',
                GFCV_PLUGIN_URL . 'assets/css/custom-buttons.css',
                array('gfcv-frontend-styles'),
                GFCV_PLUGIN_VERSION
            );
            
            // Enqueue scripts
            wp_enqueue_script(
                'gfcv-frontend-scripts',
                GFCV_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                GFCV_PLUGIN_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script(
                'gfcv-frontend-scripts',
                'gfcv_vars',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('gfcv_nonce'),
                    'loading_text' => __('در حال بارگذاری...', 'gravity-form-custom-view'),
                    'error_text' => __('خطا در بارگذاری اطلاعات', 'gravity-form-custom-view')
                )
            );
        }
    }

    /**
     * Render shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'id' => 0,
                'per_page' => 10,
            ),
            $atts,
            'gf-custom-view'
        );
        
        $view_id = absint($atts['id']);
        $per_page = absint($atts['per_page']);
        
        if (!$view_id) {
            return '<p>' . __('شناسه نمای سفارشی نامعتبر است', 'gravity-form-custom-view') . '</p>';
        }
        
        // Get the view
        $db = GFCV_DB::get_instance();
        $view = $db->get_view($view_id);
        
        if (!$view) {
            return '<p>' . __('نمای سفارشی یافت نشد', 'gravity-form-custom-view') . '</p>';
        }
        
        // Check user access
        if (!empty($view->user_access_ids) && !$this->user_has_access($view->user_access_ids)) {
            return '<p>' . __('شما اجازه مشاهده این محتوا را ندارید', 'gravity-form-custom-view') . '</p>';
        }
        
        // Get form entries
        if (!class_exists('GFAPI')) {
            return '<p>' . __('API گرویتی فرم در دسترس نیست', 'gravity-form-custom-view') . '</p>';
        }
        
        $entries = GFAPI::get_entries($view->form_id);
        
        if (is_wp_error($entries)) {
            return '<p>' . __('خطا در بازیابی اطلاعات فرم', 'gravity-form-custom-view') . '</p>';
        }
        
        // Get form fields
        $form = GFAPI::get_form($view->form_id);
        $field_ids = explode(',', $view->field_ids);
        $fields = array();
        
        foreach ($form['fields'] as $field) {
            if (in_array($field->id, $field_ids)) {
                $fields[$field->id] = $field;
            }
        }
        
        // Start output buffer
        ob_start();
        
        // Include template
        include GFCV_PLUGIN_PATH . 'templates/frontend/custom-view.php';
        
        // Return the buffered content
        return ob_get_clean();
    }

    /**
     * Check if current user has access
     *
     * @param string $user_access_ids Comma-separated list of user IDs
     * @return bool True if user has access, false otherwise
     */
    private function user_has_access($user_access_ids) {
        // If empty, everyone has access
        if (empty($user_access_ids)) {
            return true;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check if user is an administrator
        if (current_user_can('administrator')) {
            return true;
        }
        
        $current_user_id = get_current_user_id();
        $allowed_user_ids = explode(',', $user_access_ids);
        
        // Check if current user ID is in the allowed list
        return in_array($current_user_id, $allowed_user_ids);
    }

    /**
     * AJAX handler for getting entry details
     */
    public function ajax_get_entry_details() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gfcv_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'gravity-form-custom-view')));
        }
        
        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
        $view_id = isset($_POST['view_id']) ? absint($_POST['view_id']) : 0;
        
        if (!$entry_id || !$view_id) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'gravity-form-custom-view')));
        }
        
        // Get the view
        $db = GFCV_DB::get_instance();
        $view = $db->get_view($view_id);
        
        if (!$view) {
            wp_send_json_error(array('message' => __('Custom view not found', 'gravity-form-custom-view')));
        }
        
        // Get the entry
        if (!class_exists('GFAPI')) {
            wp_send_json_error(array('message' => __('Gravity Forms API not available', 'gravity-form-custom-view')));
        }
        
        $entry = GFAPI::get_entry($entry_id);
        
        if (is_wp_error($entry)) {
            wp_send_json_error(array('message' => __('Entry not found', 'gravity-form-custom-view')));
        }
        
        // Get the form
        $form = GFAPI::get_form($view->form_id);
        
        // Process the details view HTML
        $details_html = $this->process_template($view->details_view_html, $entry, $form);
        
        wp_send_json_success(array(
            'html' => $details_html
        ));
    }

    /**
     * AJAX handler for getting SMS template
     */
    public function ajax_get_sms_template() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gfcv_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'gravity-form-custom-view')));
        }
        
        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
        $view_id = isset($_POST['view_id']) ? absint($_POST['view_id']) : 0;
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        
        if (!$entry_id || !$view_id || !in_array($type, array('user', 'admin'))) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'gravity-form-custom-view')));
        }
        
        // Get the view
        $db = GFCV_DB::get_instance();
        $view = $db->get_view($view_id);
        
        if (!$view) {
            wp_send_json_error(array('message' => __('Custom view not found', 'gravity-form-custom-view')));
        }
        
        // Get the entry
        if (!class_exists('GFAPI')) {
            wp_send_json_error(array('message' => __('Gravity Forms API not available', 'gravity-form-custom-view')));
        }
        
        $entry = GFAPI::get_entry($entry_id);
        
        if (is_wp_error($entry)) {
            wp_send_json_error(array('message' => __('Error retrieving entry', 'gravity-form-custom-view')));
        }
        
        // Get the form
        $form = GFAPI::get_form($view->form_id);
        
        // Get the pattern
        $pattern = $type === 'admin' ? $view->admin_sms_pattern : $view->user_sms_pattern;
        
        // Replace placeholders with entry values
        $message = $this->process_template($pattern, $entry, $form);
        
        // Get recipient information
        $recipient = '';
        if ($type === 'user' && !empty($view->user_mobile_field_id)) {
            $recipient = rgar($entry, $view->user_mobile_field_id);
        } elseif ($type === 'admin' && !empty($view->admin_mobile_numbers)) {
            $recipient = $view->admin_mobile_numbers;
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'recipient' => $recipient
        ));
    }

    /**
     * AJAX handler for getting API data
     */
    public function ajax_get_api_data() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gfcv_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'gravity-form-custom-view')));
        }
        
        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
        $view_id = isset($_POST['view_id']) ? absint($_POST['view_id']) : 0;
        
        if (!$entry_id || !$view_id) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'gravity-form-custom-view')));
        }
        
        // Get the view
        $db = GFCV_DB::get_instance();
        $view = $db->get_view($view_id);
        
        if (!$view) {
            wp_send_json_error(array('message' => __('Custom view not found', 'gravity-form-custom-view')));
        }
        
        // Get the entry
        if (!class_exists('GFAPI')) {
            wp_send_json_error(array('message' => __('Gravity Forms API not available', 'gravity-form-custom-view')));
        }
        
        $entry = GFAPI::get_entry($entry_id);
        
        if (is_wp_error($entry)) {
            wp_send_json_error(array('message' => __('Entry not found', 'gravity-form-custom-view')));
        }
        
        // Get the form
        $form = GFAPI::get_form($view->form_id);
        
        // Process the API details
        $api_details = $this->process_template($view->send_to_details, $entry, $form);
        
        // Convert entry to JSON
        $entry_json = json_encode($entry);
        
        wp_send_json_success(array(
            'api_details' => $api_details,
            'entry_json' => $entry_json
        ));
    }

    /**
     * Process a template by replacing merge tags with entry values
     *
     * @param string $template The template to process
     * @param array $entry The entry data
     * @param array $form The form data
     * @return string The processed template
     */
    private function process_template($template, $entry, $form) {
        // Replace merge tags
        $processed = $template;
        
        // Replace all field merge tags
        foreach ($form['fields'] as $field) {
            $field_id = $field->id;
            $merge_tag = '{' . $field_id . '}';
            
            if (strpos($processed, $merge_tag) !== false) {
                $field_value = rgar($entry, $field_id);
                $processed = str_replace($merge_tag, $field_value, $processed);
            }
        }
        
        // Replace entry ID
        $processed = str_replace('{entry_id}', $entry['id'], $processed);
        
        // Replace date created
        $processed = str_replace('{date_created}', $entry['date_created'], $processed);
        
        return $processed;
    }
    
    /**
     * AJAX handler for sending SMS via Kavenegar
     */
    public function ajax_send_sms() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gfcv_nonce')) {
            wp_send_json_error(array('message' => __('بررسی امنیتی ناموفق بود', 'gravity-form-custom-view')));
        }
        
        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
        $view_id = isset($_POST['view_id']) ? absint($_POST['view_id']) : 0;
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        
        if (!$entry_id || !$view_id || !in_array($type, array('user', 'admin'))) {
            wp_send_json_error(array('message' => __('پارامترهای نامعتبر', 'gravity-form-custom-view')));
        }
        
        // Get the view
        $db = GFCV_DB::get_instance();
        $view = $db->get_view($view_id);
        
        if (!$view) {
            wp_send_json_error(array('message' => __('نمای سفارشی یافت نشد', 'gravity-form-custom-view')));
        }
        
        // Check if API key is set
        if (empty($view->kavenegar_api_key)) {
            wp_send_json_error(array('message' => __('کلید API کاوه نگار تنظیم نشده است', 'gravity-form-custom-view')));
        }
        
        // Get the entry
        if (!class_exists('GFAPI')) {
            wp_send_json_error(array('message' => __('API گرویتی فرم در دسترس نیست', 'gravity-form-custom-view')));
        }
        
        $entry = GFAPI::get_entry($entry_id);
        
        if (is_wp_error($entry)) {
            wp_send_json_error(array('message' => __('خطا در بازیابی اطلاعات فرم', 'gravity-form-custom-view')));
        }
        
        // Get the form
        $form = GFAPI::get_form($view->form_id);
        
        // Get the pattern and recipient
        $pattern = $type === 'admin' ? $view->admin_sms_pattern : $view->user_sms_pattern;
        
        if (empty($pattern)) {
            wp_send_json_error(array('message' => __('الگوی پیامک تنظیم نشده است', 'gravity-form-custom-view')));
        }
        
        // Get recipient information
        $recipient = '';
        if ($type === 'user' && !empty($view->user_mobile_field_id)) {
            $recipient = rgar($entry, $view->user_mobile_field_id);
            if (empty($recipient)) {
                wp_send_json_error(array('message' => __('شماره موبایل کاربر یافت نشد', 'gravity-form-custom-view')));
            }
        } elseif ($type === 'admin' && !empty($view->admin_mobile_numbers)) {
            $recipient = $view->admin_mobile_numbers;
        } else {
            wp_send_json_error(array('message' => __('شماره گیرنده تنظیم نشده است', 'gravity-form-custom-view')));
        }
        
        // Parse the pattern to extract Kavenegar Lookup parameters
        $lookup_params = $this->parse_kavenegar_pattern($pattern, $entry, $form);
        
        // Send SMS via Kavenegar API
        $result = $this->send_kavenegar_lookup($view->kavenegar_api_key, $recipient, $lookup_params);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => __('پیامک با موفقیت ارسال شد', 'gravity-form-custom-view')));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
    
    /**
     * Parse Kavenegar pattern string to extract lookup parameters
     *
     * @param string $pattern The pattern string (e.g., 'token={1}token2={5}template=verify')
     * @param array $entry The entry data
     * @param array $form The form data
     * @return array The lookup parameters
     */
    private function parse_kavenegar_pattern($pattern, $entry, $form) {
        $params = array();
        
        // Process the pattern first to replace all merge tags
        $processed_pattern = $this->process_template($pattern, $entry, $form);
        
        // Extract key-value pairs from the pattern
        $pairs = explode('token', $processed_pattern);
        
        foreach ($pairs as $pair) {
            if (empty($pair)) continue;
            
            // Check if this is a token parameter
            if (strpos($pair, '=') === 0) {
                $token_parts = explode('=', $pair, 2);
                if (count($token_parts) === 2) {
                    $token_number = '';
                    $token_value = $token_parts[1];
                    
                    // Extract token number if it exists
                    if (preg_match('/^(\d+)(.*)/', $token_parts[0], $matches)) {
                        $token_number = $matches[1];
                        // If there's more content after the token number, it belongs to the next parameter
                        if (!empty($matches[2])) {
                            // Find where the next parameter starts
                            $next_param_pos = strpos($token_value, $matches[2]);
                            if ($next_param_pos !== false) {
                                $token_value = substr($token_value, 0, $next_param_pos);
                            }
                        }
                    }
                    
                    $token_key = 'token' . $token_number;
                    $params[$token_key] = $token_value;
                }
            } else if (strpos($pair, 'template=') !== false) {
                // Extract template parameter
                $template_parts = explode('=', $pair, 2);
                if (count($template_parts) === 2) {
                    $params['template'] = trim($template_parts[1]);
                }
            }
        }
        
        return $params;
    }
    
    /**
     * Send SMS via Kavenegar Lookup API
     *
     * @param string $api_key The Kavenegar API key
     * @param string $receptor The recipient mobile number
     * @param array $params The lookup parameters
     * @return array Result with success status and message
     */
    private function send_kavenegar_lookup($api_key, $receptor, $params) {
        if (empty($api_key) || empty($receptor) || empty($params['template'])) {
            return array(
                'success' => false,
                'message' => __('پارامترهای ارسال پیامک ناقص است', 'gravity-form-custom-view')
            );
        }
        
        // Prepare the API URL
        $base_url = 'https://api.kavenegar.com/v1/' . $api_key . '/verify/lookup.json';
        
        // Prepare the request parameters
        $request_params = array(
            'receptor' => $receptor,
            'template' => $params['template']
        );
        
        // Add token parameters
        foreach ($params as $key => $value) {
            if (strpos($key, 'token') === 0) {
                $request_params[$key] = $value;
            }
        }
        
        // Make the API request
        $response = wp_remote_post($base_url, array(
            'body' => $request_params,
            'timeout' => 30
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        // Check the response
        if (isset($result['return']['status']) && $result['return']['status'] == 200) {
            return array(
                'success' => true,
                'message' => __('پیامک با موفقیت ارسال شد', 'gravity-form-custom-view')
            );
        } else {
            $error_message = isset($result['return']['message']) ? $result['return']['message'] : __('خطا در ارسال پیامک', 'gravity-form-custom-view');
            return array(
                'success' => false,
                'message' => $error_message
            );
        }
    }
}