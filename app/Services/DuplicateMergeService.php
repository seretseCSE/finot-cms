<?php

namespace App\Services;

use App\Models\DuplicateRecord;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DuplicateMergeService
{
    /**
     * Find potential duplicate members based on criteria.
     */
    public function findDuplicateMembers(): array
    {
        $duplicates = [];

        // Find duplicates by phone
        $phoneDuplicates = Member::query()
            ->select('phone', DB::raw('COUNT(*) as count'))
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->having('count', '>', 1)
            ->get();

        foreach ($phoneDuplicates as $dup) {
            $members = Member::query()
                ->where('phone', $dup->phone)
                ->orderBy('created_at')
                ->get();

            $primary = $members->first();
            foreach ($members->skip(1) as $duplicate) {
                $duplicates[] = [
                    'model_type' => Member::class,
                    'primary_record_id' => $primary->id,
                    'duplicate_record_id' => $duplicate->id,
                    'match_criteria' => ['phone' => $dup->phone],
                ];
            }
        }

        // Find duplicates by name combination
        $nameDuplicates = Member::query()
            ->select(
                'first_name',
                'father_name',
                'grandfather_name',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('first_name', 'father_name', 'grandfather_name')
            ->having('count', '>', 1)
            ->get();

        foreach ($nameDuplicates as $dup) {
            $members = Member::query()
                ->where('first_name', $dup->first_name)
                ->where('father_name', $dup->father_name)
                ->where('grandfather_name', $dup->grandfather_name)
                ->orderBy('created_at')
                ->get();

            $primary = $members->first();
            foreach ($members->skip(1) as $duplicate) {
                $duplicates[] = [
                    'model_type' => Member::class,
                    'primary_record_id' => $primary->id,
                    'duplicate_record_id' => $duplicate->id,
                    'match_criteria' => [
                        'first_name' => $dup->first_name,
                        'father_name' => $dup->father_name,
                        'grandfather_name' => $dup->grandfather_name,
                    ],
                ];
            }
        }

        return $duplicates;
    }

    /**
     * Store detected duplicates in the database.
     */
    public function storeDetectedDuplicates(array $duplicates): int
    {
        $count = 0;

        foreach ($duplicates as $dup) {
            $exists = DuplicateRecord::query()
                ->where('model_type', $dup['model_type'])
                ->where('primary_record_id', $dup['primary_record_id'])
                ->where('duplicate_record_id', $dup['duplicate_record_id'])
                ->exists();

            if (! $exists) {
                DuplicateRecord::create($dup);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Merge a duplicate member into the primary member.
     */
    public function mergeMember(int $duplicateRecordId, int $userId): void
    {
        $duplicateRecord = DuplicateRecord::query()->findOrFail($duplicateRecordId);

        if ($duplicateRecord->status !== 'pending') {
            throw new \RuntimeException('Duplicate record has already been processed.');
        }

        $primary = Member::query()->findOrFail($duplicateRecord->primary_record_id);
        $duplicate = Member::query()->findOrFail($duplicateRecord->duplicate_record_id);

        DB::transaction(function () use ($primary, $duplicate, $duplicateRecord, $userId): void {
            // Merge related records
            $this->transferMemberRelations($primary, $duplicate);

            // Soft delete the duplicate member
            $duplicate->delete();

            // Mark duplicate record as merged
            $duplicateRecord->markAsMerged($userId);

            Log::info('Member merged successfully', [
                'primary_id' => $primary->id,
                'duplicate_id' => $duplicate->id,
                'merged_by' => $userId,
            ]);
        });
    }

    /**
     * Transfer relations from duplicate to primary member.
     */
    protected function transferMemberRelations(Member $primary, Member $duplicate): void
    {
        // Update attendance records
        DB::table('attendance_records')
            ->where('member_id', $duplicate->id)
            ->update(['member_id' => $primary->id]);

        // Update student enrollments
        DB::table('student_enrollments')
            ->where('member_id', $duplicate->id)
            ->update(['member_id' => $primary->id]);

        // Update contributions
        DB::table('contributions')
            ->where('member_id', $duplicate->id)
            ->update(['member_id' => $primary->id]);

        // Update member group assignments
        DB::table('member_group_assignments')
            ->where('member_id', $duplicate->id)
            ->update(['member_id' => $primary->id]);

        // Update student attendances
        DB::table('student_attendances')
            ->where('student_id', $duplicate->id)
            ->update(['student_id' => $primary->id]);

        // Update parent guardians where member is the child
        DB::table('member_parent_guardians')
            ->where('member_id', $duplicate->id)
            ->update(['member_id' => $primary->id]);
    }
}
