<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use App\Models\Traits\ScopedByDepartment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends BaseModel
{
    use HasFactory, ScopedByDepartment, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'member_code',
        'member_type',
        'status',
        'member_since',
        'hr_notes',
        'title',
        'first_name',
        'father_name',
        'grandfather_name',
        'mother_name',
        'date_of_birth',
        'gender',
        'christian_name',
        'city',
        'sub_city',
        'woreda',
        'zone',
        'block',
        'neighborhood',
        'phone',
        'email',
        'emergency_contact_name',
        'emergency_contact_phone',
        'confession_father_name',
        'confession_father_phone',
        'spiritual_education_level',
        'special_talents',
        'family_size',
        'brothers_count',
        'sisters_count',
        'family_confession_father',
        'sunday_school_entry_year',
        'past_service_departments',
        'occupation_status',
        'employment_status',
        'company_name',
        'job_role',
        'company_address',
        'marital_status',
        'marriage_year',
        'spouse_name',
        'spouse_phone',
        'children_count',
        'photo',
        'consent_for_photography',
        'department_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'sunday_school_entry_year' => 'date',
        'marriage_year' => 'date',
        'member_since' => 'date',
        'consent_for_photography' => 'boolean',
        'family_size' => 'integer',
        'brothers_count' => 'integer',
        'sisters_count' => 'integer',
        'children_count' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($member) {
            if (empty($member->member_code)) {
                $member->member_code = static::generateMemberCode();
            }
        });
    }

    /**
     * Generate unique member code in M-000001 format
     */
    public static function generateMemberCode(): string
    {
        return \DB::transaction(function () {
            // Lock the table to prevent race conditions
            \DB::statement('SELECT id FROM members ORDER BY id DESC LIMIT 1 FOR UPDATE');
            
            $lastId = \DB::table('members')->max('id') ?? 0;
            $nextId = $lastId + 1;
            
            return 'M-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Get full name accessor
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->father_name} {$this->grandfather_name}";
    }

    /**
     * Get full name with title accessor
     */
    public function getFullNameWithTitleAttribute(): string
    {
        return "{$this->title} {$this->getFullNameAttribute()}";
    }

    /**
     * Get age accessor
     */
    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    /**
     * Get formatted phone accessor
     */
    public function getFormattedPhoneAttribute(): string
    {
        return $this->phone;
    }

    /**
     * Get address as string accessor
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->city,
            $this->sub_city,
            $this->woreda,
            $this->zone,
            $this->block,
            $this->neighborhood,
        ]);

        return implode(', ', $parts);
    }

    // Relationships

    /**
     * Get parent/guardian relationships
     */
    public function parentGuardians()
    {
        return $this->hasMany(MemberParentGuardian::class);
    }

    /**
     * Get linked parents through member_parent_guardians
     */
    public function parents()
    {
        return $this->hasManyThrough(
            ParentModel::class,
            MemberParentGuardian::class,
            'member_id',
            'id',
            'id',
            'parent_id'
        )->where('member_parent_guardians.is_external', false);
    }

    /**
     * Get member group assignments
     */
    public function groupAssignments()
    {
        return $this->hasMany(MemberGroupAssignment::class);
    }

    /**
     * Get current group assignment
     */
    public function currentGroupAssignment()
    {
        return $this->hasOne(MemberGroupAssignment::class)
            ->whereNull('effective_to')
            ->latest('effective_from');
    }

    /**
     * Get current group through assignment
     */
    public function currentGroup()
    {
        return $this->hasOneThrough(
            MemberGroup::class,
            MemberGroupAssignment::class,
            'group_id',
            'id',
            'id',
            'member_id'
        )->whereNull('member_group_assignments.effective_to');
    }

    /**
     * Get student enrollments
     */
    public function studentEnrollments()
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /**
     * Get contributions
     */
    public function contributions()
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * Get attendance records
     */
    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Get tour passengers (via phone matching)
     */
    public function tourPassengers()
    {
        return $this->hasMany(TourPassenger::class, 'phone', 'phone');
    }

    /**
     * Get department
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get children information
     */
    public function children()
    {
        return $this->hasMany(MemberChild::class);
    }

    /**
     * Get education history
     */
    public function educationHistory()
    {
        return $this->hasMany(MemberEducationHistory::class);
    }

    /**
     * Get current education
     */
    public function currentEducation()
    {
        return $this->hasOne(MemberEducationHistory::class)
            ->where('is_current', true);
    }

    /**
     * Get children names (for marital status)
     */
    public function childrenNames()
    {
        return $this->hasMany(MemberChildName::class);
    }

    // Scopes

    /**
     * Scope by member type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('member_type', $type);
    }

    /**
     * Scope by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope active members
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Scope kids members
     */
    public function scopeKids($query)
    {
        return $query->where('member_type', 'Kids');
    }

    /**
     * Scope youth members
     */
    public function scopeYouth($query)
    {
        return $query->where('member_type', 'Youth');
    }

    /**
     * Scope adult members
     */
    public function scopeAdult($query)
    {
        return $query->where('member_type', 'Adult');
    }

    /**
     * Get the resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'members';
    }

    /**
     * Get the navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Members / አባላት';
    }

    /**
     * Get the navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Membership';
    }
}
