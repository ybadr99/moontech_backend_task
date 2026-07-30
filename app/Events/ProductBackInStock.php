<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;

class ProductBackInStock
{
    use Dispatchable;

    public function __construct(
        public Product $product,
    ) {}
}
