<?php

namespace App\Enums;

enum StudentImportRowStatus: string
{
    /** Validated clean — will import. Non-blocking notes may sit in issues. */
    case Ready = 'ready';

    /** Matches an existing student; the row's resolution decides its fate. */
    case Duplicate = 'duplicate';

    /** Blocking validation errors — excluded from commit until fixed. */
    case Error = 'error';

    /** Import wrote this row (student created or existing student enrolled). */
    case Imported = 'imported';

    /** Deliberately not imported (duplicate resolved as skip). */
    case Skipped = 'skipped';

    /** The import attempted this row and it errored — kept for the report. */
    case Failed = 'failed';
}
