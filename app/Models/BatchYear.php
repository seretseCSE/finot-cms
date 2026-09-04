<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchYear extends Model
{
    protected $fillable = [
        'batch_id',
        'program_year',
        'name',
        'status',
    ];

    protected $casts = [
        'program_year' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function offerings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public static function getPermissionName(string $action): string
    {
        return match ($action) {
            'view' => 'batches.view',
            'create', 'update' => 'batches.update',
            'delete' => 'batches.delete',
            default => 'batches.'.$action,
        };
    }
}
