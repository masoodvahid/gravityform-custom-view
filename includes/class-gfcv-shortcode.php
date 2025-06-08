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
                    'error_text' => __('خطا در بارگذاری اطلاعات', 'gravity-form-custom-view'),
                    'frontend_css_url' => GFCV_PLUGIN_URL . 'assets/css/frontend.css',
                    'custom_buttons_css_url' => GFCV_PLUGIN_URL . 'assets/css/custom-buttons.css'
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
        
        // Pagination parameters
        $current_page = isset($_GET['gfcv_page']) ? absint($_GET['gfcv_page']) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Search criteria for GFAPI::get_entries
        $search_criteria = array(
            'status'        => 'active',
            'field_filters' => array(), // Add any field filters if needed
        );

        // Paging parameters for GFAPI::get_entries
        $paging = array(
            'offset'    => $offset,
            'page_size' => $per_page
        );

        // Sorting parameters (get the latest entries first)
        $sorting = array('key' => 'date_created', 'direction' => 'DESC');

        // Get total number of entries for pagination (limited to the last 20 for display)
        // We first get the IDs of the last 20 entries
        $total_entries_for_display_query = GFAPI::get_entries(
            $view->form_id,
            array('status' => 'active'), // Basic criteria to count relevant entries
            array('key' => 'date_created', 'direction' => 'DESC'),
            array('offset' => 0, 'page_size' => 20) // Get only the last 20
        );

        $total_entries_for_pagination = 0;
        if (!is_wp_error($total_entries_for_display_query)) {
            $total_entries_for_pagination = count($total_entries_for_display_query) > 20 ? 20 : count($total_entries_for_display_query);
        } else {
            // Handle error if needed, though for count it might not be critical
            $total_entries_for_pagination = 0;
        }
        
        // If current page requests entries beyond the last 20, adjust offset to stay within the last 20
        // For example, if per_page is 10, and we only show last 20, max page is 2.
        // If user tries to access page 3, we should show page 2 or an empty set.
        // The total_entries_for_pagination already caps at 20.
        $max_pages_for_display = ceil($total_entries_for_pagination / $per_page);
        if ($current_page > $max_pages_for_display && $max_pages_for_display > 0) {
            $current_page = $max_pages_for_display;
            $offset = ($current_page - 1) * $per_page;
            $paging['offset'] = $offset;
        }

        // Get entries for the current page, from the latest 20
        $entries = GFAPI::get_entries($view->form_id, $search_criteria, $sorting, $paging);

        // We only want to paginate through the latest 20 entries.
        // So, if $entries has more than 20, we slice it.
        // However, the $paging and $total_entries_for_display_query should handle this.
        // Let's ensure $entries are capped at $per_page for the current page, within the 20 overall.

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
                // Handle File Upload fields
                if ($field->type === 'fileupload') {
                    if (!empty($field_value)) {
                        // Check if it's a JSON string (multi-file upload)
                        if (is_string($field_value) && strpos($field_value, '[') === 0) {
                            $json_files = json_decode($field_value, true);
                            if (is_array($json_files)) {
                                $field_value = $json_files;
                            }
                        }
                        
                        // Process files array or comma-separated string
                        $files = is_array($field_value) ? $field_value : explode(',', $field_value);
                        $links = array();
                        
                        foreach ($files as $file) {
                            if (is_array($file) && isset($file['url'])) {
                                // Handle JSON format from multi-file upload
                                $file_url = $file['url'];
                                $file_name = isset($file['name']) ? $file['name'] : basename($file_url);
                                $links[] = '<a href="' . esc_url($file_url) . '" target="_blank" class="gfcv-file-link"><i class="dashicons dashicons-media-document"></i> ' . esc_html($file_name) . '</a>';
                            } else if (is_string($file) && !empty($file)) {
                                // Handle string URL format
                                $file_url = trim($file);
                                $links[] = '<a href="' . esc_url($file_url) . '" target="_blank" class="gfcv-file-link"><i class="dashicons dashicons-media-document"></i> ' . basename($file_url) . '</a>';
                            }
                        }
                        
                        if (!empty($links)) {
                            $field_value = '<div class="gfcv-file-uploads">' . implode('<br>', $links) . '</div>';
                        } else {
                            $field_value = '';
                        }
                    } else {
                        $field_value = '';
                    }
                }
                // Handle List fields
                elseif ($field->type === 'list') {
                    if (!empty($field_value)) {
                        // Check if the value is a serialized string
                        if (is_string($field_value) && strpos($field_value, 'a:') === 0) {
                            // Try to unserialize the data
                            $unserialized_data = @unserialize($field_value);
                            
                            if ($unserialized_data !== false) {
                                // Successfully unserialized
                                $field_value = '<table class="gfcv-list-table">';
                                
                                // Check if it's a multi-column list
                                $is_multi_column = false;
                                $column_headers = array();
                                
                                // Determine if it's a multi-column list and get column headers
                                if (!empty($unserialized_data) && is_array($unserialized_data)) {
                                    $first_item = reset($unserialized_data);
                                    if (is_array($first_item) && !empty($first_item)) {
                                        $is_multi_column = true;
                                        // If columns are enabled, use field choices for headers
                                        if (!empty($field->choices) && is_array($field->choices)) {
                                            foreach ($field->choices as $choice) {
                                                $column_headers[] = $choice['text'];
                                            }
                                        } else {
                                            // Use keys as headers if no choices defined
                                            $column_headers = array_keys($first_item);
                                        }
                                    }
                                }
                                
                                // Add table headers for multi-column lists
                                if ($is_multi_column && !empty($column_headers)) {
                                    $field_value .= '<thead><tr>';
                                    foreach ($column_headers as $header) {
                                        $field_value .= '<th>' . esc_html($header) . '</th>';
                                    }
                                    $field_value .= '</tr></thead>';
                                }
                                
                                $field_value .= '<tbody>';
                                
                                // Process each row
                                foreach ($unserialized_data as $row) {
                                    $field_value .= '<tr>';
                                    
                                    if ($is_multi_column && is_array($row)) {
                                        // Multi-column list
                                        foreach ($row as $cell) {
                                            $field_value .= '<td>' . esc_html($cell) . '</td>';
                                        }
                                    } else {
                                        // Single column list
                                        $field_value .= '<td>' . esc_html($row) . '</td>';
                                    }
                                    
                                    $field_value .= '</tr>';
                                }
                                
                                $field_value .= '</tbody></table>';
                            } else {
                                // Failed to unserialize, display raw
                                $field_value = esc_html($field_value);
                            }
                        } else if (is_array($field_value)) {
                            // Already an array (may happen in some contexts)
                            $field_value = '<ul>';
                            foreach ($field_value as $item) {
                                if (is_array($item)) {
                                    $field_value .= '<li>' . implode(' | ', array_map('esc_html', $item)) . '</li>';
                                } else {
                                    $field_value .= '<li>' . esc_html($item) . '</li>';
                                }
                            }
                            $field_value .= '</ul>';
                        } else {
                            // Just a string, display as is
                            $field_value = esc_html($field_value);
                        }
                    } else {
                        $field_value = '';
                    }
                }
                // Handle Checkbox fields
                elseif ($field->type === 'checkbox') {
                    error_log('GFCV Debug: Checkbox Field ID: ' . $field_id);
                    error_log('GFCV Debug: Raw Checkbox Field Value: ' . print_r($field_value, true));
                    if (!empty($field_value)) {
                        if (is_array($field_value)) {
                            $field_value = implode(', ', array_map('esc_html', $field_value));
                        } else {
                            $field_value = esc_html($field_value);
                        }
                    } else {
                        $field_value = '';
                    }
                }
                $processed = str_replace($merge_tag, $field_value, $processed);
            }
        }
        
        // Replace entry ID
        $processed = str_replace('{entry_id}', $entry['id'], $processed);
        
        // Replace date created
        $date_created_formatted = date_i18n('Y/m/d', strtotime($entry['date_created']));
            $processed = str_replace('{date_created}', $date_created_formatted, $processed);
        
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