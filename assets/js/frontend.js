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
        // Archive print: pure black ink on white paper, even when the source
        // HTML uses inline theme colors. No faded native checkbox rendering.
        $(document).on('click', '.gfcv-btn-print', function() {
            var modalContent = $('#gfcv-modal-details .gfcv-modal-body').html();
            if (!modalContent) {
                return;
            }
            var printWindow = window.open('', '_blank', 'height=800,width=1000');
            if (!printWindow) {
                window.alert('لطفاً نمایش پنجره جدید را برای چاپ مجاز کنید.');
                return;
            }

            var css = [
                '@page { size: A4; margin: 12mm; }',
                'html,body { direction:rtl; margin:0; padding:0; background:#fff!important; color:#000!important; }',
                'body { font-family:Tahoma,Arial,sans-serif; font-size:12px; }',
                '.gfcv-print-content { width:100%; max-width:100%; color:#000!important; background:#fff!important; }',
                '.gfcv-print-content *, .gfcv-print-content *::before, .gfcv-print-content *::after { color:#000!important; background-color:#fff!important; background-image:none!important; border-color:#000!important; box-shadow:none!important; text-shadow:none!important; }',
                '.gfcv-print-content .mstid-print-report { max-width:none!important; width:100%!important; }',
                '.gfcv-print-content table { border-collapse:collapse!important; margin-bottom:9px; page-break-inside:auto!important; }',
                '.gfcv-print-content .mstid-section { border:1px solid #000!important; border-radius:0!important; }',
                '.gfcv-print-content .mstid-section th { background:#fff!important; border-bottom:2px solid #000!important; border-right:4px solid #000!important; font-weight:800!important; }',
                '.gfcv-print-content .mstid-data-row td { border-bottom:1px solid #000!important; }',
                '.gfcv-print-content .mstid-answer { background:#fff!important; border-right:3px solid #000!important; border-radius:0!important; }',
                '.gfcv-print-content tr { break-inside:avoid; page-break-inside:avoid; }',
                '.gfcv-print-content img { filter:grayscale(1) contrast(1000%)!important; }',
                '.gfcv-print-content .gfcv-choice { display:inline-flex!important; align-items:center!important; gap:5px; margin:3px 3px; padding:3px 7px; border:1px solid #000!important; border-radius:0!important; background:#fff!important; color:#000!important; white-space:normal; }',
                '.gfcv-print-content .gfcv-choice--selected { border:2px solid #000!important; font-weight:800!important; }',
                '.gfcv-print-content .gfcv-choice-marker { font-family:Arial,Tahoma,sans-serif!important; font-size:17px!important; color:#000!important; line-height:1; }',
                '.gfcv-print-content .gfcv-choice-selected-note { font-weight:700!important; color:#000!important; }',
                '.gfcv-print-content a { color:#000!important; text-decoration:underline!important; }',
                '.gfcv-print-content .gfcv-choice-marker { font-weight:900!important; opacity:1!important; }'
            ].join(' ');

            printWindow.document.open();
            printWindow.document.write('<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><title>گزارش آرشیوی پرسشنامه</title><style>' + css + '</style></head><body><main class="gfcv-print-content">' + modalContent + '</main></body></html>');
            printWindow.document.close();

            // Legacy templates or cached markup may still contain disabled
            // native form controls. Convert them into ink-safe static symbols
            // in the print document only. The original entry and preview are
            // not modified, and there are no disabled inputs left to fade.
            var printDocument = printWindow.document;
            var legacyChoices = printDocument.querySelectorAll('input[type="checkbox"], input[type="radio"]');
            Array.prototype.forEach.call(legacyChoices, function(input) {
                var selected = !!input.checked || input.hasAttribute('checked');
                var isRadio = input.type === 'radio';
                var symbol = printDocument.createElement('span');
                symbol.className = 'gfcv-choice-marker';
                symbol.setAttribute('aria-hidden', 'true');
                symbol.textContent = selected ? (isRadio ? '●' : '■') : (isRadio ? '○' : '□');

                var label = input.closest('label');
                if (label) {
                    label.classList.add('gfcv-choice');
                    label.classList.add(selected ? 'gfcv-choice--selected' : 'gfcv-choice--unselected');
                }

                input.parentNode.replaceChild(symbol, input);

                if (selected && label && !label.querySelector('.gfcv-choice-selected-note')) {
                    var note = printDocument.createElement('strong');
                    note.className = 'gfcv-choice-selected-note';
                    note.textContent = ' (انتخاب‌شده)';
                    label.appendChild(note);
                }
            });

            // Give the logo and fonts time to finish loading before printing.
            var images = Array.prototype.slice.call(printWindow.document.images);
            var finished = Promise.all(images.map(function(img) {
                if (img.complete) {
                    return Promise.resolve();
                }
                return new Promise(function(resolve) {
                    img.addEventListener('load', resolve, { once:true });
                    img.addEventListener('error', resolve, { once:true });
                });
            }));
            var timeout = new Promise(function(resolve) { setTimeout(resolve, 2000); });
            Promise.race([finished, timeout]).then(function() {
                if (!printWindow.closed) {
                    printWindow.focus();
                    printWindow.print();
                }
            });
        });
    });
    
})(jQuery);