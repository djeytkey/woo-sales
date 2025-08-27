# Woo Reports (boukjijtarik/woo-reports)

Laravel package to fetch WooCommerce orders and items using Eloquent (no REST API), show them in a DataTable with Excel export, and auto-export if the result exceeds a threshold.

## Requirements
- PHP >= 8.1
- Laravel 10/11/12
- Access to the WordPress/WooCommerce database

## Install
```bash
composer require boukjijtarik/woo-reports
```

If discovery is disabled, register `Boukjijtarik\\WooSales\\WooSalesServiceProvider::class` in `config/app.php`.

Publish config (optional):
```bash
php artisan vendor:publish --tag=woo-sales-config
```

Publish views (optional):
```bash
php artisan vendor:publish --tag=woo-sales-views
```

## Configure
`.env` overrides:
```env
WOO_SALES_CONNECTION=mysql
WOO_SALES_WP_PREFIX=wp_
WOO_SALES_ROUTE_PREFIX=woo-sales
WOO_SALES_DIRECT_EXPORT_THRESHOLD=1500
```

If the WordPress DB is separate, define a connection in `config/database.php` and point `WOO_SALES_CONNECTION` to it.

Adjust meta keys in `config/woo_sales.php` if needed.

## Use
Open:
```
/woo-sales
```
Filters: date range, order ID, order statuses. If total rows exceed threshold, it redirects to Excel export and shows a notice. Otherwise, a DataTable is shown with an Excel button.

Direct export example:
```
/woo-sales/export?date_from=2024-01-01&date_to=2024-12-31&statuses[]=completed
```

## Data model (tables)
- `{prefix}posts` (orders: `post_type=shop_order`)
- `{prefix}postmeta` (order meta)
- `{prefix}woocommerce_order_items` (order items)
- `{prefix}woocommerce_order_itemmeta` (item meta)
- `{prefix}terms`, `{prefix}term_taxonomy (product_cat)`, `{prefix}term_relationships` (product categories)

## Security
Routes use `web` middleware. To require login, publish config and set:
```php
'middleware' => ['web', 'auth']
```

## License
MIT
