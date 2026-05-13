<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Services\BlogCommentService;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    private BlogCommentService $commentService;

    public function __construct(BlogCommentService $commentService)
    {
        $this->commentService = $commentService;
    }
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

        // Load all approved comments for this post as a flat list
        $allComments = \App\Models\BlogComment::where('blog_post_id', $post->id)
            ->where('is_approved', true)
            ->orderBy('created_at', 'asc')
            ->get();

        // Build nested tree
        $commentsTree = $this->commentService->buildCommentTree($allComments);

        $relatedPosts = BlogPost::where('status', 'Published')
            ->where('id', '!=', $post->id)
            ->when(! empty($post->parsed_tags), function ($query) use ($post) {
                $query->where('tags', 'like', '%' . $post->parsed_tags[0] . '%');
            })
            ->limit(3)
            ->get();

        return view('public.blog.show', compact('post', 'commentsTree', 'relatedPosts'));
    }


    public function storeComment(Request $request, $slug)
    {
        $comment = $this->commentService->storeComment($request, $slug);

        return redirect()->route('blog.show', $slug . '#comments')
            ->with('success', __('Your comment has been posted.'));
    }
}
