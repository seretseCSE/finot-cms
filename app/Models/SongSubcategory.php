<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SongSubcategory extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'display_order',
        'status',
        'created_by',
    ];

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
        return $this->hasMany(Song::class);
    }

    public function activeSongs(): HasMany
    {
        return $this->hasMany(Song::class)->where('is_active', true);
    }

    /**
     * Check if subcategory can be deleted
     */
    public function canBeDeleted(): bool
    {
        return !$this->songs()->exists();
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'song_subcategories';
    }
}
