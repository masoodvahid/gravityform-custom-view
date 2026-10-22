<?php
/**
 * Frontend template for displaying a custom view
 *
 * @package Gravity_Form_Custom_View
 * @subpackage Gravity Forms Results Custom View with ACL
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<?php
// Pagination parameters are now mostly handled in class-gfcv-shortcode.php
// $entries variable already contains the paged entries (max 10 for the current page, from the latest 20 overall)
// $total_entries_for_pagination (max 20) is available from the shortcode rendering function
// $per_page is available from the shortcode rendering function
// $current_page is available from the shortcode rendering function

$total_pages = 0;
if ($per_page > 0) {
    $total_pages = ceil($total_entries_for_pagination / $per_page);
}

// $paged_entries is now just $entries, as it's already paged.
$paged_entries = $entries;
$offset = ($current_page - 1) * $per_page; // Still needed for row numbering
?>

<?php
$visible_field_ids = array_values(array_filter($field_ids, function($field_id) use ($fields) {
    return isset($fields[$field_id]);
}));
$column_count = count($visible_field_ids) + 4;
?>
<div class="gfcv-container" data-view-id="<?php echo esc_attr($view->id); ?>" dir="rtl">
    <div class="gfcv-list-topbar">
        <div class="gfcv-list-headings">
            <h3 class="gfcv-list-title">لیست رکوردهای ثبت‌شده</h3>
            <div class="gfcv-list-subtitle">مشاهده و مدیریت اطلاعات ثبت‌شده در فرم</div>
        </div>
        <div class="gfcv-total-entries" role="status">
            <span class="gfcv-total-number"><?php echo esc_html(number_format_i18n($total_entries_for_pagination)); ?></span>
            <span>رکورد ثبت‌شده</span>
        </div>
    </div>
    <div class="gfcv-table-scroll" tabindex="0" role="region" aria-label="جدول رکوردها، قابل پیمایش افقی">
        <table class="gfcv-table">
            <thead>
                <tr>
                    <th class="gfcv-col-num" scope="col">ردیف</th>
                    <th class="gfcv-col-id" scope="col">شناسه</th>
                    <?php foreach ($visible_field_ids as $field_id) : ?>
                        <th scope="col"><?php echo esc_html($fields[$field_id]->label); ?></th>
                    <?php endforeach; ?>
                    <th class="gfcv-col-date" scope="col">تاریخ ثبت</th>
                    <th class="gfcv-col-actions" scope="col">عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($paged_entries)) : ?>
                    <tr><td class="gfcv-empty-state" colspan="<?php echo esc_attr($column_count); ?>">
                        <span class="dashicons dashicons-database"></span>
                        <span>هنوز رکوردی برای نمایش وجود ندارد.</span>
                    </td></tr>
                <?php else : ?>
                    <?php $row_number = $offset + 1; ?>
                    <?php foreach ($paged_entries as $entry) : ?>
                        <tr data-entry-id="<?php echo esc_attr($entry['id']); ?>">
                            <td class="gfcv-col-num"><?php echo esc_html(number_format_i18n($row_number++)); ?></td>
                            <td class="gfcv-col-id"><span class="gfcv-id-pill"><?php echo esc_html($entry['id']); ?></span></td>
                            <?php foreach ($visible_field_ids as $field_id) : ?>
                                <?php
                                $field = $fields[$field_id];
                                $value = rgar($entry, (string) $field_id);
                                if ($field->type === 'checkbox' && !empty($field->inputs)) {
                                    $selected = array();
                                    foreach ($field->inputs as $input) {
                                        $choice_value = rgar($entry, (string) $input['id']);
                                        if ($choice_value !== null && trim((string) $choice_value) !== '') {
                                            $selected[] = (string) $choice_value;
                                        }
                                    }
                                    $value = implode('، ', $selected);
                                } elseif (is_array($value)) {
                                    $value = implode('، ', array_map('strval', $value));
                                }
                                ?>
                                <td class="gfcv-data-cell"><span><?php echo $value !== null && trim((string)$value) !== '' ? esc_html($value) : '<span class="gfcv-empty-value">—</span>'; ?></span></td>
                            <?php endforeach; ?>
                            <td class="gfcv-col-date"><span class="gfcv-date-text"><?php echo esc_html(GFCommon::format_date($entry['date_created'], false, 'Y/m/d')); ?></span></td>
                            <td class="gfcv-actions">
                                <div class="gfcv-action-group">
                                    <button type="button" class="gfcv-btn gfcv-btn-details" title="مشاهده جزئیات" aria-label="مشاهده جزئیات رکورد <?php echo esc_attr($entry['id']); ?>"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button>
                                    <?php if (!empty($view->user_sms_pattern)) : ?>
                                        <button type="button" class="gfcv-btn gfcv-btn-user-sms" title="پیامک کاربر" aria-label="پیامک کاربر برای رکورد <?php echo esc_attr($entry['id']); ?>"><span class="dashicons dashicons-email" aria-hidden="true"></span></button>
                                    <?php endif; ?>
                                    <?php if (!empty($view->admin_sms_pattern)) : ?>
                                        <button type="button" class="gfcv-btn gfcv-btn-admin-sms" title="پیامک مدیر" aria-label="پیامک مدیر برای رکورد <?php echo esc_attr($entry['id']); ?>"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span></button>
                                    <?php endif; ?>
                                    <?php if (!empty($view->send_to_api)) : ?>
                                        <button type="button" class="gfcv-btn gfcv-btn-api" title="جزئیات API" aria-label="نمایش اطلاعات API رکورد <?php echo esc_attr($entry['id']); ?>"><span class="dashicons dashicons-rest-api" aria-hidden="true"></span></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="gfcv-list-bottom">
        <span>نمایش <?php echo esc_html(number_format_i18n(count($paged_entries))); ?> رکورد در این صفحه</span>
        <span>برای مشاهده جزئیات، از ستون عملیات استفاده کنید.</span>
    </div>
</div>

<!-- Modal Templates -->
<div class="gfcv-modal" id="gfcv-modal-details" style="display: none;">
    <div class="gfcv-modal-content">
        <button type="button" class="gfcv-modal-close" aria-label="بستن پنجره">&times;</button> 
        <div class="gfcv-modal-header">
            <button type="button" class="gfcv-btn gfcv-btn-print"><i class="dashicons dashicons-printer"></i></button>
        </div> 
        <div class="gfcv-modal-body"></div>
        <div class="gfcv-modal-footer">
            <button type="button" class="gfcv-btn gfcv-btn-print"><i class="dashicons dashicons-printer"></i>پیش‌نمایش چاپ و PDF</button>
        </div>
    </div>
</div>

<div class="gfcv-modal" id="gfcv-modal-sms" style="display: none;">
    <div class="gfcv-modal-content">
        <button type="button" class="gfcv-modal-close" aria-label="بستن پنجره">&times;</button>
        <h2 class="gfcv-modal-title"></h2>
        <div class="gfcv-modal-body"></div>
        <div class="gfcv-modal-footer">
            <button type="button" class="gfcv-btn gfcv-btn-send-sms"><i class="dashicons dashicons-email-alt"></i> <?php _e('ارسال پیامک', 'gravity-form-custom-view'); ?></button>
        </div>
    </div>
</div>

<div class="gfcv-modal" id="gfcv-modal-api" style="display: none;">
    <div class="gfcv-modal-content">
        <span class="gfcv-modal-close">&times;</span>
        <h2><?php _e('اطلاعات ارتباط با API', 'gravity-form-custom-view'); ?></h2>
        <div class="gfcv-modal-body">
            <pre class="gfcv-json-data"></pre>
        </div>
    </div>
</div>



<!-- Pagination -->
<?php if ($total_pages > 1) : ?>
<div class="gfcv-pagination">
    <div class="gfcv-pagination-info">
        <?php printf(__('صفحه %1$s از %2$s', 'gravity-form-custom-view'), $current_page, $total_pages); ?>
    </div>
    <div class="gfcv-pagination-links">
        <?php if ($current_page > 1) : ?>
            <a href="<?php echo esc_url(add_query_arg('gfcv_page', $current_page - 1)); ?>" class="gfcv-pagination-prev"><?php _e('قبلی', 'gravity-form-custom-view'); ?></a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
            <?php if ($i == $current_page) : ?>
                <span class="gfcv-pagination-current"><?php echo $i; ?></span>
            <?php else : ?>
                <a href="<?php echo esc_url(add_query_arg('gfcv_page', $i)); ?>" class="gfcv-pagination-link"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($current_page < $total_pages) : ?>
            <a href="<?php echo esc_url(add_query_arg('gfcv_page', $current_page + 1)); ?>" class="gfcv-pagination-next"><?php _e('بعدی', 'gravity-form-custom-view'); ?></a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

