<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\SongCategory;
use Illuminate\Http\Request;

class SongController extends Controller
{
    public function index(Request $request)
    {
        $query = Song::where('is_active', true)
            ->with(['category', 'subcategory'])
            ->orderBy('title');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('lyrics', 'like', "%{$search}%")
                    ->orWhere('artist', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        // Has audio filter
        if ($request->input('has_audio')) {
            $query->whereNotNull('audio_file');
        }

        // Has video filter
        if ($request->input('has_video')) {
            $query->whereNotNull('video_file');
        }

        $songs = $query->paginate(12)->withQueryString();
        $categories = SongCategory::where('status', 'Active')->orderBy('name')->get();

        return view('public.songs.index', compact('songs', 'categories'));
    }

    public function show($id)
    {
        $song = Song::where('is_active', true)
            ->with(['category', 'subcategory'])
            ->findOrFail($id);

        $relatedSongs = Song::where('is_active', true)
            ->where('id', '!=', $song->id)
            ->where('category_id', $song->category_id)
            ->limit(4)
            ->get();

        return view('public.songs.show', compact('song', 'relatedSongs'));
    }
}
