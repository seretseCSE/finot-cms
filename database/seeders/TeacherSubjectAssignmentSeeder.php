<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\Subject;
use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeacherSubjectAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin user for created_by
        $adminUser = User::where('email', 'admin@finotetsidik.org')->first();
        $createdBy = $adminUser ? $adminUser->id : User::first()->id;

        // Clear existing teacher assignments
        DB::table('teacher_assignments')->delete();
        DB::statement('ALTER TABLE teacher_assignments AUTO_INCREMENT = 1');

        // Create teachers if none exist
        if (Teacher::count() === 0) {
            $this->command->warn('No teachers found. Creating sample teachers...');
            $this->createSampleTeachers();
        }

        // Create subjects if none exist
        if (Subject::count() === 0) {
            $this->command->warn('No subjects found. Creating sample subjects...');
            $this->createSampleSubjects();
        }

        // Get all active teachers and subjects
        $teachers = Teacher::where('status', 'Active')->get();
        $subjects = Subject::where('is_active', true)->get();

        if ($teachers->isEmpty()) {
            $this->command->error('No active teachers found even after creating samples. Aborting.');
            return;
        }

        if ($subjects->isEmpty()) {
            $this->command->error('No active subjects found even after creating samples. Aborting.');
            return;
        }

        $this->command->info("Found {$teachers->count()} active teachers and {$subjects->count()} active subjects.");

        // Create a default class if none exists
        $defaultClass = DB::table('classes')->first();
        if (!$defaultClass) {
            $defaultClassId = DB::table('classes')->insertGetId([
                'name' => 'General Studies',
                'description' => 'Default class for teacher assignments',
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $defaultClassId = $defaultClass->id;
        }

        // Get or create current academic year
        $academicYear = AcademicYear::where('status', 'Active')->first();
        if (!$academicYear) {
            $academicYear = AcademicYear::create([
                'name' => '2024-2025',
                'start_date' => '2024-09-01',
                'end_date' => '2025-07-31',
                'status' => 'Active',
                'created_by' => $createdBy,
            ]);
        }

        $assignments = [];

        // Adjust target based on actual data available
        $maxPossible = $teachers->count() * $subjects->count();
        $targetAssignments = min(30, $maxPossible);
        $this->command->info("Target assignments: {$targetAssignments} (max possible: {$maxPossible})");

        // First pass: ensure each subject has at least one teacher
        foreach ($subjects as $subject) {
            $teachersPerSubject = min(rand(1, 3), $teachers->count());
            $availableTeachers = $teachers->shuffle()->take($teachersPerSubject);

            foreach ($availableTeachers as $teacher) {
                $assignments[] = [
                    'teacher_id' => $teacher->id,
                    'class_id' => $defaultClassId,
                    'subject_id' => $subject->id,
                    'academic_year_id' => $academicYear->id,
                    'assigned_date' => now()->toDateString(),
                    'effective_from' => now()->toDateString(),
                    'effective_to' => null,
                    'assignment_status' => 'Active',
                    'created_by' => $createdBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Second pass: add more random assignments up to target
        $usedCombinations = [];
        foreach ($assignments as $a) {
            $usedCombinations[] = "{$a['teacher_id']}-{$a['subject_id']}";
        }

        $attempts = 0;
        $maxAttempts = 200;

        while (count($assignments) < $targetAssignments && $attempts < $maxAttempts) {
            $attempts++;
            $teacher = $teachers->random();
            $subject = $subjects->random();

            $key = "{$teacher->id}-{$subject->id}";

            if (!in_array($key, $usedCombinations)) {
                $usedCombinations[] = $key;
                $assignments[] = [
                    'teacher_id' => $teacher->id,
                    'class_id' => $defaultClassId,
                    'subject_id' => $subject->id,
                    'academic_year_id' => $academicYear->id,
                    'assigned_date' => now()->toDateString(),
                    'effective_from' => now()->toDateString(),
                    'effective_to' => null,
                    'assignment_status' => 'Active',
                    'created_by' => $createdBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($assignments)) {
            DB::table('teacher_assignments')->insert($assignments);
        }

        $this->command->info('Created ' . count($assignments) . ' teacher-subject assignments successfully.');

        // Show assignment summary
        $this->command->info("\nAssignment Summary:");
        foreach ($subjects as $subject) {
            $teacherCount = DB::table('teacher_assignments')
                ->where('subject_id', $subject->id)
                ->where('academic_year_id', $academicYear->id)
                ->count();

            if ($teacherCount > 0) {
                $assignedTeachers = DB::table('teacher_assignments')
                    ->join('teachers', 'teacher_assignments.teacher_id', '=', 'teachers.id')
                    ->where('teacher_assignments.subject_id', $subject->id)
                    ->where('teacher_assignments.academic_year_id', $academicYear->id)
                    ->pluck('teachers.full_name')
                    ->take(3)
                    ->implode(', ');

                if ($teacherCount > 3) {
                    $assignedTeachers .= ' and ' . ($teacherCount - 3) . ' more';
                }

                $this->command->line("- {$subject->name}: {$teacherCount} teacher(s) - {$assignedTeachers}");
            }
        }
    }

    /**
     * Create sample teachers if none exist.
     */
    private function createSampleTeachers(): void
    {
        $teacherData = [
            ['full_name' => 'Mekonnen Alemu', 'phone' => '+251911000101', 'email' => 'mekonnen.alemu@example.com', 'gender' => 'Male', 'qualification' => 'Masters', 'specialization' => 'Mathematics'],
            ['full_name' => 'Tigist Haile',    'phone' => '+251911000102', 'email' => 'tigist.haile@example.com',   'gender' => 'Female', 'qualification' => 'Bachelors', 'specialization' => 'English'],
            ['full_name' => 'Samuel Bekele',   'phone' => '+251911000103', 'email' => 'samuel.bekele@example.com',  'gender' => 'Male', 'qualification' => 'Masters', 'specialization' => 'Science'],
            ['full_name' => 'Meron Tadesse',   'phone' => '+251911000104', 'email' => 'meron.tadesse@example.com',  'gender' => 'Female', 'qualification' => 'Bachelors', 'specialization' => 'Amharic'],
            ['full_name' => 'Dawit Wondimu',   'phone' => '+251911000105', 'email' => 'dawit.wondimu@example.com',  'gender' => 'Male', 'qualification' => 'PhD', 'specialization' => 'Physics'],
            ['full_name' => 'Hanna Girma',     'phone' => '+251911000106', 'email' => 'hanna.girma@example.com',    'gender' => 'Female', 'qualification' => 'Masters', 'specialization' => 'Chemistry'],
            ['full_name' => 'Yonas Kebede',    'phone' => '+251911000107', 'email' => 'yonas.kebede@example.com',   'gender' => 'Male', 'qualification' => 'Bachelors', 'specialization' => 'History'],
            ['full_name' => 'Bethel Solomon',  'phone' => '+251911000108', 'email' => 'bethel.solomon@example.com', 'gender' => 'Female', 'qualification' => 'Masters', 'specialization' => 'Geography'],
        ];

        foreach ($teacherData as $data) {
            Teacher::create(array_merge($data, [
                'status' => 'Active',
                'employment_type' => 'Full-time',
                'hire_date' => now()->subYears(rand(1, 5))->format('Y-m-d'),
                'created_by' => $createdBy,
            ]));
        }
        $this->command->info('Created ' . count($teacherData) . ' sample teachers.');
    }

    /**
     * Create sample subjects matching the actual subjects table schema.
     */
    private function createSampleSubjects(): void
    {
        $subjectData = [
            ['name' => 'Mathematics', 'description' => 'Basic Mathematics'],
            ['name' => 'English',     'description' => 'English Language'],
            ['name' => 'Amharic',     'description' => 'Amharic Language'],
            ['name' => 'Science',     'description' => 'General Science'],
            ['name' => 'Physics',     'description' => 'Basic Physics'],
            ['name' => 'Chemistry',   'description' => 'Basic Chemistry'],
            ['name' => 'History',     'description' => 'World History'],
            ['name' => 'Geography',   'description' => 'Physical Geography'],
        ];

        foreach ($subjectData as $data) {
            Subject::create(array_merge($data, [
                'is_active' => true,
                'created_by' => $createdBy,
            ]));
        }
        $this->command->info('Created ' . count($subjectData) . ' sample subjects.');
    }
}
