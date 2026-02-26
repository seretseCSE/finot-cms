<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibrarySubcategory extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'category_id',
        'name',
        'display_order',
        'status',
        'created_by',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LibraryResource::class);
    }

    public function activeResources(): HasMany
    {
        return $this->hasMany(LibraryResource::class)->where('is_active', true);
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'library_subcategories';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Library Subcategories';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-folder-open';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Archives';
    }
}
