<?php

namespace App\Http\Controllers;

use App\Models\MediaCategory;
use App\Models\MediaItem;
use App\Models\Song;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function legacyIndex()
    {
        return redirect()->route('media', [], 301);
    }

    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'photos');
        if (! in_array($activeTab, ['photos', 'videos', 'songs'], true)) {
            $activeTab = 'photos';
        }

        $categories = MediaCategory::where('status', 'Active')->orderBy('name')->get();
        $songs = collect();
        $mediaGroups = null;

        if ($activeTab === 'songs') {
            try {
                $songs = Song::query()
                    ->where('is_active', true)
                    ->latest()
                    ->paginate(12)
                    ->withQueryString();
            } catch (\Throwable $e) {
                $songs = Song::query()->latest()->paginate(12)->withQueryString();
            }

            return view('public.media', compact('mediaGroups', 'categories', 'activeTab', 'songs'));
        }

        $query = MediaItem::where('visibility', 'Public')
            ->with(['category', 'subcategory'])
            ->orderBy('created_at', 'desc');

        if ($activeTab === 'photos') {
            $query->where('type', 'Photo');
        } elseif ($activeTab === 'videos') {
            $query->where('type', 'Video');
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

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

        $grouped = $allItems->groupBy(fn ($item) => $item->event_album ?: $item->title);

        $mediaGroups = $grouped->map(function ($items) {
            return [
                'main' => $items->first(),
                'count' => $items->count(),
                'photos' => $items->where('type', 'Photo')->count(),
                'videos' => $items->where('type', 'Video')->count(),
            ];
        })->values();

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

        return view('public.media', compact('mediaGroups', 'categories', 'activeTab', 'songs'));
    }

    public function show(MediaItem $mediaItem)
    {
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
