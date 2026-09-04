<?php

namespace App\Enums;

/**
 * One tutoring session inside a cycle. The tutor logs it (`logged`), the
 * family confirms (or it auto-confirms after Marketplace::AUTO_CONFIRM_HOURS);
 * only CONFIRMED hours count toward the release. Disputes freeze just this
 * session's value until Temari.et staff resolve.
 */
enum TutoringSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Logged = 'logged';
    case Confirmed = 'confirmed';
    case Disputed = 'disputed';
    case Canceled = 'canceled';
}
