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
            modal.removeData('pdf-title');
            
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
                        modal.data('pdf-title', response.data.pdf_title || 'گزارش ثبت‌نام');
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
            modal.find('.gfcv-btn-send-sms').hide();
            
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
                    var $body=modal.find('.gfcv-modal-body');
                    var $send=modal.find('.gfcv-btn-send-sms');
                    if (!response || !response.success) {
                        $body.empty().append($('<p/>').text(
                            response && response.data && response.data.message || gfcv_vars.error_text
                        ));
                        $send.hide();
                        return;
                    }
                    var data=response.data;
                    var $preview=$('<div class="gfcv-sms-info"/>');
                    $preview.append($('<div class="gfcv-sms-preview-row"/>')
                        .append($('<strong/>').text('نام پترن: '))
                        .append($('<code dir="ltr"/>').text(data.template || 'تنظیم نشده')));
                    $preview.append($('<div class="gfcv-sms-preview-row"/>')
                        .append($('<strong/>').text(type === 'user' ? 'شماره کاربر: ' : 'شماره‌های مدیران: '))
                        .append($('<span dir="ltr"/>').text(data.recipient || 'تنظیم نشده')));
                    $preview.append($('<h3/>').text('پیش‌نمایش پیامک برای همین رکورد'));
                    $preview.append($('<div class="gfcv-sms-message" dir="rtl"/>').text(data.message || ''));
                    if (!data.exact_preview) {
                        $preview.append($('<p class="gfcv-sms-note"/>')
                            .text('متن کامل پترن در تنظیمات نما وارد نشده است؛ فقط مقادیر متغیرها نمایش داده می‌شوند.'));
                    }
                    var $details=$('<details class="gfcv-sms-params"/>')
                        .append($('<summary/>').text('نمایش مقادیر متغیرهای پیامک'))
                        .append($('<pre dir="ltr"/>').text(JSON.stringify(data.params || {},null,2)));
                    $preview.append($details);
                    $preview.append($('<div class="gfcv-sms-send-status" aria-live="polite"/>'));
                    $body.empty().append($preview);
                    if (!data.send_enabled) {
                        $preview.append($('<p class="gfcv-sms-note"/>')
                            .text('کلید API سراسری کاوه‌نگار هنوز تنظیم نشده است؛ به تنظیمات افزونه مراجعه کنید.'));
                    }
                    $send.toggle(!!data.send_enabled).prop(
                        'disabled',!data.send_enabled||!data.recipient||!data.template
                    );
                },
                error: function() {
                    modal.find('.gfcv-modal-body').html('<p>' + gfcv_vars.error_text + '</p>');
                }
            });
        });
        
        // Sending is separate from preview and requires explicit confirmation.
        $(document).on('click', '#gfcv-modal-sms .gfcv-btn-send-sms', function() {
            var modal=$('#gfcv-modal-sms'), $button=$(this);
            var type=modal.data('sms-type'), entryId=modal.data('entry-id');
            var recipient=modal.find('.gfcv-sms-preview-row').eq(1).find('span').text();
            if (!entryId || !recipient) { return; }
            if (!window.confirm('ارسال واقعی پیامک برای رکورد '+entryId+' به '+recipient+
                ' را تأیید می‌کنید؟ ارسال می‌تواند هزینه داشته باشد.')) { return; }
            $button.prop('disabled',true);
            var $status=modal.find('.gfcv-sms-send-status').text('در حال ارسال پیامک...');
            $.post(gfcv_vars.ajax_url,{
                action:'gfcv_send_sms',
                entry_id:entryId,view_id:$('.gfcv-container').data('view-id'),
                type:type,nonce:gfcv_vars.nonce
            }).done(function(resp){
                var message=resp&&resp.data&&resp.data.message||'پاسخ سرویس پیامک نامشخص است.';
                if (resp&&resp.success) {
                    $status.text(message).attr('data-send-status','accepted');
                } else {
                    $status.text(message).attr('data-send-status','failed');
                }
            }).fail(function(xhr){
                var message=xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message||
                    'ارسال ناموفق بود (HTTP '+xhr.status+'). اتصال سرور به کاوه‌نگار را بررسی کنید.';
                $status.text(message).attr('data-send-status','failed');
            }).always(function(){ $button.prop('disabled',false); });
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
            var documentTitle = $('#gfcv-modal-details').data('pdf-title') || 'گزارش ثبت‌نام';
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
            // Chrome/Edge normally propose the document title as the PDF file name.
            // No .pdf extension is added here; the browser adds it automatically.
            printWindow.document.title = documentTitle;

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