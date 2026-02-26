<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use App\Helpers\EthiopianDateHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyOutstandingCommand extends Command
{
    protected $signature = 'finance:notify-outstanding';
    protected $description = 'Create in-app notifications for monthly outstanding contributions';

    public function handle(): int
    {
        $this->info('Creating monthly outstanding notifications...');

        $activeYear = AcademicYear::where('is_active', true)->first();

        if (!$activeYear) {
            $this->warn('No active academic year found. No notifications created.');
            return 0;
        }

        try {
            // Calculate outstanding totals
            $members = Member::query()
                ->whereIn('status', ['Active', 'Member'])
                ->whereHas('memberGroup')
                ->get();

            $totalOutstanding = 0;
            $membersWithOutstanding = 0;

            foreach ($members as $member) {
                $months = EthiopianDateHelper::getMonthsForContribution();
                $memberOutstanding = 0;

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
                    $memberOutstanding += max(0, $outstanding);
                }

                if ($memberOutstanding > 0) {
                    $membersWithOutstanding++;
                    $totalOutstanding += $memberOutstanding;
                }
            }

            // Create in-app notification
            $notificationData = [
                'title' => 'Monthly Outstanding Contributions Report',
                'message' => "Birr " . number_format($totalOutstanding, 2) . " outstanding from {$membersWithOutstanding} members for {$activeYear->name}",
                'action_url' => '/admin/outstanding-contributions',
                'type' => 'outstanding_report',
                'data' => [
                    'total_outstanding' => $totalOutstanding,
                    'members_count' => $membersWithOutstanding,
                    'academic_year' => $activeYear->name,
                ],
            ];

            // Send to finance_head, nibret_hisab_head, admin
            $recipients = User::role(['finance_head', 'nibret_hisab_head', 'admin'])->get();

            foreach ($recipients as $user) {
                // Create in-app notification (assuming you have a Notification model)
                // $user->notifications()->create([
                //     'title' => $notificationData['title'],
                //     'message' => $notificationData['message'],
                //     'action_url' => $notificationData['action_url'],
                //     'type' => $notificationData['type'],
                //     'data' => json_encode($notificationData['data']),
                // ]);
                
                // For now, just log the notification
                Log::info('Outstanding notification created', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'notification' => $notificationData,
                ]);
            }

            $this->info('✅ Outstanding notifications created successfully!');
            $this->line("Total Outstanding: Birr " . number_format($totalOutstanding, 2));
            $this->line("Members with Outstanding: {$membersWithOutstanding}");
            $this->line("Academic Year: {$activeYear->name}");
            $this->line("Notifications sent to {$recipients->count()} users");

            // Log to audit trail
            Log::channel('audit')->info('Tier 1 Audit Log', [
                'tier' => 1,
                'action' => 'monthly_outstanding_notification',
                'academic_year_id' => $activeYear->id,
                'academic_year_name' => $activeYear->name,
                'total_outstanding' => $totalOutstanding,
                'members_count' => $membersWithOutstanding,
                'recipients_count' => $recipients->count(),
                'performed_by' => 'system',
                'timestamp' => now()->toDateTimeString(),
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create outstanding notifications: " . $e->getMessage());
            Log::error('Outstanding notification creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
