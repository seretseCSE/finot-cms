<?php

namespace App\Filament\Widgets;

use App\Models\Beneficiary;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveBeneficiariesWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeCount = Beneficiary::where('status', 'Active')->count();
        
        return [
            Stat::make('Active Beneficiaries', $activeCount)
                ->description('Total active beneficiaries')
                ->icon('heroicon-o-users')
                ->color('success'),
        ];
    }

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['charity_head', 'internal_relations_head', 'admin', 'superadmin']);
    }
}

