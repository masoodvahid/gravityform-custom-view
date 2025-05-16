<?php
/**
 * Database operations for Gravity Forms Results Custom View with ACL
 *
 * @package Gravity_Form_Custom_View
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GFCV_DB {
    /**
     * Instance of this class.
     *
     * @var object
     */
    private static $instance = null;

    /**
     * Table name for custom views
     *
     * @var string
     */
    private $table_name;

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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'gf_custom_views';
    }

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'gf_custom_views';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            view_title varchar(255) NOT NULL,
            form_id mediumint(9) NOT NULL,
            field_ids text NOT NULL,
            admin_sms_pattern text,
            user_sms_pattern text,
            kavenegar_api_key varchar(255),
            send_to_api tinyint(1) DEFAULT 0,
            send_to_details text,
            details_view_html text,
            user_access_ids text,
            user_mobile_field_id varchar(10),
            admin_mobile_numbers text,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get all custom views
     *
     * @return array Array of custom views
     */
    public function get_views() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY id DESC");
    }

    /**
     * Get a single custom view
     *
     * @param int $id View ID
     * @return object Custom view object
     */
    public function get_view($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
    }

    /**
     * Add a new custom view
     *
     * @param array $data View data
     * @return int|false The number of rows inserted, or false on error
     */
    public function add_view($data) {
        global $wpdb;
        return $wpdb->insert($this->table_name, $data);
    }

    /**
     * Update a custom view
     *
     * @param int $id View ID
     * @param array $data View data
     * @return int|false The number of rows updated, or false on error
     */
    public function update_view($id, $data) {
        global $wpdb;
        return $wpdb->update($this->table_name, $data, array('id' => $id));
    }

    /**
     * Delete a custom view
     *
     * @param int $id View ID
     * @return int|false The number of rows deleted, or false on error
     */
    public function delete_view($id) {
        global $wpdb;
        return $wpdb->delete($this->table_name, array('id' => $id));
    }
}