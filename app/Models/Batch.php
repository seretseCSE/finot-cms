<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = [
        'name',
        'start_year',
        'tenure_years',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_year' => 'integer',
        'tenure_years' => 'integer',
    ];

    public function years(): HasMany
    {
        return $this->hasMany(BatchYear::class)->orderBy('program_year');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public static function getPermissionName(string $action): string
    {
        return match ($action) {
            'view' => 'batches.view',
            'create' => 'batches.create',
            'update' => 'batches.update',
            'delete' => 'batches.delete',
            default => 'batches.'.$action,
        };
    }

    public static function getResourceName(): string
    {
        return 'batches';
    }
}
