<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\School;
use App\Services\Ai\AtRiskAnalysisService;
use App\Services\Notify\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use function Laravel\Ai\agent;

/**
 * Weekly AI briefing for school leadership — School Plan schools only.
 * Per active branch: the at-risk snapshot is summarized by the CHEAP model
 * into two sentences and delivered through the notification pipeline
 * (in-app only, deduped per ISO week). The full conversation happens in
 * /ai; this is the nudge that brings leaders there.
 */
class AiWeeklyBriefings extends Command
{
    protected $signature = 'ai:weekly-briefings';

    protected $description = 'Send the weekly School AI briefing to leadership of School Plan schools';

    public function handle(AtRiskAnalysisService $atRisk, Notifier $notifier): int
    {
        $week = now()->format('o-W');
        $sent = 0;

        School::query()
            ->where('is_active', true)
            ->whereDate('ai_plan_until', '>=', today())
            ->with(['branches' => fn ($q) => $q->where('is_active', true)])
            ->chunkById(20, function ($schools) use ($atRisk, $notifier, $week, &$sent): void {
                foreach ($schools as $school) {
                    foreach ($school->branches as $branch) {
                        $summary = $this->branchSummary($atRisk, $branch);

                        if ($summary === null) {
                            continue;
                        }

                        $notifier->toStaff($school->id, $branch->id, 'reports.view', 'ai.weekly_briefing', [
                            'summary' => $summary,
                        ], [
                            'link' => '/ai',
                            'dedupeKey' => "ai_briefing:{$branch->id}:{$week}",
                        ]);

                        $sent++;
                    }
                }
            });

        $this->info("Briefings sent for {$sent} branches.");

        return self::SUCCESS;
    }

    private function branchSummary(AtRiskAnalysisService $atRisk, Branch $branch): ?string
    {
        $analysis = $atRisk->analyze(collect([$branch->id]), includeArrears: false, limit: 10);

        if ($analysis['students'] === []) {
            return null;
        }

        $count = count($analysis['students']);
        $fallback = "{$count} students need attention this week — open Temari AI for the details.";

        try {
            $response = agent(
                'You summarize school early-warning data for a principal in TWO sentences max: how many students need attention and the dominant reasons. Factual, calm, no names.',
            )->prompt(
                json_encode($analysis['students'], JSON_UNESCAPED_UNICODE) ?: '[]',
                model: (string) config('temari-ai.light_model'),
                timeout: 30,
            );

            $summary = trim($response->text);

            return $summary !== '' ? Str::limit($summary, 400) : $fallback;
        } catch (\Throwable $e) {
            Log::info('AI briefing summary fell back.', ['branch_id' => $branch->id, 'error' => $e->getMessage()]);

            return $fallback;
        }
    }
}
