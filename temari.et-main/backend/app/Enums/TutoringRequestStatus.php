<?php

namespace App\Enums;

/**
 * A family's (or adult learner's) request to hire a tutor. Accepting one
 * creates the engagement; everything else is terminal.
 */
enum TutoringRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}
