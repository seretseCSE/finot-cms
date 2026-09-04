<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The attendance-alert ledger: dedupe key + SMS/email meter. Never deleted.
 */
#[Fillable([
    'school_id', 'branch_id', 'student_id', 'guardian_user_id', 'date',
    'status', 'channel', 'recipient', 'result',
])]
class AttendanceNotificationLog extends Model
{
    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function guardianUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }
}
