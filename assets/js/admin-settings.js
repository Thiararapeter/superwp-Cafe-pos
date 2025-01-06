// Remove the entire file if it was only used for logo handling
// Or if the file has other functionality, remove these sections:

// Remove Media Library Handler
let mediaUploader = null;

// Remove Logo click handler
// $('#select_pos_logo, .upload-logo-btn').on('click', function(e) {
//     // ... remove this entire handler
// });

// Remove Logo Handler
// $('#remove_pos_logo').on('click', function(e) {
//     // ... remove this entire handler
// });

jQuery(document).ready(function($) {
    // Function to update receipt preview
    function updateReceiptPreview() {
        const data = {
            action: 'superwpcaf_update_receipt_preview',
            nonce: superwpCafePosSettings.nonce,
            settings: getFormSettings()
        };

        // Show loading state
        $('#receipt-preview').addClass('loading');

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                $('#receipt-preview').html(response.data.preview);
            }
        }).always(function() {
            $('#receipt-preview').removeClass('loading');
        });
    }

    // Helper function to get all form settings
    function getFormSettings() {
        return {
            header: $('#receipt_header').val(),
            footer: $('#receipt_footer').val(),
            show_sku: $('input[name="superwp_cafe_pos_options[show_sku]"]').is(':checked') ? 1 : 0,
            show_tax: $('input[name="superwp_cafe_pos_options[show_tax]"]').is(':checked') ? 1 : 0,
            show_discount: $('input[name="superwp_cafe_pos_options[show_discount]"]').is(':checked') ? 1 : 0,
            show_cashier: $('input[name="superwp_cafe_pos_options[show_cashier]"]').is(':checked') ? 1 : 0,
            show_waiter: $('input[name="superwp_cafe_pos_options[show_waiter]"]').is(':checked') ? 1 : 0
        };
    }

    // Handle form submission
    $('form#pos-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const data = {
            action: 'superwpcaf_save_receipt_settings',
            nonce: superwpCafePosSettings.nonce,
            ...getFormSettings()
        };

        // Show saving indicator
        const $submitButton = $form.find('button[type="submit"]');
        const originalText = $submitButton.text();
        $submitButton.prop('disabled', true).text('Saving...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    const notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                    $('.pos-settings-section').first().prepend(notice);
                    setTimeout(function() {
                        notice.fadeOut();
                    }, 3000);

                    // Update preview with saved settings
                    updateReceiptPreview();
                } else {
                    // Show error message
                    const notice = $('<div class="notice notice-error is-dismissible"><p>' + (response.data ? response.data.message : 'Error saving settings') + '</p></div>');
                    $('.pos-settings-section').first().prepend(notice);
                    setTimeout(function() {
                        notice.fadeOut();
                    }, 3000);
                }
            },
            error: function() {
                // Show error message for network/server errors
                const notice = $('<div class="notice notice-error is-dismissible"><p>Error saving settings. Please try again.</p></div>');
                $('.pos-settings-section').first().prepend(notice);
                setTimeout(function() {
                    notice.fadeOut();
                }, 3000);
            },
            complete: function() {
                // Restore submit button
                $submitButton.prop('disabled', false).text(originalText);
            }
        });
    });

    // Update preview on any change to other settings
    $('.preview-trigger:not(#receipt_template), #receipt_header, #receipt_footer').on('change', updateReceiptPreview);
    
    // Refresh preview button
    $('#refresh-preview').on('click', updateReceiptPreview);

    // Handle print preview button click
    $('.print-preview').on('click', function() {
        // Add a temporary class for printing
        $('.pos-receipt.print-friendly').addClass('printing');

        // Trigger print
        window.print();

        // Remove the temporary class after printing
        setTimeout(function() {
            $('.pos-receipt.print-friendly').removeClass('printing');
        }, 1000);
    });

    // Add keyboard shortcut for printing (Ctrl/Cmd + P)
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 80) {
            // Only handle if we're on the settings page
            if ($('.receipt-preview').length) {
                e.preventDefault();
                $('.print-preview').trigger('click');
            }
        }
    });
});