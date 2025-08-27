<?php

namespace Boukjijtarik\WooSales\Models;

class OrderItem extends BaseWpModel
{
    protected string $baseTable = '{prefix}woocommerce_order_items';
    protected $primaryKey = 'order_item_id';

    public function getProductIdAttribute(): ?int
    {
        // Some stores store product id as meta on item
        return null;
    }
}


