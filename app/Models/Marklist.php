<?php

namespace App\Models;

use App\Enums\MarklistStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marklist extends Model
{
    protected $fillable = [
        'class_id', 'term_id', 'subject_id', 'status',
        'submitted_at', 'submitted_by', 'approved_at', 'approved_by',
        'assisted_by', 'assisted_at', 'assist_reason', 'remarks',
    ];

    protected $casts = [
        'status' => MarklistStatus::class,
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'assisted_at' => 'datetime',
    ];

    public static function getResourceName(): string
    {
        return 'results';
    }

    public static function getPermissionName(string $action): string
    {
        return match ($action) {
            'view' => 'results.view',
            'create', 'update' => 'results.record',
            default => 'results.'.$action,
        };
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarklistItem::class);
    }

    public function assistedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assisted_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
