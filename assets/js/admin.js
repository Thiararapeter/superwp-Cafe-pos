jQuery(document).ready(function($) {
    // Media Library Selector
    let file_frame;
    
    $('#select_pos_logo').on('click', function(e) {
        e.preventDefault();
        
        // If frame exists, reopen it
        if (file_frame) {
            file_frame.open();
            return;
        }
        
        // Create the media frame
        file_frame = wp.media({
            title: 'Select POS Logo',
            button: {
                text: 'Use this logo'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        // When an image is selected
        file_frame.on('select', function() {
            const attachment = file_frame.state().get('selection').first().toJSON();
            
            // Update hidden input with image URL
            $('#pos_logo_url').val(attachment.url);
            
            // Update image preview
            $('#pos_logo_preview').attr('src', attachment.url);
            
            // Show remove button
            $('#remove_pos_logo').removeClass('hidden');
            
            // Submit the form to save changes
            $('form#pos-settings-form').submit();
        });

        file_frame.open();
    });

    // Remove Logo
    $('#remove_pos_logo').on('click', function(e) {
        e.preventDefault();
        
        const defaultLogo = superwpcafAdmin.plugin_url + '/assets/images/default-logo.png';
        
        // Update hidden input
        $('#pos_logo_url').val('');
        
        // Update preview to default
        $('#pos_logo_preview').attr('src', defaultLogo);
        
        // Hide remove button
        $(this).addClass('hidden');
        
        // Submit form to save changes
        $('form#pos-settings-form').submit();
    });

    // Helper function to show notices
    function showLogoNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Remove existing notices
        $('.notice').remove();
        
        // Add new notice
        $('.pos-logo-upload').before(notice);
        
        // Add dismiss button
        var dismissButton = $('<button type="button" class="notice-dismiss"></button>');
        notice.append(dismissButton);
        
        // Handle dismiss click
        dismissButton.on('click', function() {
            notice.fadeOut(300, function() { $(this).remove(); });
        });
        
        // Auto dismiss after 3 seconds
        setTimeout(function() {
            notice.fadeOut(300, function() { $(this).remove(); });
        }, 3000);
    }

    // Tab Navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const tab = $(this).attr('href').replace('#', '');
        
        // Update URL without reload
        const newUrl = window.location.href.split('?')[0] + '?page=superwp-cafe-pos&tab=' + tab;
        window.history.pushState({ tab: tab }, '', newUrl);
        
        // Update active state
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show content
        $('.superwpcaf-settings-content').hide();
        $('#' + tab + '-content').show();
    });

    // Handle back/forward browser buttons
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.tab) {
            $('.nav-tab[href="#' + e.state.tab + '"]').trigger('click');
        }
    });
}); 