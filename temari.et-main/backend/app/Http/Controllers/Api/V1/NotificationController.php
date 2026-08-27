<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The authenticated user's own notification feed (/me lane — every account
 * type has one: staff, parents, students, platform). Rows are strictly
 * self-scoped; there is no staff view into another user's feed.
 */
class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->notifications()->latest('id');

        if ($request->query('filter') === 'unread') {
            $query->whereNull('read_at');
        }

        if (($category = $request->query('category')) !== null) {
            $query->where('category', $category);
        }

        $page = $query->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        return NotificationResource::collection($page)->additional([
            'meta' => ['unread' => $this->unread($request)],
        ]);
    }

    /**
     * Lightweight badge poll — count only, cheap enough for a 60s cadence
     * from every open client on 3G.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread' => $this->unread($request)]]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'data' => new NotificationResource($notification),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    private function unread(Request $request): int
    {
        return $request->user()->notifications()->whereNull('read_at')->count();
    }
}
