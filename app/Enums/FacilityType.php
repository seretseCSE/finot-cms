<?php

namespace App\Enums;

enum FacilityType: string
{
    case Office = 'office';
    case Hall = 'hall';
    case Classroom = 'classroom';
    case Other = 'other';
}
