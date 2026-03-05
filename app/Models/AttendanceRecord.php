<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $table = 'attendance_records';

    protected $fillable = [
        'member_id',
        'event_type',
        'event_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
