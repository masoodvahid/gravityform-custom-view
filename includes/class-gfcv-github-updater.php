<?php
/**
 * GitHub Release-based updater for Gravity Form Custom View.
 * Publish a GitHub Release with a versioned tag (e.g. v1.4.0).
 * Prefer attaching gravity-form-custom-view.zip with the plugin folder at ZIP root.
 */
if (!defined('ABSPATH')) { exit; }

class GFCV_GitHub_Updater {
    const REPO = 'masoodvahid/gravityform-custom-view';
    const SLUG = 'gravity-form-custom-view';
    const CACHE_KEY = 'gfcv_github_latest_release_v1';

    public static function init() {
        add_filter('update_plugins_github.com', array(__CLASS__, 'check_update'), 10, 4);
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 20, 3);
        add_filter('upgrader_source_selection', array(__CLASS__, 'normalize_source'), 10, 4);
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_cache'), 10, 2);
    }

    private static function latest_release() {
        $cached = get_transient(self::CACHE_KEY);
        if (false !== $cached) { return $cached; }
        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::REPO . '/releases/latest',
            array('timeout' => 12, 'headers' => array('Accept' => 'application/vnd.github+json', 'User-Agent' => 'GFCV-WordPress-Updater'))
        );
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }
        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease']) || empty($release['tag_name'])) { return false; }
        $version = ltrim($release['tag_name'], 'vV');
        if (!preg_match('/^\\d+\\.\\d+\\.\\d+(?:[.-][0-9A-Za-z.-]+)?$/', $version)) { return false; }
        $package = '';
        foreach ((array) ($release['assets'] ?? array()) as $asset) {
            if (($asset['name'] ?? '') === self::SLUG . '.zip' && !empty($asset['browser_download_url'])) {
                $package = $asset['browser_download_url'];
                break;
            }
        }
        if (!$package && !empty($release['zipball_url'])) { $package = $release['zipball_url']; }
        if (!$package) { return false; }
        $data = array('version' => $version, 'package' => esc_url_raw($package), 'url' => 'https://github.com/' . self::REPO, 'notes' => (string) ($release['body'] ?? ''));
        set_transient(self::CACHE_KEY, $data, 6 * HOUR_IN_SECONDS);
        return $data;
    }

    public static function check_update($update, $plugin_data, $plugin_file, $locales) {
        if ($plugin_file !== plugin_basename(GFCV_PLUGIN_FILE)) { return $update; }
        $latest = self::latest_release();
        if (!$latest) { return $update; }
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
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== self::SLUG) { return $result; }
        $latest = self::latest_release();
        if (!$latest) { return $result; }
        return (object) array(
            'name' => 'Gravity Forms Custom View',
            'slug' => self::SLUG,
            'version' => $latest['version'],
            'author' => 'مسعود وحید',
            'homepage' => $latest['url'],
            'download_link' => $latest['package'],
            'sections' => array('description' => 'Custom Gravity Forms views with access control.', 'changelog' => nl2br(esc_html($latest['notes']))),
        );
    }

    public static function normalize_source($source, $remote_source, $upgrader, $hook_extra = array()) {
        // GitHub's source archives unpack to owner-repo-hash. Preserve the installed folder.
        $is_ours = isset($hook_extra['plugin']) && $hook_extra['plugin'] === plugin_basename(GFCV_PLUGIN_FILE);
        if (!$is_ours && !empty($hook_extra['plugins']) && is_array($hook_extra['plugins'])) {
            $is_ours = in_array(plugin_basename(GFCV_PLUGIN_FILE), $hook_extra['plugins'], true);
        }
        if (!$is_ours || is_wp_error($source)) { return $source; }
        global $wp_filesystem;
        if (!$wp_filesystem) { return $source; }
        $target = trailingslashit($remote_source) . self::SLUG;
        $origin = untrailingslashit($source);
        if (basename($origin) === self::SLUG) { return $source; }
        if ($wp_filesystem->exists($target)) { return new WP_Error('gfcv_source_exists', 'Unable to normalize GitHub package directory.'); }
        if (!$wp_filesystem->move($origin, $target)) { return new WP_Error('gfcv_source_move_failed', 'Unable to normalize GitHub package directory.'); }
        return trailingslashit($target);
    }

    public static function clear_cache($upgrader, $options) {
        if (isset($options['type']) && $options['type'] === 'plugin') { delete_transient(self::CACHE_KEY); }
    }
}
