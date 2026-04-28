<?php

namespace App\Http\Controllers;

use App\Models\MediaCategory;
use App\Models\MediaItem;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $query = MediaItem::where('visibility', 'Public')
            ->with(['category', 'subcategory'])
            ->orderBy('created_at', 'desc');

        // Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%")
                    ->orWhere('event_album', 'like', "%{$search}%");
            });
        }

        $allItems = $query->get();

        // Group by event_album (or title if no album set)
        $grouped = $allItems->groupBy(fn ($item) => $item->event_album ?: $item->title);

        $mediaGroups = $grouped->map(function ($items) {
            return [
                'main' => $items->first(),
                'count' => $items->count(),
                'photos' => $items->where('type', 'Photo')->count(),
                'videos' => $items->where('type', 'Video')->count(),
            ];
        })->values();

        // Paginate the groups
        $perPage = 12;
        $page = $request->get('page', 1);
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $mediaGroups->forPage($page, $perPage),
            $mediaGroups->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $mediaGroups = $paginator->withQueryString();

        $categories = MediaCategory::where('status', 'Active')->orderBy('name')->get();

        return view('public.media', compact('mediaGroups', 'categories'));
    }

    public function show(MediaItem $mediaItem)
    {
        // Ensure only public or accessible media can be viewed
        if (! $mediaItem->canBeViewedBy(auth()->user())) {
            abort(404);
        }

        $groupKey = $mediaItem->event_album ?: $mediaItem->title;

        $relatedMedia = MediaItem::where('visibility', 'Public')
            ->where('id', '!=', $mediaItem->id)
            ->when(
                $mediaItem->event_album,
                fn ($q) => $q->where('event_album', $mediaItem->event_album),
                fn ($q) => $q->where('title', $mediaItem->title)
            )
            ->orderBy('created_at')
            ->limit(12)
            ->get();

        return view('public.media-show', compact('mediaItem', 'relatedMedia', 'groupKey'));
    }
}
