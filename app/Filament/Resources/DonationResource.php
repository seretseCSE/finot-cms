<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use App\Filament\Forms\Components\CustomOptionSelect;
use App\Filament\Resources\DonationResource\Pages;
use App\Models\Donation;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DonationResource extends Resource
{
    protected static ?string $model = Donation::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-gift'; }

    public static function getNavigationLabel(): string { return 'Donations'; }

    public static function getNavigationGroup(): ?string { return 'Finance'; }

    public static function getModelLabel(): string { return 'Donation'; }

    public static function getPluralModelLabel(): string { return 'Donations'; }

    protected static ?int $navigationSort = 2; // After Contributions

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['superadmin']) && $record->canBeDeleted();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Donation Details')
                    ->schema([
                        Forms\Components\TextInput::make('donor_name')
                            ->label('Donor Name')
                            ->placeholder('Leave empty or type Anonymous')
                            ->helperText('Leave empty to record as Anonymous donation')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (Birr)')
                            ->numeric()
                            ->step(0.01)
                            ->min(0.01)
                            ->required()
                            ->prefix('Birr'),

                        EthiopianDatePicker::make('donation_date')
                            ->label('Donation Date')
                            ->default(now())
                            ->required(),

                        CustomOptionSelect::makeWithOther('donation_type', 'donation_type', [
                            'General Fund' => 'General Fund',
                            'Building Fund' => 'Building Fund',
                            'Missionary Support' => 'Missionary Support',
                            'Charity/Aid' => 'Charity/Aid',
                        ])
                            ->required()
                            ->helperText('Select the fund or purpose for this donation'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->maxLength(500)
                            ->rows(3)
                            ->helperText('Optional additional information about this donation'),

                        Forms\Components\TextInput::make('recorded_by_name')
                            ->label('Recorded By')
                            ->default(fn () => Auth::user()?->name)
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('formatted_donor_name')
                    ->label('Donor Name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->formatted_donor_name),

                Tables\Columns\TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->formatted_amount),

                Tables\Columns\TextColumn::make('formatted_donation_type')
                    ->label('Donation Type')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => match($record->donation_type) {
                        'General Fund' => 'primary',
                        'Building Fund' => 'success',
                        'Missionary Support' => 'info',
                        'Charity/Aid' => 'warning',
                        'Other' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('ethiopian_date')
                    ->label('Donation Date')
                    ->date()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->ethiopian_date)
                    ->tooltip(fn ($record) => $record->donation_date->format('M d, Y')),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date'),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $data['start_date'] && $data['end_date']
                            ? $query->dateRange($data['start_date'], $data['end_date'])
                            : $query;
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['start_date'] || !$data['end_date']) {
                            return null;
                        }
                        return 'Date: ' . $data['start_date'] . ' to ' . $data['end_date'];
                    }),

                Tables\Filters\SelectFilter::make('donation_type')
                    ->label('Donation Type')
                    ->options([
                        'General Fund' => 'General Fund',
                        'Building Fund' => 'Building Fund',
                        'Missionary Support' => 'Missionary Support',
                        'Charity/Aid' => 'Charity/Aid',
                        'Other' => 'Other',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Donation $record) => static::canEdit($record)),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Donation $record) => static::canDelete($record))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Donation')
                    ->modalDescription('Are you sure you want to delete this donation? This action is permanent and will be logged to Tier-2 audit trail.')
                    ->before(function (Donation $record) {
                        // Log to Tier-2 audit trail
                        Log::channel('audit')->warning('Tier 2 Audit Log', [
                            'tier' => 2,
                            'action' => 'donation_deleted',
                            'donation_id' => $record->id,
                            'donor_name' => $record->formatted_donor_name,
                            'amount' => $record->amount,
                            'donation_type' => $record->donation_type,
                            'performed_by' => Auth::id(),
                            'timestamp' => now()->toDateTimeString(),
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => static::canDelete(null))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Selected Donations')
                    ->modalDescription('Are you sure you want to delete selected donations? This action is permanent and will be logged to Tier-2 audit trail.')
                    ->before(function ($records) {
                        foreach ($records as $record) {
                            Log::channel('audit')->warning('Tier 2 Audit Log', [
                                'tier' => 2,
                                'action' => 'donation_bulk_deleted',
                                'donation_id' => $record->id,
                                'donor_name' => $record->formatted_donor_name,
                                'amount' => $record->amount,
                                'donation_type' => $record->donation_type,
                                'performed_by' => Auth::id(),
                                'timestamp' => now()->toDateTimeString(),
                            ]);
                        }
                    }),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No donations recorded')
            ->emptyStateDescription('Start recording donations to track charitable contributions to the church.')
            ->emptyStateIcon('heroicon-o-gift');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDonations::class,
            'create' => Pages\CreateDonation::class,
            'edit' => Pages\EditDonation::class,
            'view' => Pages\ViewDonation::class,
        ];
    }

    public static function beforeSave(array $data): array
    {
        // Set recorded_by to current user
        $data['recorded_by'] = Auth::id();

        // Handle Anonymous donor
        if (empty($data['donor_name'])) {
            $data['donor_name'] = null;
        }

        return $data;
    }

    public static function afterCreate($record, array $data): void
    {
        Notification::make()
            ->title('Donation Recorded')
            ->body("Successfully recorded donation of {$record->formatted_amount} from {$record->formatted_donor_name}")
            ->success()
            ->send();
    }

    public static function afterUpdate($record, array $data): void
    {
        Notification::make()
            ->title('Donation Updated')
            ->body("Donation information has been updated successfully")
            ->success()
            ->send();
    }
}

