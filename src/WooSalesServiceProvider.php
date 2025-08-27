<?php

namespace Boukjijtarik\WooSales;

use Illuminate\Support\ServiceProvider;

class WooSalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/woo_sales.php', 'woo_sales');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'woo-sales');

        $this->publishes([
            __DIR__.'/../config/woo_sales.php' => config_path('woo_sales.php'),
        ], 'woo-sales-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/woo-sales'),
        ], 'woo-sales-views');
    }
}


