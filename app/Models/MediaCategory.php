<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaCategory extends BaseModel
{
    use HasAuditLog;
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'display_order',
        'status',
        'created_by',
    ];

    /**
     * Boot the model to automatically set created_by.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (MediaCategory $category) {
            if (auth()->check() && !$category->created_by) {
                $category->created_by = auth()->id();
            }
        });
    }

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(MediaSubcategory::class, 'category_id')->orderBy('display_order');
    }

    public function activeSubcategories(): HasMany
    {
        return $this->hasMany(MediaSubcategory::class, 'category_id')->where('status', 'Active')->orderBy('display_order');
    }

    public function mediaItems(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'category_id');
    }

    /**
     * Check if category can be deleted
     */
    public function canBeDeleted(): bool
    {
        if ($this->mediaItems()->exists()) {
            return false;
        }

        foreach ($this->subcategories as $subcategory) {
            if ($subcategory->mediaItems()->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'media_categories';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Media Categories';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-tag';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Worship & Media';
    }
}
