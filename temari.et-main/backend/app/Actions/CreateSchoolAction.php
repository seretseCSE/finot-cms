<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\School;
use App\Models\SchoolDirectoryEntry;
use Illuminate\Support\Facades\DB;

/**
 * Creates a school and provisions its legal contact (Principal) and optional
 * technical contact (School Admin). Both are school-scoped — no branch yet.
 */
class CreateSchoolAction
{
    public function __construct(private readonly ProvisionContactUserAction $provisionContact) {}

    /**
     * @param  array{
     *     name: string,
     *     phone?: ?string,
     *     address?: ?string,
     *     principal_name: string,
     *     principal_phone: string,
     *     technical_name?: ?string,
     *     technical_phone?: ?string,
     *     is_active?: bool,
     * }  $data
     */
    public function execute(array $data): School
    {
        return DB::transaction(function () use ($data): School {
            $school = School::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Every Temari-hosted school is findable in the platform-wide
            // school directory (used as "previous school" by other schools).
            SchoolDirectoryEntry::updateOrCreate(
                ['school_id' => $school->id],
                ['name' => $school->name, 'is_verified' => true],
            );

            $this->provisionContact->execute(
                name: $data['principal_name'],
                phone: $data['principal_phone'],
                role: Role::Principal,
                school: $school,
            );

            if (! empty($data['technical_name']) && ! empty($data['technical_phone'])) {
                $this->provisionContact->execute(
                    name: $data['technical_name'],
                    phone: $data['technical_phone'],
                    role: Role::SchoolAdmin,
                    school: $school,
                );
            }

            return $school->refresh();
        });
    }
}
