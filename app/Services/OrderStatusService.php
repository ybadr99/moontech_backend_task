<?php

namespace App\Services;

use App\Enums\OrderStatus;

class OrderStatusService
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function isValidTransition(OrderStatus $currentStatus, OrderStatus $newStatus): bool
    {
        if ($currentStatus === $newStatus) {
            return true;
        }

        return in_array($newStatus->value, self::TRANSITIONS[$currentStatus->value] ?? [], true);
    }

    public function isTerminal(OrderStatus $status): bool
    {
        return in_array($status, [OrderStatus::Delivered, OrderStatus::Cancelled], true);
    }
}
