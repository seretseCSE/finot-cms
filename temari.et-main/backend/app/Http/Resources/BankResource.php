<?php

namespace App\Http\Resources;

use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Bank */
class BankResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'logo' => $this->logo,
            'is_active' => $this->is_active,
            'accounts_count' => $this->whenCounted('accounts'),
            'created_at' => $this->created_at,
        ];
    }
}
