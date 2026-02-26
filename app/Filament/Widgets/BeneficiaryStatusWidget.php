<?php

namespace App\Filament\Widgets;

use App\Models\Beneficiary;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class BeneficiaryStatusWidget extends ChartWidget
{
    protected ?string $heading = 'Beneficiary Status Distribution';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $data = Beneficiary::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Active',
                    'data' => $data->where('status', 'Active')->pluck('count'),
                    'backgroundColor' => '#10B981',
                ],
                [
                    'label' => 'Inactive',
                    'data' => $data->where('status', 'Inactive')->pluck('count'),
                    'backgroundColor' => '#F59E0B',
                ],
                [
                    'label' => 'Completed',
                    'data' => $data->where('status', 'Completed')->pluck('count'),
                    'backgroundColor' => '#6B7280',
                ],
            ],
            'labels' => $data->pluck('status')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['charity_head', 'internal_relations_head', 'admin', 'superadmin']);
    }
}

