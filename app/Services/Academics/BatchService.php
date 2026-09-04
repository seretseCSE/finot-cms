<?php

namespace App\Services\Academics;

use App\Models\Batch;
use App\Models\BatchYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BatchService
{
    public function create(array $data, ?User $actor = null): Batch
    {
        return DB::transaction(function () use ($data, $actor) {
            $tenure = max(1, min(10, (int) ($data['tenure_years'] ?? 4)));

            $batch = Batch::query()->create([
                'name' => $data['name'],
                'start_year' => $data['start_year'] ?? null,
                'tenure_years' => $tenure,
                'status' => $data['status'] ?? 'open',
                'created_by' => $actor?->id,
            ]);

            for ($year = 1; $year <= $tenure; $year++) {
                BatchYear::query()->create([
                    'batch_id' => $batch->id,
                    'program_year' => $year,
                    'name' => $data['year_names'][$year] ?? ('Year '.$year),
                    'status' => $year === 1 ? 'active' : 'planned',
                ]);
            }

            return $batch->fresh(['years']);
        });
    }

    public function close(Batch $batch): Batch
    {
        $batch->update(['status' => 'closed']);
        $batch->years()->update(['status' => 'completed']);

        return $batch->fresh(['years']);
    }
}
