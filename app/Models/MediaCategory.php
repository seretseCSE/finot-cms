<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaCategory extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'name',
        'description',
        'display_order',
        'status',
        'created_by',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(MediaSubcategory::class)->orderBy('display_order');
    }

    public function activeSubcategories(): HasMany
    {
        return $this->hasMany(MediaSubcategory::class)->where('status', 'Active')->orderBy('display_order');
    }

    public function mediaItems(): HasMany
    {
        return $this->hasMany(MediaItem::class);
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
