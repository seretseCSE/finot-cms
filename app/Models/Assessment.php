<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $fillable = [
        'subject_offering_id',
        'name',
        'max_score',
        'weight',
        'sort_order',
        'is_open',
        'created_by',
    ];

    protected $casts = [
        'max_score' => 'integer',
        'weight' => 'float',
        'sort_order' => 'integer',
        'is_open' => 'boolean',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(SubjectOffering::class, 'subject_offering_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function getPermissionName(string $action): string
    {
        return match ($action) {
            'view' => 'results.view',
            'create', 'update', 'delete' => 'results.manage',
            default => 'results.'.$action,
        };
    }
}
