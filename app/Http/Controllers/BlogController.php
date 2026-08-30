<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Services\BlogCommentService;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function __construct(
        private BlogCommentService $commentService,
    ) {
    }

    public function index()
    {
        $posts = BlogPost::where('status', 'Published')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->paginate(12);

        return view('public.blog.index', [
            'posts' => $posts,
            'currentPage' => 'blog',
        ]);
    }

    public function show($slug)
    {
        $post = BlogPost::where('slug', $slug)
            ->where('status', 'Published')
            ->whereNotNull('published_at')
            ->firstOrFail();

        $allComments = \App\Models\BlogComment::where('blog_post_id', $post->id)
            ->where('is_approved', true)
            ->orderBy('created_at', 'asc')
            ->get();

        $commentsTree = $this->commentService->buildCommentTree($allComments);

        $relatedPosts = BlogPost::where('status', 'Published')
            ->where('id', '!=', $post->id)
            ->when(! empty($post->parsed_tags), function ($query) use ($post) {
                $query->where('tags', 'like', '%'.$post->parsed_tags[0].'%');
            })
            ->limit(3)
            ->get();

        return view('public.blog.show', compact('post', 'commentsTree', 'relatedPosts'));
    }

    public function storeComment(Request $request, $slug)
    {
        $this->commentService->storeComment($request, $slug);

        return redirect()->route('blog.show', $slug.'#comments')
            ->with('success', __('Your comment has been posted.'));
    }
}
