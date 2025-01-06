jQuery(document).ready(function($) {
class POSReceipt {
    constructor() {
        this.bindEvents();
    }

    bindEvents() {
        $(document).on('click', '.print-receipt', this.printReceipt.bind(this));
        
        // Add keyboard shortcut handler
        $(document).on('keydown', (e) => {
            // Check if Ctrl+P or Command+P is pressed
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 80) {
                // Only handle shortcut if receipt modal is open
                const $receiptModal = $('.pos-modal .pos-receipt');
                if ($receiptModal.length) {
                    e.preventDefault(); // Prevent default browser print dialog
                    this.handlePrint($receiptModal);
                }
            }
        });
    }

    handlePrint($receipt) {
        const options = superwpcafPOS.receipt_options || {};
        const copies = parseInt(options.print_copies) || 1;
        
        // Add printer type class
        $receipt.addClass(`thermal-${options.printer_type || '80mm'}`);
        
        // Create temporary container
        const $receiptContainer = $('<div>', {
            class: 'receipt-print-container'
        });

        // Add specified number of copies
        for (let i = 0; i < copies; i++) {
            $receiptContainer.append($receipt.clone());
            if (i < copies - 1) {
                // Add cut mark between copies
                $receiptContainer.append('<div class="receipt-cut-mark"></div>');
            }
        }

        // Append to body
        $('body').append($receiptContainer);

        // Print the receipt
        window.print();

        // Remove the temporary container after printing
        setTimeout(() => {
            $receiptContainer.remove();
        }, 100);
    }

    printReceipt(e) {
        e.preventDefault();
        const orderId = $(e.currentTarget).data('order-id');
        
        // Show loading state
        $(e.currentTarget).prop('disabled', true).text('Preparing...');

        // Fetch receipt HTML
        $.ajax({
            url: superwpcafPOS.ajaxurl,
            type: 'POST',
            data: {
                action: 'superwpcaf_get_receipt',
                order_id: orderId,
                nonce: superwpcafPOS.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.handlePrint($(response.data.html));
                } else {
                    POS.showNotification('Error generating receipt', 'error');
                }
            },
            error: () => {
                POS.showNotification('Error generating receipt', 'error');
            },
            complete: () => {
                $(e.currentTarget).prop('disabled', false).text('Print Receipt');
            }
        });
    }

    static previewReceipt(orderId) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: superwpcafPOS.ajaxurl,
                type: 'POST',
                data: {
                    action: 'superwpcaf_get_receipt',
                    order_id: orderId,
                    nonce: superwpcafPOS.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Create modal with print instructions
                        const modalContent = `
                            <div class="receipt-preview-container">
                                <div class="receipt-actions">
                                    <div class="print-instructions">
                                        Press <kbd>${navigator.platform.indexOf('Mac') === 0 ? '⌘' : 'Ctrl'}+P</kbd> to print
                                        <span class="or-divider">or</span>
                                        <button class="print-receipt" data-order-id="${orderId}">
                                            <i class="fas fa-print"></i> Print Receipt
                                        </button>
                                    </div>
                                </div>
                                ${response.data.html}
                            </div>
                        `;
                        POS.showModal(modalContent);
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data.message));
                    }
                },
                error: reject
            });
        });
    }
}

// Initialize receipt functionality

    window.POSReceipt = new POSReceipt();
}); 