<?php

namespace App\Observers;

use App\Events\OrderStatusChanged;
use App\Models\Order;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->isDirty('status')) {
            OrderStatusChanged::dispatch($order, $order->getOriginal('status')->value);
        }
    }
}
