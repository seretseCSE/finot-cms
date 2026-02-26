<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FundraisingCampaignResource\Pages;
use App\Models\FundraisingCampaign;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FundraisingCampaignResource extends Resource
{
    protected static ?string $model = FundraisingCampaign::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-heart'; }

    public static function getNavigationGroup(): ?string { return 'Events & Fundraising'; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('target_amount')
                    ->required()
                    ->numeric()
                    ->prefix('ETB')
                    ->label('Target Amount'),
                Forms\Components\DatePicker::make('start_date')
                    ->required()
                    ->label('Start Date'),
                Forms\Components\DatePicker::make('end_date')
                    ->label('End Date'),
                Forms\Components\RichEditor::make('description')
                    ->label('Description')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('featured_image')
                    ->label('Featured Image')
                    ->image()
                    ->directory('fundraising')
                    ->visibility('public')
                    ->maxSize(2048),
                Forms\Components\Select::make('category')
                    ->options([
                        'Building' => 'Building',
                        'Missionary' => 'Missionary',
                        'Charity' => 'Charity',
                        'General' => 'General',
                    ]),
                Forms\Components\Textarea::make('bank_account_info')
                    ->label('Bank Account Info')
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Active' => 'Active',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_amount')
                    ->label('Target')
                    ->money('ETB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_raised')
                    ->label('Total Raised')
                    ->money('ETB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progress %')
                    ->getStateUsing(fn ($record) => $record->progress_percentage . '%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Active' => 'Active',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'Building' => 'Building',
                        'Missionary' => 'Missionary',
                        'Charity' => 'Charity',
                        'General' => 'General',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\Action::make('update_total_raised')
                    ->label('Update Total Raised')
                    ->icon('heroicon-o-currency-dollar')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('ETB')
                            ->label('Amount'),
                    ])
                    ->action(function ($record, $data) {
                        $record->updateTotalRaised($data['amount']);
                    }),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFundraisingCampaigns::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->role === 'admin' || auth()->user()->role === 'superadmin';
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === 'admin' || auth()->user()->role === 'superadmin';
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->role === 'admin' || auth()->user()->role === 'superadmin';
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->role === 'admin' || auth()->user()->role === 'superadmin';
    }
}

