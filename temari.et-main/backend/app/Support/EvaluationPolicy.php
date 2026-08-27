<?php

namespace App\Support;

use App\Models\EvaluationTemplate;
use App\Models\School;

/**
 * The national default appraisal rubric (MoE continuous teacher performance
 * appraisal shape): eight weighted criteria out of 5, weights summing to 100.
 * Auto-provisioned per school on first access — the LeavePolicy pattern —
 * then fully school-editable. Labels are school DATA (not UI strings), so
 * they seed in English and each school rewrites them as it likes.
 */
class EvaluationPolicy
{
    /** @var list<array{domain: string, label: string, weight: float}> */
    public const DEFAULT_CRITERIA = [
        ['domain' => 'planning', 'label' => 'Lesson planning & preparation', 'weight' => 15],
        ['domain' => 'teaching', 'label' => 'Teaching methods & lesson delivery', 'weight' => 20],
        ['domain' => 'assessment', 'label' => 'Student assessment & feedback', 'weight' => 15],
        ['domain' => 'management', 'label' => 'Classroom management & discipline', 'weight' => 10],
        ['domain' => 'results', 'label' => 'Student results & improvement', 'weight' => 10],
        ['domain' => 'cocurricular', 'label' => 'Co-curricular participation', 'weight' => 10],
        ['domain' => 'ethics', 'label' => 'Professional ethics & conduct', 'weight' => 10],
        ['domain' => 'punctuality', 'label' => 'Punctuality & attendance', 'weight' => 10],
    ];

    /** The school's active rubric, provisioning the MoE default on first use. */
    public static function templateFor(School $school): EvaluationTemplate
    {
        $existing = EvaluationTemplate::query()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $template = EvaluationTemplate::create([
            'school_id' => $school->id,
            'name' => 'Teacher performance appraisal',
            'description' => 'Continuous performance appraisal, Ministry of Education format.',
        ]);

        foreach (self::DEFAULT_CRITERIA as $index => $criterion) {
            $template->criteria()->create([
                ...$criterion,
                'max_score' => 5,
                'sort_order' => $index,
            ]);
        }

        return $template;
    }
}
