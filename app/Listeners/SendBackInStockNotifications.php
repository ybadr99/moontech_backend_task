<?php

namespace App\Listeners;

use App\Events\ProductBackInStock;
use App\Notifications\ProductBackInStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendBackInStockNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(ProductBackInStock $event): void
    {
        $event->product->subscriptions()
            ->whereNull('notified_at')
            ->chunk(200, function ($subscriptions) use ($event) {
                foreach ($subscriptions as $subscription) {
                    $subscription->user->notify(
                        new ProductBackInStockNotification($event->product)
                    );
                }

                $subscriptions->each->update(['notified_at' => now()]);
            });
    }
}
