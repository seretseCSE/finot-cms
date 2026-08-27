<?php

namespace App\Http\Controllers;

use App\Models\Song;

class SongController extends Controller
{
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
