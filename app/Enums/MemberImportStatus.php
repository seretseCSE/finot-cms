<?php

namespace App\Enums;

enum MemberImportStatus: string
{
    case Draft = 'draft';
    case Importing = 'importing';
    case Completed = 'completed';
    case Failed = 'failed';
}
