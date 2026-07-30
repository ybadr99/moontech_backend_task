<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = request()->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    public function read(DatabaseNotification $notification): JsonResponse
    {
        if ($notification->notifiable_id !== request()->user()->id ||
            $notification->notifiable_type !== request()->user()->getMorphClass()) {
            abort(403);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read.',
        ]);
    }
}
