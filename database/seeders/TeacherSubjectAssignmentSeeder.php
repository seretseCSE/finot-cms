<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeacherSubjectAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@finotetsidik.org')->first();
        $createdBy = $adminUser?->id ?? User::first()->id;

        DB::table('teacher_assignments')->delete();

        $teachers = Teacher::where('status', 'Active')->get();

        if ($teachers->isEmpty()) {
            $this->command?->warn('No active teachers found. Run TeacherSeeder first.');
            return;
        }

        $subjects = Subject::where('is_active', true)->get();

        if ($subjects->count() < 3) {
            $this->command?->error('Need at least 3 active subjects. Found ' . $subjects->count() . '.');
            return;
        }

        $classes = DB::table('classes')->where('is_active', true)->get();

        if ($classes->isEmpty()) {
            $this->command?->error('No active classes found. Run ClassSeeder first.');
            return;
        }

        $academicYear = AcademicYear::query()
            ->where('status', 'Active')
            ->where('phase', 'current')
            ->first()
            ?? AcademicYear::query()->where('status', 'Active')->orderBy('start_date', 'desc')->first();

        if (! $academicYear) {
            $academicYear = AcademicYear::create([
                'name' => '2024-2025',
                'start_date' => '2024-09-01',
                'end_date' => '2025-07-31',
                'status' => 'Active',
                'phase' => 'current',
                'created_by' => $createdBy,
            ]);
        }

        $assignments = [];
        $teacherCount = $teachers->count();

        foreach ($classes as $class) {
            $picked = $subjects->shuffle()->take(3);

            foreach ($picked as $subject) {
                $teacher = $teachers[array_rand($teachers->all())];

                $assignments[] = [
                    'teacher_id' => $teacher->id,
                    'class_id' => $class->id,
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

        DB::table('teacher_assignments')->insert($assignments);

        $this->command?->info("Created " . count($assignments) . " teacher assignments — 3 subjects each across " . $classes->count() . " class(es).");

        foreach ($classes as $class) {
            $this->command?->line("  {$class->name}: " .
                DB::table('teacher_assignments')
                    ->where('class_id', $class->id)
                    ->where('academic_year_id', $academicYear->id)
                    ->count() . " assignments");
        }
    }
}
