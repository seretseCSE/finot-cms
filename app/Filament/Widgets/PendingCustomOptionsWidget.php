<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ManageCustomOptions;
use App\Models\CustomOption;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class PendingCustomOptionsWidget extends Widget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $description = 'System configuration options awaiting review';

    /**
     * Get the widget's data.
     */
    protected function getData(): array
    {
        return [
            'count' => $this->getPendingOptionsCount(),
            'url' => ManageCustomOptions::getUrl(),
        ];
    }

    /**
     * Get the widget's data.
     */
    protected function getPendingOptionsCount(): int
    {
        return CustomOption::query()->pending()->count();
    }

    /**
     * Get the widget's data.
     */
    protected function getColumns(): int
    {
        return 1;
    }

    /**
     * Get the widget's data.
     */
    protected function getChartOptions(): array
    {
        return [
            'type' => 'line',
            'height' => 100,
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Render the widget.
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        $data = $this->getData();
        
        return view('filament.widgets.pending-custom-options-widget', [
            'count' => $data['count'],
            'url' => $data['url'],
            'description' => static::$description,
            'chartOptions' => $this->getChartOptions(),
        ]);
    }
}

