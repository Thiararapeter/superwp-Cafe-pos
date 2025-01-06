<?php
/**
 * Compact Receipt Template
 */
?>
<div class="receipt-preview-content compact">
    <div class="receipt-header compact">
        <h2 class="business-name"><?php echo esc_html(get_bloginfo('name')); ?></h2>
        <?php if (!empty($options['receipt_header'])): ?>
            <div class="receipt-custom-header small"><?php echo wp_kses_post($options['receipt_header']); ?></div>
        <?php endif; ?>
    </div>

    <div class="receipt-order-info compact">
        <div class="order-basic-info">
            #<?php echo esc_html($sample_order['order_number']); ?> | 
            <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($sample_order['date']))); ?>
        </div>
        
        <?php if (!empty($options['show_cashier']) || !empty($options['show_waiter'])): ?>
            <div class="staff-info-compact">
                <?php 
                $staff = array();
                if (!empty($options['show_cashier'])) {
                    $staff[] = sprintf(__('C: %s', 'superwp-cafe-pos'), esc_html($sample_order['cashier']));
                }
                if (!empty($options['show_waiter'])) {
                    $staff[] = sprintf(__('W: %s', 'superwp-cafe-pos'), esc_html($sample_order['waiter']));
                }
                echo implode(' | ', $staff);
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Compact Items List -->
    <div class="items-list compact">
        <?php foreach ($sample_order['items'] as $item): ?>
            <div class="item-row">
                <div class="item-info">
                    <span class="item-name"><?php echo esc_html($item['name']); ?></span>
                    <span class="item-qty">x<?php echo esc_html($item['qty']); ?></span>
                </div>
                <span class="item-price"><?php echo wc_price($item['price'] * $item['qty']); ?></span>
            </div>
            <?php if (!empty($options['show_sku'])): ?>
                <div class="item-sku small"><?php echo esc_html($item['sku']); ?></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Compact Totals -->
    <div class="totals-compact">
        <div class="total-line">
            <span><?php _e('Total', 'superwp-cafe-pos'); ?></span>
            <span class="amount"><?php echo wc_price($sample_order['total']); ?></span>
        </div>
    </div>

    <?php if (!empty($options['receipt_footer'])): ?>
        <div class="receipt-footer compact">
            <?php echo wp_kses_post($options['receipt_footer']); ?>
        </div>
    <?php endif; ?>
</div> 