<?php

namespace App\Http\Controllers;

use App\Models\BlogComment;
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

        // Load all approved comments for this post as a flat list
        $allComments = BlogComment::where('blog_post_id', $post->id)
            ->where('is_approved', true)
            ->orderBy('created_at', 'asc')
            ->get();

        // Build nested tree
        $commentsTree = $this->buildCommentTree($allComments);

        $relatedPosts = BlogPost::where('status', 'Published')
            ->where('id', '!=', $post->id)
            ->where(function ($q) use ($post) {
                $q->whereRaw('tags LIKE ?', ["%{$post->parsed_tags[0]}%"]);
            })
            ->limit(3)
            ->get();

        return view('public.blog.show', compact('post', 'commentsTree', 'relatedPosts'));
    }

    /**
     * Build a nested comment tree from a flat collection.
     *
     * @param \Illuminate\Support\Collection $comments
     * @param int|null $parentId
     * @return array
     */
    private function buildCommentTree($comments, ?int $parentId = null): array
    {
        $branch = [];

        foreach ($comments as $comment) {
            if ($comment->parent_id === $parentId) {
                $children = $this->buildCommentTree($comments, $comment->id);
                $comment->children = $children;
                $branch[] = $comment;
            }
        }

        return $branch;
    }

    public function storeComment(Request $request, $slug)
    {
        $post = BlogPost::where('slug', $slug)
            ->where('status', 'Published')
            ->whereNotNull('published_at')
            ->firstOrFail();

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:blog_comments,id',
        ]);

        $comment = new BlogComment([
            'blog_post_id' => $post->id,
            'content' => $validated['content'],
            'parent_id' => $validated['parent_id'] ?? null,
            'is_approved' => true,
        ]);

        if (auth()->check()) {
            $comment->user_id = auth()->id();
            $comment->name = auth()->user()->name;
            $comment->email = auth()->user()->email;
        } else {
            $comment->name = 'Anonymous';
            $comment->email = 'anonymous@example.com';
        }

        $comment->save();

        return redirect()->route('blog.show', $post->slug . '#comments')
            ->with('success', __('Your comment has been posted.'));
    }
}
