<?php
/**
 * Standard Receipt Template
 */
?>
<div class="receipt-preview-content standard">
    <div class="receipt-header">
        <h2 class="business-name"><?php echo esc_html(get_bloginfo('name')); ?></h2>
        <?php if (!empty($options['receipt_header'])): ?>
            <div class="receipt-custom-header"><?php echo wp_kses_post($options['receipt_header']); ?></div>
        <?php endif; ?>
    </div>

    <div class="receipt-order-info">
        <div class="order-details">
            <p><?php _e('Order:', 'superwp-cafe-pos'); ?> #<?php echo esc_html($sample_order['order_number']); ?></p>
            <p><?php _e('Date:', 'superwp-cafe-pos'); ?> <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($sample_order['date']))); ?></p>
        </div>
        
        <?php if (!empty($options['show_cashier']) || !empty($options['show_waiter'])): ?>
            <div class="staff-details">
                <?php if (!empty($options['show_cashier'])): ?>
                    <p class="staff-info cashier"><?php _e('Cashier:', 'superwp-cafe-pos'); ?> <?php echo esc_html($sample_order['cashier']); ?></p>
                <?php endif; ?>
                <?php if (!empty($options['show_waiter'])): ?>
                    <p class="staff-info waiter"><?php _e('Waiter:', 'superwp-cafe-pos'); ?> <?php echo esc_html($sample_order['waiter']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php 
    // Include items table
    require_once dirname(__FILE__) . '/partials/items-table.php';
    
    // Include totals
    require_once dirname(__FILE__) . '/partials/totals.php';
    ?>

    <?php if (!empty($options['receipt_footer'])): ?>
        <div class="receipt-footer">
            <?php echo wp_kses_post($options['receipt_footer']); ?>
        </div>
    <?php endif; ?>
</div> 