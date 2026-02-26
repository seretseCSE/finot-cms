<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Member;
use App\Models\User;

class Teacher extends BaseModel
{
    use HasFactory, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'member_id',
        'teacher_code',
        'full_name',
        'phone',
        'qualifications',
        'status',
        'created_by',
    ];

    protected $casts = [
    ];

    protected static function booted(): void
    {
        static::creating(function (Teacher $teacher): void {
            if (blank($teacher->teacher_code)) {
                $teacher->teacher_code = static::generateTeacherCode();
            }
        });
    }

    protected static function generateTeacherCode(): string
    {
        $max = static::query()
            ->select(DB::raw("MAX(CAST(SUBSTRING(teacher_code, 3) AS UNSIGNED)) as max_code"))
            ->value('max_code');

        $next = ((int) $max) + 1;

        return 'T-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeAttribute(): string
    {
        return filled($this->member_id) ? 'Member' : 'External';
    }

    public function canDelete(): bool
    {
        $hasAssignments = Schema::hasTable('teacher_assignments')
            ? DB::table('teacher_assignments')->where('teacher_id', $this->getKey())->exists()
            : false;

        $hasAttendance = Schema::hasTable('attendance_sessions')
            ? DB::table('attendance_sessions')->where('teacher_id', $this->getKey())->exists()
            : false;

        return ! $hasAssignments && ! $hasAttendance;
    }

    /**
     * Get the resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'teachers';
    }

    /**
     * Get the navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Teachers';
    }

    /**
     * Get the navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
