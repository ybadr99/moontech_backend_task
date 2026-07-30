<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createOrder(
                $request->user(),
                $request->validated()['items'],
            );
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        $order->load('items.product');

        return response()->json([
            'message' => 'Order created successfully',
            'data' => [
                'id' => $order->id,
                'items' => $order->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'product_title' => $item->product->title,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'subtotal' => (float) $item->subtotal,
                ]),
                'total' => (float) $order->total,
                'status' => $order->status,
                'created_at' => $order->created_at,
            ],
        ], 201);
    }
}
