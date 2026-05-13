<?php

namespace App\Services;

use App\Models\Member;
use App\Models\TourPassenger;

class TourPhoneLookupService
{
    /**
     * Lookup phone number for tour registration
     */
    public function lookup(string $phone, int $tourId): array
    {
        $normalizedPhone = $this->normalizePhone($phone);

        // Check members table first
        $member = Member::where('phone', $normalizedPhone)->first();
        if ($member) {
            return [
                'found' => true,
                'source' => 'member',
                'full_name' => $member->full_name,
                'member_id' => $member->id,
                'message' => 'Member found',
            ];
        }

        // Check previous tour registrations
        $previousPassenger = TourPassenger::where('phone', $normalizedPhone)
            ->whereHas('tour', function ($query) {
                $query->where('status', 'Completed');
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if ($previousPassenger) {
            return [
                'found' => true,
                'source' => 'previous',
                'full_name' => $previousPassenger->full_name,
                'member_id' => null,
                'message' => 'Previous passenger found',
            ];
        }

        return [
            'found' => false,
            'source' => 'new',
            'full_name' => null,
            'member_id' => null,
            'message' => 'New passenger – enter details manually',
        ];
    }

    /**
     * Normalize phone number with country prefix
     */
    private function normalizePhone(string $phone): string
    {
        $phonePrefix = config('finot.phone_prefix', '+251');

        if (! str_starts_with($phone, $phonePrefix)) {
            return $phonePrefix . preg_replace('/^0?/', '', $phone);
        }

        return $phone;
    }
}
