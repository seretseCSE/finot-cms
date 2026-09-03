<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingScaleBand extends Model
{
    protected $fillable = [
        'grading_scale_id',
        'label',
        'min_score',
        'max_score',
        'sort_order',
    ];

    protected $casts = [
        'min_score' => 'integer',
        'max_score' => 'integer',
        'sort_order' => 'integer',
    ];

    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class, 'grading_scale_id');
    }

    public function contains(float $percent): bool
    {
        return $percent >= $this->min_score && $percent <= $this->max_score;
    }
}
