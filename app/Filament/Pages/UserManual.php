<?php

namespace App\Filament\Pages;

use App\Support\RoleGate;
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
        return RoleGate::check();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getHeading(): string
    {
        return 'User Manual';
    }

    public function getSubheading(): ?string
    {
        return 'Full training guide: what each role can do, and how work moves from one person to the next';
    }

    /**
     * Preferred tab for the signed-in user.
     */
    public function getUserRole(): string
    {
        $user = Auth::user();

        if (! $user) {
            return 'superadmin';
        }

        $known = array_keys($this->allRoleTabs());

        foreach ($user->roles->pluck('name') as $role) {
            if (in_array($role, $known, true)) {
                return $role;
            }
        }

        return array_key_first($this->getTabs()) ?: 'superadmin';
    }

    /**
     * Super Admin and Admin see every role (for training and printing).
     * Other users see only the guides for roles they actually have.
     *
     * @return array<string, array{label: string, icon: string, color: string}>
     */
    public function getTabs(): array
    {
        $all = $this->allRoleTabs();

        if (RoleGate::isAny(['superadmin', 'admin'])) {
            return $all;
        }

        $owned = Auth::user()?->roles->pluck('name')->all() ?? [];

        return array_filter(
            $all,
            fn (string $key): bool => in_array($key, $owned, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return array<string, array{label: string, icon: string, color: string}>
     */
    protected function allRoleTabs(): array
    {
        return [
            'superadmin' => [
                'label' => 'Super Admin',
                'icon' => 'heroicon-o-shield-check',
                'color' => 'danger',
            ],
            'admin' => [
                'label' => 'Admin',
                'icon' => 'heroicon-o-user-group',
                'color' => 'primary',
            ],
            'hr_head' => [
                'label' => 'HR Head',
                'icon' => 'heroicon-o-users',
                'color' => 'success',
            ],
            'internal_relations_head' => [
                'label' => 'Internal Relations',
                'icon' => 'heroicon-o-hand-raised',
                'color' => 'info',
            ],
            'finance_head' => [
                'label' => 'Finance',
                'icon' => 'heroicon-o-banknotes',
                'color' => 'warning',
            ],
            'nibret_hisab_head' => [
                'label' => 'Nibret Hisab',
                'icon' => 'heroicon-o-calculator',
                'color' => 'warning',
            ],
            'inventory_staff' => [
                'label' => 'Inventory',
                'icon' => 'heroicon-o-archive-box',
                'color' => 'success',
            ],
            'education_head' => [
                'label' => 'Education Head',
                'icon' => 'heroicon-o-academic-cap',
                'color' => 'info',
            ],
            'education_monitor' => [
                'label' => 'Education Monitor',
                'icon' => 'heroicon-o-clipboard-document-check',
                'color' => 'info',
            ],
            'data_encoder' => [
                'label' => 'Data Encoder',
                'icon' => 'heroicon-o-pencil-square',
                'color' => 'info',
            ],
            'student' => [
                'label' => 'Student',
                'icon' => 'heroicon-o-identification',
                'color' => 'gray',
            ],
            'mezmur_head' => [
                'label' => 'Mezmur Head',
                'icon' => 'heroicon-o-musical-note',
                'color' => 'info',
            ],
            'worship_monitor' => [
                'label' => 'Worship Monitor',
                'icon' => 'heroicon-o-musical-note',
                'color' => 'info',
            ],
            'av_head' => [
                'label' => 'AV / Media',
                'icon' => 'heroicon-o-camera',
                'color' => 'primary',
            ],
            'charity_head' => [
                'label' => 'Charity',
                'icon' => 'heroicon-o-heart',
                'color' => 'danger',
            ],
            'tour_head' => [
                'label' => 'Tours',
                'icon' => 'heroicon-o-globe-alt',
                'color' => 'warning',
            ],
            'revenue_and_charity_head' => [
                'label' => 'Revenue & Charity',
                'icon' => 'heroicon-o-currency-dollar',
                'color' => 'success',
            ],
        ];
    }

    /**
     * @return list<array{name: string, label: string}>
     */
    public function getUserDisplayRoles(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        return $user->roles->map(fn ($role) => [
            'name' => $role->name,
            'label' => $role->label ?: ucwords(str_replace('_', ' ', $role->name)),
        ])->toArray();
    }
}
