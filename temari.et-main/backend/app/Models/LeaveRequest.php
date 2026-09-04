<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_id', 'branch_id', 'employee_id', 'leave_type_id',
    'start_date', 'end_date', 'days', 'is_half_day', 'reason',
    'status', 'requested_by', 'decided_by', 'decided_at', 'decision_note',
])]
class LeaveRequest extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'float',
            'is_half_day' => 'boolean',
            'status' => LeaveRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === LeaveRequestStatus::Pending;
    }

    /**
     * Approved requests overlapping the given date window.
     *
     * @param  Builder<LeaveRequest>  $query
     * @return Builder<LeaveRequest>
     */
    public function scopeApprovedOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('status', LeaveRequestStatus::Approved->value)
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
