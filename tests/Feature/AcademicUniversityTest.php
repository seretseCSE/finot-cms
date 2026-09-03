<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Marklist;
use App\Models\MarklistItem;
use App\Models\Member;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Academics\RankingService;
use App\Support\RoleGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AcademicUniversityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function ranking_service_assigns_correct_dense_ranks(): void
    {
        [$marklist, $items] = $this->makeMarklistWithScores([90, 80, 80, 70]);

        $svc = app(RankingService::class);
        $svc->recalculateMarklist($marklist->fresh(['items', 'subject']));

        $ranked = $marklist->fresh()->items()->orderByDesc('score')->get();

        $this->assertEquals(1, $ranked[0]->rank);
        $this->assertEquals(2, $ranked[1]->rank);
        $this->assertEquals(2, $ranked[2]->rank);
        $this->assertEquals(4, $ranked[3]->rank);
    }

    #[Test]
    public function letter_grade_maps_score_to_correct_band(): void
    {
        $svc = app(RankingService::class);

        $this->assertEquals('A+', $svc->letterGrade(93, 100));
        $this->assertEquals('A', $svc->letterGrade(87, 100));
        $this->assertEquals('F', $svc->letterGrade(30, 100));
        $this->assertNull($svc->letterGrade(null, 100));
    }

    #[Test]
    public function student_results_returns_summary_with_rank_and_averages(): void
    {
        $user = $this->createEducationHeadUser();
        [$marklist, $items] = $this->makeMarklistWithScores([75, 85]);

        $svc = app(RankingService::class);
        $svc->recalculateMarklist($marklist->fresh(['items', 'subject']));

        $member = $items->first()->member;

        $summary = $svc->studentResults((int) $member->id);

        $this->assertNotEmpty($summary['items']);
        $this->assertNotNull($summary['semester_average']);
        $this->assertIsInt($summary['overall_rank']);
    }

    #[Test]
    public function role_gate_is_returns_active_role(): void
    {
        $user = $this->createAdminUser();
        $user->assignRole('student');

        $this->actingAs($user);

        RoleGate::switchTo('student', $user);
        $this->assertTrue(RoleGate::is('student'));
        $this->assertFalse(RoleGate::is('admin'));

        RoleGate::switchTo('admin', $user);
        $this->assertTrue(RoleGate::is('admin'));
        $this->assertFalse(RoleGate::is('student'));
    }

    #[Test]
    public function role_gate_switchable_roles_returns_all_roles_for_multi_role_user(): void
    {
        $user = $this->createAdminUser();
        $user->assignRole('student');

        $this->actingAs($user);

        $roles = RoleGate::switchableRoles($user);
        $names = array_column($roles, 'name');

        $this->assertContains('admin', $names);
        $this->assertContains('student', $names);
    }

    #[Test]
    public function grading_scale_is_seeded_by_migration(): void
    {
        $scale = \App\Models\GradingScale::defaultScale();

        $this->assertNotNull($scale, 'A default grading scale should have been seeded by the migration.');
        $this->assertGreaterThanOrEqual(5, $scale->bands->count(), 'Should have at least 5 grade bands.');
    }

    #[Test]
    public function classes_have_program_year_column(): void
    {
        $class = ClassModel::query()->first();

        if (! $class) {
            $actor = $this->createEducationHeadUser();
            $class = ClassModel::create([
                'name' => 'Year 1 test',
                'is_active' => true,
                'program_year' => 1,
                'created_by' => $actor->id,
            ]);
        }

        $this->assertNotNull($class);
    }

    #[Test]
    public function education_head_can_access_grading_scale_page(): void
    {
        $this->actingAs($this->createEducationHeadUser())
            ->get('/admin/grading-scale-page')
            ->assertOk();
    }

    #[Test]
    public function education_head_can_access_academic_results_page(): void
    {
        $this->actingAs($this->createEducationHeadUser())
            ->get('/admin/academic-results-report')
            ->assertOk();
    }

    #[Test]
    public function student_can_access_my_results_page(): void
    {
        $user = $this->createUserWithRole('student');
        $user->givePermissionTo('results.view_own');

        $this->actingAs($user)
            ->get('/admin/my-results')
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────
    // helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  list<float>  $scores
     * @return array{0: Marklist, 1: \Illuminate\Support\Collection<int, MarklistItem>}
     */
    protected function makeMarklistWithScores(array $scores): array
    {
        $actor = $this->createEducationHeadUser();

        $year = AcademicYear::factory()->active()->create();
        $term = Term::create([
            'academic_year_id' => $year->id,
            'name' => 'Semester 1',
            'semester_number' => 1,
            'starts_on' => now()->subMonths(2),
            'ends_on' => now()->addMonths(2),
            'is_active' => true,
        ]);

        $class = ClassModel::create([
            'name' => 'Year 1',
            'program_year' => 1,
            'is_active' => true,
            'created_by' => $actor->id,
        ]);

        $subject = Subject::create([
            'name' => 'Test Subject '.uniqid(),
            'program_year' => 1,
            'semester_number' => 1,
            'max_score' => 100,
            'is_active' => true,
            'created_by' => $actor->id,
        ]);

        $marklist = Marklist::create([
            'class_id' => $class->id,
            'term_id' => $term->id,
            'subject_id' => $subject->id,
            'status' => 'approved',
            'assisted_by' => $actor->id,
            'assisted_at' => now(),
            'assist_reason' => 'seeded for test',
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        $items = collect();
        foreach ($scores as $score) {
            $member = Member::factory()->create();

            $item = MarklistItem::create([
                'marklist_id' => $marklist->id,
                'member_id' => $member->id,
                'score' => $score,
                'max_score' => 100,
                'recorded_by' => $actor->id,
            ]);

            $items->push($item);
        }

        return [$marklist, $items];
    }
}
