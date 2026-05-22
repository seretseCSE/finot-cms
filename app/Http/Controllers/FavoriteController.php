<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\LibraryResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favoriteIds = $this->getFavoriteIds();
        $favoriteType = $request->input('type');

        $resources = collect();
        $courses = collect();

        if (! $favoriteType || $favoriteType === 'library') {
            $libIds = $favoriteIds['library'] ?? [];
            if (! empty($libIds)) {
                $resources = LibraryResource::whereIn('id', $libIds)
                    ->where('is_active', true)
                    ->with('category')
                    ->get();
            }
        }

        if (! $favoriteType || $favoriteType === 'course') {
            $courseIds = $favoriteIds['course'] ?? [];
            if (! empty($courseIds)) {
                $courses = Course::whereIn('id', $courseIds)
                    ->where('status', 'Published')
                    ->with('category')
                    ->get();
            }
        }

        return view('public.favorites', compact('resources', 'courses', 'favoriteType'));
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'favorable_type' => 'required|string',
            'favorable_id' => 'required|integer',
        ]);

        $type = $request->favorable_type;
        $id = (int) $request->favorable_id;

        if (Auth::check()) {
            $existing = Favorite::where('user_id', Auth::id())
                ->where('favorable_type', $type)
                ->where('favorable_id', $id)
                ->first();

            if ($existing) {
                $existing->delete();
                return response()->json(['favorited' => false]);
            }

            Favorite::create([
                'user_id' => Auth::id(),
                'favorable_type' => $type,
                'favorable_id' => $id,
            ]);

            return response()->json(['favorited' => true]);
        }

        $key = 'favorites_' . $type;
        $favorites = json_decode($request->cookie($key, '[]'), true) ?? [];

        if (in_array($id, $favorites)) {
            $favorites = array_values(array_filter($favorites, fn($fid) => $fid !== $id));
            return response()->json(['favorited' => false])->cookie($key, json_encode($favorites), 60 * 24 * 365);
        }

        $favorites[] = $id;
        return response()->json(['favorited' => true])->cookie($key, json_encode($favorites), 60 * 24 * 365);
    }

    public function status(Request $request)
    {
        $request->validate([
            'favorable_type' => 'required|string',
            'favorable_id' => 'required|integer',
        ]);

        $type = $request->favorable_type;
        $id = (int) $request->favorable_id;

        if (Auth::check()) {
            $favorited = Favorite::where('user_id', Auth::id())
                ->where('favorable_type', $type)
                ->where('favorable_id', $id)
                ->exists();
        } else {
            $key = 'favorites_' . $type;
            $favorites = json_decode($request->cookie($key, '[]'), true) ?? [];
            $favorited = in_array($id, $favorites);
        }

        return response()->json(['favorited' => $favorited]);
    }

    private function getFavoriteIds(): array
    {
        if (Auth::check()) {
            $favorites = Favorite::where('user_id', Auth::id())->get();
            $grouped = ['library' => [], 'course' => []];
            foreach ($favorites as $fav) {
                $shortType = match ($fav->favorable_type) {
                    'App\Models\LibraryResource' => 'library',
                    'App\Models\Course' => 'course',
                    default => 'other',
                };
                if ($shortType !== 'other') {
                    $grouped[$shortType][] = $fav->favorable_id;
                }
            }
            return $grouped;
        }

        return [
            'library' => json_decode(request()->cookie('favorites_App\\Models\\LibraryResource', '[]'), true) ?? [],
            'course' => json_decode(request()->cookie('favorites_App\\Models\\Course', '[]'), true) ?? [],
        ];
    }
}
