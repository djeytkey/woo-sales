<?php

return [
    // Database connection name where WordPress/WooCommerce tables exist
    'connection' => env('DB_CONNECTION', 'mysql'),

    // WordPress table prefix, e.g., 'wp_'
    'wp_prefix' => env('DB_TABLE_PREFIX', 'wp_'),

    // Route prefix for accessing the UI
    'route_prefix' => env('WOO_SALES_ROUTE_PREFIX', 'woo-sales'),

    // Middleware for routes
    'middleware' => ['admin'],

    // Meta keys used in your store
    'meta_keys' => [
        'invoice_id' => '_invoice_id',
        'payment_reference' => '_payment_reference',
        'wallet_amount' => '_partial_pay_through_wallet_compleate',
        'discount_total' => '_cart_discount',
        'coupon_code' => '_coupon_code',
        'shipping_total' => '_order_shipping',
    ],

    // Export threshold: if total rows (orders + items) exceed this, export directly
    'direct_export_threshold' => (int) env('WOO_SALES_DIRECT_EXPORT_THRESHOLD', 1500),
];


