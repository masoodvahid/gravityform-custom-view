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
        // Print Preview button click for details modal
        $(document).on('click', '.gfcv-btn-print', function() {
            var entryId = $('#gfcv-modal-details h2').text() || 'Entry Details';
            var modalContent = $('#gfcv-modal-details .gfcv-modal-body').html();
            var printWindow = window.open('', '', 'height=600,width=800');
            
            printWindow.document.write('<html><head><title>' + entryId + '</title>');
            // Include all necessary stylesheets
            printWindow.document.write('<link rel="stylesheet" href="' + gfcv_vars.frontend_css_url + '">');
            printWindow.document.write('<link rel="stylesheet" href="' + gfcv_vars.custom_buttons_css_url + '">');
            printWindow.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">');
            // Include dashicons
            printWindow.document.write('<link rel="stylesheet" href="' + gfcv_vars.ajax_url.replace('admin-ajax.php', '') + 'wp-includes/css/dashicons.min.css">');
             
            // Add Vazirmatn font with Farsi numbers from Google Fonts
            printWindow.document.write('<link rel="preconnect" href="https://fonts.googleapis.com">');
            printWindow.document.write('<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">');
            
            // Add custom print styles
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: "Vazirmatn", Tahoma, Arial, sans-serif; direction: rtl; padding: 20px; }');
            printWindow.document.write('.gfcv-modal-body { margin: 0 auto; max-width: 800px; font-family: "Vazirmatn", Tahoma, Arial, sans-serif; }');
            printWindow.document.write('.gfcv-file-uploads { margin: 10px 0; }');
            printWindow.document.write('.gfcv-file-link { display: inline-flex; align-items: center; margin: 5px 0; padding: 5px 10px; background-color: #f5f5f5; border-radius: 4px; text-decoration: none; color: #333; }');
            printWindow.document.write('.gfcv-file-link .dashicons { margin-right: 5px; color: #0073aa; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }');
            printWindow.document.write('table, th, td { border: 1px solid #ddd; }');
            printWindow.document.write('th, td { padding: 8px; text-align: right; font-family: "Vazirmatn", Tahoma, Arial, sans-serif; }');
            printWindow.document.write('th { background-color: #f5f5f5; }');
            printWindow.document.write('h1, h2, h3, h4, h5, h6, p, span, div { font-family: "Vazirmatn", Tahoma, Arial, sans-serif; }');
            // Enable Farsi digits for all elements
            printWindow.document.write('* { font-feature-settings: "ss01", "tnum"; -moz-font-feature-settings: "ss01", "tnum"; -webkit-font-feature-settings: "ss01", "tnum"; }');
            printWindow.document.write('@media print { body { padding: 0; } }');
            printWindow.document.write('</style>');
            
            printWindow.document.write('</head><body dir="rtl">');
            // Add a title to the print view
            printWindow.document.write('<h1 style="text-align: center; margin-bottom: 20px; font-family: \'Vazirmatn\', Tahoma, Arial, sans-serif;">' + entryId + '</h1>');
            // Add the content
            printWindow.document.write('<div class="gfcv-print-content">' + modalContent + '</div>');
            printWindow.document.write('</body></html>');
            
            // Ensure content is loaded before printing
            printWindow.document.close();
            
            // Wait for resources to load before triggering print
            setTimeout(function() {
                printWindow.focus();
                printWindow.print();
            }, 2000);
        });
    });
    
})(jQuery);