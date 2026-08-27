<?php

namespace App\Ai\Tools\Leadership;

use App\Services\Ai\AtRiskAnalysisService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The early-warning list: students whose attendance and/or results say
 * they need support now. Same engine as the weekly AI briefing
 * (AtRiskAnalysisService); fee arrears join the picture only when the
 * caller's kernel scope actually includes fees.reports.view.
 */
class AtRiskStudentsTool extends LeadershipScopedTool
{
    public function description(): Stringable|string
    {
        return 'The early-warning list: students flagged by low/declining term averages and high absence, with the reasons per student. Use for "which students are at risk / need support". Never present this as blame — these students need help.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (($denied = $this->missingPermission('reports.view', 'grades.view', 'attendance.reports.view')) !== null) {
            return $this->deny($denied);
        }

        $result = app(AtRiskAnalysisService::class)->analyze(
            $this->branchIds($request->integer('branch_id') ?: null),
            includeArrears: $this->context->allows('fees.reports.view'),
            limit: min(max($request->integer('limit') ?: 15, 5), 30),
        );

        if ($result['students'] === []) {
            return $this->deny('No students currently trip the risk thresholds — or no computed results/attendance yet.');
        }

        return $this->ok($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('How many students (5–30, default 15).'),
            'branch_id' => $schema->integer()->description('School-wide sessions only: narrow to one branch.'),
        ];
    }
}
