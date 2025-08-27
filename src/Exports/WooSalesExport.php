<?php

namespace Boukjijtarik\WooSales\Exports;

use Boukjijtarik\WooSales\Http\Controllers\WooSalesController;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WooSalesExport implements FromArray, WithHeadings
{
    public function __construct(private array $filters)
    {
    }

    public function array(): array
    {
        // Reuse controller logic to build rows
        $controller = app(WooSalesController::class);
        $request = new Request($this->filters);

        // Build orders
        $reflection = new \ReflectionClass($controller);
        $buildOrders = $reflection->getMethod('buildOrdersBaseQuery');
        $buildOrders->setAccessible(true);

        $ordersQuery = $buildOrders->invoke($controller, $this->filters);
        $orders = $ordersQuery->get();

        // Use controller methods for formatting
        $formatOrder = $reflection->getMethod('formatOrderRow');
        $formatOrder->setAccessible(true);
        $formatItem = $reflection->getMethod('formatItemRow');
        $formatItem->setAccessible(true);

        $connection = config('woo_sales.connection');

        $orderIds = $orders->pluck('ID')->all();
        if (empty($orderIds)) {
            return [];
        }

        $items = \Boukjijtarik\WooSales\Models\OrderItem::on($connection)
            ->whereIn('order_id', $orderIds)
            ->get();

        $itemIds = $items->pluck('order_item_id')->all();
        $itemMeta = \Boukjijtarik\WooSales\Models\OrderItemMeta::on($connection)
            ->whereIn('order_item_id', $itemIds)
            ->get()
            ->groupBy('order_item_id');

        // Product ids from item meta
        $productIds = collect($itemMeta)
            ->flatMap(function ($metas, $itemId) {
                foreach ($metas as $m) {
                    if ($m->meta_key === '_product_id' && $m->meta_value) {
                        return [(int) $m->meta_value];
                    }
                }
                return [];
            })
            ->filter()->unique()->values()->all();

        $products = \Boukjijtarik\WooSales\Models\Post::on($connection)
            ->whereIn('ID', $productIds)
            ->get(['ID', 'post_title'])
            ->keyBy('ID');

        $relationships = \Boukjijtarik\WooSales\Models\TermRelationship::on($connection)
            ->whereIn('object_id', $products->keys()->all())
            ->get();

        $taxonomyIds = $relationships->pluck('term_taxonomy_id')->unique()->all();
        $taxonomies = \Boukjijtarik\WooSales\Models\TermTaxonomy::on($connection)
            ->whereIn('term_taxonomy_id', $taxonomyIds)
            ->where('taxonomy', 'product_cat')
            ->get()
            ->keyBy('term_taxonomy_id');

        $termIds = $taxonomies->pluck('term_id')->unique()->all();
        $terms = \Boukjijtarik\WooSales\Models\Term::on($connection)
            ->whereIn('term_id', $termIds)
            ->get()
            ->keyBy('term_id');

        $productCategories = [];
        foreach ($relationships as $rel) {
            $tt = $taxonomies->get($rel->term_taxonomy_id);
            if (!$tt) { continue; }
            $term = $terms->get($tt->term_id);
            if (!$term) { continue; }
            $productCategories[$rel->object_id][] = $term->name;
        }

        $metaKeys = config('woo_sales.meta_keys');
        $orderMetas = \Boukjijtarik\WooSales\Models\OrderMeta::on($connection)
            ->whereIn('post_id', $orderIds)
            ->whereIn('meta_key', array_merge(array_values($metaKeys), [
                '_billing_first_name','_billing_last_name','_billing_phone','_payment_method','_payment_method_title','_cart_discount','_coupon_code','_order_shipping','_order_total','_billing_address_1','_billing_address_2','_billing_city','_billing_country'
            ]))
            ->get()
            ->groupBy('post_id');

        $rows = [];
        foreach ($orders as $order) {
            $rows[] = $formatOrder->invoke($controller, $order, $orderMetas->get($order->ID, collect()));
            foreach ($items->where('order_id', $order->ID) as $item) {
                $rows[] = $formatItem->invoke($controller, $item, $itemMeta->get($item->order_item_id, collect()), $products, $productCategories);
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'type',
            'customer_name',
            'customer_phone',
            'order_id',
            'invoice_id',
            'customer_address',
            'customer_city',
            'customer_country',
            'order_status',
            'payment_method',
            'payment_reference',
            'wallet_amount',
            'discount',
            'coupon',
            'shipping',
            'order_total',
            'product_name',
            'sku',
            'categories',
            'weight',
            'quantity',
            'item_cost_without_vat',
            'coupon_discounted_amount',
            'total_vat_amount',
        ];
    }
}


