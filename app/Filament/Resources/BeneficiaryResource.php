<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BeneficiaryResource\Pages;
use Filament\Schemas\Schema;
use App\Filament\Resources\BeneficiaryResource\RelationManager\AidDistributionsRelationManager;
use App\Models\Beneficiary;
use Filament\Actions;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;

class BeneficiaryResource extends BaseResource
{
    protected static ?string $model = Beneficiary::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financial Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\TextInput::make('beneficiary_code')
                    ->label('Beneficiary Code')
                    ->default(function () {
                        $lastCode = Beneficiary::orderBy('id', 'desc')->first()?->beneficiary_code;
                        if (! $lastCode) {
                            return 'B-000001';
                        }
                        $number = intval(substr($lastCode, 2)) + 1;

                        return 'B-'.str_pad($number, 6, '0', STR_PAD_LEFT);
                    })
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\TextInput::make('full_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->required()
                    ->tel()
                    ->unique(ignoreRecord: true)
                    ->prefix(config('finot.phone_prefix', '+251'))
                    ->regex('/^[0-9]{9}$/')
                    ->placeholder('912345678')
                    ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                    ->maxLength(9)
                    ->formatStateUsing(function ($state) {
                        $prefix = config('finot.phone_prefix', '+251');

                        return $state ? preg_replace('/^(' . preg_quote($prefix, '/') . '|0)/', '', $state) : null;
                    })
                    ->dehydrateStateUsing(fn ($state) => $state ? config('finot.phone_prefix', '+251').$state : null),
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
                    ->color(fn ($record) => match ($record->status) {
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

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('beneficiaries.delete') && $record->canBeDeleted();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBeneficiaries::route('/'),
            'create' => Pages\CreateBeneficiary::route('/create'),
            'edit' => Pages\EditBeneficiary::route('/{record}/edit'),
            'view' => Pages\ViewBeneficiary::route('/{record}'),
        ];
    }
}
