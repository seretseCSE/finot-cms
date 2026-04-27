<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaSubcategory extends BaseModel
{
    use HasAuditLog;
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
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

        static::creating(function (MediaSubcategory $subcategory) {
            if (auth()->check() && !$subcategory->created_by) {
                $subcategory->created_by = auth()->id();
            }
        });
    }

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MediaCategory::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mediaItems(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'subcategory_id');
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'media_subcategories';
    }
}
