<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max($request->integer('per_page', 15), 1), 20);

        $paginator = $user->notifications()
            ->latest()
            ->paginate($perPage);

        $data = $paginator->getCollection()->map(fn ($notification) => [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? '',
            'body' => $notification->data['body'] ?? '',
            'type' => $notification->data['type'] ?? 'system',
            'action_url' => $notification->data['action_url'] ?? null,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'unread_count' => $user->unreadNotifications()->count(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json(['status' => 'ok']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
