<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\ClassModel;
use App\Models\StudentEnrollment;
use App\Models\Tour;
use App\Models\TourAttendanceSession;
use App\Models\TourPassenger;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating attendance test data...');

        // Get admin user for created_by
        $adminUser = User::where('email', 'admin@finot.org')->first();
        $createdBy = $adminUser?->id ?? User::first()?->id ?? 1;

        // Use existing departments
        $departments = Department::limit(4)->get();
        if ($departments->count() < 4) {
            $departments = collect($this->createDepartments());
        }

        // Use existing academic years
        $activeYear = AcademicYear::where('status', 'Active')->first();
        if (!$activeYear) {
            $this->createAcademicYears();
            $activeYear = AcademicYear::where('status', 'Active')->first();
        }

        // Use existing members
        $this->command->info('Using existing members for testing...');
        $members = Member::all();

        // Create classes 1, 2, 3 if they don't exist
        $classes = ClassModel::all();
        if ($classes->count() < 3) {
            $classes = collect($this->createSchoolClasses($createdBy));
        }

        // Assign kids to classes
        $this->assignKidsToClasses($members->all(), $classes->all(), $createdBy);

        $rehearsals = $this->createRehearsals($createdBy);
        $this->createRehearsalAttendance($rehearsals, $members->all(), $createdBy);

        $tours = $this->createTours($createdBy);
        $this->createTourPassengers($tours, $members->all());
        $this->createTourAttendanceSessions($tours, $createdBy);

        $this->command->info('Attendance test data created successfully!');
        $this->displayTestCredentials();
    }

    private function clearTestData(): void
    {
        $this->command->info('Clearing existing test data...');

        // Clear test users first
        User::whereIn('email', [
            'edu_monitor@test.com',
            'edu_head@test.com',
            'worship_monitor@test.com',
            'tour_head@test.com',
            'admin@test.com',
            'superadmin@test.com'
        ])->delete();

        // Clear test members
        Member::where('member_code', 'like', 'TEST%')->delete();

        DB::table('tour_attendance_sessions')->delete();
        DB::table('tour_attendance')->delete();
        DB::table('tour_passengers')->delete();
        DB::table('tours')->delete();
        DB::table('rehearsal_attendance')->delete();
        DB::table('rehearsals')->delete();
        DB::table('student_attendances')->delete();
        DB::table('teacher_attendance')->delete();
        DB::table('attendance_sessions')->delete();
        DB::table('student_enrollments')->delete();
        DB::table('teacher_assignments')->delete();
        DB::table('teachers')->delete();
        DB::table('classes')->delete();
        DB::table('academic_years')->delete();

        // Keep members, departments, and users for other functionality
    }

    private function createDepartments(): void
    {
        $departments = [
            ['name_en' => 'Worship', 'name_am' => 'አምልጮች', 'code' => 'WORSHIP', 'is_active' => true],
            ['name_en' => 'Education', 'name_am' => 'ትምህርት', 'code' => 'EDU', 'is_active' => true],
            ['name_en' => 'Media', 'name_am' => 'ሚዲያ', 'code' => 'MEDIA', 'is_active' => true],
            ['name_en' => 'Charity', 'name_am' => 'ቻሪቲ', 'code' => 'CHARITY', 'is_active' => true],
        ];

        foreach ($departments as $dept) {
            Department::create($dept);
        }

        $this->command->info('Created ' . count($departments) . ' departments');
    }

    private function createAcademicYears(): void
    {
        $years = [
            ['name' => '2024-2025', 'start_date' => '2024-09-01', 'end_date' => '2025-06-30', 'status' => 'Active'],
            ['name' => '2025-2026', 'start_date' => '2025-09-01', 'end_date' => '2026-06-30', 'status' => 'Draft'],
        ];

        foreach ($years as $year) {
            AcademicYear::create(array_merge($year, ['created_by' => $createdBy]));
        }

        $this->command->info('Created ' . count($years) . ' academic years');
    }


    private function createSchoolClasses($createdBy): array
    {
        $classes = [
            ['name' => 'Class 1', 'description' => 'First level education for kids', 'is_active' => true],
            ['name' => 'Class 2', 'description' => 'Second level education for kids', 'is_active' => true],
            ['name' => 'Class 3', 'description' => 'Third level education for kids', 'is_active' => true],
        ];

        $createdClasses = [];
        foreach ($classes as $classData) {
            $class = ClassModel::create(array_merge($classData, ['created_by' => $createdBy]));
            $createdClasses[] = $class;
        }

        $this->command->info('Created ' . count($createdClasses) . ' school classes');
        return $createdClasses;
    }

    private function assignKidsToClasses(array $members, array $classes, $createdBy): void
    {
        $kids = collect($members)->filter(fn ($member) => $member->member_type === 'Kids');
        $academicYear = AcademicYear::where('status', 'Active')->first();

        if ($kids->count() === 0) {
            $this->command->info('No kids found to assign to classes');
            return;
        }

        $assignedCount = 0;
        foreach ($kids as $index => $kid) {
            $classIndex = $index % count($classes); // Round-robin assignment
            $class = $classes[$classIndex];

            // Check if already enrolled
            $existingEnrollment = StudentEnrollment::where('member_id', $kid->id)
                ->where('academic_year_id', $academicYear->id)
                ->first();

            if (!$existingEnrollment) {
                StudentEnrollment::create([
                    'member_id' => $kid->id,
                    'class_id' => $class->id,
                    'academic_year_id' => $academicYear->id,
                    'enrolled_date' => now()->subMonths(rand(1, 6))->toDateString(),
                    'status' => 'Enrolled',
                    'enrolled_by' => $createdBy,
                ]);
                $assignedCount++;
            }
        }

        $this->command->info("Assigned {$assignedCount} kids to Class 1, 2, and 3");
    }

    private function createRehearsals($createdBy): array
    {
        $rehearsals = [];

        $rehearsalData = [
            [
                'date_time' => now()->addDays(2),
                'location' => 'Church Hall',
                'status' => 'Scheduled',
                'recurrence_type' => 'Weekly',
                'created_by' => $createdBy,
            ],
            [
                'date_time' => now()->addDays(4),
                'location' => 'Main Sanctuary',
                'status' => 'Scheduled',
                'recurrence_type' => 'Weekly',
                'created_by' => $createdBy,
            ],
            [
                'date_time' => now()->addDays(6),
                'location' => 'Youth Center',
                'status' => 'Scheduled',
                'recurrence_type' => 'Weekly',
                'created_by' => $createdBy,
            ],
        ];

        foreach ($rehearsalData as $data) {
            $rehearsals[] = Rehearsal::create($data);
        }

        $this->command->info('Created 3 rehearsals');
        return $rehearsals;
    }

    private function createRehearsalAttendance(array $rehearsals, array $members, $createdBy): void
    {
        foreach ($rehearsals as $rehearsal) {
            // Add some members to rehearsal attendance
            foreach (array_slice($members, 0, 10) as $member) {
                \DB::table('rehearsal_attendance')->insert([
                    'rehearsal_id' => $rehearsal->id,
                    'member_id' => $member->id,
                    'status' => rand(0, 4) === 0 ? 'Absent' : 'Present',
                    'marked_by' => $createdBy,
                    'marked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Created rehearsal attendance records');
    }

    private function createTours($createdBy): array
    {
        $tours = [
            [
                'place' => 'Lake Tana',
                'description' => 'Spiritual retreat to Lake Tana',
                'tour_date' => now()->addMonths(2),
                'start_time' => '08:00',
                'cost_per_person' => 500.00,
                'registration_deadline' => now()->addMonth(),
                'max_capacity' => 50,
                'status' => 'Published',
            ],
            [
                'place' => 'Axum',
                'description' => 'Historical tour to Axum',
                'tour_date' => now()->addMonths(3),
                'start_time' => '06:00',
                'cost_per_person' => 750.00,
                'registration_deadline' => now()->addMonths(1),
                'max_capacity' => 30,
                'status' => 'Published',
            ],
        ];

        $createdTours = [];
        foreach ($tours as $tourData) {
            $tour = Tour::create(array_merge($tourData, ['created_by' => $createdBy]));
            $createdTours[] = $tour;
        }

        $this->command->info('Created ' . count($createdTours) . ' tours');
        return $createdTours;
    }

    private function createTourPassengers(array $tours, array $members): void
    {
        // Find the active tour (published and upcoming)
        $activeTour = collect($tours)->first(function ($tour) {
            return $tour->status === 'Published' && $tour->tour_date > now();
        });

        if (!$activeTour) {
            $this->command->info('No active tour found for passengers');
            return;
        }

        // Clear all existing tour passengers to avoid conflicts
        TourPassenger::query()->delete();

        $creator = User::first();
        $passengerCount = 0;

        // Create 50 passengers
        $usedPhoneNumbers = [];
        for ($i = 1; $i <= 50; $i++) {
            $member = $members[array_rand($members)] ?? null;

            // Generate unique phone number
            do {
                $phone = '9' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            } while (in_array($phone, $usedPhoneNumbers));
            $usedPhoneNumbers[] = $phone;

            TourPassenger::create([
                'tour_id' => $activeTour->id,
                'passenger_code' => 'TPA' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'full_name' => $member ? $member->full_name : "Passenger {$i}",
                'phone' => $phone,
                'passenger_count' => rand(1, 4),
                'registration_type' => rand(1, 100) <= 70 ? 'Internal' : 'Public',
                'status' => 'Confirmed',
                'registration_date' => now()->subDays(rand(1, 30)),
                'registered_by' => $creator->id,
            ]);
            $passengerCount++;
        }

        $this->command->info("Created {$passengerCount} passengers for active tour: {$activeTour->place}");
    }

    private function createTourAttendanceSessions(array $tours, $createdBy): void
    {
        foreach ($tours as $tour) {
            TourAttendanceSession::create([
                'tour_id' => $tour->id,
                'session_date' => $tour->tour_date,
                'status' => 'Open',
                'created_by' => $createdBy,
            ]);
        }

        $this->command->info('Created tour attendance sessions');
    }

    private function displayTestCredentials(): void
    {
        $memberCount = Member::count();
        $kidsCount = Member::where('member_type', 'Kids')->count();
        $tourPassengerCount = TourPassenger::count();

        $this->command->info("\n=== ATTENDANCE TEST DATA SUMMARY ===");
        $this->command->info("Total Members: {$memberCount}");
        $this->command->info("Kids Members: {$kidsCount}");
        $this->command->info("School Classes: 3 (Class 1, Class 2, Class 3)");
        $this->command->info("Tour Passengers: {$tourPassengerCount}");
        $this->command->info("Rehearsals: 3");
        $this->command->info("Tours: 2");
        $this->command->info("\n=== FEATURES READY FOR TESTING ===");
        $this->command->info("✓ Kids assigned to Class 1, 2, and 3");
        $this->command->info("✓ 50 passengers for active tour");
        $this->command->info("✓ Rehearsal attendance data");
        $this->command->info("✓ Tour attendance sessions");
        $this->command->info("\n=== NOTE ===");
        $this->command->info("Use existing users from UserSeeder for testing attendance features.");
        $this->command->info("Kids are automatically enrolled in classes for attendance testing.\n");
    }
}
