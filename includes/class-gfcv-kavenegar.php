<?php
/**
 * Shared Kavenegar configuration and administrator-only outbox lookup.
 *
 * No key is ever returned to the browser. Read-only API requests are made
 * from WordPress; archived SMS logs belong to the Kavenegar account.
 */
if (!defined('ABSPATH')) { exit; }

class GFCV_Kavenegar {
    const OPTION_KEY = 'gfcv_kavenegar_api_key';
    const MIGRATION_KEY = 'gfcv_kavenegar_key_migration';

    public static function init() {
        add_action('wp_ajax_gfcv_kavenegar_outbox', array(__CLASS__, 'ajax_outbox'));
    }

    public static function get_key() {
        return (string) get_option(self::OPTION_KEY, '');
    }

    /**
     * Preserve a previously per-view API key without exposing it in admin HTML.
     * If more than one different key was used, require an explicit selection
     * instead of silently choosing the wrong Kavenegar account.
     */
    public static function migrate_legacy_keys() {
        if (get_option(self::MIGRATION_KEY, '') !== '') {
            return;
        }
        if (self::get_key() !== '') {
            update_option(self::MIGRATION_KEY, 'existing', false);
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'gf_custom_views';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($exists !== $table) {
            return;
        }
        $old_keys = $wpdb->get_col(
            "SELECT DISTINCT kavenegar_api_key FROM {$table}
             WHERE kavenegar_api_key IS NOT NULL AND kavenegar_api_key <> ''"
        );
        $old_keys = array_values(array_unique(array_filter(array_map('trim', (array) $old_keys))));
        if (count($old_keys) === 1) {
            update_option(self::OPTION_KEY, $old_keys[0], false);
            update_option(self::MIGRATION_KEY, 'migrated', false);
        } elseif (count($old_keys) > 1) {
            update_option(self::MIGRATION_KEY, 'conflict', false);
        } else {
            update_option(self::MIGRATION_KEY, 'none', false);
        }
    }

    private static function parse_date($date) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            return false;
        }
        $parts = explode('-', $date);
        if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
            return false;
        }
        return DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
    }

    public static function ajax_outbox() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message'=>'دسترسی به گزارش پیامک‌ها فقط برای مدیرکل مجاز است.'),403);
        }
        check_ajax_referer('gfcv_outbox', 'nonce');
        $key = self::get_key();
        if ($key === '') {
            wp_send_json_error(array('message'=>'ابتدا کلید API را در تنظیمات سرویس پیامک ذخیره کنید.'),400);
        }
        $mode = sanitize_key(wp_unslash($_POST['mode'] ?? 'latest'));
        $limit = 100;
        $query = array('sender'=>'');
        if ($mode === 'range') {
            $start = self::parse_date(sanitize_text_field(wp_unslash($_POST['from'] ?? '')));
            $end = self::parse_date(sanitize_text_field(wp_unslash($_POST['to'] ?? '')));
            if (!$start || !$end || $end < $start || ($end->getTimestamp()-$start->getTimestamp()) > 31*DAY_IN_SECONDS) {
                wp_send_json_error(array('message'=>'بازه زمانی معتبر با حداکثر ۳۱ روز انتخاب کنید.'),400);
            }
            $endpoint = 'selectoutbox';
            $query = array(
                'startdate'=>$start->getTimestamp(),
                'enddate'=>$end->getTimestamp()+DAY_IN_SECONDS-1,
                'sender'=>''
            );
        } elseif ($mode === 'latest') {
            $endpoint = 'latestoutbox';
            $query = array('pagesize'=>$limit,'sender'=>'');
        } else {
            wp_send_json_error(array('message'=>'نوع گزارش معتبر نیست.'),400);
        }

        $url = 'https://api.kavenegar.com/v1/' . rawurlencode($key)
            . '/sms/' . $endpoint . '.json?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        // Prevent cross-domain redirects because the API credential is in the path.
        $response = wp_remote_get($url, array(
            'timeout'=>18, 'redirection'=>0,
            'headers'=>array('Accept'=>'application/json')
        ));
        if (is_wp_error($response)) {
            wp_send_json_error(array('message'=>'ارتباط با سرویس کاوه‌نگار برقرار نشد.'),502);
        }
        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($status !== 200 || !is_array($data) || (int)($data['return']['status'] ?? 0) !== 200) {
            // Avoid echoing provider details that might include secret URLs.
            $message = (string)($data['return']['message'] ?? 'پاسخ سرویس قابل پردازش نیست.');
            $message = str_replace($key, '[کلید]', $message);
            $message = preg_replace('/(?:https?:\/\/)?api\.kavenegar\.com\/[^\s]+/iu', '[نشانی API]', $message);
            wp_send_json_error(array('message'=>'دریافت گزارش پیامک انجام نشد: ' . sanitize_text_field($message)),502);
        }
        $entries = isset($data['entries']) && is_array($data['entries']) ? $data['entries'] : array();
        $rows = array();
        foreach (array_slice($entries, 0, 3000) as $entry) {
            if (!is_array($entry)) { continue; }
            $timestamp = absint($entry['date'] ?? 0);
            $rows[] = array(
                'id'=>sanitize_text_field((string)($entry['messageid'] ?? '')),
                'receptor'=>sanitize_text_field((string)($entry['receptor'] ?? '')),
                'sender'=>sanitize_text_field((string)($entry['sender'] ?? '')),
                'message'=>sanitize_textarea_field((string)($entry['message'] ?? '')),
                'status'=>sanitize_text_field((string)($entry['statustext'] ?? ($entry['status'] ?? 'نامشخص'))),
                'date'=>$timestamp ? wp_date('Y/m/d H:i', $timestamp) : '—',
                'cost'=>isset($entry['cost']) ? (float)$entry['cost'] : null
            );
        }
        wp_send_json_success(array(
            'rows'=>$rows,
            'count'=>count($rows),
            'mode'=>$mode,
            'limited'=>count($entries)>3000,
            'description'=>$mode === 'latest'
                ? 'آخرین پیامک‌های ثبت‌شده در حساب کاوه‌نگار (حداکثر ۱۰۰ پیام)'
                : 'پیامک‌های ثبت‌شده در بازه انتخابی در حساب کاوه‌نگار (حداکثر ۳۰۰۰ پیام)'
        ));
    }
}
