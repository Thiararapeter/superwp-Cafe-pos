<?php
/**
 * Receipt Items Table Partial
 */
?>
<div class="items-section">
    <table class="items-table">
        <thead>
            <tr>
                <th><?php _e('Item', 'superwp-cafe-pos'); ?></th>
                <th><?php _e('Qty', 'superwp-cafe-pos'); ?></th>
                <th><?php _e('Price', 'superwp-cafe-pos'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sample_order['items'] as $item): ?>
                <tr>
                    <td>
                        <?php echo esc_html($item['name']); ?>
                        <?php if (!empty($options['show_sku'])): ?>
                            <br><small class="sku"><?php echo esc_html($item['sku']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($item['qty']); ?></td>
                    <td><?php echo wc_price($item['price'] * $item['qty']); ?></td>
                </tr>
                <?php if (!empty($options['show_tax'])): ?>
                    <tr class="tax-row">
                        <td colspan="2"><?php _e('Tax', 'superwp-cafe-pos'); ?></td>
                        <td><?php echo wc_price($item['tax']); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($options['show_discount']) && $item['discount'] > 0): ?>
                    <tr class="discount-row">
                        <td colspan="2"><?php _e('Discount', 'superwp-cafe-pos'); ?></td>
                        <td>-<?php echo wc_price($item['discount']); ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div> 