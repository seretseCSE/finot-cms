<?php

namespace App\Jobs;

use App\Models\Member;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkAssignToDepartmentJob implements ShouldQueue
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
        public readonly int $departmentId,
        public readonly ?string $reason,
        public readonly int $assignedBy,
    ) {
    }

    public function handle(): void
    {
        $department = \App\Models\Department::query()->findOrFail($this->departmentId);
        $memberCount = 0;

        DB::transaction(function () use ($department, &$memberCount): void {
            Member::query()
                ->whereIn('id', $this->memberIds)
                ->lazy()
                ->each(function ($member) use ($department, &$memberCount) {
                    $oldDepartmentId = $member->department_id;
                    $member->update([
                        'department_id' => $department->id,
                        'updated_by' => $this->assignedBy,
                    ]);

                    Log::channel('audit')->warning('Member Department Assignment Changed', [
                        'action' => 'bulk_department_assignment',
                        'member_id' => $member->id,
                        'member_name' => $member->full_name,
                        'old_department_id' => $oldDepartmentId,
                        'new_department_id' => $department->id,
                        'new_department_name' => $department->name_en,
                        'reason' => $this->reason,
                        'assigned_by' => $this->assignedBy,
                        'timestamp' => now()->toDateTimeString(),
                    ]);

                    $memberCount++;
                });
        });

        Notification::make()
            ->title('Department Assignment Successful')
            ->body("{$memberCount} members assigned to {$department->name_en} successfully")
            ->success()
            ->sendToDatabase(\App\Models\User::find($this->assignedBy));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BulkAssignToDepartmentJob failed', [
            'member_ids' => $this->memberIds,
            'department_id' => $this->departmentId,
            'error' => $exception->getMessage(),
        ]);

        Notification::make()
            ->title('Assignment Failed')
            ->body('Bulk department assignment failed: ' . $exception->getMessage())
            ->danger()
            ->sendToDatabase(\App\Models\User::find($this->assignedBy));
    }
}
