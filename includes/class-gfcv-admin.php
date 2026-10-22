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

    /** Hook suffix for the dedicated admin page. */
    private $page_hook = '';

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
        add_action('wp_ajax_gfcv_test_sms', array($this, 'ajax_test_sms'));
        add_action('wp_ajax_gfcv_preview_sms', array($this, 'ajax_preview_sms'));
        add_action('wp_ajax_gfcv_sms_builder_sample', array($this, 'ajax_sms_builder_sample'));
    }

    /**
     * Add admin menu under Gravity Forms
     */
    public function add_admin_menu() {
        // A dedicated top-level screen avoids inheriting Gravity Forms menu
        // capabilities. Only users with manage_options can see/use this page.
        // Keep the legacy gf_custom_view slug so saved links still work.
        $this->page_hook = add_menu_page(
            __('مدیریت لیست‌های نمایشی', 'gravity-form-custom-view'),
            __('لیست‌های گرویتی فرم', 'gravity-form-custom-view'),
            'manage_options',
            'gf_custom_view',
            array($this, 'display_admin_page'),
            'dashicons-forms',
            58
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_gf_custom_view' !== $hook && $this->page_hook !== $hook) {
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
        wp_localize_script('gfcv-admin-scripts', 'gfcvAdminData', array('smsNonce' => wp_create_nonce('gfcv_test_sms'), 'builderNonce' => wp_create_nonce('gfcv_sms_builder')));
        // List overview is a separate, lightweight interaction bundle.
        if (!isset($_GET['action']) || !in_array(sanitize_key(wp_unslash($_GET['action'])), array('add', 'edit'), true)) {
            wp_enqueue_script(
                'gfcv-dashboard',
                GFCV_PLUGIN_URL . 'assets/js/admin-dashboard.js',
                array('jquery'),
                GFCV_PLUGIN_VERSION,
                true
            );
            wp_localize_script('gfcv-dashboard', 'gfcvDashboardData', array(
                'nonce'=>wp_create_nonce('gfcv_outbox'),
                'ajaxUrl'=>admin_url('admin-ajax.php')
            ));
        }
        wp_enqueue_script(
            'gfcv-sms-builder',
            GFCV_PLUGIN_URL . 'assets/js/sms-builder.js',
            array('jquery', 'gfcv-admin-scripts'),
            GFCV_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Display admin page
     */
    public function display_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('شما اجازه مدیریت این افزونه را ندارید.', 'gravity-form-custom-view'), '', array('response' => 403));
        }
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
        if (!current_user_can('manage_options') || !isset($_POST['gfcv_action'])) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['gfcv_nonce']) || !wp_verify_nonce($_POST['gfcv_nonce'], 'gfcv_save_view')) {
            wp_die(__('بررسی امنیتی ناموفق بود.', 'gravity-form-custom-view'));
        }
        
        $action = sanitize_text_field(wp_unslash($_POST['gfcv_action']));

        // Kavenegar credentials are shared by all custom views.
        if ('save_kavenegar_settings' === $action) {
            $provided = isset($_POST['gfcv_kavenegar_key'])
                ? trim(sanitize_text_field(wp_unslash($_POST['gfcv_kavenegar_key']))) : '';
            $clear = !empty($_POST['gfcv_clear_kavenegar_key']);
            if ($clear) {
                delete_option(GFCV_Kavenegar::OPTION_KEY);
                update_option(GFCV_Kavenegar::MIGRATION_KEY, 'manual', false);
            } elseif ($provided !== '') {
                if (!preg_match('/^[A-Za-z0-9_-]{10,255}$/D', $provided)) {
                    wp_safe_redirect(admin_url('admin.php?page=gf_custom_view&api_error=1#gfcv-service-settings'));
                    exit;
                }
                update_option(GFCV_Kavenegar::OPTION_KEY, $provided, false);
                update_option(GFCV_Kavenegar::MIGRATION_KEY, 'manual', false);
            }
            wp_safe_redirect(admin_url('admin.php?page=gf_custom_view&api_updated=1#gfcv-service-settings'));
            exit;
        }

        // Store regular and bold font sources separately. Use a neutral
        // internal family alias so this setting works with any licensed font.
        // Retain the old single IranYekan URL as a one-time regular fallback.
        if ('save_print_font' === $action) {
            $regular_input = isset($_POST['gfcv_print_font_regular_url'])
                ? trim(sanitize_text_field(wp_unslash($_POST['gfcv_print_font_regular_url'])))
                : '';
            $bold_input = isset($_POST['gfcv_print_font_bold_url'])
                ? trim(sanitize_text_field(wp_unslash($_POST['gfcv_print_font_bold_url'])))
                : '';

            $urls = array(
                'regular' => esc_url_raw($regular_input),
                'bold'    => esc_url_raw($bold_input),
            );
            foreach (array('regular' => $regular_input, 'bold' => $bold_input) as $weight => $input) {
                if ('' === $input) {
                    continue; // Allow clearing either weight.
                }
                $url = $urls[$weight];
                $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
                $host = (string) wp_parse_url($url, PHP_URL_HOST);
                $path = (string) wp_parse_url($url, PHP_URL_PATH);
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                if (
                    !in_array($scheme, array('https', 'http'), true)
                    || '' === $host
                    || !in_array($ext, array('woff2', 'woff', 'ttf', 'otf'), true)
                ) {
                    wp_safe_redirect(admin_url('admin.php?page=gf_custom_view&font_error=1'));
                    exit;
                }
            }

            update_option('gfcv_print_font_regular_url', $urls['regular'], false);
            update_option('gfcv_print_font_bold_url', $urls['bold'], false);
            // The old option is kept for compatibility with any older copy
            // of the plugin but is no longer used once the new options exist.
            wp_safe_redirect(admin_url('admin.php?page=gf_custom_view&font_updated=1'));
            exit;
        }

        $db = GFCV_DB::get_instance();
        
        if ('save_view' === $action) {
            $view_id = isset($_POST['view_id']) ? absint($_POST['view_id']) : 0;
            
            $data = array(
                'view_title' => sanitize_text_field($_POST['view_title']),
                'form_id' => absint($_POST['form_id']),
                'pdf_first_name_field_id' => $this->sanitize_pdf_field_id($_POST['pdf_first_name_field_id'] ?? ''),
                'pdf_last_name_field_id' => $this->sanitize_pdf_field_id($_POST['pdf_last_name_field_id'] ?? ''),
                'field_ids' => implode(',', array_map('absint', array_filter(explode(',', sanitize_text_field(wp_unslash($_POST['field_ids'] ?? '')))))),
                'show_all_options' => isset($_POST['show_all_options']) ? 1 : 0,
                'respect_conditional_logic' => isset($_POST['respect_conditional_logic']) ? 1 : 0,
                'admin_sms_pattern' => sanitize_textarea_field(wp_unslash($_POST['admin_sms_pattern'] ?? '')),
                'user_sms_pattern' => sanitize_textarea_field(wp_unslash($_POST['user_sms_pattern'] ?? '')),
                'admin_sms_template_text' => sanitize_textarea_field(wp_unslash($_POST['admin_sms_template_text'] ?? '')),
                'user_sms_template_text' => sanitize_textarea_field(wp_unslash($_POST['user_sms_template_text'] ?? '')),
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
     * Accept a Gravity Forms field ID or compound input ID (e.g. 1.3).
     * These IDs are configuration, not arbitrary merge-tag expressions.
     */
    private function sanitize_pdf_field_id($value) {
        $value = sanitize_text_field(wp_unslash((string) $value));
        return preg_match('/^[0-9]+(?:\\.[0-9]+)?$/', $value) ? $value : '';
    }

    /**
     * AJAX handler for getting form fields
     */
    public function ajax_get_form_fields() {
        if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'دسترسی غیرمجاز'), 403); }
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gfcv_save_view')) {
            wp_send_json_error(array('message' => __('بررسی امنیتی ناموفق بود.', 'gravity-form-custom-view')));
        }
        
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        
        if (!$form_id) {
            wp_send_json_error(array('message' => __('شناسه فرم معتبر نیست.', 'gravity-form-custom-view')));
        }
        
        // Get form fields
        if (!class_exists('GFAPI')) {
            wp_send_json_error(array('message' => __('دسترسی به Gravity Forms ممکن نیست.', 'gravity-form-custom-view')));
        }
        
        $form = GFAPI::get_form($form_id);
        
        if (!$form) {
            wp_send_json_error(array('message' => __('فرم موردنظر یافت نشد.', 'gravity-form-custom-view')));
        }
        
        // Build HTML for field list
        $html = '';
        
        if (!empty($form['fields'])) {
            foreach ($form['fields'] as $field) {
                if (!$field->displayOnly) {
                    $html .= '<button type="button" class="field-item" data-field-id="' . esc_attr($field->id) . '"><code>' . esc_html($field->id) . '</code><span>' . esc_html($field->label) . '</span></button>';
                }
            }
        } else {
            $html = '<p>' . __('این فرم فیلدی برای نمایش ندارد.', 'gravity-form-custom-view') . '</p>';
        }
        
        // PDF filename selectors support both ordinary fields and Name sub-inputs.
        $pdf_fields = array();
        foreach ($form['fields'] as $field) {
            if (!empty($field->displayOnly)) {
                continue;
            }
            $pdf_fields[] = array('id' => (string) $field->id, 'label' => (string) $field->label);
            if (!empty($field->inputs) && is_array($field->inputs)) {
                foreach ($field->inputs as $input) {
                    if (isset($input['id'])) {
                        $pdf_fields[] = array(
                            'id' => (string) $input['id'],
                            'label' => (string) $field->label . ' — ' . (string) ($input['label'] ?? $input['id']),
                        );
                    }
                }
            }
        }

        wp_send_json_success(array('pdf_fields' => $pdf_fields, 'html' => $html, 'fields' => array_values(array_map(function($field) {return array('id' => (string)$field->id, 'label' => $field->label);}, array_filter($form['fields'], function($field) {return !$field->displayOnly;})))));
    }
    /** Preview sample data for the SMS form builder. Access is admin-only. */
    public function ajax_sms_builder_sample() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message'=>'دسترسی غیرمجاز.'),403);
        }
        check_ajax_referer('gfcv_sms_builder','nonce');
        $form_id = absint($_POST['form_id'] ?? 0);
        if (!$form_id || !class_exists('GFAPI')) {
            wp_send_json_error(array('message'=>'ابتدا فرم معتبر انتخاب کنید.'),400);
        }
        $form = GFAPI::get_form($form_id);
        if (!$form || is_wp_error($form)) {
            wp_send_json_error(array('message'=>'فرم یافت نشد.'),404);
        }
        $entries = GFAPI::get_entries($form_id, array('status'=>'active'),
            array('key'=>'id','direction'=>'DESC'), array('offset'=>0,'page_size'=>1));
        $entry = (!is_wp_error($entries) && !empty($entries)) ? $entries[0] : array();
        $values = array();
        foreach ((array)($form['fields'] ?? array()) as $field) {
            if (!empty($field->displayOnly)) { continue; }
            $id = (string)$field->id;
            $value = rgar($entry, $id);
            $values[$id] = is_scalar($value) ? sanitize_text_field((string)$value) : '';
            if (!empty($field->inputs) && is_array($field->inputs)) {
                $pieces = array();
                foreach ($field->inputs as $input) {
                    $input_id = (string)($input['id'] ?? '');
                    if (!$input_id) { continue; }
                    $v = rgar($entry, $input_id);
                    $values[$input_id] = is_scalar($v) ? sanitize_text_field((string)$v) : '';
                    if ($values[$input_id] !== '') { $pieces[] = $values[$input_id]; }
                }
                if (!$values[$id]) { $values[$id] = implode(' ', $pieces); }
            }
        }
        wp_send_json_success(array(
            'entry_id'=>absint(rgar($entry,'id')),
            'values'=>$values,
            'has_entry'=>!empty($entry)
        ));
    }

    /** Only admins can preview/send a test using persisted credentials. */
    private function sms_test_context() {
        if (!current_user_can('manage_options')) { wp_send_json_error(array('message'=>'دسترسی غیرمجاز'),403); }
        check_ajax_referer('gfcv_test_sms', 'nonce');
        $id=absint($_POST['view_id'] ?? 0);
        $view=GFCV_DB::get_instance()->get_view($id);
        if (!$view || !class_exists('GFAPI')) {wp_send_json_error(array('message'=>'ابتدا نما را ذخیره کنید.'),400);}
        $numbers=preg_split('/[,;\\s]+/', (string)$view->admin_mobile_numbers,-1,PREG_SPLIT_NO_EMPTY);
        $numbers=array_values(array_unique(array_filter($numbers,function($n){return (bool)preg_match('/^(?:\\+98|0098|0)?9\\d{9}$/', $n);})));
        if (!$numbers || count($numbers)>10) {wp_send_json_error(array('message'=>'۱ تا ۱۰ شماره همراه معتبر برای مدیران وارد کنید.'),400);}
        $entries=GFAPI::get_entries($view->form_id,array('status'=>'active'),array('key'=>'id','direction'=>'DESC'),array('offset'=>0,'page_size'=>1));
        if (is_wp_error($entries) || !$entries) {wp_send_json_error(array('message'=>'رکورد نمونه برای ساخت پیامک وجود ندارد.'),400);}
        $form=GFAPI::get_form($view->form_id);
        if (!$form || !$view->admin_sms_pattern || !GFCV_Kavenegar::get_key()) {wp_send_json_error(array('message'=>'الگوی مدیر یا کلید API تنظیم نشده است.'),400);}
        return array($view,$numbers,$entries[0],$form);
    }

    public function ajax_preview_sms() {
        list($view,$numbers,$entry,$form)=$this->sms_test_context();
        $shortcode=GFCV_Shortcode::get_instance();
        $params=$shortcode->build_sms_test_params($view->admin_sms_pattern,$entry,$form);
        if (empty($params['template'])) {wp_send_json_error(array('message'=>'نام قالب کاوه‌نگار مشخص نیست.'),400);}
        wp_send_json_success(array('recipients'=>$numbers,'entry_id'=>$entry['id'],'params'=>$params,'fingerprint'=>hash('sha256',wp_json_encode(array($view->id,$numbers,$entry['id'],$params)))));
    }

    public function ajax_test_sms() {
        list($view,$numbers,$entry,$form)=$this->sms_test_context();
        $shortcode=GFCV_Shortcode::get_instance();
        $params=$shortcode->build_sms_test_params($view->admin_sms_pattern,$entry,$form);
        if (empty($params['template'])) {wp_send_json_error(array('message'=>'الگوی پیامک معتبر نیست.'),400);}
        $fingerprint=hash('sha256',wp_json_encode(array($view->id,$numbers,$entry['id'],$params)));
        if (empty($_POST['fingerprint']) || !hash_equals($fingerprint,sanitize_text_field(wp_unslash($_POST['fingerprint'])))) {
            wp_send_json_error(array('message'=>'اطلاعات پیش‌نمایش تغییر کرده است؛ دوباره پیش‌نمایش بگیرید.'),400);
        }
        $key='gfcv_sms_test_lock_'.get_current_user_id();
        if (get_transient($key)) {wp_send_json_error(array('message'=>'برای ارسال تست بعدی یک دقیقه صبر کنید.'),429);}
        set_transient($key,1,MINUTE_IN_SECONDS);
        $results=array();
        foreach ($numbers as $number) {
            $result=$shortcode->send_sms_test_lookup(GFCV_Kavenegar::get_key(),$number,$params);
            $results[]=array('number'=>$number,'success'=>!empty($result['success']),'message'=>$result['message'] ?? '');
        }
        wp_send_json_success(array('results'=>$results));
    }

}