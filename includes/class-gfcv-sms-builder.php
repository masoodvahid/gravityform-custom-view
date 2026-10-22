<?php
/**
 * Secure administrative preview and one-recipient test sending for Kavenegar.
 * A preview is not a send. Real sends require an explicit second action.
 */
if (!defined('ABSPATH')) { exit; }

class GFCV_SMS_Builder {
    public static function init() {
        add_action('wp_ajax_gfcv_sms_builder_preview', array(__CLASS__, 'preview'));
        add_action('wp_ajax_gfcv_sms_builder_send', array(__CLASS__, 'send'));
    }

    private static function authorize() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message'=>'تنها مدیر کل مجاز به انجام این عملیات است.'), 403);
        }
        check_ajax_referer('gfcv_sms_builder', 'nonce');
    }

    private static function normalize_mobile($value) {
        $value = strtr((string)$value, array(
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
            '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'
        ));
        $value = preg_replace('/[\\s\\-]+/', '', $value);
        if (preg_match('/^\\+989[0-9]{9}$/', $value)) {
            $value = '0' . substr($value, 3);
        } elseif (preg_match('/^989[0-9]{9}$/', $value)) {
            $value = '0' . substr($value, 2);
        }
        return preg_match('/^09[0-9]{9}$/', $value) ? $value : '';
    }

    /** Plain text-only replacement: do not let an SMS pattern turn into HTML. */
    public static function render_body($body, $params) {
        $body = sanitize_textarea_field((string)$body);
        $body = preg_replace_callback('/%(token(?:2|3|10|20)?)(?:%|\\b)/iu',
            function($m) use ($params) {
                return (string)($params[strtolower($m[1])] ?? '[' . $m[1] . '؟]');
            }, $body);
        return $body;
    }

    public static function preview() {
        self::authorize();
        $form_id = absint($_POST['form_id'] ?? 0);
        $view_id = absint($_POST['view_id'] ?? 0);
        $type = sanitize_key(wp_unslash($_POST['type'] ?? ''));
        $pattern = sanitize_textarea_field(wp_unslash($_POST['pattern'] ?? ''));
        $body = sanitize_textarea_field(wp_unslash($_POST['template_body'] ?? ''));
        $mobile_input = sanitize_text_field(wp_unslash($_POST['mobile'] ?? ''));
        if (!$form_id || !in_array($type, array('admin','user'), true) || !$pattern || !class_exists('GFAPI')) {
            wp_send_json_error(array('message'=>'فرم، نوع پیامک و نام پترن را مشخص کنید.'), 400);
        }
        if (strlen($pattern) > 3000 || strlen($body) > 4000) {
            wp_send_json_error(array('message'=>'متن الگو از حد مجاز طولانی‌تر است.'), 400);
        }
        $form = GFAPI::get_form($form_id);
        if (!$form || is_wp_error($form)) {
            wp_send_json_error(array('message'=>'فرم انتخاب‌شده یافت نشد.'), 400);
        }
        $view = $view_id ? GFCV_DB::get_instance()->get_view($view_id) : null;
        if ($view_id && (!$view || (int)$view->form_id !== $form_id)) {
            wp_send_json_error(array('message'=>'شناسه نما با فرم انتخاب‌شده مطابقت ندارد.'), 400);
        }
        $entries = GFAPI::get_entries($form_id, array('status'=>'active'),
            array('key'=>'id','direction'=>'DESC'), array('offset'=>0,'page_size'=>1));
        $has_entry = !is_wp_error($entries) && !empty($entries);
        $entry = $has_entry ? $entries[0] : array('id'=>0, 'form_id'=>$form_id, 'status'=>'active');
        $params = GFCV_Shortcode::get_instance()->build_sms_test_params($pattern, $entry, $form);
        $template = isset($params['template']) ? trim((string)$params['template']) : '';
        $allowed = array('token','token2','token3','token10','token20');
        $result = array();
        foreach ($allowed as $key) {
            if (isset($params[$key]) && trim((string)$params[$key]) !== '') {
                $result[$key] = sanitize_text_field((string)$params[$key]);
            }
        }
        if (!$template || !preg_match('/^[a-zA-Z0-9_\\-]{1,100}$/', $template)) {
            wp_send_json_error(array('message'=>'نام پترن باید شامل حروف لاتین، اعداد، خط تیره یا زیرخط باشد.'),400);
        }
        if (empty($result['token'])) {
            wp_send_json_error(array('message'=>'مقدار متغیر اصلی token باید مشخص شود.'),400);
        }
        $preview = trim($body) !== '' ? self::render_body($body, $result) : '';
        if ('' === $preview) {
            $lines = array('پترن: ' . $template);
            foreach ($result as $key=>$value) {
                $lines[] = $key . ': ' . $value;
            }
            $preview = implode("\n", $lines);
        }

        $mobile = $mobile_input !== '' ? self::normalize_mobile($mobile_input) : '';
        if ($mobile_input !== '' && !$mobile) {
            wp_send_json_error(array('message'=>'شماره موبایل تستی معتبر نیست؛ نمونه: 09123456789.'),400);
        }
        $ticket = '';
        $reason = '';
        if (!$mobile) {
            $reason = 'برای ارسال واقعی، شماره موبایل تستی وارد کنید.';
        } elseif (GFCV_Kavenegar::get_key() === '') {
            $reason = 'کلید API سراسری کاوه‌نگار ثبت نشده است. آن را در صفحه اصلی افزونه تنظیم کنید.';
        } else {
            foreach ($result as $token_key => $token_value) {
                // Do not send unexpanded GF field placeholders.
                if (preg_match('/\\{(?:entry_id|[0-9]+(?:\\.[0-9]+)?)\\}/u', $token_value)) {
                    $reason = 'مقدار ' . $token_key . ' هنوز به یک مقدار واقعی تبدیل نشده است.';
                    break;
                }
            }
        }

        if ($reason === '' && $mobile) {
            // One-use server-side snapshot: allowing tests before saving is safe,
            // because ONLY an administrator can mint this ticket. Values and
            // recipient cannot be changed after review and confirmation.
            $ticket = wp_generate_password(30, false, false);
            set_transient('gfcv_sms_preview_' . get_current_user_id() . '_' . $ticket, array(
                'view_id'=>$view_id,
                'form_id'=>$form_id,
                'type'=>$type,
                'mobile'=>$mobile,
                'entry_id'=>$has_entry ? absint(rgar($entry, 'id')) : 0,
                'params'=>array_merge(array('template'=>$template),$result),
                'issued_at'=>time()
            ), 5 * MINUTE_IN_SECONDS);
        }
        wp_send_json_success(array(
            'preview'=>$preview,
            'source'=>$has_entry ? 'آخرین رکورد: ' . absint($entry['id']) : 'بدون رکورد؛ مقادیر ثابت وارد کنید',
            'params'=>array_merge(array('template'=>$template),$result),
            'can_send'=>(bool)$ticket,
            'ticket'=>$ticket,
            'message'=>$ticket
                ? 'ارسال آزمایشی آماده است. برای ارسال واقعی، دکمه تأیید نهایی را بزنید.'
                : $reason
        ));
    }

    public static function send() {
        self::authorize();
        $ticket = sanitize_text_field(wp_unslash($_POST['ticket'] ?? ''));
        if (!preg_match('/^[a-zA-Z0-9]{30}$/', $ticket)) {
            wp_send_json_error(array('message'=>'پیش‌نمایش تست منقضی شده است؛ دوباره بررسی کنید.'),400);
        }
        $key = 'gfcv_sms_preview_' . get_current_user_id() . '_' . $ticket;
        $ticket_data = get_transient($key);
        if (!is_array($ticket_data)) {
            wp_send_json_error(array('message'=>'تأیید ارسال منقضی شده؛ دوباره پیش‌نمایش بگیرید.'),400);
        }
        // Consume one-time ticket before the external request.
        delete_transient($key);
        $limit = 'gfcv_sms_send_limit_' . get_current_user_id();
        if (get_transient($limit)) {
            wp_send_json_error(array('message'=>'حداقل یک دقیقه تا ارسال آزمایشی بعدی فاصله بگذارید.'),429);
        }
        // The test pattern may be new or still unsaved. We only trust the
        // reviewed one-time server snapshot, not fresh client-side parameters.
        $test_form = class_exists('GFAPI')
            ? GFAPI::get_form((int)$ticket_data['form_id']) : false;
        if (!$test_form || is_wp_error($test_form)) {
            wp_send_json_error(array('message'=>'فرم تست دیگر در دسترس نیست.'),400);
        }
        if (!empty($ticket_data['view_id'])) {
            $view = GFCV_DB::get_instance()->get_view((int)$ticket_data['view_id']);
            if (!$view || (int)$view->form_id !== (int)$ticket_data['form_id']) {
                wp_send_json_error(array('message'=>'نمای انتخاب‌شده دیگر معتبر نیست.'),400);
            }
        }
        if (GFCV_Kavenegar::get_key() === '') {
            wp_send_json_error(array('message'=>'کلید API سراسری تنظیم نشده است.'),400);
        }
        $params = (array)$ticket_data['params'];
        if (empty($params['template']) || !isset($params['token']) || trim((string)$params['token']) === '') {
            wp_send_json_error(array('message'=>'نام پترن یا مقدار اصلی پیامک مشخص نیست.'),400);
        }
        set_transient($limit, 1, MINUTE_IN_SECONDS);
        $result = GFCV_Shortcode::get_instance()->send_sms_test_lookup(
            GFCV_Kavenegar::get_key(), $ticket_data['mobile'], $ticket_data['params']
        );
        if (empty($result['success'])) {
            wp_send_json_error(array('message'=>$result['message'] ?? 'ارسال ناموفق بود.'),502);
        }
        wp_send_json_success(array(
            'message'=>'شماره مقصد: ' . $ticket_data['mobile'] . ' — ' .
                (string)($result['message'] ?? 'درخواست ارسال توسط کاوه‌نگار ثبت شد.'),
            'message_id'=>(string)($result['message_id'] ?? '')
        ));
    }
}
