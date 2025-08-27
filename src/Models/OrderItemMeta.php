<?php

namespace Boukjijtarik\WooSales\Models;

class OrderItemMeta extends BaseWpModel
{
    protected string $baseTable = '{prefix}woocommerce_order_itemmeta';
    protected $primaryKey = 'meta_id';
}


