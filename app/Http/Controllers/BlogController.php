<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::where('status', 'Published')
            ->whereNotNull('published_at')
            ->orderBy('published_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        // Tag filter
        if ($request->filled('tag')) {
            $tag = $request->input('tag');
            $query->where('tags', 'like', "%{$tag}%");
        }

        $posts = $query->paginate(9)->withQueryString();

        // Get all unique tags for filter
        $allTags = BlogPost::where('status', 'Published')
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatMap(fn ($tags) => array_map('trim', explode(',', $tags)))
            ->unique()
            ->filter()
            ->values();

        return view('public.blog.index', compact('posts', 'allTags'));
    }

    public function show($slug)
    {
        $post = BlogPost::where('slug', $slug)
            ->where('status', 'Published')
            ->whereNotNull('published_at')
            ->firstOrFail();

        $relatedPosts = BlogPost::where('status', 'Published')
            ->where('id', '!=', $post->id)
            ->where(function ($q) use ($post) {
                $q->whereRaw('tags LIKE ?', ["%{$post->parsed_tags[0]}%"]);
            })
            ->limit(3)
            ->get();

        return view('public.blog.show', compact('post', 'relatedPosts'));
    }
}
