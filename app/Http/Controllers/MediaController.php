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
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        $mediaItems = $query->paginate(12)->withQueryString();
        $categories = MediaCategory::where('status', 'Active')->orderBy('name')->get();

        return view('public.media', compact('mediaItems', 'categories'));
    }

    public function show(MediaItem $mediaItem)
    {
        // Ensure only public or accessible media can be viewed
        if (! $mediaItem->canBeViewedBy(auth()->user())) {
            abort(404);
        }

        return view('public.media-show', compact('mediaItem'));
    }
}
