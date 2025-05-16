/**
 * Frontend JavaScript for Gravity Form Custom View
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
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
            var titleText = type === 'user' ? 'قالب پیامک کاربر' : 'قالب پیامک مدیر';
            
            // Store entry ID and SMS type in the modal for later use
            modal.data('entry-id', entryId);
            modal.data('sms-type', type);
            
            // Set title and show loading
            modal.find('.gfcv-modal-title').text(titleText);
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
                        var html = '<div class="gfcv-sms-info">';
                        if (response.data.recipient) {
                            html += '<p><strong>' + (type === 'user' ? 'شماره گیرنده:' : 'شماره های مدیران:') + '</strong> ' + response.data.recipient + '</p>';
                        }
                        html += '<p><strong>پیام:</strong></p>';
                        html += '<pre dir="rtl" class="gfcv-sms-message">' + response.data.message + '</pre>';
                        html += '</div>';
                        modal.find('.gfcv-modal-body').html(html);
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
    });
    
})(jQuery);