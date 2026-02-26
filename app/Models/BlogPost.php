<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPost extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'title',
        'title_am',
        'content',
        'content_am',
        'author_id',
        'publish_date',
        'featured_image',
        'tags',
        'status',
        'published_at',
    ];

    protected $casts = [
        'publish_date' => 'date',
        'published_at' => 'datetime',
        'is_urgent' => 'boolean',
    ];

    protected $dates = [
        'publish_date',
        'published_at',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get formatted publish date in Ethiopian
     */
    public function getEthiopianPublishDateAttribute(): string
    {
        if (!$this->publish_date) {
            return '';
        }

        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->publish_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->publish_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->publish_date)['year'];
    }

    /**
     * Get featured image URL
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        if (!$this->featured_image) {
            return null;
        }

        return asset('storage/' . $this->featured_image);
    }

    /**
     * Get parsed tags as array
     */
    public function getParsedTagsAttribute(): array
    {
        if (!$this->tags) {
            return [];
        }
        
        return array_map('trim', explode(',', $this->tags));
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Draft' => 'gray',
            'Scheduled' => 'yellow',
            'Published' => 'green',
            'Archived' => 'red',
            default => 'gray',
        };
    }

    /**
     * Check if post is published
     */
    public function isPublished(): bool
    {
        return $this->status === 'Published';
    }

    /**
     * Check if post should be published (for scheduled posts)
     */
    public function shouldPublish(): bool
    {
        return $this->status === 'Scheduled' && 
               $this->publish_date && 
               $this->publish_date->lte(now());
    }

    /**
     * Publish the post
     */
    public function publish(): void
    {
        $this->update([
            'status' => 'Published',
            'published_at' => now(),
        ]);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'blog_post_published',
            'entity_id' => $this->id,
            'entity_type' => 'blog_post',
            'old_value' => json_encode(['status' => 'Scheduled']),
            'new_value' => json_encode(['status' => 'Published', 'published_at' => now()]),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'blog_posts';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Blog Posts';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-text';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Worship & Media';
    }
}
