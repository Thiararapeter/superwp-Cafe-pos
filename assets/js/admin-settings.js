jQuery(document).ready(function($) {
    // Logo upload handling
    $('.upload-logo-btn').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const logoInput = $('#receipt_logo');
        const previewContainer = $('.receipt-logo-preview img');
        const removeButton = $('.remove-logo-btn');
        
        const frame = wp.media({
            title: 'Select or Upload Receipt Logo',
            button: {
                text: 'Use this logo'
            },
            multiple: false
        });
        
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            logoInput.val(attachment.id);
            previewContainer.attr('src', attachment.url).show();
            removeButton.show();
        });
        
        frame.open();
    });
    
    // Remove logo
    $('.remove-logo-btn').on('click', function(e) {
        e.preventDefault();
        $('#receipt_logo').val('');
        $('.receipt-logo-preview img').attr('src', '').hide();
        $(this).hide();
    });
}); 