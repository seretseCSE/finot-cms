<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'facility_id', 'department_id', 'booked_by', 'purpose',
        'start_at', 'end_at', 'status', 'recurrence_rule', 'class_id', 'rehearsal_id',
    ];

    protected $casts = [
        'status' => BookingStatus::class,
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public static function getPermissionName(string $action): string
    {
        return match ($action) {
            'view' => 'facilities.view',
            'create' => 'facilities.book',
            'update', 'delete' => 'facilities.manage',
            default => 'facilities.'.$action,
        };
    }
}
