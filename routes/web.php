<?php

use Illuminate\Support\Facades\Route;
use Boukjijtarik\WooSales\Http\Controllers\WooSalesController;

Route::group([
    'prefix' => config('woo_sales.route_prefix'),
    'middleware' => config('woo_sales.middleware')
], function () {
    Route::get('/', [WooSalesController::class, 'index'])->name('woo-sales.index');
    Route::get('/data', [WooSalesController::class, 'data'])->name('woo-sales.data');
    Route::get('/export', [WooSalesController::class, 'export'])->name('woo-sales.export');
});


