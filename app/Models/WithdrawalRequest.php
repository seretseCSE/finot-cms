<?php

namespace App\Models;

use App\Enums\WithdrawalRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'member_id', 'enrollment_id', 'class_id', 'requested_by', 'reason', 'destination',
        'requested_at', 'status', 'education_decided_by', 'education_decided_at',
        'finalized_by', 'finalized_at', 'effective_date', 'guardian_acknowledged',
    ];

    protected $casts = [
        'status' => WithdrawalRequestStatus::class,
        'requested_at' => 'datetime',
        'education_decided_at' => 'datetime',
        'finalized_at' => 'datetime',
        'effective_date' => 'date',
        'guardian_acknowledged' => 'boolean',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function educationDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'education_decided_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
