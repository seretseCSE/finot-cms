<?php

namespace App\Services;

use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogCommentService
{
    /**
     * Build a nested comment tree from a flat collection
     */
    public function buildCommentTree($comments, ?int $parentId = null): array
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

    /**
     * Store a new comment
     */
    public function storeComment(Request $request, string $slug): BlogComment
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

        $this->setCommentAuthor($comment);

        $comment->save();

        return $comment;
    }

    /**
     * Set comment author based on authentication status
     */
    private function setCommentAuthor(BlogComment $comment): void
    {
        if (auth()->check()) {
            $comment->user_id = auth()->id();
            $comment->name = auth()->user()->name;
            $comment->email = auth()->user()->email;
        } else {
            $comment->name = 'Anonymous';
            $comment->email = 'anonymous@example.com';
        }
    }
}
