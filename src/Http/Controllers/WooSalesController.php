<?php

namespace Boukjijtarik\WooSales\Http\Controllers;

use Boukjijtarik\WooSales\Exports\WooSalesExport;
use Boukjijtarik\WooSales\Models\Order;
use Boukjijtarik\WooSales\Models\OrderItem;
use Boukjijtarik\WooSales\Models\OrderItemMeta;
use Boukjijtarik\WooSales\Models\OrderMeta;
use Boukjijtarik\WooSales\Models\Post;
use Boukjijtarik\WooSales\Models\Term;
use Boukjijtarik\WooSales\Models\TermRelationship;
use Boukjijtarik\WooSales\Models\TermTaxonomy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class WooSalesController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $filters = $this->normalizeFilters($request);
        $counts = $this->countOrdersAndItems($filters);
        $threshold = (int) config('woo_sales.direct_export_threshold');

        if (($counts['orders'] + $counts['items']) > $threshold) {
            return redirect()->route('woo-sales.export', $filters)
                ->with('woo_sales_notice', "Result exceeded {$threshold} rows. Exported directly to Excel.");
        }

        return view('woo-sales::index', [
            'filters' => $filters,
            'counts' => $counts,
        ]);
    }

    public function data(Request $request)
    {
        $filters = $this->normalizeFilters($request);

        $ordersQuery = $this->buildOrdersBaseQuery($filters);

        $orders = $ordersQuery->get();

        $wpPrefix = config('woo_sales.wp_prefix');
        $connection = config('woo_sales.connection');

        $orderIds = $orders->pluck('ID')->all();
        if (empty($orderIds)) {
            return response()->json(['data' => []]);
        }

        $items = OrderItem::on($connection)
            ->whereIn('order_id', $orderIds)
            ->get();

        $itemIds = $items->pluck('order_item_id')->all();

        $itemMeta = OrderItemMeta::on($connection)
            ->whereIn('order_item_id', $itemIds)
            ->get()
            ->groupBy('order_item_id');

        // Collect product IDs from item meta (_product_id)
        $productIds = collect($itemMeta)
            ->flatMap(function ($metas, $itemId) {
                foreach ($metas as $m) {
                    if ($m->meta_key === '_product_id' && $m->meta_value) {
                        return [(int) $m->meta_value];
                    }
                }
                return [];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        $products = Post::on($connection)
            ->whereIn('ID', $productIds)
            ->get(['ID', 'post_title'])
            ->keyBy('ID');

        // Build categories map for product IDs
        $relationships = TermRelationship::on($connection)
            ->whereIn('object_id', $products->keys()->all())
            ->get();

        $taxonomyIds = $relationships->pluck('term_taxonomy_id')->unique()->all();
        $taxonomies = TermTaxonomy::on($connection)
            ->whereIn('term_taxonomy_id', $taxonomyIds)
            ->where('taxonomy', 'product_cat')
            ->get()
            ->keyBy('term_taxonomy_id');

        $termIds = $taxonomies->pluck('term_id')->unique()->all();
        $terms = Term::on($connection)
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
        $orderMetas = OrderMeta::on($connection)
            ->whereIn('post_id', $orderIds)
            ->whereIn('meta_key', array_values($metaKeys))
            ->get()
            ->groupBy('post_id');

        $data = [];

        foreach ($orders as $order) {
            $orderRow = $this->formatOrderRow($order, $orderMetas->get($order->ID, collect()));
            $data[] = $orderRow;

            foreach ($items->where('order_id', $order->ID) as $item) {
                $data[] = $this->formatItemRow($item, $itemMeta->get($item->order_item_id, collect()), $products, $productCategories);
            }
        }

        return response()->json(['data' => $data]);
    }

    public function export(Request $request)
    {
        $filters = $this->normalizeFilters($request);
        $fileName = 'woo-sales-'.now()->format('Ymd_His').'.xlsx';
        return Excel::download(new WooSalesExport($filters), $fileName);
    }

    private function normalizeFilters(Request $request): array
    {
        $statuses = array_filter(array_map('trim', (array) $request->get('statuses', [])));
        $orderId = $request->get('order_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        return [
            'statuses' => $statuses,
            'order_id' => $orderId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function buildOrdersBaseQuery(array $filters)
    {
        $connection = config('woo_sales.connection');
        $wpPrefix = config('woo_sales.wp_prefix');

        $query = Order::on($connection)
            ->select(['ID', 'post_date', 'post_status', 'post_author'])
            ->where('post_type', 'shop_order');

        if (!empty($filters['order_id'])) {
            $query->where('ID', (int) $filters['order_id']);
        }

        if (!empty($filters['statuses'])) {
            // WordPress stores statuses as wc-{status}
            $prefixed = array_map(fn($s) => str_starts_with($s, 'wc-') ? $s : 'wc-' . $s, $filters['statuses']);
            $query->whereIn('post_status', $prefixed);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('post_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('post_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('ID');
    }

    private function countOrdersAndItems(array $filters): array
    {
        $ordersQuery = $this->buildOrdersBaseQuery($filters);

        $connection = config('woo_sales.connection');
        $orders = $ordersQuery->pluck('ID');
        $ordersCount = $orders->count();
        if ($ordersCount === 0) {
            return ['orders' => 0, 'items' => 0];
        }

        $itemsCount = OrderItem::on($connection)
            ->whereIn('order_id', $orders->all())
            ->count();

        return ['orders' => $ordersCount, 'items' => $itemsCount];
    }

    private function formatOrderRow($order, $metaCollection): array
    {
        $meta = [];
        foreach ($metaCollection as $m) {
            $meta[$m->meta_key] = $m->meta_value;
        }

        $metaKeys = config('woo_sales.meta_keys');

        // Customer data stored in post meta in WooCommerce
        $billingFirstName = $meta['_billing_first_name'] ?? '';
        $billingLastName = $meta['_billing_last_name'] ?? '';
        $customerName = trim($billingFirstName.' '.$billingLastName);

        $customerPhone = $meta['_billing_phone'] ?? '';
        $invoiceId = $meta[$metaKeys['invoice_id']] ?? '';
        $paymentMethod = $meta['_payment_method_title'] ?? ($meta['_payment_method'] ?? '');
        $paymentReference = $meta[$metaKeys['payment_reference']] ?? '';
        $walletAmount = (float) ($meta[$metaKeys['wallet_amount']] ?? 0);
        $discount = (float) ($meta[$metaKeys['discount_total']] ?? ($meta['_cart_discount'] ?? 0));
        $coupon = $meta[$metaKeys['coupon_code']] ?? ($meta['_coupon_code'] ?? '');
        $shipping = (float) ($meta[$metaKeys['shipping_total']] ?? ($meta['_order_shipping'] ?? 0));
        $orderTotal = (float) ($meta['_order_total'] ?? 0);
        $address1 = $meta['_billing_address_1'] ?? '';
        $address2 = $meta['_billing_address_2'] ?? '';
        $city = $meta['_billing_city'] ?? '';
        $country = $meta['_billing_country'] ?? '';
        $status = $order->post_status;

        return [
            'type' => 'order',
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'order_id' => $order->ID,
            'invoice_id' => $invoiceId,
            'customer_address' => trim($address1.' '.$address2),
            'customer_city' => $city,
            'customer_country' => $country,
            'order_status' => $status,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'wallet_amount' => $walletAmount,
            'discount' => $discount,
            'coupon' => $coupon,
            'shipping' => $shipping,
            'order_total' => $orderTotal,
        ];
    }

    private function formatItemRow($item, $metaCollection, $products, $productCategories): array
    {
        $meta = [];
        foreach ($metaCollection as $m) {
            $meta[$m->meta_key] = $m->meta_value;
        }

        $productId = (int) ($meta['_product_id'] ?? $item->product_id ?? 0);
        $productName = $products->get($productId)->post_title ?? ($item->order_item_name ?? '');
        $sku = $meta['_sku'] ?? ($meta['_product_id'] ? ($meta['_variation_id'] ?? '') : '');
        $categories = implode(', ', $productCategories[$productId] ?? []);
        $weight = $meta['_weight'] ?? '';
        $qty = (int) ($meta['_qty'] ?? 1);
        $lineSubtotal = (float) ($meta['_line_subtotal'] ?? 0);
        $lineSubtotalTax = (float) ($meta['_line_subtotal_tax'] ?? 0);
        $lineTotal = (float) ($meta['_line_total'] ?? 0);
        $lineTax = (float) ($meta['_line_tax'] ?? 0);
        $couponDiscounted = max(0, $lineSubtotal - $lineTotal);
        $totalVat = $lineTax; // tax on discounted line total

        // Item cost without VAT = line total without VAT divided by qty
        $itemCostWithoutVat = $qty > 0 ? round(($lineTotal) / $qty, 2) : $lineTotal;

        return [
            'type' => 'item',
            'product_name' => $productName,
            'sku' => $sku,
            'categories' => $categories,
            'weight' => $weight,
            'quantity' => $qty,
            'item_cost_without_vat' => $itemCostWithoutVat,
            'coupon_discounted_amount' => $couponDiscounted,
            'total_vat_amount' => $totalVat,
        ];
    }
}


