<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockSubscription;
use Illuminate\Http\JsonResponse;

class StockSubscriptionController extends Controller
{
    public function store(Product $product): JsonResponse
    {
        if ($product->stock > 0) {
            return response()->json([
                'message' => 'Product is already in stock.',
            ], 422);
        }

        $subscription = StockSubscription::firstOrCreate([
            'user_id' => request()->user()->id,
            'product_id' => $product->id,
        ]);

        $wasAlreadySubscribed = ! $subscription->wasRecentlyCreated;

        return response()->json([
            'message' => $wasAlreadySubscribed
                ? 'You are already subscribed to this product.'
                : 'You will be notified when this product is back in stock.',
        ]);
    }
}
