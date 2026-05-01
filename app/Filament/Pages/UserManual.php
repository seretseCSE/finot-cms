<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;
use BackedEnum;

class UserManual extends Page
{
    protected static ?string $title = 'User Manual';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'User Manual';

    protected static string | UnitEnum | null $navigationGroup = 'Help';

    protected static ?int $navigationSort = 100;

    public function getView(): string
    {
        return 'filament.pages.user-manual';
    }

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::check();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function getHeading(): string
    {
        return 'User Manual';
    }

    public function getSubheading(): ?string
    {
        return 'Role-specific guides to help you navigate the system';
    }

    public function getUserRole(): string
    {
        $user = Auth::user();

        if (! $user) {
            return 'superadmin';
        }

        $roles = $user->roles->pluck('name')->toArray();

        foreach ($roles as $role) {
            if (in_array($role, ['superadmin', 'admin', 'hr_head', 'finance_head',
                'nibret_hisab_head', 'inventory_staff', 'education_head', 'education_monitor',
                'worship_monitor', 'mezmur_head', 'av_head', 'charity_head', 'tour_head',
                'internal_relations_head', 'department_secretary', 'staff', ], true)) {
                return $role;
            }
        }

        return 'staff';
    }

    public function getTabs(): array
    {
        $tabs = [];

        if (Auth::user()?->hasRole(['superadmin'])) {
            $tabs['superadmin'] = [
                'label' => 'Super Admin',
                'icon' => 'heroicon-o-shield-check',
                'color' => 'danger',
            ];
        }

        if (Auth::user()?->hasRole(['admin', 'superadmin'])) {
            $tabs['admin'] = [
                'label' => 'Admin',
                'icon' => 'heroicon-o-user-group',
                'color' => 'primary',
            ];
        }

        if (Auth::user()?->hasRole(['hr_head', 'admin', 'superadmin'])) {
            $tabs['hr_head'] = [
                'label' => 'HR Head',
                'icon' => 'heroicon-o-users',
                'color' => 'success',
            ];
        }

        if (Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin'])) {
            $tabs['finance_head'] = [
                'label' => 'Finance',
                'icon' => 'heroicon-o-banknotes',
                'color' => 'warning',
            ];

            if (Auth::user()?->hasRole(['nibret_hisab_head', 'admin', 'superadmin'])) {
                $tabs['nibret_hisab_head'] = [
                    'label' => 'Nibret Hisab',
                    'icon' => 'heroicon-o-calculator',
                    'color' => 'warning',
                ];
            }
        }

        if (Auth::user()?->hasRole(['inventory_staff', 'nibret_hisab_head', 'admin', 'superadmin'])) {
            $tabs['inventory_staff'] = [
                'label' => 'Inventory',
                'icon' => 'heroicon-o-archive-box',
                'color' => 'success',
            ];
        }

        if (Auth::user()?->hasRole(['education_head', 'education_monitor', 'admin', 'superadmin'])) {
            $tabs['education_head'] = [
                'label' => 'Education',
                'icon' => 'heroicon-o-academic-cap',
                'color' => 'info',
            ];
        }

        if (Auth::user()?->hasRole(['worship_monitor', 'mezmur_head', 'admin', 'superadmin'])) {
            $tabs['worship_monitor'] = [
                'label' => 'Worship',
                'icon' => 'heroicon-o-musical-note',
                'color' => 'info',
            ];
        }

        if (Auth::user()?->hasRole(['av_head', 'admin', 'superadmin'])) {
            $tabs['av_head'] = [
                'label' => 'AV / Media',
                'icon' => 'heroicon-o-camera',
                'color' => 'primary',
            ];
        }

        if (Auth::user()?->hasRole(['charity_head', 'admin', 'superadmin'])) {
            $tabs['charity_head'] = [
                'label' => 'Charity',
                'icon' => 'heroicon-o-heart',
                'color' => 'danger',
            ];
        }

        if (Auth::user()?->hasRole(['tour_head', 'admin', 'superadmin'])) {
            $tabs['tour_head'] = [
                'label' => 'Tours',
                'icon' => 'heroicon-o-globe-alt',
                'color' => 'warning',
            ];
        }

        if (Auth::user()?->hasRole(['internal_relations_head', 'admin', 'superadmin'])) {
            $tabs['internal_relations_head'] = [
                'label' => 'Internal Relations',
                'icon' => 'heroicon-o-hand-raised',
                'color' => 'info',
            ];
        }

        if (Auth::user()?->hasRole(['department_secretary', 'staff', 'admin', 'superadmin'])) {
            $tabs['department_secretary'] = [
                'label' => 'Secretary / Staff',
                'icon' => 'heroicon-o-clipboard-document',
                'color' => 'gray',
            ];
        }

        return $tabs;
    }

    public function getUserDisplayRoles(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        return $user->roles->map(fn ($role) => [
            'name' => $role->name,
            'label' => ucwords(str_replace('_', ' ', $role->name)),
        ])->toArray();
    }
}
