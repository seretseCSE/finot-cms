<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SongCategory extends BaseModel
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
        return $this->hasMany(SongSubcategory::class)->orderBy('display_order');
    }

    public function activeSubcategories(): HasMany
    {
        return $this->hasMany(SongSubcategory::class)->where('status', 'Active')->orderBy('display_order');
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
     * Check if category can be deleted
     */
    public function canBeDeleted(): bool
    {
        // Check if category has any songs
        if ($this->songs()->exists()) {
            return false;
        }

        // Check if any subcategory has songs
        foreach ($this->subcategories as $subcategory) {
            if ($subcategory->songs()->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Soft delete category (set to Inactive) if it has songs
     */
    public function softDeleteIfHasSongs(): void
    {
        if (!$this->canBeDeleted()) {
            $this->update(['status' => 'Inactive']);
            
            // Also deactivate all subcategories
            $this->subcategories()->update(['status' => 'Inactive']);
        } else {
            $this->delete();
        }
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'song_categories';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Song Categories';
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
