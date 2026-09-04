<?php

namespace App\Services\Academics;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\StudentEnrollment;
use App\Models\SubjectCredit;
use App\Models\SubjectOffering;
use App\Models\User;
use App\Support\TermGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentScoreService
{
    public function saveScores(Assessment $assessment, array $rows, User $actor): Assessment
    {
        $assessment->loadMissing('offering.term');
        $term = $assessment->offering?->term;

        if (! $term) {
            throw ValidationException::withMessages(['term' => 'Assessment has no semester.']);
        }

        TermGate::assertWritable($term, $actor);

        if (! $assessment->is_open && ! $actor->hasRole(['admin', 'superadmin', 'education_head'])) {
            throw ValidationException::withMessages(['assessment' => 'Assessment is closed.']);
        }

        DB::transaction(function () use ($assessment, $rows, $actor): void {
            foreach ($rows as $row) {
                $memberId = (int) ($row['member_id'] ?? 0);
                if ($memberId <= 0) {
                    continue;
                }

                AssessmentScore::query()->updateOrCreate(
                    [
                        'assessment_id' => $assessment->id,
                        'member_id' => $memberId,
                    ],
                    [
                        'score' => array_key_exists('score', $row) && $row['score'] !== '' && $row['score'] !== null
                            ? (float) $row['score']
                            : null,
                        'is_absent' => (bool) ($row['is_absent'] ?? false),
                        'recorded_by' => $actor->id,
                    ]
                );
            }
        });

        return $assessment->fresh(['scores']);
    }

    /**
     * Weighted subject total for one member on one offering.
     */
    public function subjectTotal(SubjectOffering $offering, int $memberId): ?float
    {
        $credit = SubjectCredit::query()
            ->where('member_id', $memberId)
            ->where('subject_id', $offering->subject_id)
            ->whereIn('status', ['passed', 'transferred'])
            ->first();

        if ($credit && $credit->score !== null && $credit->max_score) {
            return round(((float) $credit->score / (int) $credit->max_score) * 100, 2);
        }

        $assessments = $offering->assessments()->with(['scores' => fn ($q) => $q->where('member_id', $memberId)])->get();
        if ($assessments->isEmpty()) {
            return null;
        }

        $totalWeight = 0.0;
        $earned = 0.0;
        $any = false;

        foreach ($assessments as $assessment) {
            $scoreRow = $assessment->scores->first();
            if (! $scoreRow || $scoreRow->is_absent || $scoreRow->score === null || $assessment->max_score <= 0) {
                continue;
            }

            $weight = (float) $assessment->weight;
            $totalWeight += $weight;
            $earned += ((float) $scoreRow->score / (int) $assessment->max_score) * $weight;
            $any = true;
        }

        if (! $any || $totalWeight <= 0) {
            return null;
        }

        return round(($earned / $totalWeight) * 100, 2);
    }

    /**
     * @return list<int>
     */
    public function rosterMemberIds(SubjectOffering $offering): array
    {
        $query = StudentEnrollment::query()
            ->where('status', 'Enrolled')
            ->whereNull('removed_at');

        if ($offering->batch_year_id) {
            $query->where('batch_year_id', $offering->batch_year_id);
        }

        if ($offering->class_id) {
            $query->where('class_id', $offering->class_id);
        }

        return $query->pluck('member_id')->map(fn ($id) => (int) $id)->all();
    }
}
