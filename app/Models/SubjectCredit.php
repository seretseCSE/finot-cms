<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectCredit extends Model
{
    protected $fillable = [
        'member_id',
        'subject_id',
        'source_batch_year_id',
        'source_term_id',
        'score',
        'max_score',
        'status',
        'created_by',
    ];

    protected $casts = [
        'score' => 'float',
        'max_score' => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function sourceBatchYear(): BelongsTo
    {
        return $this->belongsTo(BatchYear::class, 'source_batch_year_id');
    }

    public function sourceTerm(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'source_term_id');
    }
}
