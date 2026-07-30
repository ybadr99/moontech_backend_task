<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function createOrder(User $user, array $items): Order
    {
        return DB::transaction(function () use ($user, $items) {
            $productIds = collect($items)->pluck('product_id')->unique();

            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $orderTotal = 0;
            $orderItems = [];

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    throw new InsufficientStockException;
                }

                if ($product->stock < $item['quantity']) {
                    throw new InsufficientStockException($product->title);
                }

                $subtotal = $product->price * $item['quantity'];
                $orderTotal += $subtotal;

                $orderItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $subtotal,
                ];
            }

            $order = $user->orders()->create([
                'total' => $orderTotal,
                'status' => 'pending',
            ]);

            foreach ($orderItems as $orderItem) {
                $order->items()->create([
                    'product_id' => $orderItem['product']->id,
                    'quantity' => $orderItem['quantity'],
                    'price' => $orderItem['price'],
                    'subtotal' => $orderItem['subtotal'],
                ]);

                $orderItem['product']->decrement('stock', $orderItem['quantity']);
            }

            return $order;
        });
    }
}
