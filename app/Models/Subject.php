<?php

namespace App\Models;

use App\Models\Traits\GeneratesAutoId;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends BaseModel
{
    use GeneratesAutoId;
    use HasAuditLog;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'subjects';
    }

    /**
     * Get the navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Subjects';
    }

    /**
     * Get the navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-book-open';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }
}
