<?php

namespace App\Enums;

/**
 * Tutor profile lifecycle. Only `approved` profiles appear in the public
 * directory or may take engagements; every approval is a Temari.et staff
 * decision (`tutors.review`) after checking credentials + Fayda ID.
 * Suspension hides the profile and freezes new business but never touches
 * history or the wallet.
 */
enum TutorStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Suspended = 'suspended';

    public function isPubliclyVisible(): bool
    {
        return $this === self::Approved;
    }

    /** Statuses the tutor may edit + (re)submit from. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Declined], true);
    }
}
