<?php

namespace Boukjijtarik\WooSales\Models;

use Illuminate\Database\Eloquent\Model;

abstract class BaseWpModel extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected string $baseTable = '';

    public function getTable()
    {
        $prefix = config('woo_sales.wp_prefix');
        $table = $this->baseTable ?: $this->table;
        if (str_contains($table, '{prefix}')) {
            return str_replace('{prefix}', $prefix, $table);
        }
        return $table;
    }

    public function getConnectionName()
    {
        return config('woo_sales.connection');
    }
}


