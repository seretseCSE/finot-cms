<?php

namespace App\Models;

use App\Enums\RubricScore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarklistItem extends Model
{
    protected $fillable = [
        'marklist_id', 'member_id', 'conduct', 'memorization', 'participation', 'remarks', 'recorded_by',
    ];

    protected $casts = [
        'conduct' => RubricScore::class,
        'memorization' => RubricScore::class,
        'participation' => RubricScore::class,
    ];

    public function marklist(): BelongsTo
    {
        return $this->belongsTo(Marklist::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
