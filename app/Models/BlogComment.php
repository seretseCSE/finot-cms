<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'blog_post_id',
        'user_id',
        'name',
        'email',
        'content',
        'is_approved',
        'parent_id',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BlogComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(BlogComment::class, 'parent_id')->where('is_approved', true)->orderBy('created_at');
    }

    /**
     * Get the permission name for a given action.
     */
    public static function getPermissionName(string $action): string
    {
        return "blog_comments.{$action}";
    }

    /**
     * Get the resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'blog_comments';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Blog Comments';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Worship & Media';
    }
}
