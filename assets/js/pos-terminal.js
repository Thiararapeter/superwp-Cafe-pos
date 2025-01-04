jQuery(document).ready(function($) {
    const POS = {
        init: function() {
            this.loadCategories();
            this.loadProducts();
            this.bindEvents();
            this.initCart();
        },

        bindEvents: function() {
            $('.clear-cart').on('click', this.clearCart.bind(this));
            $('.pay-button').on('click', this.processPayment.bind(this));
            $('.sync-button').on('click', this.syncData.bind(this));
            
            $(document).on('click', '.category-item', function(e) {
                e.preventDefault();
                const categoryId = $(this).data('category-id');
                
                // Update active state
                $('.category-item').removeClass('active');
                $(this).addClass('active');
                
                // Load products for this category
                POS.loadProducts(categoryId);
            });

            $(document).on('click', '.add-to-cart', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $product = $(this).closest('.product-item');
                const productId = $product.data('product-id');
                const productName = $product.find('.product-name').text();
                const productPrice = $product.find('.product-price').text();
                
                if (productId) {
                    this.addToCart(productId, productName, productPrice);
                }
            }.bind(this));

            // Add cart button events
            $('.floating-cart-button').on('click', this.toggleCart.bind(this));
            $('.cart-modal-close').on('click', this.toggleCart.bind(this));
            $('.cart-overlay').on('click', this.toggleCart.bind(this));

            // Product search with suggestions
            let searchTimeout;
            $('#product-search').on('input', function() {
                const searchTerm = $(this).val();
                clearTimeout(searchTimeout);
                
                // Clear suggestions if search is empty
                if (searchTerm.length < 2) {
                    $('.search-suggestions').empty().hide();
                    return;
                }
                
                // Add loading state
                $('.pos-search').addClass('searching');
                
                // Debounce the search
                searchTimeout = setTimeout(() => {
                    $.ajax({
                        url: superwpcafPOS.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'superwpcaf_search_products',
                            search: searchTerm,
                            nonce: superwpcafPOS.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                const suggestions = response.data;
                                let suggestionsHtml = '';
                                
                                if (suggestions.length) {
                                    suggestions.forEach(product => {
                                        suggestionsHtml += `
                                            <div class="search-suggestion" data-product-id="${product.id}">
                                                <div class="suggestion-image">${product.image}</div>
                                                <div class="suggestion-details">
                                                    <div class="suggestion-name">${product.name}</div>
                                                    <div class="suggestion-price">${product.price}</div>
                                                </div>
                                            </div>
                                        `;
                                    });
                                } else {
                                    suggestionsHtml = '<div class="no-suggestions">No products found</div>';
                                }
                                
                                $('.search-suggestions').html(suggestionsHtml).show();
                            }
                        },
                        complete: function() {
                            $('.pos-search').removeClass('searching');
                        }
                    });
                }, 300);
            });

            // Handle suggestion click
            $(document).on('click', '.search-suggestion', function() {
                const productId = $(this).data('product-id');
                const productName = $(this).find('.suggestion-name').text();
                const productPrice = $(this).find('.suggestion-price').text();
                
                // Add to cart with full details
                this.addToCart(productId, productName, productPrice);
                
                // Clear search
                $('#product-search').val('');
                $('.search-suggestions').hide();
            }.bind(this));

            // Close suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.pos-search').length) {
                    $('.search-suggestions').hide();
                }
            });
        },

        loadCategories: function() {
            $.ajax({
                url: superwpcafPOS.ajaxurl,
                type: 'POST',
                data: {
                    action: 'superwpcaf_get_categories',
                    nonce: superwpcafPOS.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.pos-categories').html(response.data);
                    }
                }
            });
        },

        loadProducts: function(category = 0, page = 1, search = '') {
            // Add loading class immediately
            $('.pos-products').addClass('loading');
            
            $.ajax({
                url: superwpcafPOS.ajaxurl,
                type: 'POST',
                data: {
                    action: 'superwpcaf_get_products',
                    category: category,
                    page: page,
                    search: search,
                    nonce: superwpcafPOS.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Fade out current products
                        $('.products-grid').fadeOut(200, function() {
                            // Update products and fade in
                            $(this).html(response.data.html).fadeIn(200);
                        });
                    }
                },
                error: function() {
                    // Show error message if request fails
                    $('.products-grid').html(
                        '<div class="no-products error">' +
                        'Error loading products. Please try again.' +
                        '</div>'
                    );
                },
                complete: function() {
                    // Remove loading classes
                    $('.pos-products').removeClass('loading searching');
                }
            });
        },

        addToCart: function(productId, productName = '', productPrice = '') {
            $.ajax({
                url: superwpcafPOS.ajaxurl,
                type: 'POST',
                data: {
                    action: 'superwpcaf_add_to_cart',
                    product_id: productId,
                    quantity: 1,
                    variation_id: 0,
                    variation: {},
                    product_name: productName,
                    product_price: productPrice,
                    nonce: superwpcafPOS.nonce
                },
                beforeSend: function() {
                    $('.add-to-cart[data-product-id="' + productId + '"]').addClass('loading');
                    $('.search-suggestion[data-product-id="' + productId + '"]').addClass('loading');
                    $('.pos-notification').remove();
                },
                success: function(response) {
                    if (response.success) {
                        this.updateCart(response.data);
                        this.showNotification(`Added ${productName || 'Product'} to cart`, 'success');
                        
                        // Clear search if it was a search result
                        if ($('.search-suggestions').is(':visible')) {
                            $('#product-search').val('');
                            $('.search-suggestions').hide();
                        }
                    } else {
                        this.showNotification(response.data.message || 'Error adding product to cart', 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showNotification('Error adding product to cart', 'error');
                }.bind(this),
                complete: function() {
                    $('.add-to-cart[data-product-id="' + productId + '"]').removeClass('loading');
                    $('.search-suggestion[data-product-id="' + productId + '"]').removeClass('loading');
                }
            });
        },

        updateCart: function(cartData) {
            if (!cartData) return;

            // Update cart items
            if (cartData.items) {
                $('.cart-items').html(cartData.items);
            }

            // Update totals
            if (cartData.subtotal) {
                $('.subtotal span').text(cartData.subtotal);
            }
            if (cartData.tax) {
                $('.tax span').text(cartData.tax);
            }
            if (cartData.total) {
                $('.total span').text(cartData.total);
            }

            // Update cart count
            if (cartData.item_count) {
                $('.cart-count').text(cartData.item_count);
            } else {
                $('.cart-count').text('0');
            }

            // Enable/disable pay button based on cart contents
            const $payButton = $('.pay-button');
            if (cartData.item_count && cartData.item_count > 0) {
                $payButton.prop('disabled', false);
            } else {
                $payButton.prop('disabled', true);
            }

            // Bind cart item events
            this.bindCartEvents();
        },

        clearCart: function() {
            if (!confirm('Are you sure you want to clear the cart?')) return;
            
            $.ajax({
                url: superwpcafPOS.ajaxurl,
                type: 'POST',
                data: {
                    action: 'superwpcaf_clear_cart',
                    nonce: superwpcafPOS.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateCart(response.data);
                    }
                }.bind(this)
            });
        },

        processPayment: function(paymentData) {
            $.ajax({
                url: superwpcafPOS.ajaxurl,
                type: 'POST',
                data: {
                    action: 'superwpcaf_process_sale',
                    payment_data: paymentData,
                    nonce: superwpcafPOS.nonce
                },
                beforeSend: function() {
                    $('.checkout-button').prop('disabled', true).text('Processing...');
                },
                success: function(response) {
                    if (response.success) {
                        this.showNotification('Payment completed successfully', 'success');
                        this.clearCart();
                        $('.cart-modal, .cart-overlay').removeClass('active');
                    } else {
                        this.showNotification(response.data.message || 'Payment failed', 'error');
                    }
                }.bind(this),
                complete: function() {
                    $('.checkout-button').prop('disabled', false).text('Complete Payment');
                }
            });
        },

        showPaymentComplete: function(data) {
            const receiptHtml = `
                <div class="payment-receipt">
                    <h3>Sale Complete</h3>
                    <p>Order #${data.order_id}</p>
                    <div class="receipt-details">
                        <div>Total: ${data.order_total}</div>
                        <div>Paid: ${data.payment_amount}</div>
                        <div>Change: ${data.change_amount}</div>
                    </div>
                    <button class="print-receipt" data-order-id="${data.order_id}">Print Receipt</button>
                </div>
            `;

            // Show receipt modal
            this.showModal(receiptHtml);
        },

        showModal: function(content) {
            const modal = $('<div class="pos-modal"></div>').html(content);
            $('body').append(modal);
            
            // Close modal on background click
            modal.on('click', function(e) {
                if (e.target === this) {
                    modal.remove();
                }
            });
        },

        syncData: function() {
            const $syncButton = $('.sync-button');
            
            // Prevent multiple clicks
            if ($syncButton.prop('disabled')) {
                return;
            }
            
            // Add loading state
            $syncButton.prop('disabled', true)
                       .addClass('syncing');
            
            // Show loading notification
            this.showNotification('Syncing products...', 'info');
            
            $.ajax({
                url: superwpcafPOS.ajaxurl,
                type: 'POST',
                data: {
                    action: 'superwpcaf_sync_products',
                    nonce: superwpcafPOS.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload products
                        this.loadProducts();
                        // Show success message
                        this.showNotification('Products synchronized successfully', 'success');
                    } else {
                        // Show error message with details if available
                        this.showNotification(
                            response.data?.message || 'Error syncing products. Please try again.',
                            'error'
                        );
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    // Show detailed error message
                    this.showNotification(
                        'Error syncing products: ' + (error || 'Unknown error occurred'),
                        'error'
                    );
                }.bind(this),
                complete: function() {
                    // Remove loading state
                    $syncButton.prop('disabled', false)
                              .removeClass('syncing');
                }
            });
        },

        bindProductEvents: function() {
            // Remove any existing bindings first
            $(document).off('click', '.product-item');
            $(document).off('click', '.add-to-cart');
            
            // Add click handler for the add to cart button
            $(document).on('click', '.add-to-cart', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const productId = $(this).data('product-id');
                if (productId) {
                    this.addToCart(productId);
                }
            }.bind(this));

            // Add click handler for the product item
            $(document).on('click', '.product-item', function(e) {
                // Only proceed if not clicking on the add to cart button
                if (!$(e.target).closest('.add-to-cart').length) {
                    e.preventDefault();
                    const productId = $(this).data('product-id');
                    if (productId) {
                        this.addToCart(productId);
                    }
                }
            }.bind(this));
        },

        showNotification: function(message, type) {
            const notificationClass = type === 'success' ? 'pos-notification-success' : 'pos-notification-error';
            const $notification = $('<div class="pos-notification ' + notificationClass + '">' + message + '</div>');
            
            // Remove existing notifications
            $('.pos-notification').remove();
            
            // Add new notification
            $('.pos-container').append($notification);
            
            // Auto remove after 3 seconds
            setTimeout(function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        updateQuantity: function(cartItemKey, quantity) {
            $.ajax({
                url: superwpcafPOS.ajaxurl,
                type: 'POST',
                data: {
                    action: 'superwpcaf_update_cart',
                    cart_item_key: cartItemKey,
                    quantity: quantity,
                    nonce: superwpcafPOS.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateCart(response.data);
                    } else {
                        this.showNotification(response.data.message || 'Error updating cart', 'error');
                    }
                }.bind(this)
            });
        },

        bindCartEvents: function() {
            $(document).on('click', '.quantity-minus', function() {
                const key = $(this).data('key');
                const input = $('.quantity-input[data-key="' + key + '"]');
                const currentVal = parseInt(input.val());
                if (currentVal > 1) {
                    this.updateQuantity(key, currentVal - 1);
                }
            }.bind(this));

            $(document).on('click', '.quantity-plus', function() {
                const key = $(this).data('key');
                const input = $('.quantity-input[data-key="' + key + '"]');
                const currentVal = parseInt(input.val());
                this.updateQuantity(key, currentVal + 1);
            }.bind(this));

            $(document).on('change', '.quantity-input', function() {
                const key = $(this).data('key');
                const quantity = parseInt($(this).val());
                if (quantity >= 1) {
                    this.updateQuantity(key, quantity);
                }
            }.bind(this));

            $(document).on('click', '.remove-item', function() {
                const key = $(this).data('key');
                this.updateQuantity(key, 0);
            }.bind(this));
        },

        toggleCart: function() {
            $('.cart-modal, .cart-overlay').toggleClass('active');
        },

        initCart: function() {
            const self = this;
            
            // Toggle cart modal
            $('.floating-cart').on('click', function() {
                $('.cart-modal, .cart-overlay').addClass('active');
            });

            $('.cart-modal-close, .cart-overlay').on('click', function() {
                $('.cart-modal, .cart-overlay').removeClass('active');
            });

            // Update cart count and total
            this.updateCartDisplay = function(response) {
                if (response.fragments) {
                    $('.cart-count').text(response.fragments.cart_count || '0');
                    $('.cart-total').html(response.fragments.cart_total || '0.00');
                    $('.modal-cart-total').html(response.fragments.cart_total || '0.00');
                    $('.cart-modal-content').html(response.fragments.cart_contents || '');
                }
            };

            // Payment method handling
            $('input[name="payment_method"]').on('change', function() {
                const method = $(this).val();
                
                // Hide all payment fields first
                $('.payment-fields').hide();
                
                // Show the relevant payment fields
                if (method === 'cash') {
                    $('.cash-fields').fadeIn();
                    $('#cash-amount').focus();
                } else if (method === 'mpesa') {
                    $('.mpesa-fields').fadeIn();
                    $('#mpesa-code').focus();
                }
                
                // Reset fields
                $('#cash-amount').val('');
                $('#mpesa-code').val('');
                $('.change-amount span').text('0.00');
                
                // Update button text based on payment method
                $('.checkout-button').text(method === 'mpesa' ? 
                    'Verify & Complete Payment' : 'Complete Payment');
                
                this.validatePaymentFields();
            }.bind(this));

            // Update validation for MPESA code
            $('#mpesa-code').on('input', function() {
                const code = $(this).val().toUpperCase();
                $(this).val(code); // Convert to uppercase
                
                // Validate MPESA code format (e.g., "PXL12345678")
                const isValidFormat = /^[A-Z]{3}[0-9]{6,10}$/.test(code);
                $(this).toggleClass('invalid', !isValidFormat);
                
                this.validatePaymentFields();
            }.bind(this));

            // Handle cash amount input
            $('#cash-amount').on('input', function() {
                const amount = parseFloat($(this).val()) || 0;
                const total = parseFloat($('.modal-cart-total').text().replace(/[^0-9.-]+/g, ''));
                const change = (amount - total).toFixed(2);
                
                $('.change-amount span').text(change >= 0 ? change : '0.00');
                this.validatePaymentFields();
            }.bind(this));

            // Handle MPESA code input
            $('#mpesa-code').on('input', function() {
                this.validatePaymentFields();
            }.bind(this));

            // Add waiter selection handling
            $('#waiter-select').on('change', function() {
                this.validatePaymentFields();
            }.bind(this));

            // Update checkout button handler
            $('.checkout-button').on('click', function() {
                const paymentMethod = $('input[name="payment_method"]:checked').val();
                const waiterId = $('#waiter-select').val();
                
                if (!waiterId) {
                    this.showNotification('Please select a waiter', 'error');
                    return;
                }

                const data = {
                    payment_method: paymentMethod,
                    payment_amount: paymentMethod === 'cash' ? 
                        parseFloat($('#cash-amount').val()) : 0,
                    mpesa_code: paymentMethod === 'mpesa' ? 
                        $('#mpesa-code').val() : '',
                    waiter_id: waiterId
                };
                
                this.processPayment(data);
            }.bind(this));

            // Handle payment method switching
            $('input[name="payment_method"]').on('change', function() {
                const method = $(this).val();
                
                // Hide all payment fields first
                $('.payment-fields').hide();
                
                // Show the relevant payment fields
                if (method === 'cash') {
                    $('.cash-fields').fadeIn();
                    $('#cash-amount').focus();
                } else if (method === 'mpesa') {
                    $('.mpesa-fields').fadeIn();
                    $('#mpesa-code').focus();
                }
                
                // Validate fields after switching
                this.validatePaymentFields();
            }.bind(this));
        },

        validatePaymentFields: function() {
            const method = $('input[name="payment_method"]:checked').val();
            const total = parseFloat($('.modal-cart-total').text().replace(/[^0-9.-]+/g, ''));
            const waiterId = $('#waiter-select').val();
            let isValid = false;

            // Check if waiter selection is available and selected
            const $waiterSelect = $('#waiter-select');
            if ($waiterSelect.find('option').length <= 1) {
                // No waiters available
                $('.checkout-button').prop('disabled', true)
                    .text('No POS Waiters Available');
                return;
            }

            // Check if waiter is selected
            if (!waiterId) {
                $('.checkout-button').prop('disabled', true);
                return;
            }

            if (method === 'cash') {
                const amount = parseFloat($('#cash-amount').val()) || 0;
                isValid = amount >= total;
            } else if (method === 'mpesa') {
                const code = $('#mpesa-code').val();
                isValid = /^[A-Z]{3}[0-9]{6,10}$/.test(code);
            }

            $('.checkout-button').prop('disabled', !isValid)
                .text(isValid ? 'Complete Payment' : 'Complete Required Fields');
        }
    };

    // Initialize POS
    POS.init();

    // Theme Toggle functionality
    $(document).ready(function() {
        const themeToggle = $('#theme-toggle');
        const container = $('.pos-container');
        
        // Check for saved theme preference
        const savedTheme = localStorage.getItem('pos-theme');
        if (savedTheme === 'dark') {
            container.addClass('dark-theme');
            themeToggle.find('i').removeClass('fa-moon').addClass('fa-sun');
        }
        
        // Handle theme toggle
        themeToggle.on('click', function() {
            container.toggleClass('dark-theme');
            const isDark = container.hasClass('dark-theme');
            
            // Update icon
            const icon = $(this).find('i');
            if (isDark) {
                icon.removeClass('fa-moon').addClass('fa-sun');
                localStorage.setItem('pos-theme', 'dark');
            } else {
                icon.removeClass('fa-sun').addClass('fa-moon');
                localStorage.setItem('pos-theme', 'light');
            }
        });
    });
});
