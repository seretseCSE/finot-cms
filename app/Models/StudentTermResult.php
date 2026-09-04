<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTermResult extends Model
{
    protected $fillable = [
        'member_id',
        'term_id',
        'batch_year_id',
        'class_id',
        'enrollment_id',
        'total',
        'average',
        'rank',
        'rank_of',
        'breakdown',
        'computed_at',
        'computed_by',
    ];

    protected $casts = [
        'total' => 'float',
        'average' => 'float',
        'rank' => 'integer',
        'rank_of' => 'integer',
        'breakdown' => 'array',
        'computed_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function batchYear(): BelongsTo
    {
        return $this->belongsTo(BatchYear::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }
}
