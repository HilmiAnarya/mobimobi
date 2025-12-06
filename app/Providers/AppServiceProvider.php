<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Order; // Import Model
use App\Observers\OrderObserver; // Import Observer

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Daftarkan Observer di sini
        Order::observe(OrderObserver::class);
    }
}
