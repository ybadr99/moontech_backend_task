<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class LogChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $message = $notification->toLog($notifiable);

        Log::info('Order Status Notification', $message);
    }
}
