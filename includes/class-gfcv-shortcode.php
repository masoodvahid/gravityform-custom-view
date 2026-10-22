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
        // SMS details require a logged-in, authorized user.
        
        add_action('wp_ajax_gfcv_send_sms', array($this, 'ajax_send_sms'));
        // Sending is restricted to authorized logged-in users.
        
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
            
            // Reusable details/report layout, independent from stored HTML.
            wp_enqueue_style(
                'gfcv-report-styles',
                GFCV_PLUGIN_URL . 'assets/css/report.css',
                array('gfcv-frontend-styles'),
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
                    'custom_buttons_css_url' => GFCV_PLUGIN_URL . 'assets/css/custom-buttons.css',
                    'report_css_url' => add_query_arg('ver', GFCV_PLUGIN_VERSION, GFCV_PLUGIN_URL . 'assets/css/report.css'),
                    'report_print_css_url' => add_query_arg('ver', GFCV_PLUGIN_VERSION, GFCV_PLUGIN_URL . 'assets/css/report-print.css'),
                    'print_font_regular_url' => esc_url_raw(get_option('gfcv_print_font_regular_url', get_option('gfcv_print_font_url', ''))),
                    'print_font_bold_url' => esc_url_raw(get_option('gfcv_print_font_bold_url', ''))
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

        // Get total number of entries for pagination
        $total_entries_for_pagination = GFAPI::count_entries(
            $view->form_id,
            $search_criteria
        );
        
        // Adjust current page and offset if it exceeds total pages
        $total_pages = ceil($total_entries_for_pagination / $per_page);
        if ($current_page > $total_pages && $total_pages > 0) {
            $current_page = $total_pages;
            $offset = ($current_page - 1) * $per_page;
            $paging['offset'] = $offset;
        }

        // Get entries for the current page
        $entries = GFAPI::get_entries($view->form_id, $search_criteria, $sorting, $paging);

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
        if (current_user_can('manage_options') || is_super_admin()) {
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

        // A public nonce is not an access-control check.
        if (!empty($view->user_access_ids) && !$this->user_has_access((string) $view->user_access_ids)) {
            wp_send_json_error(array('message' => __('شما اجازه مشاهده این گزارش را ندارید.', 'gravity-form-custom-view')), 403);
        }

        // Get the entry
        if (!class_exists('GFAPI')) {
            wp_send_json_error(array('message' => __('Gravity Forms API not available', 'gravity-form-custom-view')));
        }
        
        $entry = GFAPI::get_entry($entry_id);
        
        if (is_wp_error($entry) || !is_array($entry) || (int) rgar($entry, 'form_id') !== (int) $view->form_id || rgar($entry, 'status') !== 'active') {
            wp_send_json_error(array('message' => __('Entry not found', 'gravity-form-custom-view')), 404);
        }

        // Get the form
        $form = GFAPI::get_form($view->form_id);
        
        if (!$form || is_wp_error($form)) {
            wp_send_json_error(array('message' => __('Form not found', 'gravity-form-custom-view')), 404);
        }

        // Process the details view HTML
        $details_template = GFCV_Conditional_Details::prepare($view->details_view_html, $form, $entry, !isset($view->respect_conditional_logic) || (bool)$view->respect_conditional_logic);
        $details_html = $this->process_template($details_template, $entry, $form, !isset($view->show_all_options) || (bool)$view->show_all_options);
        
        wp_send_json_success(array(
            'html' => $details_html,
            'pdf_title' => $this->pdf_document_title($view, $form, $entry)
        ));
    }

    /**
     * Build a safe and useful title for browser Print > Save as PDF.
     * Browsers usually derive the suggested filename from document.title.
     */
    private function pdf_document_title($view, $form, $entry) {
        $first = $this->pdf_entry_text($entry, $form, $view->pdf_first_name_field_id ?? '');
        $last = $this->pdf_entry_text($entry, $form, $view->pdf_last_name_field_id ?? '');
        $full_name = trim($first . ' ' . $last);
        if ('' === $full_name) {
            $full_name = 'رکورد ' . absint(rgar($entry, 'id'));
        }
        $form_title = !empty($form['title']) ? $form['title'] : 'فرم';
        $title = absint($view->form_id) . ' - ' . $full_name . ' - ' . $form_title;

        // Keep filenames compatible with Windows, macOS and common PDF tools.
        $title = wp_strip_all_tags($title, true);
        $title = str_replace(array('/', chr(92), ':', '*', '?', '"', '<', '>', '|'), ' ', $title);
        $title = preg_replace('/[\\x00-\\x1F\\x7F]+/u', ' ', $title);
        $title = preg_replace('/\\s+/u', ' ', $title);
        $title = trim($title, " .-\\t\\n\\r\\0\\x0B");
        return function_exists('mb_substr') ? mb_substr($title, 0, 180) : substr($title, 0, 180);
    }

    /**
     * Get a textual value from a normal field or a Gravity Forms compound
     * field such as Name (its saved sub-input IDs are 1.3, 1.6, etc.).
     */
    private function pdf_entry_text($entry, $form, $field_id) {
        $field_id = (string) $field_id;
        if (!preg_match('/^[0-9]+(?:\\.[0-9]+)?$/', $field_id)) {
            return '';
        }
        $value = rgar($entry, $field_id);
        if (!is_array($value) && !is_object($value) && (null === $value || '' === trim((string) $value)) && false === strpos($field_id, '.')) {
            foreach ((array) ($form['fields'] ?? array()) as $field) {
                if ((string) $field->id !== $field_id || empty($field->inputs)) {
                    continue;
                }
                $pieces = array();
                foreach ($field->inputs as $input) {
                    if (isset($input['id'])) {
                        $part = trim((string) rgar($entry, (string) $input['id']));
                        if ('' !== $part) {
                            $pieces[] = $part;
                        }
                    }
                }
                $value = implode(' ', $pieces);
                break;
            }
        }
        if (is_array($value) || is_object($value)) {
            return '';
        }
        return sanitize_text_field((string) $value);
    }

    /**
     * AJAX handler for getting SMS template
     */
    public function ajax_get_sms_template() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'gfcv_nonce')) {
            wp_send_json_error(array('message'=>'بررسی امنیتی ناموفق بود.'),403);
        }
        // Record-level SMS details must never be exposed via anonymous AJAX.
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message'=>'برای پیش‌نمایش پیامک وارد حساب کاربری شوید.'),403);
        }
        $entry_id = absint($_POST['entry_id'] ?? 0);
        $view_id = absint($_POST['view_id'] ?? 0);
        $type = sanitize_key(wp_unslash($_POST['type'] ?? ''));
        if (!$entry_id || !$view_id || !in_array($type,array('user','admin'),true)) {
            wp_send_json_error(array('message'=>'پارامترهای پیامک معتبر نیست.'),400);
        }
        $view = GFCV_DB::get_instance()->get_view($view_id);
        if (!$view || (!current_user_can('manage_options') && !is_super_admin() &&
                (empty($view->user_access_ids) || !$this->user_has_access((string)$view->user_access_ids)))) {
            wp_send_json_error(array('message'=>'شما اجازه مشاهده پیامک‌های این نما را ندارید.'),403);
        }
        if (!class_exists('GFAPI')) { wp_send_json_error(array('message'=>'Gravity Forms در دسترس نیست.'),400); }
        $entry = GFAPI::get_entry($entry_id);
        if (is_wp_error($entry) || !is_array($entry) ||
            (int)rgar($entry,'form_id') !== (int)$view->form_id || rgar($entry,'status')!=='active') {
            wp_send_json_error(array('message'=>'رکورد انتخاب‌شده یافت نشد.'),404);
        }
        $form = GFAPI::get_form($view->form_id);
        if (!$form || is_wp_error($form)) { wp_send_json_error(array('message'=>'فرم یافت نشد.'),404); }
        $pattern = (string)($type === 'admin' ? $view->admin_sms_pattern : $view->user_sms_pattern);
        $body = (string)($type === 'admin' ? ($view->admin_sms_template_text ?? '') : ($view->user_sms_template_text ?? ''));
        $params = $this->build_sms_test_params($pattern,$entry,$form);
        $template = (string)($params['template'] ?? '');
        $recipient = $type === 'user' && !empty($view->user_mobile_field_id)
            ? (string)rgar($entry,(string)$view->user_mobile_field_id)
            : (string)($view->admin_mobile_numbers ?? '');
        if (trim($body)!=='') {
            $message = GFCV_SMS_Builder::render_body($body,$params);
            $exact = true;
        } else {
            $lines = array('پترن: ' . $template);
            foreach (array('token','token2','token3','token10','token20') as $key) {
                if (isset($params[$key])) { $lines[]=$key . ': ' . $params[$key]; }
            }
            $message = implode("\n", $lines);
            $exact = false;
        }
        wp_send_json_success(array(
            'message'=>$message,
            'recipient'=>$recipient,
            'template'=>$template,
            'params'=>array_intersect_key($params,array_flip(array('token','token2','token3','token10','token20'))),
            'exact_preview'=>$exact,
            'entry_id'=>$entry_id
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
    /**
     * Text/glyph-based answer marker survives black-only printing and photocopy.
     * The word "selected" is intentionally retained for archival clarity.
     */
    private function format_print_choice($label, $checked, $type) {
        $selected_class = $checked ? ' gfcv-choice--selected' : ' gfcv-choice--unselected';
        $mark = $checked ? '■' : '□';
        $label_html = $checked
            ? '<strong class="gfcv-choice-label">' . esc_html((string) $label) . '</strong>'
            : '<span class="gfcv-choice-label">' . esc_html((string) $label) . '</span>';
        $selected_note = $checked
            ? '<span class="gfcv-choice-selected-note"> (انتخاب‌شده)</span>'
            : '';

        return '<span class="gfcv-choice' . $selected_class . '" data-choice-type="' . esc_attr($type) . '">'
            . '<span class="gfcv-choice-marker" aria-hidden="true">' . $mark . '</span> '
            . '<span class="gfcv-choice-text">' . $label_html . $selected_note . '</span></span>';
    }

    private function process_template($template, $entry, $form, $show_all_options = true) {
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
                                $links[] = '<a href="' . esc_url($file_url) . '" target="_blank" class="gfcv-file-link">' . basename($file_url) . '</a>';
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
                // Never print disabled browser inputs: their checkmarks can fade
                // or vanish when the printer ignores form-control backgrounds.
                // A solid black glyph and a textual label are always printable.
                elseif ($field->type === 'checkbox') {
                    $items = array();
                    if (!empty($field->inputs) && is_array($field->inputs)) {
                        foreach ($field->inputs as $index => $input) {
                            $value = rgar($entry, (string) $input['id']);
                            $checked = $value !== null && trim((string) $value) !== '';
                            $choice = isset($field->choices[$index]) ? $field->choices[$index] : array();
                            $label = $choice['text'] ?? ($input['label'] ?? $value);
                            if ($checked || $show_all_options) {
                                $items[] = $this->format_print_choice($label, $checked, 'checkbox');
                            }
                        }
                    }
                    $field_value = $items ? '<div class="gfcv-choice-list" data-choice-layout="three-columns">' . implode('', $items) . '</div>' : '';
                }
                elseif ($field->type === 'radio') {
                    if (!empty($field->choices)) {
                        $items = array();
                        foreach ($field->choices as $choice) {
                            $choice_value = $choice['value'] ?? $choice['text'];
                            $checked = (string) $choice_value === (string) $field_value
                                || (string) $choice['text'] === (string) $field_value;
                            if ($checked || $show_all_options) {
                                $items[] = $this->format_print_choice($choice['text'], $checked, 'radio');
                            }
                        }
                        $field_value = $items ? '<div class="gfcv-choice-list" data-choice-layout="three-columns">' . implode('', $items) . '</div>' : '';
                    } else {
                        $field_value = esc_html((string) $field_value);
                    }
                }

                // Handle Select fields
                elseif ($field->type === 'select') {
                    error_log('GFCV Debug: Select Field ID: ' . $field_id);
                    error_log('GFCV Debug: Raw Select Field Value: ' . print_r($field_value, true));
                    if (!empty($field_value)) {
                        $field_value = esc_html($field_value);
                    } else {
                        $field_value = '';
                    }
                }
                // Convert numbers to Farsi digits for all field values                
                $processed = str_replace($merge_tag, $field_value, $processed);
            }
        }
        
        // Replace entry ID
        $processed = str_replace('{entry_id}', $this->convert_to_farsi_numbers($entry['id']), $processed);
        
        // Replace date created
        $date_created_formatted = date_i18n('Y/m/d', strtotime($entry['date_created']));
        $date_created_formatted = $this->convert_to_farsi_numbers($date_created_formatted);
            $processed = str_replace('{date_created}', $date_created_formatted, $processed);
        
        return $processed;
    }

    /**
     * Converts Arabic/Latin digits in a string to Farsi digits.
     *
     * @param string $string The input string.
     * @return string The string with Farsi digits.
     */
    private function convert_to_farsi_numbers($string) {
        $farsi_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        $latin_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        return str_replace($latin_digits, $farsi_digits, $string);
    }
    
    /**
     * AJAX handler for sending SMS via Kavenegar
     */
    public function ajax_send_sms() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'gfcv_nonce')) {
            wp_send_json_error(array('message'=>'بررسی امنیتی ناموفق بود.'),403);
        }
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message'=>'برای ارسال پیامک باید وارد حساب کاربری شوید.'),403);
        }
        $entry_id = absint($_POST['entry_id'] ?? 0);
        $view_id = absint($_POST['view_id'] ?? 0);
        $type = sanitize_key(wp_unslash($_POST['type'] ?? ''));
        if (!$entry_id || !$view_id || !in_array($type,array('user','admin'),true)) {
            wp_send_json_error(array('message'=>'شناسه پیامک معتبر نیست.'),400);
        }
        $view = GFCV_DB::get_instance()->get_view($view_id);
        if (!$view || (!current_user_can('manage_options') && !is_super_admin() &&
            (empty($view->user_access_ids) || !$this->user_has_access((string)$view->user_access_ids)))) {
            wp_send_json_error(array('message'=>'اجازه ارسال پیامک از این نما را ندارید.'),403);
        }
        if (empty($view->kavenegar_api_key) || !class_exists('GFAPI')) {
            wp_send_json_error(array('message'=>'کلید کاوه‌نگار یا Gravity Forms در دسترس نیست.'),400);
        }
        $entry = GFAPI::get_entry($entry_id);
        if (is_wp_error($entry) || !is_array($entry) || (int)rgar($entry,'form_id')!==(int)$view->form_id ||
            rgar($entry,'status')!=='active') {
            wp_send_json_error(array('message'=>'رکورد متعلق به این فرم نیست.'),404);
        }
        $form = GFAPI::get_form($view->form_id);
        if (!$form || is_wp_error($form)) { wp_send_json_error(array('message'=>'فرم یافت نشد.'),404); }
        $pattern = $type === 'admin' ? $view->admin_sms_pattern : $view->user_sms_pattern;
        if (!$pattern) { wp_send_json_error(array('message'=>'پترن پیامک تعیین نشده است.'),400); }
        $raw_recipient = $type === 'user'
            ? (string)rgar($entry, (string)$view->user_mobile_field_id)
            : (string)$view->admin_mobile_numbers;
        $numbers = preg_split('/[,;\\s]+/u', $raw_recipient, -1, PREG_SPLIT_NO_EMPTY);
        $numbers = array_values(array_unique(array_filter($numbers,function($n){
            return (bool)preg_match('/^(?:\\+98|0098|0)?9[0-9]{9}$/', $n);
        })));
        if (!$numbers || count($numbers)>10 || ($type === 'user' && count($numbers)!==1)) {
            wp_send_json_error(array('message'=>'شماره گیرنده معتبر نیست؛ حداکثر ۱۰ شماره مجاز است.'),400);
        }
        $params = $this->parse_kavenegar_pattern($pattern,$entry,$form);
        if (empty($params['template']) || empty($params['token'])) {
            wp_send_json_error(array('message'=>'نام پترن یا مقدار token تعریف نشده است.'),400);
        }
        $lock = 'gfcv_sms_front_send_' . get_current_user_id();
        if (get_transient($lock)) {
            wp_send_json_error(array('message'=>'برای جلوگیری از ارسال تکراری، یک دقیقه فاصله بگذارید.'),429);
        }
        set_transient($lock,1,MINUTE_IN_SECONDS);
        $out = array();
        foreach ($numbers as $n) {
            $result = $this->send_kavenegar_lookup($view->kavenegar_api_key,$n,$params);
            $out[] = array('number'=>$n,'success'=>!empty($result['success']),'message'=>$result['message']??'');
        }
        $success = count(array_filter($out,function($v){return $v['success'];})) === count($out);
        if ($success) {
            wp_send_json_success(array('message'=>'پیامک با موفقیت ارسال شد.','results'=>$out));
        }
        wp_send_json_error(array('message'=>'ارسال برای برخی گیرندگان ناموفق بود.','results'=>$out),502);
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
        $processed = $this->process_template($pattern, $entry, $form, false);
        $params = array();
        // Existing templates are encoded as token=...token2=...template=...
        // Capture each value up to the next recognized parameter.
        preg_match_all('/(?:^|(?<=\\s)|(?<=[^a-zA-Z]))(token(?:2|3|10|20)?|template)=(.*?)(?=token(?:2|3|10|20)?=|template=|$)/us', $processed, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $params[$match[1]] = trim($match[2]);
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
    /** Reuse existing SMS pattern resolution without exposing API keys. */
    public function build_sms_test_params($pattern, $entry, $form) {
        return $this->parse_kavenegar_pattern($pattern, $entry, $form);
    }

    public function send_sms_test_lookup($key, $recipient, $params) {
        return $this->send_kavenegar_lookup($key, $recipient, $params);
    }

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