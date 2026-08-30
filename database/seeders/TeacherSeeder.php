<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeacherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing teachers
        DB::table('teachers')->delete();

        // Reset auto-increment
        DB::statement('ALTER TABLE teachers AUTO_INCREMENT = 1');

        // Get some members to convert to teachers
        $members = DB::table('members')
            ->where('status', 'Active')
            ->where('member_type', 'Adult')
            ->limit(20)
            ->get();

        // Get admin user for created_by
        $adminUser = User::where('email', 'admin@finot.org')->first();
        $createdBy = $adminUser?->id ?? User::first()?->id ?? 1;

        $teachers = [];

        // Create teachers from members
        $teacherCounter = 1;
        foreach ($members as $member) {
            $teachers[] = [
                'member_id' => $member->id,
                'teacher_code' => 'T-' . str_pad((string)$teacherCounter, 6, '0', STR_PAD_LEFT),
                'full_name' => trim("{$member->first_name} {$member->father_name} {$member->grandfather_name}"),
                'phone' => $member->phone,
                'qualifications' => $this->generateQualifications(),
                'status' => 'Active',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $teacherCounter++;
        }

        // Add some external teachers
        $externalTeachers = [
            [
                'member_id' => null,
                'teacher_code' => 'T-' . str_pad((string)($teacherCounter), 6, '0', STR_PAD_LEFT),
                'full_name' => 'Dr. Alemayehu Tesfaye',
                'phone' => '+251911234567',
                'qualifications' => 'PhD in Theology, 15 years teaching experience',
                'status' => 'Active',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'member_id' => null,
                'teacher_code' => 'T-' . str_pad((string)($teacherCounter + 1), 6, '0', STR_PAD_LEFT),
                'full_name' => 'Prof. Tigist Hailemariam',
                'phone' => '+251922345678',
                'qualifications' => 'Masters in Education, 10 years teaching experience',
                'status' => 'Active',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'member_id' => null,
                'teacher_code' => 'T-' . str_pad((string)($teacherCounter + 2), 6, '0', STR_PAD_LEFT),
                'full_name' => 'Solomon Berhane',
                'phone' => '+251933456789',
                'qualifications' => 'BA in Religious Studies, 8 years teaching experience',
                'status' => 'Active',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'member_id' => null,
                'teacher_code' => 'T-' . str_pad((string)($teacherCounter + 3), 6, '0', STR_PAD_LEFT),
                'full_name' => 'Wondimu Fikre',
                'phone' => '+251944567890',
                'qualifications' => 'Diploma in Teaching, 5 years teaching experience',
                'status' => 'Active',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'member_id' => null,
                'teacher_code' => 'T-' . str_pad((string)($teacherCounter + 4), 6, '0', STR_PAD_LEFT),
                'full_name' => 'Hanna Samuel',
                'phone' => '+251955678901',
                'qualifications' => 'BSc in Education, 6 years teaching experience',
                'status' => 'Active',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        $teacherCounter += 5;

        $allTeachers = array_merge($teachers, $externalTeachers);

        DB::table('teachers')->insert($allTeachers);

        $this->command->info('Seeded ' . count($allTeachers) . ' teachers successfully (' . count($teachers) . ' from members, ' . count($externalTeachers) . ' external).');
    }

    private function generateQualifications(): string
    {
        $qualifications = [
            'BA in Education, 5 years teaching experience',
            'Masters in Theology, 8 years teaching experience',
            'Diploma in Teaching, 3 years teaching experience',
            'PhD in Religious Studies, 12 years teaching experience',
            'Certificate in Education, 10 years teaching experience',
            'MA in Educational Leadership, 7 years teaching experience',
            'BSc in Education, 4 years teaching experience',
            'Advanced Teaching Certificate, 6 years teaching experience',
            'Religious Studies Major, 5 years teaching experience',
            'Early Childhood Education, 8 years teaching experience',
        ];

        return $qualifications[array_rand($qualifications)];
    }
}
