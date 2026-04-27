<?php

namespace App\Jobs;

use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\MemberGroupAssignment;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkAssignToGroupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @param array<int> $memberIds
     */
    public function __construct(
        public readonly array $memberIds,
        public readonly int $groupId,
        public readonly string $effectiveFrom,
        public readonly int $assignedBy,
    ) {
    }

    public function handle(): void
    {
        $group = MemberGroup::query()->findOrFail($this->groupId);
        $members = Member::query()->whereIn('id', $this->memberIds)->get();
        $effectiveFrom = $this->effectiveFrom;

        DB::transaction(function () use ($members, $group, $effectiveFrom): void {
            foreach ($members as $member) {
                MemberGroupAssignment::query()
                    ->forMember($member->id)
                    ->active()
                    ->update([
                        'effective_to' => $effectiveFrom,
                        'removed_by' => $this->assignedBy,
                    ]);

                $assignment = MemberGroupAssignment::create([
                    'member_id' => $member->id,
                    'group_id' => $group->id,
                    'effective_from' => $effectiveFrom,
                    'assigned_by' => $this->assignedBy,
                    'created_by' => $this->assignedBy,
                ]);

                Log::channel('audit')->warning('Tier 2 Audit Log', [
                    'tier' => '2',
                    'action' => 'member_group_assigned',
                    'member_id' => $member->id,
                    'member_name' => $member->full_name,
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'effective_from' => $assignment->effective_from?->toDateString(),
                    'assigned_by' => $this->assignedBy,
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }
        });

        Notification::make()
            ->title('Assignment successful')
            ->body("{$members->count()} members assigned to {$group->name} successfully")
            ->success()
            ->sendToDatabase(\App\Models\User::find($this->assignedBy));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BulkAssignToGroupJob failed', [
            'member_ids' => $this->memberIds,
            'group_id' => $this->groupId,
            'error' => $exception->getMessage(),
        ]);

        Notification::make()
            ->title('Assignment Failed')
            ->body('Bulk group assignment failed: ' . $exception->getMessage())
            ->danger()
            ->sendToDatabase(\App\Models\User::find($this->assignedBy));
    }
}
