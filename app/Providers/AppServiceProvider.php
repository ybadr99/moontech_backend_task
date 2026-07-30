<?php

namespace App\Providers;

use App\Events\ProductBackInStock;
use App\Listeners\SendBackInStockNotifications;
use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Product::observe(ProductObserver::class);

        Event::listen(
            ProductBackInStock::class,
            SendBackInStockNotifications::class,
        );

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(1)->by($request->input('phone') ?: $request->ip());
        });
    }
}
