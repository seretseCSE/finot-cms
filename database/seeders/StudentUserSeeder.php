<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Member;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Identity\ProvisionStudentUser;
use Illuminate\Database\Seeder;

class StudentUserSeeder extends Seeder
{
    public function run(): void
    {
        $member = Member::query()
            ->where('member_code', 'M-000001')
            ->orWhere('member_type', 'Kids')
            ->orderBy('id')
            ->first();

        if (! $member) {
            $this->command?->warn('No youth member found. Run MemberSeeder first.');

            return;
        }

        $educationHead = User::where('email', 'education_head@finot.org')->first()
            ?? User::first();

        $academicYear = AcademicYear::query()
            ->where('status', 'Active')
            ->orderByDesc('start_date')
            ->first();

        if (! $academicYear) {
            $academicYear = AcademicYear::create([
                'name' => '2024-2025',
                'start_date' => '2024-09-01',
                'end_date' => '2025-07-31',
                'status' => 'Active',
                'phase' => 'current',
                'created_by' => $educationHead?->id,
            ]);
        }

        $class = ClassModel::query()->where('is_active', true)->orderBy('id')->first();

        if (! $class) {
            $this->command?->warn('No active class found. Run ClassSeeder first.');

            return;
        }

        StudentEnrollment::query()->updateOrCreate(
            [
                'member_id' => $member->id,
                'academic_year_id' => $academicYear->id,
            ],
            [
                'class_id' => $class->id,
                'enrolled_date' => now()->toDateString(),
                'status' => 'Enrolled',
                'enrolled_by' => $educationHead?->id,
            ]
        );

        $user = app(ProvisionStudentUser::class)->sync($member->fresh());

        if ($user) {
            $this->command?->info("Provisioned student user for {$member->full_name} (phone: {$user->phone}).");
        }
    }
}
