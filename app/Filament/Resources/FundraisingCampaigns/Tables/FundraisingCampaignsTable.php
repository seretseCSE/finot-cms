<?php

namespace App\Filament\Resources\FundraisingCampaigns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class FundraisingCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('campaign_name')
                    ->label('Campaign Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_amount')
                    ->label('Target')
                    ->money('ETB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_raised')
                    ->label('Raised')
                    ->money('ETB')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->formatStateUsing(function ($record) {
                        $percentage = $record->progress_percentage;
                        $color = $percentage >= 100 ? 'success' : ($percentage >= 50 ? 'primary' : 'warning');

                        return new HtmlString(
                            '<div class="flex items-center gap-2">'.
                            '<div class="w-16 bg-gray-200 rounded-full h-2">'.
                            '<div class="bg-'.$color.'-500 h-2 rounded-full" style="width: '.$percentage.'%"></div>'.
                            '</div>'.
                            '<span class="text-xs">'.number_format($percentage, 1).'%</span>'.
                            '</div>'
                        );
                    }),

                Tables\Columns\TextColumn::make('campaign_category')
                    ->label('Category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Building' => 'danger',
                        'Missionary' => 'success',
                        'Charity' => 'warning',
                        'General' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Active' => 'success',
                        'Completed' => 'primary',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable()
                    ->placeholder('No end date'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Draft' => 'Draft',
                        'Active' => 'Active',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('campaign_category')
                    ->label('Category')
                    ->options([
                        'Building' => 'Building',
                        'Missionary' => 'Missionary',
                        'Charity' => 'Charity',
                        'General' => 'General',
                    ]),

                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
