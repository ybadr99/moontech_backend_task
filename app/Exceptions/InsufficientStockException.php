<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(string $productTitle = '')
    {
        $message = $productTitle
            ? "Insufficient stock for product: {$productTitle}"
            : 'Insufficient stock for one or more products.';

        parent::__construct($message, 409);
    }
}
