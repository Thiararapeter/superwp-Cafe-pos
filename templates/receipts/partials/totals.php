<?php
/**
 * Receipt Totals Partial
 */
?>
<div class="receipt-totals">
    <div class="subtotal">
        <span><?php _e('Subtotal', 'superwp-cafe-pos'); ?></span>
        <span><?php echo wc_price($sample_order['subtotal']); ?></span>
    </div>
    
    <?php if (!empty($options['show_tax'])): ?>
        <div class="tax">
            <span><?php _e('Tax', 'superwp-cafe-pos'); ?></span>
            <span><?php echo wc_price($sample_order['tax']); ?></span>
        </div>
    <?php endif; ?>
    
    <div class="total">
        <span><?php _e('Total', 'superwp-cafe-pos'); ?></span>
        <span><?php echo wc_price($sample_order['total']); ?></span>
    </div>
</div> 