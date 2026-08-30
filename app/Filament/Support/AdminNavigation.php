<?php

namespace App\Filament\Support;

use App\Support\RoleGate;
use Filament\Navigation\NavigationGroup;

class AdminNavigation
{
    /**
     * Keep the user's primary group expanded; collapse the rest.
     *
     * @return array<int, NavigationGroup>
     */
    public static function groups(): array
    {
        return [
            static::group('Membership Management', ['superadmin', 'admin', 'hr_head', 'internal_relations_head']),
            static::group('Education Management', ['superadmin', 'admin', 'education_head']),
            static::group('Attendance & Results', ['superadmin', 'admin', 'education_head', 'education_monitor', 'data_encoder']),
            static::group('Course Management', ['education_head', 'education_monitor']),
            static::group('Donations', ['superadmin', 'admin', 'finance_head', 'nibret_hisab_head']),
            static::group('Contributions', ['superadmin', 'admin', 'finance_head', 'nibret_hisab_head']),
            static::group('Finance', ['superadmin', 'admin', 'finance_head', 'nibret_hisab_head']),
            static::group('Charity Management', ['superadmin', 'admin', 'charity_head', 'revenue_and_charity_head']),
            static::group('Inventory Management', ['superadmin', 'admin', 'inventory_staff', 'nibret_hisab_head']),
            static::group('Tour Management', ['superadmin', 'admin', 'tour_head', 'revenue_and_charity_head']),
            static::group('Content Management', ['superadmin', 'admin', 'av_head', 'worship_monitor', 'mezmur_head']),
            static::group('Users & Access', ['superadmin', 'admin']),
            static::group('Operations', ['superadmin', 'admin']),
            static::group('Settings & Logs', ['superadmin', 'admin']),
        ];
    }

    /**
     * @param  list<string>  $primaryRoles
     */
    protected static function group(string $label, array $primaryRoles): NavigationGroup
    {
        return NavigationGroup::make($label)
            ->collapsed(fn (): bool => ! RoleGate::isAny($primaryRoles))
            ->collapsible(true);
    }
}
