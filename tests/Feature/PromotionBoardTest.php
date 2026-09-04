<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Batch;
use App\Models\BatchYear;
use App\Models\ClassModel;
use App\Models\Member;
use App\Models\StudentEnrollment;
use App\Models\StudentTermResult;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Models\User;
use App\Services\Academics\BatchPromotionService;
use App\Services\Academics\BatchService;
use App\Services\Academics\PromotionBoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromotionBoardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function suggest_pass_or_fail_from_average(): void
    {
        $boards = app(PromotionBoardService::class);

        $this->assertSame('pass', $boards->suggest(55.0));
        $this->assertSame('fail', $boards->suggest(49.9));
        $this->assertNull($boards->suggest(null));
    }

    #[Test]
    public function promote_keeps_batch_and_advances_program_year(): void
    {
        $head = $this->createEducationHeadUser();
        [$batch, $year1, $term, $class1, $class2, $enrollment] = $this->seedClassPromotion($head);

        app(BatchPromotionService::class)->promote(
            $enrollment->fresh(),
            (int) $class2->id,
            $head,
        );

        $year2 = $batch->years->firstWhere('program_year', 2);

        $this->assertDatabaseHas('student_enrollments', [
            'member_id' => $enrollment->member_id,
            'status' => 'Enrolled',
            'batch_id' => $batch->id,
            'batch_year_id' => $year2->id,
            'class_id' => $class2->id,
        ]);
        $this->assertDatabaseHas('student_enrollments', [
            'id' => $enrollment->id,
            'status' => 'Promoted',
        ]);
    }

    #[Test]
    public function apply_board_passes_and_fails_students_in_one_class(): void
    {
        $head = $this->createEducationHeadUser();
        [$batch, $year1, $term, $class1, $class2, $enrollmentA] = $this->seedClassPromotion($head);

        $memberB = Member::factory()->create();
        $enrollmentB = StudentEnrollment::create([
            'member_id' => $memberB->id,
            'class_id' => $class1->id,
            'academic_year_id' => $enrollmentA->academic_year_id,
            'batch_id' => $batch->id,
            'batch_year_id' => $year1->id,
            'enrolled_date' => now()->toDateString(),
            'status' => 'Enrolled',
            'enrolled_by' => $head->id,
        ]);

        $batchB = app(BatchService::class)->create([
            'name' => 'Class of 2027',
            'start_year' => 2027,
            'tenure_years' => 4,
        ], $head);
        $yearB = $batchB->years->firstWhere('program_year', 1);

        $result = app(BatchPromotionService::class)->applyBoard(
            [
                $enrollmentA->id => 'pass',
                $enrollmentB->id => 'fail',
            ],
            (int) $class2->id,
            (int) $yearB->id,
            (int) $class1->id,
            $head,
        );

        $this->assertSame(1, $result['passed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame([], $result['errors']);

        $this->assertDatabaseHas('student_enrollments', [
            'member_id' => $enrollmentA->member_id,
            'status' => 'Enrolled',
            'batch_id' => $batch->id,
            'class_id' => $class2->id,
        ]);
        $this->assertDatabaseHas('student_enrollments', [
            'member_id' => $enrollmentB->member_id,
            'status' => 'Enrolled',
            'batch_id' => $batchB->id,
            'class_id' => $class1->id,
        ]);
    }

    #[Test]
    public function board_build_includes_averages_from_term_results(): void
    {
        $head = $this->createEducationHeadUser();
        [$batch, $year1, $term, $class1, $class2, $enrollment] = $this->seedClassPromotion($head);

        StudentTermResult::create([
            'member_id' => $enrollment->member_id,
            'term_id' => $term->id,
            'batch_year_id' => $year1->id,
            'class_id' => $class1->id,
            'enrollment_id' => $enrollment->id,
            'total' => 80,
            'average' => 80,
            'rank' => 1,
            'rank_of' => 1,
            'breakdown' => [],
            'computed_at' => now(),
            'computed_by' => $head->id,
        ]);

        $board = app(PromotionBoardService::class)->build(
            (int) $enrollment->academic_year_id,
            (int) $batch->id,
            (int) $class1->id,
        );

        $this->assertCount(1, $board['rows']);
        $this->assertSame(80.0, $board['rows'][0]['average']);
        $this->assertSame('pass', $board['rows'][0]['suggestion']);
    }

    #[Test]
    public function promotion_board_page_is_accessible(): void
    {
        $user = $this->createEducationHeadUser();
        $response = $this->actingAs($user)->get('/admin/promotion-board-page');

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        $response->assertSee('Promotion board');
        $response->assertSee('Load class');
    }

    /**
     * @return array{0: Batch, 1: BatchYear, 2: Term, 3: ClassModel, 4: ClassModel, 5: StudentEnrollment}
     */
    protected function seedClassPromotion(User $actor): array
    {
        $batch = app(BatchService::class)->create([
            'name' => 'Class of 2026',
            'start_year' => 2026,
            'tenure_years' => 4,
        ], $actor);
        $year1 = $batch->years->firstWhere('program_year', 1);

        $academicYear = AcademicYear::factory()->active()->create();
        $class1 = ClassModel::create([
            'name' => 'Year 1 Section A',
            'program_year' => 1,
            'is_active' => true,
            'created_by' => $actor->id,
        ]);
        $class2 = ClassModel::create([
            'name' => 'Year 2 Section A',
            'program_year' => 2,
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
            'batch_year_id' => $year1->id,
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
            'class_id' => $class1->id,
            'academic_year_id' => $academicYear->id,
            'batch_id' => $batch->id,
            'batch_year_id' => $year1->id,
            'enrolled_date' => now()->toDateString(),
            'status' => 'Enrolled',
            'enrolled_by' => $actor->id,
        ]);

        SubjectOffering::create([
            'batch_year_id' => $year1->id,
            'term_id' => $term->id,
            'class_id' => $class1->id,
            'subject_id' => $subject->id,
            'max_score' => 100,
            'created_by' => $actor->id,
        ]);

        Assessment::create([
            'subject_offering_id' => SubjectOffering::first()->id,
            'name' => 'Midterm',
            'max_score' => 100,
            'weight' => 100,
            'sort_order' => 1,
            'is_open' => true,
            'created_by' => $actor->id,
        ]);

        return [$batch, $year1, $term, $class1, $class2, $enrollment];
    }
}
