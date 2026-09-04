<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectOffering extends Model
{
    protected $fillable = [
        'batch_year_id',
        'term_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'max_score',
        'created_by',
    ];

    protected $casts = [
        'max_score' => 'integer',
    ];

    public function batchYear(): BelongsTo
    {
        return $this->belongsTo(BatchYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function getPermissionName(string $action): string
    {
        return match ($action) {
            'view' => 'results.view',
            'create', 'update' => 'results.manage',
            'delete' => 'results.manage',
            default => 'results.'.$action,
        };
    }
}
