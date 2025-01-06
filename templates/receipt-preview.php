<?php
// Get preview settings if available, otherwise use saved settings
$preview_settings = get_option('superwp_cafe_pos_preview_settings', array());
$saved_options = get_option('superwp_cafe_pos_options', array());

// Merge saved options with preview settings
$options = array_merge(
    array(
        'receipt_header' => '',
        'receipt_footer' => '',
        'show_cashier' => 1,
        'show_waiter' => 1,
        'show_sku' => 0,
        'show_tax' => 1,
        'show_discount' => 0
    ),
    $saved_options,
    $preview_settings
);

// Sample data for preview
$sample_order = array(
    'order_number' => '1234',
    'date' => current_time('mysql'),
    'cashier' => 'John Doe',
    'waiter' => 'Jane Smith',
    'items' => array(
        array(
            'name' => 'Sample Product 1',
            'qty' => 2,
            'price' => 10.00,
            'sku' => 'SKU001',
            'tax' => 1.00,
            'discount' => 0.50,
        ),
        array(
            'name' => 'Sample Product 2',
            'qty' => 1,
            'price' => 15.00,
            'sku' => 'SKU002',
            'tax' => 1.50,
            'discount' => 0,
        ),
    ),
    'subtotal' => 35.00,
    'tax' => 3.50,
    'total' => 38.50,
);

// Load the standard template
include SUPERWPCAF_PLUGIN_DIR . 'templates/receipts/standard.php'; 