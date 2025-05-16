/**
 * Admin JavaScript for Gravity Form Custom View
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize CodeMirror for code fields if available
        if (typeof wp.codeEditor !== 'undefined') {
            wp.codeEditor.initialize($('#send_to_details'));
            wp.codeEditor.initialize($('#details_view_html'));
        }
        
        // Update available fields when form changes
        $('#form_id').on('change', function() {
            var formId = $(this).val();
            if (!formId) {
                $('#field_list').html('<p>Please select a form to view available fields.</p>');
                return;
            }
            
            // Show loading message
            $('#field_list').html('<p>Loading fields...</p>');
            
            // AJAX call to get form fields
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gfcv_get_form_fields',
                    form_id: formId,
                    nonce: $('#gfcv_nonce').val()
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        $('#field_list').html(response.data.html);
                    } else {
                        $('#field_list').html('<p>Error loading form fields.</p>');
                    }
                },
                error: function() {
                    $('#field_list').html('<p>Error loading form fields.</p>');
                }
            });
        });
        
        // Add field ID to field_ids input when clicking on a field
        $(document).on('click', '.field-item', function() {
            var fieldId = $(this).find('code').text();
            var currentIds = $('#field_ids').val();
            
            // Add the field ID if it's not already in the list
            if (currentIds) {
                var idsArray = currentIds.split(',');
                if ($.inArray(fieldId, idsArray) === -1) {
                    idsArray.push(fieldId);
                    $('#field_ids').val(idsArray.join(','));
                }
            } else {
                $('#field_ids').val(fieldId);
            }
        });
    });
    
    // Add AJAX handler for getting form fields
    $(function() {
        // Add AJAX action for getting form fields
        if (typeof wp.ajax !== 'undefined') {
            wp.ajax.add({
                action: 'gfcv_get_form_fields',
                data: function(data) {
                    return {
                        form_id: data.form_id,
                        nonce: data.nonce
                    };
                },
                success: function(data) {
                    return data;
                },
                error: function() {
                    return {
                        success: false,
                        data: {
                            html: '<p>Error loading form fields.</p>'
                        }
                    };
                }
            });
        }
    });
    
})(jQuery);