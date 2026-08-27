<?php

namespace App\Http\Resources;

use App\Models\SchoolDirectoryEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SchoolDirectoryEntry */
class SchoolDirectoryEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'region' => $this->region,
            'zone' => $this->zone,
            'city' => $this->city,
            'school_id' => $this->school_id,
            'school_name' => $this->whenLoaded('school', fn () => $this->school?->name),
            'is_verified' => $this->is_verified,
            'created_by_school_name' => $this->whenLoaded(
                'createdBySchool',
                fn () => $this->createdBySchool?->name,
            ),
            'created_at' => $this->created_at,
        ];
    }
}
