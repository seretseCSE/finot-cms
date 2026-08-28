<?php

namespace App\Enums;

enum WithdrawalRequestStatus: string
{
    case Pending = 'pending';
    case EducationApproved = 'education_approved';
    case Finalized = 'finalized';
    case Rejected = 'rejected';
}
