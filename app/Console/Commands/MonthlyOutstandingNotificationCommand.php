<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use App\Helpers\EthiopianDateHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonthlyOutstandingNotificationCommand extends Command
{
    protected $signature = 'finance:monthly-outstanding-notification';
    protected $description = 'Send monthly notification about outstanding contributions to finance head';

    public function handle(): int
    {
        $this->info('Generating monthly outstanding contributions report...');

        $activeYear = AcademicYear::where('is_active', true)->first();

        if (!$activeYear) {
            $this->warn('No active academic year found. No outstanding report generated.');
            return 0;
        }

        try {
            // Calculate outstanding totals for all active members
            $members = Member::query()
                ->whereIn('status', ['Active', 'Member'])
                ->whereHas('memberGroup')
                ->with(['memberGroup'])
                ->get();

            $totalExpected = 0;
            $totalCollected = 0;
            $totalOutstanding = 0;
            $outstandingMembers = [];

            foreach ($members as $member) {
                $memberExpected = 0;
                $memberCollected = 0;
                $memberOutstanding = 0;

                $months = EthiopianDateHelper::getMonthsForContribution();

                foreach ($months as $monthName) {
                    $expectedAmount = ContributionAmount::where('group_id', $member->member_group_id)
                        ->forMonth($monthName)
                        ->active()
                        ->value('amount') ?? 0;

                    $paidAmount = Contribution::forMemberAndYear($member->id, $activeYear->id)
                        ->forMonth($monthName)
                        ->notArchived()
                        ->sum('amount') ?? 0;

                    $outstanding = $expectedAmount - $paidAmount;

                    $memberExpected += $expectedAmount;
                    $memberCollected += $paidAmount;
                    $memberOutstanding += max(0, $outstanding);
                }

                if ($memberOutstanding > 0) {
                    $outstandingMembers[] = [
                        'name' => $member->full_name,
                        'group' => $member->memberGroup->name,
                        'expected' => $memberExpected,
                        'collected' => $memberCollected,
                        'outstanding' => $memberOutstanding,
                    ];
                }

                $totalExpected += $memberExpected;
                $totalCollected += $memberCollected;
                $totalOutstanding += $memberOutstanding;
            }

            $collectionRate = $totalExpected > 0 ? (($totalCollected / $totalExpected) * 100) : 0;

            // Create detailed report
            $report = [
                'academic_year' => $activeYear->name,
                'report_date' => now()->toDateTimeString(),
                'total_members' => $members->count(),
                'members_with_outstanding' => count($outstandingMembers),
                'total_expected' => $totalExpected,
                'total_collected' => $totalCollected,
                'total_outstanding' => $totalOutstanding,
                'collection_rate' => round($collectionRate, 2),
                'top_outstanding' => array_slice(
                    array_sort($outstandingMembers, fn($m) => $m['outstanding'], SORT_DESC),
                    0,
                    10
                ),
            ];

            // Log the report (could also send email/notification)
            Log::info('Monthly Outstanding Contributions Report', $report);

            $this->info('✅ Monthly outstanding report generated successfully!');
            $this->line("Academic Year: {$report['academic_year']}");
            $this->line("Total Members: {$report['total_members']}");
            $this->line("Members with Outstanding: {$report['members_with_outstanding']}");
            $this->line("Total Expected: Birr " . number_format($report['total_expected'], 2));
            $this->line("Total Collected: Birr " . number_format($report['total_collected'], 2));
            $this->line("Total Outstanding: Birr " . number_format($report['total_outstanding'], 2));
            $this->line("Collection Rate: {$report['collection_rate']}%");

            if (!empty($report['top_outstanding'])) {
                $this->line("\nTop 10 Outstanding Members:");
                foreach ($report['top_outstanding'] as $index => $member) {
                    $this->line(($index + 1) . ". {$member['name']} ({$member['group']}) - Birr " . number_format($member['outstanding'], 2));
                }
            }

            // Log to audit trail
            Log::channel('audit')->info('Monthly Outstanding Report Generated', [
                'tier' => 1,
                'action' => 'monthly_outstanding_report',
                'academic_year_id' => $activeYear->id,
                'academic_year_name' => $activeYear->name,
                'total_outstanding' => $totalOutstanding,
                'collection_rate' => $collectionRate,
                'performed_by' => 'system',
                'timestamp' => now()->toDateTimeString(),
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to generate outstanding report: " . $e->getMessage());
            Log::error('Monthly outstanding report generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
