<?php

namespace App\Support;

use App\Models\School;
use Illuminate\Support\Facades\Cache;

/**
 * Cached reads of the school-scope finance control settings. The kernel and
 * the auth payload both consult these, so the flags live behind one 5-minute
 * cache (busted on SchoolController@updateSettings).
 */
final class FinanceControls
{
    /** @var array<int, bool> */
    private static array $directorAccess = [];

    /**
     * Whether branch directors hold finance authority at this school
     * (`director_finance_access` school setting, default off).
     */
    public static function directorAccess(int $schoolId): bool
    {
        return self::$directorAccess[$schoolId] ??= (bool) Cache::remember(
            "school:{$schoolId}:director_finance_access",
            300,
            fn (): bool => (bool) (School::query()->whereKey($schoolId)->first(['id', 'settings'])
                ?->directorFinanceAccessEnabled() ?? false),
        );
    }

    /** Reset the per-request memo (tests). */
    public static function flush(): void
    {
        self::$directorAccess = [];
    }
}
