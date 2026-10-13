<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\Sale;
use App\Policies\ProductPolicy;
use App\Policies\SalePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Tempat binding service; aplikasi latihan ini belum memerlukan binding tambahan.
     */
    public function register(): void
    {
        //
    }

    /**
     * Hubungkan model dan policy secara eksplisit agar pemula mudah menemukan aturan izin.
     */
    public function boot(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
    }
}
