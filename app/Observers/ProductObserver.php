<?php

namespace App\Observers;

use App\Events\ProductBackInStock;
use App\Models\Product;
use App\Models\User;
use App\Notifications\NewProductNotification;

class ProductObserver
{
    public function created(Product $product): void
    {
        User::where('role', 'user')
            ->chunk(200, function ($users) use ($product) {
                foreach ($users as $user) {
                    $user->notify(new NewProductNotification($product));
                }
            });
    }

    public function updated(Product $product): void
    {
        if ($product->isDirty('stock') && $product->stock > 0 && (int) $product->getOriginal('stock') === 0) {
            ProductBackInStock::dispatch($product);
        }
    }
}
