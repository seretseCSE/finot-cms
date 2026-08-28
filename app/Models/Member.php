<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Enums\OccupationStatus;
use App\Models\Traits\HasAuditLog;
use App\Models\Traits\ScopedByDepartment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends BaseModel
{
    use HasAuditLog;
    use HasFactory;
    use ScopedByDepartment;
    use SoftDeletes;

    protected $fillable = [
        'member_code',
        'member_type',
        'status',
        'member_since',
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
        'monthly_contribution_amount',
    ];

    protected $casts = [
        'member_type' => MemberType::class,
        'status' => MemberStatus::class,
        'gender' => Gender::class,
        'marital_status' => MaritalStatus::class,
        'occupation_status' => OccupationStatus::class,
        'member_since' => 'date',
        'date_of_birth' => 'date',
        'sunday_school_entry_year' => 'date',
        'marriage_year' => 'date',
        'consent_for_photography' => 'boolean',
        'monthly_contribution_amount' => 'decimal:2',
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

            // Set member_since to current date if not provided
            if (empty($member->member_since)) {
                $member->member_since = now();
            }
        });
    }

    /**
     * Generate unique member code in M-000001 format
     */
    public static function generateMemberCode(): string
    {
        return \DB::transaction(function () {
            // Lock the table to prevent race conditions (skip for SQLite)
            if (\DB::getDriverName() !== 'sqlite') {
                \DB::statement('SELECT id FROM members ORDER BY id DESC LIMIT 1 FOR UPDATE');
            }

            $lastId = \DB::table('members')->max('id') ?? 0;
            $nextId = $lastId + 1;

            return config('finot.member_code_prefix', 'M-').str_pad($nextId, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Get full name accessor.
     *
     * @return string The full name
     */
    public function getFullNameAttribute(): string
    {
        // Build full name from actual name fields
        $parts = [];

        if ($this->first_name) {
            $parts[] = $this->first_name;
        }

        if ($this->father_name) {
            $parts[] = $this->father_name;
        }

        if ($this->grandfather_name) {
            $parts[] = $this->grandfather_name;
        }

        // If no name fields are available, fallback to member code
        if (empty($parts)) {
            return $this->member_code ?? 'Member '.$this->id;
        }

        return implode(' ', $parts);
    }

    /**
     * Get full name with title accessor.
     *
     * @return string The full name with title
     */
    public function getFullNameWithTitleAttribute(): string
    {
        return "{$this->title} {$this->getFullNameAttribute()}";
    }

    /**
     * Get age accessor.
     *
     * @return int The member's age
     */
    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    /**
     * Get formatted phone accessor.
     *
     * @return string The formatted phone number
     */
    public function getFormattedPhoneAttribute(): string
    {
        return $this->phone;
    }

    /**
     * Get address as string accessor.
     *
     * @return string The full address
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
     * Get parent/guardian relationships.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function parentGuardians()
    {
        return $this->hasMany(MemberParentGuardian::class);
    }

    /**
     * Get linked parents through member_parent_guardians.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
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
     * Get member group assignments.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function groupAssignments()
    {
        return $this->hasMany(MemberGroupAssignment::class);
    }

    /**
     * Get current group assignment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function currentGroupAssignment()
    {
        return $this->hasOne(MemberGroupAssignment::class)
            ->whereNull('effective_to')
            ->latest('effective_from');
    }

    /**
     * Get current group attribute.
     *
     * @return mixed The current group
     */
    public function getCurrentGroupAttribute()
    {
        return $this->currentGroupAssignment?->group;
    }

    /**
     * Get member group attribute (alias for currentGroup).
     *
     * @return mixed The member group
     */
    public function getMemberGroupAttribute()
    {
        return $this->currentGroup;
    }

    /**
     * Get student enrollments.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function studentEnrollments()
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function portalUser()
    {
        return $this->hasOne(User::class, 'member_id');
    }

    public function hasActiveEnrollment(): bool
    {
        return $this->studentEnrollments()
            ->where('status', 'Enrolled')
            ->whereNull('removed_at')
            ->exists();
    }

    /**
     * Get contributions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function contributions()
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * Get attendance records.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Get tour passengers (via phone matching).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tourPassengers()
    {
        return $this->hasMany(TourPassenger::class, 'phone', 'phone');
    }

    /**
     * Get department.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get children information.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(MemberChild::class);
    }

    /**
     * Get education history.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function educationHistory()
    {
        return $this->hasMany(MemberEducationHistory::class);
    }

    /**
     * Get current education.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function currentEducation()
    {
        return $this->hasOne(MemberEducationHistory::class)
            ->where('is_current', true);
    }

    /**
     * Get children names (for marital status).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function childrenNames()
    {
        return $this->hasMany(MemberChildName::class);
    }

    // Scopes

    /**
     * Scope by member type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder
     * @param string $type The member type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('member_type', $type);
    }

    /**
     * Scope by status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder
     * @param string $status The member status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope active members.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Scope kids members.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeKids($query)
    {
        return $query->where('member_type', 'Kids');
    }

    /**
     * Scope youth members.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeYouth($query)
    {
        return $query->where('member_type', 'Youth');
    }

    /**
     * Scope adult members.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAdult($query)
    {
        return $query->where('member_type', 'Adult');
    }

    /**
     * Get the resource name for permissions.
     *
     * @return string The resource name
     */
    public static function getResourceName(): string
    {
        return 'members';
    }

    /**
     * Get the navigation label for the resource.
     *
     * @return string The navigation label
     */
    public static function getNavigationLabel(): string
    {
        return 'Members';
    }

    /**
     * Get the navigation icon for the resource.
     *
     * @return string|null The navigation icon
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    /**
     * Get the navigation group for the resource.
     *
     * @return string|null The navigation group
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Membership';
    }
}
