<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Parent/guardian profile (table `parents`). Named ParentProfile because
 * `Parent` is a reserved word in PHP. Not school-scoped. Notification channel
 * preferences live on the linked user — they belong to the person, not the
 * parent hat.
 */
#[Fillable([
    'user_id', 'first_name', 'father_name', 'grandfather_name',
    'gender', 'date_of_birth', 'nationality', 'occupation', 'employer',
    'secondary_phone', 'photo_path',
    'country', 'state', 'city', 'sub_city', 'woreda', 'house_no',
    'is_verified', 'verified_at', 'profile_completed_at',
])]
#[Hidden(['search_text'])]
class ParentProfile extends Model
{
    use SoftDeletes;

    protected $table = 'parents';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<StudentGuardian, $this>
     */
    public function guardianships(): HasMany
    {
        return $this->hasMany(StudentGuardian::class, 'parent_id');
    }

    /**
     * @return HasMany<ParentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ParentAttachment::class, 'parent_id');
    }

    /**
     * Normalise the secondary contact phone to the canonical local form on write.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function secondaryPhone(): Attribute
    {
        return Attribute::set(
            fn (?string $value): ?string => $value === null ? null : (PhoneNumber::normalize($value) ?? $value),
        );
    }

    /**
     * Signed URL for the guardian photo — private files, never direct links.
     * A real ACCESSOR (`$parent->photo_url`), matching Student/Employee.
     *
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo_path !== null ? s3Url($this->photo_path) : null);
    }

    /**
     * Every (school_id, branch_id) scope in which school staff may administer
     * this parent's profile: the union of the admin scopes of all linked
     * children. Mirrors Student::adminScopes() — a parent is a global person,
     * so staff authority always flows through a linked child.
     *
     * @return list<array{0: ?int, 1: ?int}>
     */
    public function adminScopes(): array
    {
        $scopes = [];

        foreach ($this->linkedStudents() as $student) {
            foreach ($student->adminScopes() as $scope) {
                $scopes[] = $scope;
            }
        }

        return array_values(array_unique($scopes, SORT_REGULAR));
    }

    /**
     * The scopes holding live custody of at least one linked child — the only
     * places that may mutate the parent's profile, photo, or files. Mirrors
     * Student::activeAdminScopes(): once every linked child has moved on, a
     * former school drops to the read-only archive lane.
     *
     * @return list<array{0: ?int, 1: ?int}>
     */
    public function activeAdminScopes(): array
    {
        $scopes = [];

        foreach ($this->linkedStudents() as $student) {
            foreach ($student->activeAdminScopes() as $scope) {
                $scopes[] = $scope;
            }
        }

        return array_values(array_unique($scopes, SORT_REGULAR));
    }

    /**
     * @return Collection<int, Student>
     */
    private function linkedStudents(): Collection
    {
        return Student::query()
            ->whereIn('id', $this->guardianships()->pluck('student_id'))
            ->get();
    }
}
