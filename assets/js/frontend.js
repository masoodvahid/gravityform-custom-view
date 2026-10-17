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

            // The HTML template is intentionally class-only. Load its reusable
            // stylesheet here as well as the A4/page-number print stylesheet.
            // CSS page margin boxes produce "صفحه X از Y" per printed page.
            var reportCssUrl = gfcv_vars.report_css_url;
            var printCssUrl = gfcv_vars.report_print_css_url;
            var safeHref = function(url) {
                return String(url).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
            };

            printWindow.document.open();
            printWindow.document.write(
                '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">' +
                '<title>گزارش آرشیوی پرسشنامه</title>' +
                '<link rel="stylesheet" href="' + safeHref(reportCssUrl) + '">' +
                '<link rel="stylesheet" href="' + safeHref(printCssUrl) + '">' +
                '</head><body class="gfcv-print-document">' +
                '<main class="gfcv-print-content">' + modalContent + '</main>' +
                '</body></html>'
            );
            printWindow.document.close();

            // Legacy templates or cached markup may still contain disabled
            // native form controls. Convert them into ink-safe static symbols
            // in the print document only. The original entry and preview are
            // not modified, and there are no disabled inputs left to fade.
            var printDocument = printWindow.document;

            // The popup is a new document: register two licensed font files
            // locally, using the same CSS family and distinct 400/700 weights.
            var fontFiles = [
                { url: gfcv_vars.print_font_regular_url || '', weight: '400' },
                { url: gfcv_vars.print_font_bold_url || '', weight: '700' }
            ];
            var fontLoadJobs = [];
            if (typeof printWindow.FontFace === 'function' && printDocument.fonts) {
                fontFiles.forEach(function(item) {
                    if (!item.url) {
                        return;
                    }
                    try {
                        var face = new printWindow.FontFace(
                            'GFCV Custom Print Font',
                            'url(' + JSON.stringify(item.url) + ')',
                            { style: 'normal', weight: item.weight }
                        );
                        fontLoadJobs.push(face.load().then(function(loaded) {
                            printDocument.fonts.add(loaded);
                        }).catch(function() {
                            printWindow.console.warn('GFCV: Failed loading custom print font at weight ' + item.weight);
                        }));
                    } catch (fontError) {
                        printWindow.console.warn('GFCV: Invalid custom font URL.', fontError);
                    }
                });
            }
            var customPrintFontsReady = Promise.all(fontLoadJobs);

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

            // Group old label-based answer options into the same three-column
            // grid as the current PHP-rendered markup (without editing stored data).
            var legacyContainers = printDocument.querySelectorAll('.mstid-answer');
            Array.prototype.forEach.call(legacyContainers, function(container) {
                if (container.querySelector('.gfcv-choice-list')) {
                    return;
                }
                var labels = Array.prototype.slice.call(container.querySelectorAll('label.gfcv-choice'));
                if (!labels.length) {
                    return;
                }
                var parent = labels[0].parentNode;
                if (!labels.every(function(label) { return label.parentNode === parent; })) {
                    return;
                }
                var grid = printDocument.createElement('div');
                grid.className = 'gfcv-choice-list';
                parent.insertBefore(grid, labels[0]);
                labels.forEach(function(label) { grid.appendChild(label); });
            });

            // Wait for linked CSS, images and fonts before opening the print
            // dialog. A bounded fallback avoids leaving the popup unusable
            // when an optional remote logo/font never resolves.
            var styles = Array.prototype.slice.call(printDocument.querySelectorAll('link[rel="stylesheet"]'));
            var stylesReady = Promise.all(styles.map(function(link) {
                if (link.sheet) {
                    return Promise.resolve();
                }
                return new Promise(function(resolve) {
                    link.addEventListener('load', resolve, { once: true });
                    link.addEventListener('error', resolve, { once: true });
                });
            }));
            var images = Array.prototype.slice.call(printDocument.images);
            var imagesReady = Promise.all(images.map(function(img) {
                if (img.complete) {
                    return Promise.resolve();
                }
                return new Promise(function(resolve) {
                    img.addEventListener('load', resolve, { once: true });
                    img.addEventListener('error', resolve, { once: true });
                });
            }));
            var fontsReady = printDocument.fonts && printDocument.fonts.ready
                ? printDocument.fonts.ready.catch(function() {})
                : Promise.resolve();
            var deadline = new Promise(function(resolve) { setTimeout(resolve, 8000); });
            Promise.race([Promise.all([stylesReady, imagesReady, fontsReady, customPrintFontsReady]), deadline]).then(function() {
                if (!printWindow.closed) {
                    printWindow.focus();
                    printWindow.print();
                }
            });
        });
    });
    
})(jQuery);