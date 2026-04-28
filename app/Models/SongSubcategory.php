<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SongSubcategory extends BaseModel
{
    use HasAuditLog;
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'image',
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

        static::creating(function (SongSubcategory $subcategory) {
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
        return $this->belongsTo(SongCategory::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class, 'subcategory_id');
    }

    /**
     * Get image URL
     */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return asset('storage/song-subcategories/' . $this->image);
    }

    public function activeSongs(): HasMany
    {
        return $this->hasMany(Song::class, 'subcategory_id')->where('is_active', true);
    }

    /**
     * Check if subcategory can be deleted
     */
    public function canBeDeleted(): bool
    {
        return ! $this->songs()->exists();
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'song_subcategories';
    }
}
