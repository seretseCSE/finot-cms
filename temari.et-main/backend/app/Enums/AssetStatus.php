<?php

namespace App\Enums;

/**
 * Asset unit lifecycle. ASSIGNED is set/cleared only by the assign/return
 * endpoints; LOST auto-closes any open custody; DISPOSED is terminal.
 */
enum AssetStatus: string
{
    case InStore = 'in_store';
    case Assigned = 'assigned';
    case UnderRepair = 'under_repair';
    case Lost = 'lost';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::InStore => 'In store',
            self::Assigned => 'Assigned',
            self::UnderRepair => 'Under repair',
            self::Lost => 'Lost',
            self::Disposed => 'Disposed',
        };
    }
}
