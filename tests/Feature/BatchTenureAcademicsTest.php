<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Batch;
use App\Models\BatchYear;
use App\Models\ClassModel;
use App\Models\Member;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectCredit;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Models\User;
use App\Services\Academics\AssessmentScoreService;
use App\Services\Academics\BatchPromotionService;
use App\Services\Academics\BatchService;
use App\Services\Academics\ComputeTermResultsService;
use App\Support\Ranking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BatchTenureAcademicsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_batch_seeds_named_program_years(): void
    {
        $actor = $this->createEducationHeadUser();
        $batch = app(BatchService::class)->create([
            'name' => 'Class of 2026',
            'start_year' => 2026,
            'tenure_years' => 4,
        ], $actor);

        $this->assertSame('open', $batch->status);
        $this->assertCount(4, $batch->years);
        $this->assertSame(1, $batch->years->first()->program_year);
        $this->assertSame('active', $batch->years->first()->status);
    }

    #[Test]
    public function competition_ranking_skips_after_ties(): void
    {
        $this->assertSame([1, 2, 2, 4], Ranking::competition([90, 80, 80, 70]));
    }

    #[Test]
    public function assessment_scores_save_on_active_term_and_compute_roster(): void
    {
        $head = $this->createEducationHeadUser();
        [$batch, $year, $term, $offering, $assessment, $enrollment] = $this->seedOffering($head);

        app(AssessmentScoreService::class)->saveScores($assessment, [[
            'member_id' => $enrollment->member_id,
            'score' => 40,
            'is_absent' => false,
        ]], $head);

        $result = app(ComputeTermResultsService::class)->compute($term, $head);

        $this->assertSame(1, $result['students']);
        $this->assertDatabaseHas('student_term_results', [
            'member_id' => $enrollment->member_id,
            'term_id' => $term->id,
        ]);
    }

    #[Test]
    public function fail_transfer_keeps_passed_credits_for_matching_subjects(): void
    {
        $head = $this->createEducationHeadUser();
        [$batchA, $yearA, $term, $offering, $assessment, $enrollment] = $this->seedOffering($head, 'Class of 2024');

        app(AssessmentScoreService::class)->saveScores($assessment, [[
            'member_id' => $enrollment->member_id,
            'score' => 80,
        ]], $head);
        app(ComputeTermResultsService::class)->compute($term, $head);

        $batchB = app(BatchService::class)->create([
            'name' => 'Class of 2025',
            'start_year' => 2025,
            'tenure_years' => 4,
        ], $head);
        $yearB = $batchB->years->firstWhere('program_year', 1);

        $termB = Term::create([
            'academic_year_id' => $term->academic_year_id,
            'batch_year_id' => $yearB->id,
            'name' => 'Semester 1 B',
            'semester_number' => 1,
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->addMonth(),
            'is_active' => true,
            'status' => 'active',
        ]);

        // Same subject exists on B's curriculum (different term)
        SubjectOffering::query()->create([
            'batch_year_id' => $yearB->id,
            'term_id' => $termB->id,
            'class_id' => $enrollment->class_id,
            'subject_id' => $offering->subject_id,
            'max_score' => 100,
            'created_by' => $head->id,
        ]);

        $new = app(BatchPromotionService::class)->failTransfer(
            $enrollment->fresh(),
            (int) $yearB->id,
            (int) $enrollment->class_id,
            $head,
        );

        $this->assertSame((int) $batchB->id, (int) $new->batch_id);
        $this->assertSame((int) $yearB->id, (int) $new->batch_year_id);
        $this->assertTrue(
            SubjectCredit::query()
                ->where('member_id', $enrollment->member_id)
                ->where('subject_id', $offering->subject_id)
                ->where('status', 'transferred')
                ->exists()
        );
    }

    /**
     * @return array{0: Batch, 1: BatchYear, 2: Term, 3: SubjectOffering, 4: Assessment, 5: StudentEnrollment}
     */
    protected function seedOffering(User $actor, string $batchName = 'Class of 2026'): array
    {
        $batch = app(BatchService::class)->create([
            'name' => $batchName,
            'start_year' => 2026,
            'tenure_years' => 4,
        ], $actor);
        $year = $batch->years->first();

        $academicYear = AcademicYear::factory()->active()->create();
        $class = ClassModel::create([
            'name' => 'Section A '.$batchName,
            'program_year' => 1,
            'is_active' => true,
            'created_by' => $actor->id,
        ]);
        $subject = Subject::create([
            'name' => 'Liturgy '.uniqid(),
            'is_active' => true,
            'max_score' => 100,
            'created_by' => $actor->id,
        ]);
        $term = Term::create([
            'academic_year_id' => $academicYear->id,
            'batch_year_id' => $year->id,
            'name' => 'Semester 1',
            'semester_number' => 1,
            'starts_on' => now()->subMonth(),
            'ends_on' => now()->addMonth(),
            'is_active' => true,
            'status' => 'active',
        ]);
        $member = Member::factory()->create();
        $enrollment = StudentEnrollment::create([
            'member_id' => $member->id,
            'class_id' => $class->id,
            'academic_year_id' => $academicYear->id,
            'batch_id' => $batch->id,
            'batch_year_id' => $year->id,
            'enrolled_date' => now()->toDateString(),
            'status' => 'Enrolled',
            'enrolled_by' => $actor->id,
        ]);
        $offering = SubjectOffering::create([
            'batch_year_id' => $year->id,
            'term_id' => $term->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'max_score' => 100,
            'created_by' => $actor->id,
        ]);
        $assessment = Assessment::create([
            'subject_offering_id' => $offering->id,
            'name' => 'Midterm',
            'max_score' => 100,
            'weight' => 100,
            'sort_order' => 1,
            'is_open' => true,
            'created_by' => $actor->id,
        ]);

        return [$batch, $year, $term, $offering, $assessment, $enrollment];
    }
}
