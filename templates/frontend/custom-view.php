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
// Get pagination parameters
$current_page = isset($_GET['gfcv_page']) ? max(1, intval($_GET['gfcv_page'])) : 1;
$per_page = isset($atts['per_page']) ? absint($atts['per_page']) : 10; // Number of entries per page
$total_entries = count($entries);
$total_pages = ceil($total_entries / $per_page);

// Slice the entries array for the current page
$offset = ($current_page - 1) * $per_page;
$paged_entries = array_slice($entries, $offset, $per_page);
?>
<div class="gfcv-container" data-view-id="<?php echo esc_attr($view->id); ?>">
    <table class="gfcv-table">
        <thead>
            <tr>
                <th>#</th>
                <th><?php _e('شناسه', 'gravity-form-custom-view'); ?></th>               
                <?php foreach ($field_ids as $field_id) : ?>
                    <?php if (isset($fields[$field_id])) : ?>
                        <th><?php echo esc_html($fields[$field_id]->label); ?></th>
                    <?php endif; ?>
                <?php endforeach; ?>
                <th><?php _e('تاریخ', 'gravity-form-custom-view'); ?></th>
                <th><?php _e('عملیات', 'gravity-form-custom-view'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($entries)) : ?>
                <tr>
                    <td colspan="<?php echo count($field_ids) + 3; ?>"><?php _e('هیچ موردی یافت نشد.', 'gravity-form-custom-view'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($paged_entries as $entry) : ?>
                    <tr data-entry-id="<?php echo esc_attr($entry['id']); ?>">
                        <td><?php echo esc_html($offset + $loop->index + 1); ?></td>
                        <td><?php echo esc_html($entry['id']); ?></td>
                        
                        <?php foreach ($field_ids as $field_id) : ?>
                            <?php if (isset($fields[$field_id])) : ?>
                                <td><?php echo esc_html(rgar($entry, $field_id)); ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <td><?php echo esc_html(GFCommon::format_date($entry['date_created'], false, 'Y/m/d')); ?></td>
                        <td class="gfcv-actions">
                            <button type="button" class="gfcv-btn gfcv-btn-details"><i class="dashicons dashicons-visibility"></i> <?php _e('مشاهده جزئیات', 'gravity-form-custom-view'); ?></button>
                            <button type="button" class="gfcv-btn gfcv-btn-user-sms"><i class="dashicons dashicons-smartphone"></i> <?php _e('پیامک کاربر', 'gravity-form-custom-view'); ?></button>
                            <button type="button" class="gfcv-btn gfcv-btn-admin-sms"><i class="dashicons dashicons-admin-users"></i> <?php _e('پیامک مدیر', 'gravity-form-custom-view'); ?></button>
                            <?php if ($view->send_to_api) : ?>
                                <button type="button" class="gfcv-btn gfcv-btn-api"><i class="dashicons dashicons-rest-api"></i> <?php _e('ارسال به API', 'gravity-form-custom-view'); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Templates -->
<div class="gfcv-modal" id="gfcv-modal-details" style="display: none;">
    <div class="gfcv-modal-content">
        <span class="gfcv-modal-close">&times;</span>
        <h2><?php _e('مشاهده جزئیات', 'gravity-form-custom-view'); ?></h2>
        <div class="gfcv-modal-body"></div>
        <div class="gfcv-modal-footer">
            <button type="button" class="gfcv-btn gfcv-btn-print"><i class="dashicons dashicons-printer"></i> <?php _e('چاپ', 'gravity-form-custom-view'); ?></button>
        </div>
    </div>
</div>

<div class="gfcv-modal" id="gfcv-modal-sms" style="display: none;">
    <div class="gfcv-modal-content">
        <span class="gfcv-modal-close">&times;</span>
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
        <h2><?php _e('API Data', 'gravity-form-custom-view'); ?></h2>
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

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Details button click
    $('.gfcv-btn-details').on('click', function() {
        var entryId = $(this).closest('tr').data('entry-id');
        var viewId = $('.gfcv-container').data('view-id');
        var modal = $('#gfcv-modal-details');
        
        // Show loading
        modal.find('.gfcv-modal-body').html('<p>' + gfcv_vars.loading_text + '</p>');
        modal.show();
        
        // Get entry details via AJAX
        $.ajax({
            url: gfcv_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gfcv_get_entry_details',
                entry_id: entryId,
                view_id: viewId,
                nonce: gfcv_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    modal.find('.gfcv-modal-body').html(response.data.html);
                } else {
                    modal.find('.gfcv-modal-body').html('<p>' + gfcv_vars.error_text + '</p>');
                }
            },
            error: function() {
                modal.find('.gfcv-modal-body').html('<p>' + gfcv_vars.error_text + '</p>');
            }
        });
    });
    
    // SMS buttons click (user and admin)
    $('.gfcv-btn-user-sms, .gfcv-btn-admin-sms').on('click', function() {
        var entryId = $(this).closest('tr').data('entry-id');
        var viewId = $('.gfcv-container').data('view-id');
        var modal = $('#gfcv-modal-sms');
        var type = $(this).hasClass('gfcv-btn-user-sms') ? 'user' : 'admin';
        var title = type === 'user' ? '<?php _e("قالب پیامک کاربر", "gravity-form-custom-view"); ?>' : '<?php _e("قالب پیامک مدیر", "gravity-form-custom-view"); ?>';
        
        // Set title and show loading
        modal.find('.gfcv-modal-title').text(title);
        modal.find('.gfcv-modal-body').html('<p>' + gfcv_vars.loading_text + '</p>');
        modal.show();
        
        // Get SMS template via AJAX
        $.ajax({
            url: gfcv_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gfcv_get_sms_template',
                entry_id: entryId,
                view_id: viewId,
                type: type,
                nonce: gfcv_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    modal.find('.gfcv-modal-body').html('<div class="gfcv-sms-template">' + response.data.template + '</div>');
                } else {
                    modal.find('.gfcv-modal-body').html('<p>' + gfcv_vars.error_text + '</p>');
                }
            },
            error: function() {
                modal.find('.gfcv-modal-body').html('<p>' + gfcv_vars.error_text + '</p>');
            }
        });
    });
    
    // API button click
    $('.gfcv-btn-api').on('click', function() {
        var entryId = $(this).closest('tr').data('entry-id');
        var viewId = $('.gfcv-container').data('view-id');
        var modal = $('#gfcv-modal-api');
        
        // Show loading
        modal.find('.gfcv-json-data').html(gfcv_vars.loading_text);
        modal.show();
        
        // Get API data via AJAX
        $.ajax({
            url: gfcv_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gfcv_get_api_data',
                entry_id: entryId,
                view_id: viewId,
                nonce: gfcv_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    modal.find('.gfcv-json-data').text(response.data.entry_json);
                } else {
                    modal.find('.gfcv-json-data').text(gfcv_vars.error_text);
                }
            },
            error: function() {
                modal.find('.gfcv-json-data').text(gfcv_vars.error_text);
            }
        });
    });
    
    // Close modal when clicking the X or outside the modal
    $('.gfcv-modal-close').on('click', function() {
        $(this).closest('.gfcv-modal').hide();
    });
    
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('gfcv-modal')) {
            $('.gfcv-modal').hide();
        }
    });
    
    // Send SMS button click
    $('.gfcv-btn-send-sms').on('click', function() {
        var modal = $(this).closest('.gfcv-modal');
        var entryId = modal.data('entry-id');
        var viewId = $('.gfcv-container').data('view-id');
        var type = modal.data('sms-type');
        
        // Disable button and show loading
        var $button = $(this);
        var originalText = $button.html();
        $button.html('<i class="dashicons dashicons-update-alt gfcv-spin"></i> <?php _e("در حال ارسال...", "gravity-form-custom-view"); ?>');
        $button.prop('disabled', true);
        
        // Send SMS via AJAX
        $.ajax({
            url: gfcv_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gfcv_send_sms',
                entry_id: entryId,
                view_id: viewId,
                type: type,
                nonce: gfcv_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    modal.find('.gfcv-modal-body').append('<div class="gfcv-success-message">' + response.data.message + '</div>');
                } else {
                    modal.find('.gfcv-modal-body').append('<div class="gfcv-error-message">' + response.data.message + '</div>');
                }
                // Reset button
                $button.html(originalText);
                $button.prop('disabled', false);
            },
            error: function() {
                modal.find('.gfcv-modal-body').append('<div class="gfcv-error-message"><?php _e("خطا در ارسال پیامک", "gravity-form-custom-view"); ?></div>');
                // Reset button
                $button.html(originalText);
                $button.prop('disabled', false);
            }
        });
    });
});
</script>