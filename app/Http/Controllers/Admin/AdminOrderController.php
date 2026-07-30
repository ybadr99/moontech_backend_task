<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderStatusChanged;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with('user', 'items.product')
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders)->response();
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $newStatus = OrderStatus::from($request->validated('status'));
        $currentStatus = $order->status;

        if ($currentStatus === $newStatus) {
            return response()->json([
                'message' => 'Order status is already set to ' . $newStatus->value . '.',
                'data' => [
                    'id' => $order->id,
                    'current_status' => $currentStatus->value,
                    'previous_status' => $currentStatus->value,
                    'updated_at' => $order->updated_at,
                ],
            ]);
        }

        if ($this->orderStatusService->isTerminal($currentStatus)) {
            return response()->json([
                'message' => 'Cannot change status of a ' . $currentStatus->value . ' order.',
            ], 422);
        }

        if (! $this->orderStatusService->isValidTransition($currentStatus, $newStatus)) {
            return response()->json([
                'message' => "Invalid status transition from '{$currentStatus->value}' to '{$newStatus->value}'.",
            ], 422);
        }

        $previousStatus = $currentStatus->value;
        $adminId = $request->user()->id;

        DB::transaction(function () use ($order, $newStatus, $previousStatus, $adminId) {
            $order->update(['status' => $newStatus]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus->value,
                'changed_by' => $adminId,
            ]);
        });

        $order->refresh();

        $order->user->notify(new OrderStatusChanged(
            $order->id,
            $previousStatus,
            $newStatus->value,
        ));

        return response()->json([
            'message' => 'Order status updated successfully.',
            'data' => [
                'id' => $order->id,
                'current_status' => $newStatus->value,
                'previous_status' => $previousStatus,
                'updated_at' => $order->updated_at,
                'status_history' => $order->statusHistories()
                    ->latest()
                    ->get()
                    ->map(fn ($h) => [
                        'previous_status' => $h->previous_status,
                        'new_status' => $h->new_status,
                        'changed_by' => $h->changed_by,
                        'changed_at' => $h->created_at,
                    ]),
            ],
        ]);
    }
}
