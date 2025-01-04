<?php
/**
 * Receipt Template for SuperWP Cafe POS
 */
?>
<div class="pos-receipt" id="receipt-content">
    <div class="receipt-header">
        <?php 
        $pos_options = get_option('superwp_cafe_pos_options', array());
        
        // Display logo if set
        if (!empty($pos_options['receipt_logo'])) {
            $logo_url = wp_get_attachment_url($pos_options['receipt_logo']);
            if ($logo_url) {
                echo '<img src="' . esc_url($logo_url) . '" alt="Business Logo" class="receipt-logo">';
            }
        }
        ?>
        
        <h2 class="business-name"><?php echo esc_html(get_bloginfo('name')); ?></h2>
        
        <?php if (!empty($pos_options['receipt_header'])): ?>
            <div class="receipt-custom-header"><?php echo wp_kses_post($pos_options['receipt_header']); ?></div>
        <?php endif; ?>
    </div>

    <div class="receipt-info">
        <div class="receipt-date"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?></div>
        <div class="receipt-number">Order #: <?php echo esc_html($order_id); ?></div>
        <div class="receipt-cashier">Served by: <?php echo esc_html($cashier_name); ?></div>
    </div>

    <div class="receipt-items">
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo esc_html($item['name']); ?></td>
                    <td><?php echo esc_html($item['quantity']); ?></td>
                    <td><?php echo wc_price($item['price']); ?></td>
                    <td><?php echo wc_price($item['total']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="receipt-totals">
        <div class="subtotal">
            <span>Subtotal:</span>
            <span><?php echo wc_price($subtotal); ?></span>
        </div>
        <?php if ($tax_total > 0): ?>
        <div class="tax">
            <span>Tax:</span>
            <span><?php echo wc_price($tax_total); ?></span>
        </div>
        <?php endif; ?>
        <div class="total">
            <span>Total:</span>
            <span><?php echo wc_price($total); ?></span>
        </div>
        <?php if ($payment_method === 'cash'): ?>
        <div class="payment">
            <span>Cash:</span>
            <span><?php echo wc_price($cash_given); ?></span>
        </div>
        <div class="change">
            <span>Change:</span>
            <span><?php echo wc_price($change); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="receipt-footer">
        <?php if (!empty($pos_options['receipt_footer'])): ?>
            <div class="receipt-custom-footer"><?php echo wp_kses_post($pos_options['receipt_footer']); ?></div>
        <?php endif; ?>
        <div class="receipt-thank-you">Thank you for your business!</div>
    </div>
</div> 