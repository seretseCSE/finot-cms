<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    protected $fillable = [
        'academic_year_id', 'name', 'starts_on', 'ends_on', 'is_active',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function marklists(): HasMany
    {
        return $this->hasMany(Marklist::class);
    }

    public static function getPermissionName(string $action): string
    {
        return match ($action) {
            'view' => 'academic_years.view',
            'create', 'update' => 'academic_years.update',
            'delete' => 'academic_years.delete',
            default => 'academic_years.'.$action,
        };
    }
}
