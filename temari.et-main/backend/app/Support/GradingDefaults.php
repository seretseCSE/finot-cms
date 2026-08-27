<?php

namespace App\Support;

use App\Models\GradingScale;

/**
 * The platform default grading scales (school_id null), following the common
 * Ethiopian MoE school conventions: pass mark 50, letter cut-offs at
 * 90/80/60/50. Provisioned idempotently by the seeder AND on demand by
 * GradingPolicyResolver, so a freshly created (or test) database always has
 * the numeric fallback scale.
 */
final class GradingDefaults
{
    public const FALLBACK_CODE = 'et-percentage';

    private const SCALES = [
        [
            'code' => self::FALLBACK_CODE,
            'name' => 'Percentage (0–100)',
            'description' => 'Raw marks out of 100 with the standard Ethiopian descriptors. Shown numerically.',
            'sort_order' => 1,
            'bands' => [
                ['min' => 90, 'max' => 100, 'letter' => null, 'label' => 'Excellent', 'grade_points' => 4.0, 'passing' => true],
                ['min' => 80, 'max' => 89.99, 'letter' => null, 'label' => 'Very Good', 'grade_points' => 3.0, 'passing' => true],
                ['min' => 60, 'max' => 79.99, 'letter' => null, 'label' => 'Good', 'grade_points' => 2.0, 'passing' => true],
                ['min' => 50, 'max' => 59.99, 'letter' => null, 'label' => 'Satisfactory', 'grade_points' => 1.0, 'passing' => true],
                ['min' => 0, 'max' => 49.99, 'letter' => null, 'label' => 'Needs Improvement', 'grade_points' => 0.0, 'passing' => false],
            ],
        ],
        [
            'code' => 'et-letter',
            'name' => 'Ethiopian Letter (A–F)',
            'description' => 'The standard secondary-school letter scale: A ≥ 90, B ≥ 80, C ≥ 60, D ≥ 50, F below.',
            'sort_order' => 2,
            'bands' => [
                ['min' => 90, 'max' => 100, 'letter' => 'A', 'label' => 'Excellent', 'grade_points' => 4.0, 'passing' => true],
                ['min' => 80, 'max' => 89.99, 'letter' => 'B', 'label' => 'Very Good', 'grade_points' => 3.0, 'passing' => true],
                ['min' => 60, 'max' => 79.99, 'letter' => 'C', 'label' => 'Good', 'grade_points' => 2.0, 'passing' => true],
                ['min' => 50, 'max' => 59.99, 'letter' => 'D', 'label' => 'Satisfactory', 'grade_points' => 1.0, 'passing' => true],
                ['min' => 0, 'max' => 49.99, 'letter' => 'F', 'label' => 'Fail', 'grade_points' => 0.0, 'passing' => false],
            ],
        ],
        [
            'code' => 'et-early-grade',
            'name' => 'Early Grades (E / VG / G / NI)',
            'description' => 'Descriptive scale for KG and lower primary: Excellent, Very Good, Good, Needs Improvement.',
            'sort_order' => 3,
            'bands' => [
                ['min' => 85, 'max' => 100, 'letter' => 'E', 'label' => 'Excellent', 'grade_points' => null, 'passing' => true],
                ['min' => 70, 'max' => 84.99, 'letter' => 'VG', 'label' => 'Very Good', 'grade_points' => null, 'passing' => true],
                ['min' => 50, 'max' => 69.99, 'letter' => 'G', 'label' => 'Good', 'grade_points' => null, 'passing' => true],
                ['min' => 0, 'max' => 49.99, 'letter' => 'NI', 'label' => 'Needs Improvement', 'grade_points' => null, 'passing' => false],
            ],
        ],
    ];

    public static function provision(): void
    {
        foreach (self::SCALES as $definition) {
            $scale = GradingScale::withTrashed()->firstOrCreate(
                ['school_id' => null, 'code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ],
            );

            if ($scale->bands()->exists()) {
                continue;
            }

            foreach ($definition['bands'] as $i => $band) {
                $scale->bands()->create([
                    'min_score' => $band['min'],
                    'max_score' => $band['max'],
                    'letter' => $band['letter'],
                    'label' => $band['label'],
                    'grade_points' => $band['grade_points'],
                    'is_passing' => $band['passing'],
                    'sort_order' => $i + 1,
                ]);
            }
        }
    }
}
