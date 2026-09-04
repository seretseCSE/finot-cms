<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\QuestionBank;
use App\Models\Quiz;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Guard rail for the "every new feature seeds demo data" rule: the full demo
 * world must build cleanly and contain the newest modules in demo-worthy
 * states. Slow (~90s) by nature — it seeds 10 schools — but it is the only
 * test that exercises every seeder path end-to-end.
 */
it('builds the demo world including the LMS slice', function () {
    $this->seed(DatabaseSeeder::class); // platform seed data first, like migrate:fresh --seed
    DemoSeeder::$authorized = true;
    $this->seed(DemoSeeder::class);

    expect(QuestionBank::count())->toBeGreaterThan(0)
        ->and(Quiz::where('status', 'published')->count())->toBeGreaterThan(0)
        ->and(DB::table('quiz_attempts')->where('status', 'graded')->count())->toBeGreaterThan(0)
        ->and(DB::table('quiz_attempts')->where('status', 'in_progress')->count())->toBeGreaterThan(0)
        ->and(DB::table('quiz_attempt_answers')->count())->toBeGreaterThan(0)
        ->and(Assignment::where('status', 'published')->count())->toBeGreaterThan(0)
        ->and(DB::table('assignment_submissions')->where('status', 'graded')->count())->toBeGreaterThan(0)
        ->and(Course::where('status', 'published')->count())->toBeGreaterThan(0)
        ->and(DB::table('lesson_progress')->count())->toBeGreaterThan(0)
        ->and(CourseMaterial::count())->toBeGreaterThanOrEqual(2);

    // Coverage extras (July 2026): every feature table the empty-table audit
    // flagged must land populated — a feature nobody can see does not exist.
    expect(DB::table('section_homerooms')->count())->toBeGreaterThan(0)
        ->and(DB::table('holidays')->count())->toBeGreaterThan(0)
        ->and(DB::table('grading_policies')->count())->toBeGreaterThan(0)
        ->and(DB::table('employee_qualifications')->count())->toBeGreaterThan(0)
        ->and(DB::table('employee_allowances')->count())->toBeGreaterThan(0)
        ->and(DB::table('employee_deductions')->count())->toBeGreaterThan(0)
        ->and(DB::table('payroll_runs')->where('status', 'paid')->count())->toBeGreaterThan(0)
        ->and(DB::table('payroll_runs')->where('status', 'draft')->count())->toBeGreaterThan(0)
        ->and(DB::table('payroll_items')->count())->toBeGreaterThan(0)
        ->and(DB::table('finance_categories')->count())->toBeGreaterThan(0)
        ->and(DB::table('expenses')->where('status', 'pending')->count())->toBeGreaterThan(0)
        ->and(DB::table('expenses')->where('status', 'approved')->count())->toBeGreaterThan(0)
        ->and(DB::table('other_incomes')->count())->toBeGreaterThan(0)
        ->and(DB::table('budgets')->count())->toBeGreaterThan(0)
        ->and(DB::table('id_cards')->count())->toBeGreaterThan(0)
        ->and(DB::table('device_events')->count())->toBeGreaterThan(0)
        ->and(DB::table('card_requests')->count())->toBeGreaterThan(0)
        ->and(DB::table('marklists')->where('status', 'submitted')->count())->toBeGreaterThan(0)
        ->and(DB::table('marklists')->where('status', 'approved')->count())->toBeGreaterThan(0)
        ->and(DB::table('payment_verifications')->count())->toBeGreaterThan(0)
        ->and(DB::table('student_health_conditions')->count())->toBeGreaterThan(0)
        ->and(DB::table('teacher_availabilities')->count())->toBeGreaterThan(0)
        ->and(DB::table('student_withdrawals')->count())->toBeGreaterThan(0)
        ->and(DB::table('student_promotions')->count())->toBeGreaterThan(0)
        ->and(DB::table('transfer_applications')->count())->toBeGreaterThan(0);
});
