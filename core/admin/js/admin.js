jQuery(document).ready(function($) {
    // Handle status toggle
    $('.toggle-status').on('click', function() {
        var button = $(this);
        var userId = button.data('user-id');
        var currentStatus = button.data('status');
        var newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        
        if (!confirm(
            currentStatus === 'active' 
                ? 'Are you sure you want to deactivate this user? They will no longer be able to access the POS system.'
                : 'Are you sure you want to activate this user?'
        )) {
            return;
        }
        
        // Disable button and show loading state
        button.prop('disabled', true)
              .addClass('button-disabled')
              .text(currentStatus === 'active' ? 'Deactivating...' : 'Activating...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'toggle_pos_user_status',
                user_id: userId,
                status: newStatus,
                nonce: superwpCafePosAdmin.nonce
            },
            success: function(response) {
                if (response && response.success) {
                    // Update button text and data
                    button.text(newStatus === 'active' ? 'Deactivate' : 'Activate')
                          .data('status', newStatus);
                    
                    // Update status indicator
                    var statusCell = button.closest('tr').find('.pos-status');
                    statusCell.removeClass('pos-status-active pos-status-inactive')
                            .addClass('pos-status-' + newStatus)
                            .text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
                    
                    // Show success message
                    showNotice('success', response.data.message);
                } else {
                    // Show error message
                    showNotice('error', (response && response.data && response.data.message) || 'Error updating user status');
                    // Reset button state
                    button.text(currentStatus === 'active' ? 'Deactivate' : 'Activate')
                          .data('status', currentStatus);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                // Show network error message
                showNotice('error', 'Error communicating with server. Please try again.');
                // Reset button state
                button.text(currentStatus === 'active' ? 'Deactivate' : 'Activate')
                      .data('status', currentStatus);
            },
            complete: function() {
                // Re-enable button
                button.prop('disabled', false)
                      .removeClass('button-disabled');
            }
        });
    });

    // Helper function to show notices
    function showNotice(type, message) {
        var notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>')
            .hide()
            .insertBefore('.pos-users-section')
            .fadeIn();
        
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Role Modal Management
    var $modal = $('#role-modal');
    var $modalClose = $('.pos-modal-close');
    var $roleForm = $('#role-form');

    // Open modal for new role
    $('.add-new-role').on('click', function() {
        $modal.find('.modal-title').text('Add New Role');
        $roleForm[0].reset();
        $roleForm.find('#role_id').val('');
        // Uncheck all capabilities
        $roleForm.find('input[name="capabilities[]"]').prop('checked', false);
        $modal.show();
    });

    // Add loading state functions
    function showModalLoading() {
        $('.modal-loading').show();
        $('#role-form').hide();
    }

    function hideModalLoading() {
        $('.modal-loading').hide();
        $('#role-form').show();
    }

    // Open modal for edit role
    $('.edit-role').on('click', function(e) {
        e.preventDefault();
        var roleId = $(this).data('role');
        
        $modal.show();
        showModalLoading();
        
        // Fetch role data
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_pos_role',
                role: roleId,
                nonce: superwpCafePosAdmin.nonce
            },
            success: function(response) {
                hideModalLoading();
                console.log('Role data response:', response); // Debug log
                if (response.success) {
                    var data = response.data;
                    $roleForm.find('#role_name').val(data.label);
                    $roleForm.find('#role_id').val(roleId);
                    
                    // Reset all checkboxes first
                    $roleForm.find('input[name="capabilities[]"]').prop('checked', false);
                    
                    // Check the boxes for granted capabilities
                    $.each(data.capabilities, function(cap, granted) {
                        if (granted) {
                            var $checkbox = $roleForm.find('input[value="' + cap + '"]');
                            if ($checkbox.length) {
                                $checkbox.prop('checked', true);
                            }
                        }
                    });
                } else {
                    alert(response.data.message || 'Error loading role data');
                    $modal.hide();
                }
            },
            error: function(xhr, status, error) {
                hideModalLoading();
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                alert('Error loading role data');
                $modal.hide();
            }
        });
    });

    // Handle role form submission
    $roleForm.on('submit', function(e) {
        e.preventDefault();
        
        var $submitButton = $(this).find('button[type="submit"]');
        $submitButton.prop('disabled', true).text('Saving...');
        
        var formData = {
            action: 'save_pos_role',
            nonce: superwpCafePosAdmin.nonce,
            role_id: $('#role_id').val(),
            role_name: $('#role_name').val(),
            redirect_page: $('#redirect_page').val(),
            custom_redirect_url: $('#custom_redirect_url').val(),
            capabilities: $roleForm.find('input[name="capabilities[]"]:checked').map(function() {
                return $(this).val();
            }).get()
        };
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    var role = response.data.role;
                    
                    // Create or update role row
                    var $existingRow = $('#role-' + role.id);
                    var rowHtml = `
                        <tr id="role-${role.id}">
                            <td>${role.name}</td>
                            <td>${role.capabilities.join(', ')}</td>
                            <td>${role.user_count}</td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" 
                                            class="button button-small edit-role"
                                            data-role="${role.id}">
                                        ${superwpCafePosAdmin.i18n.edit}
                                    </button>
                                    ${!['pos_manager', 'pos_cashier', 'pos_waiter', 'pos_kitchen'].includes(role.id) ? 
                                        `<button type="button" 
                                                class="button button-small delete-role"
                                                data-role="${role.id}">
                                            <span class="dashicons dashicons-trash"></span>
                                            ${superwpCafePosAdmin.i18n.delete}
                                        </button>` : 
                                        ''}
                                </div>
                            </td>
                        </tr>`;

                    if ($existingRow.length) {
                        $existingRow.replaceWith(rowHtml);
                    } else {
                        $('.roles-table tbody').append(rowHtml);
                    }

                    // Show success message
                    var message = $('<div class="notice notice-success is-dismissible"><p>' + 
                                  response.data.message + '</p></div>')
                        .hide()
                        .insertBefore('.pos-roles-section')
                        .fadeIn();
                    
                    setTimeout(function() {
                        message.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);

                    // Close modal
                    $modal.hide();
                    $roleForm[0].reset();
                } else {
                    alert(response.data.message || 'Error saving role');
                }
            },
            error: function(xhr, status, error) {
                console.error('Save error:', error);
                alert('Error saving role');
            },
            complete: function() {
                $submitButton.prop('disabled', false).text('Save Role');
            }
        });
    });

    // Handle role deletion
    $(document).on('click', '.delete-role', function(e) {
        e.preventDefault();
        var $button = $(this);
        var roleId = $button.data('role');
        var roleName = $button.closest('tr').find('td:first').text();

        if (!confirm('Are you sure you want to delete the role "' + roleName + '"?\n\nUsers with this role will be assigned to Subscriber role.\nThis action cannot be undone.')) {
            return;
        }

        $button.prop('disabled', true).text('Deleting...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_pos_role',
                role: roleId,
                nonce: superwpCafePosAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Remove the row with animation
                    $button.closest('tr').fadeOut(400, function() {
                        $(this).remove();
                        // Show success message
                        var message = $('<div class="notice notice-success is-dismissible"><p>' + 
                                      response.data.message + '</p></div>')
                            .hide()
                            .insertBefore('.pos-roles-section')
                            .fadeIn();
                        
                        // Auto dismiss after 3 seconds
                        setTimeout(function() {
                            message.fadeOut(function() {
                                $(this).remove();
                            });
                        }, 3000);
                    });
                } else {
                    alert(response.data.message || 'Error deleting role');
                    $button.prop('disabled', false).text('Delete');
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete error:', error);
                alert('Error deleting role');
                $button.prop('disabled', false).text('Delete');
            }
        });
    });

    // Close modal
    $modalClose.on('click', function() {
        $modal.hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).is($modal)) {
            $modal.hide();
        }
    });

    // Handle user role changes
    $('.user-role-select').on('change', function() {
        var $select = $(this);
        var userId = $select.data('user-id');
        var newRole = $select.val();
        
        if (!confirm('Are you sure you want to change this user\'s role?')) {
            $select.val($select.find('option[selected]').val());
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'update_pos_user_role',
                user_id: userId,
                role: newRole,
                nonce: superwpCafePosAdmin.nonce
            },
            beforeSend: function() {
                $select.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    var message = $('<div class="notice notice-success is-dismissible"><p>' + 
                                  'User role updated successfully.</p></div>')
                        .hide()
                        .insertBefore('.pos-users-section')
                        .fadeIn();
                    
                    setTimeout(function() {
                        message.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    alert(response.data.message || 'Error updating user role');
                    $select.val($select.find('option[selected]').val());
                }
            },
            error: function() {
                alert('Error communicating with server');
                $select.val($select.find('option[selected]').val());
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
    });

    // User search functionality
    $('#user-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('.users-table tbody tr').each(function() {
            var $row = $(this);
            var name = $row.find('.user-details strong').text().toLowerCase();
            var email = $row.find('.user-email').text().toLowerCase();
            
            if (name.includes(searchTerm) || email.includes(searchTerm)) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    });

    // Handle POS terminal launch
    $('.pos-launch-button a').on('click', function(e) {
        // Store the current window location
        localStorage.setItem('posReturnUrl', window.location.href);
    });

    // Handle redirect page selection
    $('#redirect_page').on('change', function() {
        var $customUrlWrapper = $('#custom_redirect_url_wrapper');
        if ($(this).val() === 'custom') {
            $customUrlWrapper.slideDown();
        } else {
            $customUrlWrapper.slideUp();
        }
    });
}); 