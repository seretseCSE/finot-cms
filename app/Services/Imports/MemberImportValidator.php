<?php

namespace App\Services\Imports;

use App\Enums\MemberImportRowStatus;
use App\Models\Member;
use App\Models\MemberImport;
use App\Models\MemberImportRow;

class MemberImportValidator
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function ingest(MemberImport $import, array $rows, array $columnMap): void
    {
        $import->rows()->delete();
        $import->update([
            'column_map' => $columnMap,
            'total_count' => count($rows),
        ]);

        foreach ($rows as $index => $raw) {
            $data = $this->mapRow($raw, $columnMap);
            $issues = [];
            $status = MemberImportRowStatus::Ready;
            $duplicateId = null;

            if (blank($data['first_name'] ?? null) || blank($data['father_name'] ?? null)) {
                $issues[] = 'first_name and father_name are required';
                $status = MemberImportRowStatus::Error;
            }

            if (filled($data['phone'] ?? null)) {
                $duplicate = Member::query()->where('phone', $data['phone'])->first();
                if ($duplicate) {
                    $status = MemberImportRowStatus::Duplicate;
                    $duplicateId = $duplicate->id;
                    $issues[] = 'phone already exists';
                }
            }

            MemberImportRow::query()->create([
                'member_import_id' => $import->id,
                'row_number' => $index + 1,
                'data' => $data,
                'status' => $status,
                'issues' => $issues ?: null,
                'duplicate_member_id' => $duplicateId,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, string>  $columnMap
     * @return array<string, mixed>
     */
    public function mapRow(array $raw, array $columnMap): array
    {
        $mapped = [];
        foreach ($columnMap as $field => $header) {
            $mapped[$field] = $raw[$header] ?? $raw[$field] ?? null;
        }

        return array_merge($raw, $mapped);
    }
}
