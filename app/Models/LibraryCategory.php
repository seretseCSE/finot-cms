<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryCategory extends BaseModel
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

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(LibrarySubcategory::class, 'category_id')->orderBy('display_order');
    }

    public function activeSubcategories(): HasMany
    {
        return $this->hasMany(LibrarySubcategory::class, 'category_id')->where('status', 'Active')->orderBy('display_order');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LibraryResource::class, 'category_id');
    }

    public function activeResources(): HasMany
    {
        return $this->hasMany(LibraryResource::class, 'category_id')->where('is_active', true);
    }

    /**
     * Check if category can be deleted
     */
    public function canBeDeleted(): bool
    {
        // Check if category has any resources
        if ($this->resources()->exists()) {
            return false;
        }

        // Check if any subcategory has resources
        foreach ($this->subcategories as $subcategory) {
            if ($subcategory->resources()->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Soft delete category (set to Inactive) if it has resources
     */
    public function softDeleteIfHasResources(): void
    {
        if (! $this->canBeDeleted()) {
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
        return 'library_categories';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Library Categories';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-folder';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Archives';
    }
}
