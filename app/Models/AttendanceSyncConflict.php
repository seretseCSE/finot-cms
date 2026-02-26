<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceSyncConflict extends BaseModel
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'student_id',
        'session_id',
        'first_user_id',
        'first_value',
        'first_synced_at',
        'second_user_id',
        'second_value',
        'second_synced_at',
        'winner_value',
    ];

    protected $casts = [
        'first_synced_at' => 'datetime',
        'second_synced_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Member::class, 'student_id');
    }

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function firstUser()
    {
        return $this->belongsTo(User::class, 'first_user_id');
    }

    public function secondUser()
    {
        return $this->belongsTo(User::class, 'second_user_id');
    }
}
