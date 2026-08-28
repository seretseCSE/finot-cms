<?php

namespace App\Jobs;

use App\Enums\MemberImportRowStatus;
use App\Enums\MemberImportStatus;
use App\Models\Member;
use App\Models\MemberImport;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Identity\ProvisionStudentUser;
use App\Services\Notifications\Notifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CommitMemberImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $importId, public int $actorId)
    {
    }

    public function handle(Notifier $notifier): void
    {
        $import = MemberImport::query()->with('rows')->findOrFail($this->importId);
        $actor = User::query()->findOrFail($this->actorId);

        $import->update(['status' => MemberImportStatus::Importing]);

        ProvisionStudentUser::$enabled = false;

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        try {
            DB::transaction(function () use ($import, $actor, &$imported, &$skipped, &$failed): void {
                foreach ($import->rows as $row) {
                    if ($row->status === MemberImportRowStatus::Skipped) {
                        $skipped++;

                        continue;
                    }

                    if ($row->status === MemberImportRowStatus::Error) {
                        $failed++;

                        continue;
                    }

                    if ($row->status === MemberImportRowStatus::Duplicate && ($row->resolution !== 'update')) {
                        $skipped++;
                        $row->update(['status' => MemberImportRowStatus::Skipped]);

                        continue;
                    }

                    try {
                        $data = $row->data ?? [];
                        $member = $row->duplicate_member_id
                            ? Member::query()->find($row->duplicate_member_id)
                            : new Member();

                        $member->fill([
                            'first_name' => $data['first_name'] ?? $member->first_name,
                            'father_name' => $data['father_name'] ?? $member->father_name,
                            'grandfather_name' => $data['grandfather_name'] ?? $member->grandfather_name ?? $data['father_name'] ?? 'N/A',
                            'mother_name' => $data['mother_name'] ?? $member->mother_name ?? 'N/A',
                            'date_of_birth' => $data['date_of_birth'] ?? $member->date_of_birth ?? '2015-01-01',
                            'phone' => $data['phone'] ?? $member->phone,
                            'gender' => $data['gender'] ?? $member->gender ?? 'Male',
                            'status' => 'Active',
                            'member_type' => $data['member_type'] ?? $member->member_type ?? 'Kids',
                            'city' => $data['city'] ?? $member->city ?? 'Addis Ababa',
                            'sub_city' => $data['sub_city'] ?? $member->sub_city ?? 'N/A',
                            'woreda' => $data['woreda'] ?? $member->woreda ?? '1',
                            'department_id' => $import->department_id ?? $member->department_id,
                            'emergency_contact_name' => $data['emergency_contact_name'] ?? $member->emergency_contact_name ?? 'N/A',
                            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? $member->emergency_contact_phone ?? ($data['phone'] ?? $member->phone),
                        ]);
                        $member->save();

                        if ($import->class_id && $import->academic_year_id) {
                            StudentEnrollment::query()->firstOrCreate(
                                [
                                    'member_id' => $member->id,
                                    'class_id' => $import->class_id,
                                    'academic_year_id' => $import->academic_year_id,
                                ],
                                [
                                    'enrolled_date' => now()->toDateString(),
                                    'status' => 'Enrolled',
                                    'enrolled_by' => $actor->id,
                                ]
                            );
                        }

                        $row->update([
                            'status' => MemberImportRowStatus::Imported,
                            'member_id' => $member->id,
                        ]);
                        $imported++;
                    } catch (\Throwable $e) {
                        $row->update([
                            'status' => MemberImportRowStatus::Failed,
                            'error' => $e->getMessage(),
                        ]);
                        $failed++;
                    }
                }
            });

            $import->update([
                'status' => MemberImportStatus::Completed,
                'imported_count' => $imported,
                'skipped_count' => $skipped,
                'failed_count' => $failed,
                'committed_at' => now(),
                'finished_at' => now(),
            ]);

            $notifier->toUser($actor, 'imports.committed', [
                'imported' => $imported,
                'skipped' => $skipped,
                'failed' => $failed,
            ]);
        } catch (\Throwable $e) {
            $import->update([
                'status' => MemberImportStatus::Failed,
                'finished_at' => now(),
            ]);
            throw $e;
        } finally {
            ProvisionStudentUser::$enabled = true;
        }
    }
}
