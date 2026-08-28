<?php

namespace App\Enums;

enum MemberImportRowStatus: string
{
    case Ready = 'ready';
    case Duplicate = 'duplicate';
    case Error = 'error';
    case Imported = 'imported';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
