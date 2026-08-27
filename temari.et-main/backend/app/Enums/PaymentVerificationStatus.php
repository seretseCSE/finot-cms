<?php

namespace App\Enums;

enum PaymentVerificationStatus: string
{
    case Verified = 'verified';
    case Failed = 'failed';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'Verified',
            self::Failed => 'Failed',
            self::NeedsReview => 'Needs review',
        };
    }
}
