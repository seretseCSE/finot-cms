<?php

namespace Database\Seeders;

use App\Enums\Cycle;
use App\Models\GradeLevel;
use Illuminate\Database\Seeder;

/**
 * Seeds the nationally fixed Ethiopian grade levels (KG-1 .. Grade 12) once at
 * the platform level. Idempotent — safe to re-run.
 */
class GradeLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['code' => 'KG1', 'name' => 'KG-1', 'cycle' => Cycle::Kindergarten],
            ['code' => 'KG2', 'name' => 'KG-2', 'cycle' => Cycle::Kindergarten],
            ['code' => 'KG3', 'name' => 'KG-3', 'cycle' => Cycle::Kindergarten],
        ];

        for ($grade = 1; $grade <= 12; $grade++) {
            $levels[] = [
                'code' => "G{$grade}",
                'name' => "Grade {$grade}",
                'cycle' => $this->cycleFor($grade),
            ];
        }

        foreach ($levels as $index => $level) {
            GradeLevel::updateOrCreate(
                ['code' => $level['code']],
                [
                    'name' => $level['name'],
                    'cycle' => $level['cycle'],
                    'sort_order' => $index + 1,
                    'has_national_exam' => in_array($level['code'], ['G6', 'G8', 'G12'], true),
                ],
            );
        }
    }

    private function cycleFor(int $grade): Cycle
    {
        return match (true) {
            $grade <= 4 => Cycle::LowerPrimary,
            $grade <= 8 => Cycle::UpperPrimary,
            $grade <= 10 => Cycle::Secondary,
            default => Cycle::Preparatory,
        };
    }
}
