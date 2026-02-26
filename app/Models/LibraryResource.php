<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryResource extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'title',
        'file_path',
        'category_id',
        'subcategory_id',
        'description',
        'is_featured',
        'is_active',
        'file_size_kb',
        'uploaded_by',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'file_size_kb' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(LibrarySubcategory::class);
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
        return asset('storage/library/' . $this->file_path);
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
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'library_resources';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Library Resources';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document-text';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Archives';
    }
}
