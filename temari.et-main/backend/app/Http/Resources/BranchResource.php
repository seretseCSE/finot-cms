<?php

namespace App\Http\Resources;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Geo coordinates are only exposed to users with the `branches.view_geo`
 * permission (Temari.et staff) — never to school principals/admins.
 *
 * @mixin Branch
 */
class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canViewGeo = $request->user()?->hasPlatformPermission('branches.view_geo') ?? false;

        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'school' => $this->whenLoaded('school', fn () => [
                'id' => $this->school->id,
                'name' => $this->school->name,
            ]),
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'address' => [
                'country' => $this->country,
                'state' => $this->state,
                'city' => $this->city,
                'sub_city' => $this->sub_city,
                'woreda' => $this->woreda,
                'house_no' => $this->house_no,
            ],
            'longitude' => $this->when($canViewGeo, $this->longitude),
            'latitude' => $this->when($canViewGeo, $this->latitude),
            'programs' => $this->whenLoaded('programs', fn () => $this->programs
                ->where('is_active', true)
                ->map(fn ($program) => [
                    'id' => $program->id,
                    'type' => $program->type,
                    'name' => $program->name,
                    // Present only when the offering matrix was eager-loaded
                    // (show/store/update — not list rows).
                    'grade_level_ids' => $program->relationLoaded('gradeLevels')
                        ? $program->gradeLevels->pluck('id')->values()
                        : null,
                ])
                ->values()),
            'director' => $this->when(
                $this->relationLoaded('directorMembership'),
                fn () => $this->directorMembership
                    ? ContactResource::fromMembership($this->directorMembership)
                    : null,
            ),
            'is_active' => $this->is_active,
            // List-table vitals — present only when queried withListStats().
            'students_count' => $this->whenHas('students_count'),
            'teachers_count' => $this->whenHas('teachers_count'),
            'sections_count' => $this->whenHas('sections_count'),
            'grade_min' => $this->whenHas('grade_min'),
            'grade_max' => $this->whenHas('grade_max'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
