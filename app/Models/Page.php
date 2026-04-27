<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Page extends BaseModel
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'title_am',
        'content',
        'content_am',
        'status',
        'meta_description',
        'meta_description_am',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($page) {
            if (empty($page->slug)) {
                $page->slug = static::generateUniqueSlug($page->title);
            }
        });

        static::updating(function ($page) {
            if ($page->isDirty('title') && empty($page->slug)) {
                $page->slug = static::generateUniqueSlug($page->title);
            }
        });
    }

    /**
     * Generate unique slug from title
     */
    public static function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$count;
            $count++;
        }

        return $slug;
    }

    /**
     * Check if page is published
     */
    public function isPublished(): bool
    {
        return $this->status === 'Published';
    }

    /**
     * Get status color
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Draft' => 'gray',
            'Published' => 'green',
            'Archived' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'pages';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Pages';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Content Management';
    }
}
