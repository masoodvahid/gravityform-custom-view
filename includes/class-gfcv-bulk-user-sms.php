<?php
/**
 * Bulk user-pattern SMS dispatch for explicitly selected Gravity Forms entries.
 *
 * Admin-only. A preview stores one immutable batch of validated recipients and
 * fully resolved token values; each confirmed request processes one recipient.
 * This avoids long PHP execution and prevents accidental repeated sends.
 */
if (!defined('ABSPATH')) { exit; }

class GFCV_Bulk_User_SMS {
    const MAX_SELECTED = 100;
    const JOB_TTL = 30 * MINUTE_IN_SECONDS;

    public static function init() {
        add_action('wp_ajax_gfcv_bulk_user_sms_preview', array(__CLASS__, 'preview'));
        add_action('wp_ajax_gfcv_bulk_user_sms_step', array(__CLASS__, 'step'));
    }

    private static function authorize() {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_send_json_error(array('message'=>'فقط مدیرکل مجاز به ارسال گروهی پیامک است.'),403);
        }
        check_ajax_referer('gfcv_bulk_user_sms', 'nonce');
    }

    private static function mobile($value) {
        $value = strtr((string)$value, array(
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'
        ));
        $value = preg_replace('/[\\s()\\-]+/u','',trim($value));
        if (preg_match('/^\\+989[0-9]{9}$/D',$value)) {
            $value='0'.substr($value,3);
        } elseif (preg_match('/^00989[0-9]{9}$/D',$value)) {
            $value='0'.substr($value,4);
        } elseif (preg_match('/^989[0-9]{9}$/D',$value)) {
            $value='0'.substr($value,2);
        }
        return preg_match('/^09[0-9]{9}$/D',$value) ? $value : '';
    }

    private static function key($ticket) {
        return 'gfcv_bulk_' . get_current_user_id() . '_' . $ticket;
    }

    private static function public_stats($job) {
        $sent=0; $failed=0; $unknown=0;
        foreach ((array) ($job['results'] ?? array()) as $result) {
            if (($result['status'] ?? '')==='accepted') { $sent++; }
            elseif (($result['status'] ?? '')==='failed') { $failed++; }
            else { $unknown++; }
        }
        return array(
            'selected'=>$job['selected'],
            'eligible'=>count($job['items']),
            'skipped'=>count($job['skipped']),
            'processed'=>count($job['results']),
            'accepted'=>$sent,'failed'=>$failed,'unknown'=>$unknown,
            'next'=>(int)$job['next'],
            'complete'=>$job['next'] >= count($job['items']),
            'results'=>array_values($job['results'])
        );
    }

    public static function preview() {
        self::authorize();
        if (GFCV_Kavenegar::get_key()==='') {
            wp_send_json_error(array('message'=>'ابتدا کلید API سراسری کاوه‌نگار را در تنظیمات افزونه ثبت کنید.'),400);
        }
        if (!class_exists('GFAPI')) {
            wp_send_json_error(array('message'=>'Gravity Forms در دسترس نیست.'),400);
        }
        $view_id=absint($_POST['view_id'] ?? 0);
        $view=GFCV_DB::get_instance()->get_view($view_id);
        if (!$view || empty($view->user_sms_pattern) || empty($view->user_mobile_field_id)) {
            wp_send_json_error(array('message'=>'در این لیست، پترن پیامک کاربر یا شناسه فیلد موبایل تنظیم نشده است.'),400);
        }
        $raw_ids=isset($_POST['entry_ids']) ? (array)wp_unslash($_POST['entry_ids']) : array();
        // Do not accept unbounded queries from the browser.
        if (count($raw_ids) < 1 || count($raw_ids) > self::MAX_SELECTED) {
            wp_send_json_error(array('message'=>'حداقل یک رکورد و حداکثر ۱۰۰ رکورد در هر نوبت انتخاب کنید.'),400);
        }
        $ids=array_values(array_unique(array_filter(array_map('absint',$raw_ids))));
        if (count($ids)===0) {
            wp_send_json_error(array('message'=>'رکورد معتبری انتخاب نشده است.'),400);
        }
        $form=GFAPI::get_form((int)$view->form_id);
        if (!$form || is_wp_error($form)) {
            wp_send_json_error(array('message'=>'فرم مرتبط با لیست پیدا نشد.'),400);
        }
        $shortcode=GFCV_Shortcode::get_instance();
        $seen=array();
        $items=array();
        $skipped=array();
        $body=(string)($view->user_sms_template_text ?? '');
        $template_name='';
        $sample='';

        foreach ($ids as $entry_id) {
            $entry=GFAPI::get_entry($entry_id);
            if (is_wp_error($entry) || !is_array($entry)
                || (int)rgar($entry,'form_id') !== (int)$view->form_id
                || rgar($entry,'status') !== 'active') {
                $skipped[]=array('entry_id'=>$entry_id,'reason'=>'رکورد معتبر یا فعال نیست.');
                continue;
            }
            $mobile=self::mobile(GFCV_Shortcode::entry_user_mobile($entry, $view));
            if ($mobile==='') {
                $skipped[]=array('entry_id'=>$entry_id,'reason'=>'شماره موبایل معتبر ندارد.');
                continue;
            }
            if (isset($seen[$mobile])) {
                $skipped[]=array('entry_id'=>$entry_id,'reason'=>'شماره موبایل تکراری در انتخاب‌ها.');
                continue;
            }
            $params=$shortcode->build_sms_test_params((string)$view->user_sms_pattern,$entry,$form);
            $pattern=(string)($params['template'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$pattern) || !isset($params['token']) || trim((string)$params['token'])==='') {
                $skipped[]=array('entry_id'=>$entry_id,'reason'=>'نام پترن یا توکن اصلی نامعتبر است.');
                continue;
            }
            $tokens=array('template'=>$pattern);
            $bad=false;
            foreach (array('token','token2','token3','token10','token20') as $token) {
                if (!array_key_exists($token,$params)) { continue; }
                if (!is_scalar($params[$token]) || preg_match('/\\{(?:entry_id|[0-9]+(?:\\.[0-9]+)?)\\}/',(string)$params[$token])) {
                    $bad=true; break;
                }
                $tokens[$token]=sanitize_text_field((string)$params[$token]);
            }
            if ($bad) {
                $skipped[]=array('entry_id'=>$entry_id,'reason'=>'متغیرهای پترن کامل نیستند.');
                continue;
            }
            $seen[$mobile]=true;
            $items[]=array(
                'entry_id'=>$entry_id,'mobile'=>$mobile,'params'=>$tokens
            );
            if (!$sample) {
                $template_name=$pattern;
                $sample=trim($body)!==''
                    ? GFCV_SMS_Builder::render_body($body,$tokens)
                    : '';
            }
        }
        if (empty($items)) {
            wp_send_json_error(array(
                'message'=>'هیچ گیرنده معتبری برای ارسال یافت نشد.',
                'skipped'=>$skipped
            ),400);
        }
        $ticket=wp_generate_password(40,false,false);
        $job=array(
            'view_id'=>$view_id,'owner'=>get_current_user_id(),
            'created'=>time(),'selected'=>count($ids),
            'items'=>$items,'skipped'=>$skipped,
            'next'=>0,'results'=>array(),
        );
        set_transient(self::key($ticket),$job,self::JOB_TTL);
        wp_send_json_success(array(
            'ticket'=>$ticket,
            'selected'=>count($ids),
            'eligible'=>count($items),
            'skipped'=>$skipped,
            'template'=>$template_name,
            'sample'=>$sample ?: 'متن پترن در تنظیمات وارد نشده است؛ فقط مقادیر زیر به کاوه‌نگار ارسال می‌شوند.',
            'exact_preview'=>trim($body)!=='',
            'sample_entry_id'=>$items[0]['entry_id'],
            'sample_params'=>$items[0]['params'],
            'first_mobile'=>$items[0]['mobile'],
            'admin_url'=>admin_url('admin.php?page=gf_custom_view')
        ));
    }

    public static function step() {
        self::authorize();
        $ticket=sanitize_text_field(wp_unslash($_POST['ticket'] ?? ''));
        $cursor=isset($_POST['cursor']) ? absint($_POST['cursor']) : 0;
        if (!preg_match('/^[A-Za-z0-9]{40}$/D',$ticket)
            || empty($_POST['confirmed']) || (string)$_POST['confirmed']!=='1') {
            wp_send_json_error(array('message'=>'تأیید ارسال یا شناسه عملیات معتبر نیست.'),400);
        }
        $key=self::key($ticket);
        $job=get_transient($key);
        if (!is_array($job) || (int)$job['owner']!==get_current_user_id()
            || time()-(int)$job['created']>self::JOB_TTL) {
            wp_send_json_error(array('message'=>'مهلت عملیات تمام شده؛ برای ادامه دوباره انتخاب و پیش‌نمایش بگیرید.'),410);
        }
        if ($cursor < (int)$job['next']) {
            wp_send_json_success(self::public_stats($job));
        }
        if ($cursor !== (int)$job['next']) {
            wp_send_json_error(array('message'=>'ترتیب ارسال نامعتبر است.'),409);
        }
        if ($job['next']>=count($job['items'])) {
            wp_send_json_success(self::public_stats($job));return;
        }
        if (GFCV_Kavenegar::get_key()==='') {
            wp_send_json_error(array('message'=>'کلید API سراسری حذف شده است؛ ارسال متوقف شد.'),400);
        }

        // Atomic option insert prevents parallel AJAX requests from sending
        // the same recipient twice; a stale lock can be safely reclaimed.
        $lock='gfcv_bulk_lock_' . md5($ticket);
        $acquired=add_option($lock,(string)time(),'','no');
        if (!$acquired) {
            $since=(int)get_option($lock,0);
            if ($since && time()-$since>90) {
                delete_option($lock);
                $acquired=add_option($lock,(string)time(),'','no');
            }
        }
        if (!$acquired) {
            wp_send_json_error(array('message'=>'درخواست قبلی هنوز در حال پردازش است. چند لحظه بعد دوباره تلاش کنید.'),409);
        }
        // Claim the next index BEFORE the external request. After a
        // timeout, replaying the same cursor cannot duplicate the SMS.
        $item=$job['items'][$job['next']];
        $index=(int)$job['next'];
        $job['next']=$index+1;
        $job['results'][$index]=array(
            'entry_id'=>$item['entry_id'],
            'mobile'=>$item['mobile'],
            'status'=>'unknown',
            'message'=>'وضعیت درخواست هنوز مشخص نیست؛ قبل از ارسال مجدد گزارش کاوه‌نگار را بررسی کنید.'
        );
        set_transient($key,$job,self::JOB_TTL);
        $response=GFCV_Shortcode::get_instance()->send_sms_test_lookup(
            GFCV_Kavenegar::get_key(),$item['mobile'],$item['params']
        );
        $job['results'][$index]=array(
            'entry_id'=>$item['entry_id'],
            'mobile'=>$item['mobile'],
            'status'=>!empty($response['success']) ? 'accepted' : 'failed',
            'message'=>sanitize_text_field((string)($response['message'] ?? 'پاسخ نامشخص کاوه‌نگار')),
            'message_id'=>sanitize_text_field((string)($response['message_id'] ?? ''))
        );
        set_transient($key,$job,self::JOB_TTL);
        delete_option($lock);
        wp_send_json_success(self::public_stats($job));
    }
}
