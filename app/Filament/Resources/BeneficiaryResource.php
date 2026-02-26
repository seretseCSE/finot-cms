<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BeneficiaryResource\Pages;
use App\Filament\Resources\BeneficiaryResource\RelationManager\AidDistributionsRelationManager;
use App\Models\Beneficiary;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BeneficiaryResource extends Resource
{
    protected static ?string $model = Beneficiary::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-users'; }

    public static function getNavigationGroup(): ?string { return 'Charity'; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('beneficiary_code')
                    ->label('Beneficiary Code')
                    ->default(function () {
                        $lastCode = Beneficiary::orderBy('id', 'desc')->first()?->beneficiary_code;
                        if (!$lastCode) {
                            return 'B-000001';
                        }
                        $number = intval(substr($lastCode, 2)) + 1;
                        return 'B-' . str_pad($number, 6, '0', STR_PAD_LEFT);
                    })
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\TextInput::make('full_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->required()
                    ->tel()
                    ->unique(ignoreRecord: true),
                Forms\Components\Textarea::make('address')
                    ->required()
                    ->rows(3),
                Forms\Components\Select::make('type')
                    ->options([
                        'Individual' => 'Individual',
                        'Family' => 'Family',
                        'Organization' => 'Organization',
                    ])
                    ->required(),
                Forms\Components\Select::make('need_category')
                    ->options([
                        'Food' => 'Food',
                        'Medical' => 'Medical',
                        'Education' => 'Education',
                        'Housing' => 'Housing',
                        'Other' => 'Other',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(191),
                Forms\Components\TextInput::make('id_number')
                    ->maxLength(100),
                Forms\Components\TextInput::make('dependents_count')
                    ->numeric(),
                Forms\Components\TextInput::make('monthly_income')
                    ->numeric()
                    ->prefix('ETB'),
                Forms\Components\Textarea::make('notes')
                    ->rows(3),
                Forms\Components\Select::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                        'Completed' => 'Completed',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('beneficiary_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('need_category')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('total_aid_received')
                    ->label('Total Received')
                    ->money('ETB')
                    ->getStateUsing(fn ($record) => $record->total_aid_received),
                Tables\Columns\TextColumn::make('last_distribution_date')
                    ->label('Last Distribution')
                    ->getStateUsing(fn ($record) => $record->last_distribution_date),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => match($record->status) {
                        'Active' => 'success',
                        'Inactive' => 'warning',
                        'Completed' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Inactive' => 'Inactive',
                        'Completed' => 'Completed',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'Individual' => 'Individual',
                        'Family' => 'Family',
                        'Organization' => 'Organization',
                    ]),
                Tables\Filters\SelectFilter::make('need_category')
                    ->options([
                        'Food' => 'Food',
                        'Medical' => 'Medical',
                        'Education' => 'Education',
                        'Housing' => 'Housing',
                        'Other' => 'Other',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn ($record) => $record->canBeDeleted()),
                Actions\Action::make('mark_completed')
                    ->label('Mark Completed')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'Active')
                    ->action(fn ($record) => $record->markAsCompleted()),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AidDistributionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBeneficiaries::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()->role, ['charity_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return in_array(auth()->user()->role, ['charity_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return in_array(auth()->user()->role, ['charity_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return in_array(auth()->user()->role, ['charity_head', 'admin', 'superadmin']) && $record->canBeDeleted();
    }
}

