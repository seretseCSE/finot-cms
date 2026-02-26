<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'title',
        'file_path',
        'file_size_kb',
        'file_type',
        'description',
        'tags',
        'document_date',
        'visibility',
        'department_id',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size_kb' => 'integer',
        'document_date' => 'date',
    ];

    protected $dates = [
        'document_date',
    ];

    public function department()
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
     * Get formatted document date in Ethiopian
     */
    public function getEthiopianDocumentDateAttribute(): string
    {
        if (!$this->document_date) {
            return '';
        }

        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->document_date)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->document_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->document_date)['year'];
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
     * Get file type icon
     */
    public function getFileTypeIconAttribute(): string
    {
        return match(strtolower($this->file_type)) {
            'pdf' => 'heroicon-o-document-text',
            'docx' => 'heroicon-o-document',
            'xlsx' => 'heroicon-o-chart-bar',
            'pptx' => 'heroicon-o-presentation-chart-bar',
            'jpg', 'png' => 'heroicon-o-photo',
            default => 'heroicon-o-document',
        };
    }

    /**
     * Check if user can view this document
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
        return 'documents';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Documents';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-document';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Archives';
    }
}
