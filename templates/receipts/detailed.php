<?php
/**
 * Detailed Receipt Template
 */
?>
<div class="receipt-preview-content detailed">
    <div class="receipt-header detailed">
        <h2 class="business-name"><?php echo esc_html(get_bloginfo('name')); ?></h2>
        <?php if (!empty($options['receipt_header'])): ?>
            <div class="receipt-custom-header"><?php echo wp_kses_post($options['receipt_header']); ?></div>
        <?php endif; ?>
    </div>

    <div class="receipt-order-info detailed">
        <div class="order-details-box">
            <h3><?php _e('Order Information', 'superwp-cafe-pos'); ?></h3>
            <table class="info-table">
                <tr>
                    <th><?php _e('Order Number:', 'superwp-cafe-pos'); ?></th>
                    <td>#<?php echo esc_html($sample_order['order_number']); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Date:', 'superwp-cafe-pos'); ?></th>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($sample_order['date']))); ?></td>
                </tr>
                <?php if (!empty($options['show_cashier'])): ?>
                    <tr>
                        <th><?php _e('Cashier:', 'superwp-cafe-pos'); ?></th>
                        <td><?php echo esc_html($sample_order['cashier']); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($options['show_waiter'])): ?>
                    <tr>
                        <th><?php _e('Waiter:', 'superwp-cafe-pos'); ?></th>
                        <td><?php echo esc_html($sample_order['waiter']); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Detailed Items Table -->
    <div class="items-section">
        <h3><?php _e('Order Items', 'superwp-cafe-pos'); ?></h3>
        <table class="items-table detailed">
            <thead>
                <tr>
                    <th><?php _e('Item', 'superwp-cafe-pos'); ?></th>
                    <th><?php _e('Qty', 'superwp-cafe-pos'); ?></th>
                    <th><?php _e('Unit Price', 'superwp-cafe-pos'); ?></th>
                    <th><?php _e('Total', 'superwp-cafe-pos'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sample_order['items'] as $item): ?>
                    <tr>
                        <td>
                            <?php echo esc_html($item['name']); ?>
                            <?php if (!empty($options['show_sku'])): ?>
                                <div class="sku-info"><?php echo esc_html($item['sku']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($item['qty']); ?></td>
                        <td><?php echo wc_price($item['price']); ?></td>
                        <td><?php echo wc_price($item['price'] * $item['qty']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Detailed Totals -->
    <div class="totals-section detailed">
        <table class="totals-table">
            <tr>
                <th><?php _e('Subtotal', 'superwp-cafe-pos'); ?></th>
                <td><?php echo wc_price($sample_order['subtotal']); ?></td>
            </tr>
            <?php if (!empty($options['show_tax'])): ?>
                <tr>
                    <th><?php _e('Tax', 'superwp-cafe-pos'); ?></th>
                    <td><?php echo wc_price($sample_order['tax']); ?></td>
                </tr>
            <?php endif; ?>
            <tr class="total-row">
                <th><?php _e('Total', 'superwp-cafe-pos'); ?></th>
                <td><?php echo wc_price($sample_order['total']); ?></td>
            </tr>
        </table>
    </div>

    <?php if (!empty($options['receipt_footer'])): ?>
        <div class="receipt-footer detailed">
            <?php echo wp_kses_post($options['receipt_footer']); ?>
        </div>
    <?php endif; ?>
</div> 