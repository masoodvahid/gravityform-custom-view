<?php
/**
 * GitHub Release-based updater.
 *
 * The published ZIP must contain the same plugin version as its Release tag.
 * Without this check, WordPress can repeatedly offer a newer tag that still
 * ships the old plugin header (e.g. v1.6.0 containing Version: 1.5.11).
 */
if (!defined('ABSPATH')) { exit; }

class GFCV_GitHub_Updater {
    const REPO = 'masoodvahid/gravityform-custom-view';
    const SLUG = 'gravity-form-custom-view';
    const CACHE_KEY = 'gfcv_github_latest_release_v2';
    const ERROR_KEY = 'gfcv_github_update_last_error';
    const CRON_HOOK = 'gfcv_github_hourly_update_check';
    const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

    public static function init() {
        add_filter('update_plugins_github.com', array(__CLASS__, 'check_update'), 10, 4);
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 20, 3);
        add_filter('upgrader_source_selection', array(__CLASS__, 'normalize_source'), 10, 4);
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_cache'), 10, 2);
        add_action('init', array(__CLASS__, 'schedule_update_check'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'periodic_check'));
        add_action('admin_post_gfcv_refresh_github_updates', array(__CLASS__, 'manual_refresh'));
    }

    public static function schedule_update_check() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK);
        }
    }

    private static function request_json($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 12,
            'redirection' => 0,
            'headers' => array(
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'GFCV-WordPress-Updater',
                'X-GitHub-Api-Version' => '2022-11-28',
            ),
        ));
        if (is_wp_error($response)) {
            self::record_error('اتصال به GitHub برقرار نشد: ' . $response->get_error_message());
            return false;
        }
        $http = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $http) {
            self::record_error('GitHub API پاسخ HTTP ' . $http . ' داد. اتصال یا محدودیت درخواست‌ها را بررسی کنید.');
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            self::record_error('پاسخ دریافتی از GitHub معتبر نیست.');
            return false;
        }
        return $data;
    }

    private static function record_error($error) {
        set_transient(self::ERROR_KEY, sanitize_text_field((string) $error), 15 * MINUTE_IN_SECONDS);
    }

    public static function last_error() {
        return (string) get_transient(self::ERROR_KEY);
    }

    private static function validate_release_file($tag, $expected_version) {
        // Use the same GitHub API host as /releases/latest, avoiding separate
        // raw.githubusercontent.com DNS/network requirements for shared hosting.
        $url = add_query_arg(
            'ref', $tag,
            'https://api.github.com/repos/' . self::REPO . '/contents/gravity-form-custom-view.php'
        );
        $file = self::request_json($url);
        if (!$file || empty($file['content']) || ($file['encoding'] ?? '') !== 'base64') {
            self::record_error('فایل اصلی افزونه در تگ ' . $tag . ' قابل بررسی نیست.');
            return false;
        }
        $source = base64_decode((string) $file['content'], true);
        if (!is_string($source) || !preg_match('/^\s*\*\s*Version:\s*([^\r\n]+)/mi', $source, $match)) {
            self::record_error('فایل اصلی افزونه در تگ ' . $tag . ' هدر Version معتبر ندارد.');
            return false;
        }
        $file_version = trim($match[1]);
        if ($file_version !== $expected_version
            || !preg_match("/define\s*\(\s*['\"]GFCV_PLUGIN_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $source, $constant)
            || trim($constant[1]) !== $expected_version) {
            self::record_error(
                'نسخه تگ GitHub (' . $expected_version . ') با نسخه فایل افزونه (' .
                $file_version . ') یا ثابت نسخه در همان تگ مطابقت ندارد. یک Release اصلاحی منتشر کنید.'
            );
            return false;
        }
        return true;
    }

    private static function latest_release() {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached) && !empty($cached['version'])) {
            return $cached;
        }
        $release = self::request_json('https://api.github.com/repos/' . self::REPO . '/releases/latest');
        if (!$release) { return false; }
        if (!empty($release['draft']) || !empty($release['prerelease']) || empty($release['tag_name'])) {
            self::record_error('آخرین انتشار GitHub هنوز برای آپدیت پایدار قابل استفاده نیست.');
            return false;
        }
        $tag = (string) $release['tag_name'];
        $version = ltrim($tag, 'vV');
        if (!preg_match('/^\d+\.\d+\.\d+(?:[.-][0-9A-Za-z.-]+)?$/', $version)) {
            self::record_error('شماره نسخه Release معتبر نیست: ' . $tag);
            return false;
        }

        // Do not offer a broken release that installs an older plugin header.
        if (!self::validate_release_file($tag, $version)) { return false; }

        // Download the source archive of the exact validated release tag.
        // A separately attached ZIP can accidentally contain stale version
        // headers; GitHub's zipball for this tag cannot point at another commit.
        $package = !empty($release['zipball_url'])
            ? (string) $release['zipball_url'] : '';
        if (!$package) {
            foreach ((array) ($release['assets'] ?? array()) as $asset) {
                if (($asset['name'] ?? '') === self::SLUG . '.zip'
                    && !empty($asset['browser_download_url'])) {
                    $package = (string) $asset['browser_download_url'];
                    break;
                }
            }
        }
        if (!$package) {
            self::record_error('فایل ZIP برای این Release در دسترس نیست.');
            return false;
        }
        $data = array(
            'version' => $version,
            'package' => esc_url_raw($package),
            'url' => 'https://github.com/' . self::REPO,
            'notes' => (string) ($release['body'] ?? ''),
        );
        set_transient(self::CACHE_KEY, $data, self::CACHE_TTL);
        delete_transient(self::ERROR_KEY);
        return $data;
    }

    public static function check_update($update, $plugin_data, $plugin_file, $locales) {
        if ($plugin_file !== plugin_basename(GFCV_PLUGIN_FILE)) { return $update; }
        $latest = self::latest_release();
        if (!$latest || !version_compare($latest['version'], (string) ($plugin_data['Version'] ?? '0'), '>')) {
            return false;
        }
        return array(
            'id' => 'https://github.com/' . self::REPO,
            'slug' => self::SLUG,
            'version' => $latest['version'],
            'url' => $latest['url'],
            'package' => $latest['package'],
            'tested' => '',
            'requires_php' => '7.4',
        );
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }
        $latest = self::latest_release();
        if (!$latest) { return $result; }
        return (object) array(
            'name' => 'نمایش رکوردهای گرویتی فرم با تعیین سطح دسترسی',
            'slug' => self::SLUG,
            'version' => $latest['version'],
            'author' => 'مسعود وحید',
            'homepage' => $latest['url'],
            'download_link' => $latest['package'],
            'sections' => array(
                'description' => 'نمایش و مدیریت رکوردهای Gravity Forms با کنترل سطح دسترسی.',
                'changelog' => nl2br(esc_html($latest['notes'])),
            ),
        );
    }

    public static function normalize_source($source, $remote_source, $upgrader, $hook_extra = array()) {
        $is_ours = isset($hook_extra['plugin'])
            && $hook_extra['plugin'] === plugin_basename(GFCV_PLUGIN_FILE);
        if (!$is_ours && !empty($hook_extra['plugins']) && is_array($hook_extra['plugins'])) {
            $is_ours = in_array(plugin_basename(GFCV_PLUGIN_FILE), $hook_extra['plugins'], true);
        }
        if (!$is_ours || is_wp_error($source)) { return $source; }
        global $wp_filesystem;
        if (!$wp_filesystem) { return $source; }
        $target = trailingslashit($remote_source) . self::SLUG;
        $origin = untrailingslashit($source);
        if (basename($origin) === self::SLUG) { return $source; }
        if ($wp_filesystem->exists($target)) {
            return new WP_Error('gfcv_source_exists', 'Unable to normalize GitHub package directory.');
        }
        if (!$wp_filesystem->move($origin, $target)) {
            return new WP_Error('gfcv_source_move_failed', 'Unable to normalize GitHub package directory.');
        }
        return trailingslashit($target);
    }

    public static function clear_cache($upgrader, $options) {
        if (isset($options['type']) && $options['type'] === 'plugin') {
            delete_transient(self::CACHE_KEY);
            delete_transient(self::ERROR_KEY);
        }
    }

    public static function periodic_check() {
        delete_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
        // This checks for updates. It never installs without consent.
        if (!function_exists('wp_update_plugins')) {
            require_once ABSPATH . WPINC . '/update.php';
        }
        wp_update_plugins();
    }

    public static function manual_refresh() {
        if (!current_user_can('manage_options') || !current_user_can('update_plugins')) {
            wp_die('شما مجوز بررسی به‌روزرسانی را ندارید.', '', array('response' => 403));
        }
        check_admin_referer('gfcv_refresh_updates');
        delete_transient(self::CACHE_KEY);
        delete_transient(self::ERROR_KEY);
        delete_site_transient('update_plugins');
        if (!function_exists('wp_update_plugins')) {
            require_once ABSPATH . WPINC . '/update.php';
        }
        wp_update_plugins();
        $latest = self::latest_release();
        $status = !$latest ? 'error'
            : (version_compare($latest['version'], GFCV_PLUGIN_VERSION, '>') ? 'available' : 'current');
        wp_safe_redirect(add_query_arg(
            'gfcv_update_check', $status,
            admin_url('admin.php?page=gf_custom_view')
        ));
        exit;
    }
}
