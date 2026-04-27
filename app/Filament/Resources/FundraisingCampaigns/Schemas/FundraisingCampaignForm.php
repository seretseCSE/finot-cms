<?php

namespace App\Filament\Resources\FundraisingCampaigns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Illuminate\Support\HtmlString;

class FundraisingCampaignForm
{
    public static function getSchema(): array
    {
        return (new static())->buildSchema();
    }

    protected function buildSchema(): array
    {
        return [
            // Campaign Information
            Grid::make(2)->schema([
                TextInput::make('campaign_name')
                    ->label('Campaign Name')
                    ->required()
                    ->maxLength(255)
                    ->disabled(function () {
                        $user = auth()->user();

                        return $user?->hasRole('finance_head') || $user?->hasRole('nibret_hisab_head');
                    }),

                TextInput::make('target_amount')
                    ->label('Target Amount')
                    ->required()
                    ->numeric()
                    ->prefix('ETB')
                    ->step(0.01)
                    ->disabled(function () {
                        $user = auth()->user();

                        return $user?->hasRole('finance_head') || $user?->hasRole('nibret_hisab_head');
                    }),

                Textarea::make('description')
                    ->label('Description')
                    ->required()
                    ->rows(4)
                    ->maxLength(1000)
                    ->disabled(function () {
                        $user = auth()->user();

                        return $user?->hasRole('finance_head') || $user?->hasRole('nibret_hisab_head');
                    }),

                Select::make('campaign_category')
                    ->label('Campaign Category')
                    ->options([
                        'Building' => 'Building',
                        'Missionary' => 'Missionary',
                        'Charity' => 'Charity',
                        'General' => 'General',
                    ])
                    ->nullable(),
            ])
                ->columns(2),

            // Dates & Status
            Grid::make(3)->schema([
                DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required()
                    ->disabled(function () {
                        $user = auth()->user();

                        return $user?->hasRole('finance_head') || $user?->hasRole('nibret_hisab_head');
                    }),

                DatePicker::make('end_date')
                    ->label('End Date')
                    ->nullable()
                    ->afterOrEqual('start_date')
                    ->validationMessages([
                        'after_or_equal' => 'The end date must be on or after the start date.',
                    ])
                    ->disabled(function () {
                        $user = auth()->user();

                        return $user?->hasRole('finance_head') || $user?->hasRole('nibret_hisab_head');
                    }),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'Draft' => 'Draft (Not Visible)',
                        'Active' => 'Active (Visible)',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->default('Draft')
                    ->required()
                    ->disabled(function () {
                        $user = auth()->user();

                        return $user?->hasRole('finance_head') || $user?->hasRole('nibret_hisab_head');
                    }),
            ])
                ->columns(3),

            // Media & Banking
            FileUpload::make('featured_image')
                ->label('Featured Image')
                ->image()
                ->directory('fundraising-campaigns')
                ->disk('public')
                ->visibility('public')
                ->nullable()
                ->maxSize(2048)
                ->disabled(function () {
                    $user = auth()->user();

                    return $user?->hasRole('finance_head') || $user?->hasRole('nibret_hisab_head');
                }),

            Textarea::make('bank_account_info')
                ->label('Bank Account Information')
                ->placeholder('Enter bank account details for donations...')
                ->rows(3)
                ->nullable()
                ->disabled(function () {
                    $user = auth()->user();

                    return $user?->hasRole('finance_head') || $user?->hasRole('nibret_hisab_head');
                }),

            // Fundraising Progress
            Grid::make(1)->schema([
                Placeholder::make('current_total_raised')
                    ->label('Current Total Raised')
                    ->content(function ($record) {
                        if (! $record) {
                            return 'Not set';
                        }

                        return new HtmlString(
                            '<span class="text-2xl font-bold text-green-600">ETB '.
                            number_format($record->total_raised, 2).'</span>'
                        );
                    }),

                Grid::make(2)->schema([
                    TextInput::make('additional_amount')
                        ->label('Add Amount')
                        ->helperText('Enter amount to add to current total')
                        ->numeric()
                        ->prefix('ETB')
                        ->step(0.01)
                        ->default(0)
                        ->minValue(0),

                ]),

                Placeholder::make('progress_percentage')
                    ->label('Progress Percentage')
                    ->content(function ($record) {
                        if (! $record) {
                            return 'Not calculated';
                        }
                        $percentage = $record->progress_percentage;
                        $color = $percentage >= 100 ? 'green' : ($percentage >= 50 ? 'blue' : 'orange');

                        return new HtmlString(
                            '<div class="w-full bg-gray-200 rounded-full h-4">'.
                            '<div class="bg-'.$color.'-600 h-4 rounded-full" style="width: '.$percentage.'%"></div>'.
                            '</div>'.
                            '<span class="text-sm font-medium">'.number_format($percentage, 1).'%</span>'
                        );
                    }),
            ])
                ->visible(function ($context) {
                    return $context === 'edit';
                }),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::getSchema());
    }
}
