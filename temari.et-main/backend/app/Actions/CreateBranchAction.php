<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\School;
use App\Models\SchoolProgram;
use App\Support\GradeOffering;
use Illuminate\Support\Facades\DB;

/**
 * Creates a branch under a school and provisions its Director (branch-scoped).
 */
class CreateBranchAction
{
    public function __construct(private readonly ProvisionContactUserAction $provisionContact) {}

    /**
     * @param  array{
     *     name: string,
     *     code: string,
     *     country?: ?string,
     *     state?: ?string,
     *     city?: ?string,
     *     sub_city?: ?string,
     *     woreda?: ?string,
     *     house_no?: ?string,
     *     phone?: ?string,
     *     longitude?: ?float,
     *     latitude?: ?float,
     *     is_active?: bool,
     *     programs?: list<array{type: string, grade_level_ids?: list<int>|null}>,
     *     director_name?: ?string,
     *     director_phone?: ?string,
     * }  $data
     */
    public function execute(School $school, array $data): Branch
    {
        return DB::transaction(function () use ($school, $data): Branch {
            $branch = $school->branches()->create([
                'name' => $data['name'],
                'code' => $data['code'],
                'country' => $data['country'] ?? 'Ethiopia',
                'state' => $data['state'] ?? null,
                'city' => $data['city'] ?? null,
                'sub_city' => $data['sub_city'] ?? null,
                'woreda' => $data['woreda'] ?? null,
                'house_no' => $data['house_no'] ?? null,
                'phone' => $data['phone'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // The principal picks which programs the branch runs and which
            // grades each is offered in (any non-empty set — Regular is only
            // the default suggestion, not forced). When the caller sends none,
            // fall back to Regular × all grades so a branch never exists
            // programless: enrollments anchor to a program, which is what
            // makes dual enrollment possible (ADR-011).
            $programs = $data['programs'] ?? [];
            if ($programs === []) {
                $programs = [['type' => SchoolProgram::TYPE_REGULAR]];
            }
            GradeOffering::sync($branch, $programs);

            if (! empty($data['director_name']) && ! empty($data['director_phone'])) {
                $this->provisionContact->execute(
                    name: $data['director_name'],
                    phone: $data['director_phone'],
                    role: Role::Director,
                    school: $school,
                    branch: $branch,
                );
            }

            return $branch;
        });
    }
}
