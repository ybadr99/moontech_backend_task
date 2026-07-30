<?php

namespace App\Notifications;

use App\Notifications\Channels\LogChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $orderId,
        public string $previousStatus,
        public string $newStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return [LogChannel::class];
    }

    public function toLog(object $notifiable): array
    {
        return [
            'order_id' => $this->orderId,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'customer_id' => $notifiable->id,
            'customer_phone' => $notifiable->phone,
        ];
    }
}
