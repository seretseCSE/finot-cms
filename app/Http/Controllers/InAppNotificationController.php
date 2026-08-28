<?php

namespace App\Http\Controllers;

use App\Models\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InAppNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (InAppNotification $n) {
                return [
                    'id' => $n->id,
                    'event' => $n->event,
                    'title' => __('notifications.'.$n->event.'.title', $n->data ?? []),
                    'body' => __('notifications.'.$n->event.'.body', $n->data ?? []),
                    'link' => $n->link,
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'unread' => InAppNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, InAppNotification $notification): JsonResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);

        $notification->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
