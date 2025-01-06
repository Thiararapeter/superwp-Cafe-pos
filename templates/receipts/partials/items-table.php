<?php
/**
 * Receipt Items Table Partial
 */
?>
<div class="items-section">
    <table class="receipt-items">
        <thead>
            <tr>
                <th><?php _e('Item', 'superwp-cafe-pos'); ?></th>
                <?php if (!empty($options['show_sku'])): ?>
                    <th><?php _e('SKU', 'superwp-cafe-pos'); ?></th>
                <?php endif; ?>
                <th><?php _e('Qty', 'superwp-cafe-pos'); ?></th>
                <th><?php _e('Price', 'superwp-cafe-pos'); ?></th>
                <?php if (!empty($options['show_discount'])): ?>
                    <th><?php _e('Disc', 'superwp-cafe-pos'); ?></th>
                <?php endif; ?>
                <th><?php _e('Total', 'superwp-cafe-pos'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sample_order['items'] as $item): ?>
                <tr>
                    <td><?php echo esc_html($item['name']); ?></td>
                    <?php if (!empty($options['show_sku'])): ?>
                        <td><?php echo esc_html($item['sku']); ?></td>
                    <?php endif; ?>
                    <td class="qty"><?php echo esc_html($item['qty']); ?></td>
                    <td class="price"><?php echo wc_price($item['price']); ?></td>
                    <?php if (!empty($options['show_discount'])): ?>
                        <td class="discount"><?php echo wc_price($item['discount']); ?></td>
                    <?php endif; ?>
                    <td class="total"><?php echo wc_price($item['qty'] * $item['price'] - $item['discount']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div> 