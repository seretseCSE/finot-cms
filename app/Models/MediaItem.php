<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaItem extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'title',
        'type',
        'category_id',
        'subcategory_id',
        'description',
        'file_path',
        'file_size_kb',
        'event_album',
        'tags',
        'visibility',
        'department_id',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size_kb' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MediaCategory::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(MediaSubcategory::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get file URL
     */
    public function getFileUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $size = $this->file_size_kb;
        
        if ($size >= 1024) {
            return round($size / 1024, 2) . ' MB';
        }
        
        return $size . ' KB';
    }

    /**
     * Get type icon
     */
    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            'Photo' => 'heroicon-o-photo',
            'Video' => 'heroicon-o-video-camera',
            default => 'heroicon-o-document',
        };
    }

    /**
     * Get visibility color
     */
    public function getVisibilityColorAttribute(): string
    {
        return match($this->visibility) {
            'Public' => 'green',
            'Members Only' => 'blue',
            'Department Only' => 'purple',
            default => 'gray',
        };
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
     * Check if user can view this media item
     */
    public function canBeViewedBy(?User $user): bool
    {
        if (!$user) {
            return $this->visibility === 'Public';
        }

        return match($this->visibility) {
            'Public' => true,
            'Members Only' => true, // All authenticated users are members
            'Department Only' => $user->department_id === $this->department_id,
            default => false,
        };
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'media_items';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Media';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-photo';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Worship & Media';
    }
}
