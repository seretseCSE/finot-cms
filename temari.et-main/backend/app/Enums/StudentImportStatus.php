<?php

namespace App\Enums;

enum StudentImportStatus: string
{
    /** Rows are being uploaded / validated / fixed — nothing written yet. */
    case Draft = 'draft';

    /** The queued import job is executing rows. */
    case Importing = 'importing';

    /** The job finished (individual rows may still have failed). */
    case Completed = 'completed';

    /** The job itself crashed before finishing. */
    case Failed = 'failed';
}
