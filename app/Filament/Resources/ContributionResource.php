<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use App\Filament\Forms\Components\CustomOptionSelect;
use App\Filament\Resources\ContributionResource\Pages;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-banknotes'; }

    public static function getNavigationLabel(): string { return 'Contributions'; }

    public static function getNavigationGroup(): ?string { return 'Finance'; }

    public static function getModelLabel(): string { return 'Contribution'; }

    public static function getPluralModelLabel(): string { return 'Contributions'; }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'charity_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['charity_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['charity_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['admin', 'superadmin']) && $record->canBeDeleted();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('contribution_details')
                    ->label('Contribution Details')
                    ->description('Record member contributions for specific months')
                    ->schema([
                        // Member Selection
                        Forms\Components\Select::make('member_id')
                            ->label('Member')
                            ->relationship('member', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                $memberId = $state;
                                $academicYearId = $get('academic_year_id');
                                
                                if ($memberId && $academicYearId) {
                                    static::checkExistingContribution($memberId, $academicYearId, null, $set);
                                }
                            }),

                        // Academic Year
                        Forms\Components\Select::make('academic_year_id')
                            ->label('Academic Year')
                            ->relationship('academicYear', 'name')
                            ->default(fn () => \App\Models\AcademicYear::where('is_active', true)->first()?->id)
                            ->required()
                            ->disabled()
                            ->helperText('Active academic year automatically selected'),

                        // Multi-month recording repeater
                        Forms\Components\Repeater::make('contributions')
                            ->label('Record Multiple Months / ብዙ ወሮችን ያስቀምጡ')
                            ->schema([
                                Forms\Components\Select::make('month_name')
                                    ->label('Month')
                                    ->options(EthiopianDateHelper::getMonthsForContribution())
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $memberId = $get('../../member_id');
                                        $academicYearId = $get('../../academic_year_id');
                                        
                                        if ($memberId && $academicYearId && $state) {
                                            static::checkExistingContribution($memberId, $academicYearId, $state, $set);
                                            static::checkUnusualAmount($memberId, $state, $get('amount'), $set);
                                        }
                                    }),

                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->prefix('ETB')
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $memberId = $get('../../member_id');
                                        $monthName = $get('month_name');
                                        
                                        if ($memberId && $monthName && $state) {
                                            static::checkUnusualAmount($memberId, $monthName, $state, $set);
                                        }
                                    }),

                                Forms\Components\Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'Cash' => 'Cash',
                                        'Check' => 'Check',
                                        'Mobile Money' => 'Mobile Money',
                                        'Bank Transfer' => 'Bank Transfer',
                                    ])
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->collapsible()
                            ->collapsed(fn ($record) => $record !== null),

                        // Payment Date (Ethiopian date picker)
                        EthiopianDatePicker::make('payment_date')
                            ->label('Payment Date')
                            ->default(now())
                            ->required(),

                        // Recorded By (auto-filled, read-only)
                        Forms\Components\TextInput::make('recorded_by_name')
                            ->label('Recorded By')
                            ->default(fn () => Auth::user()?->name)
                            ->disabled()
                            ->dehydrated(false),

                        // Notes
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }

    private static function checkExistingContribution($memberId, $academicYearId, $monthName, callable $set)
    {
        $existingAmount = Contribution::getTotalForMemberInYearForMonth($memberId, $academicYearId, $monthName);
        
        if ($existingAmount > 0) {
            $member = Member::find($memberId);
            Notification::make()
                ->title('Existing Contribution Found')
                ->body("Warning: {$member->full_name} has already paid Birr {$existingAmount} for {$monthName}. Proceed to record additional payment?")
                ->warning()
                ->persistent()
                ->send();
        }
    }

    private static function checkUnusualAmount($memberId, $monthName, $amount, callable $set)
    {
        if (Contribution::isAmountUnusual($memberId, $monthName, $amount)) {
            $member = Member::find($memberId);
            $expectedAmount = Contribution::getExpectedAmountForMemberAndMonth($memberId, $monthName);
            
            Notification::make()
                ->title('Unusual Amount Detected')
                ->body("Note: Amount differs significantly from expected Birr {$expectedAmount} for {$member->memberGroup?->name}")
                ->warning()
                ->persistent()
                ->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.full_name')
                    ->label('Member')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->member->memberGroup?->name),

                Tables\Columns\TextColumn::make('academicYear.name')
                    ->label('Academic Year')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('month_name')
                    ->label('Month')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('ETB')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => 'Birr ' . number_format($state, 2)),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Payment Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_payment_method')
                    ->label('Payment Method')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_archived')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-archive-box')
                    ->falseIcon('heroicon-o-document-text')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->formatStateUsing(fn ($state) => $state ? 'Archived' : 'Active'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('member_id')
                    ->label('Member')
                    ->searchable()
                    ->preload()
                    ->relationship('member', 'full_name'),

                Tables\Filters\SelectFilter::make('academic_year_id')
                    ->label('Academic Year')
                    ->relationship('academicYear', 'name'),

                Tables\Filters\SelectFilter::make('month_name')
                    ->label('Month')
                    ->options(EthiopianDateHelper::getMonthsForContribution()),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'Cash' => 'Cash',
                        'Check' => 'Check',
                        'Mobile Money' => 'Mobile Money',
                        'Bank Transfer' => 'Bank Transfer',
                        'Other' => 'Other',
                    ]),

                Tables\Filters\TernaryFilter::make('is_archived')
                    ->label('Archived')
                    ->placeholder('All')
                    ->trueLabel('Archived')
                    ->falseLabel('Active')
                    ->queries(
                        true: fn (Builder $query) => $query->archived(),
                        false: fn (Builder $query) => $query->notArchived(),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Contribution $record) => static::canEdit($record)),

                Tables\Actions\Action::make('archive')
                    ->label(fn ($record) => $record->is_archived ? 'Unarchive' : 'Archive')
                    ->icon(fn ($record) => $record->is_archived ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-archive-box')
                    ->color(fn ($record) => $record->is_archived ? 'warning' : 'gray')
                    ->action(function (Contribution $record) {
                        if ($record->is_archived) {
                            $record->unarchive();
                            Notification::make()
                                ->title('Contribution Unarchived')
                                ->success()
                                ->send();
                        } else {
                            $record->archive();
                            Notification::make()
                                ->title('Contribution Archived')
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(fn (Contribution $record) => static::canEdit($record)),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Contribution $record) => static::canDelete($record))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Contribution')
                    ->modalDescription('Are you sure you want to delete this contribution? This action will be logged to Tier-2 audit trail.')
                    ->before(function (Contribution $record) {
                        // Log to Tier-2 audit trail
                        \Log::channel('audit')->warning('Tier 2 Audit Log', [
                            'tier' => 2,
                            'action' => 'contribution_deleted',
                            'contribution_id' => $record->id,
                            'member_id' => $record->member_id,
                            'amount' => $record->amount,
                            'performed_by' => Auth::id(),
                            'timestamp' => now()->toDateTimeString(),
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('archive')
                        ->label('Archive Selected')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->archive();
                            }
                            Notification::make()
                                ->title('Contributions Archived')
                                ->success()
                                ->send();
                        })
                        ->visible(fn () => static::canEdit(null)),

                    Tables\Actions\BulkAction::make('unarchive')
                        ->label('Unarchive Selected')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->unarchive();
                            }
                            Notification::make()
                                ->title('Contributions Unarchived')
                                ->success()
                                ->send();
                        })
                        ->visible(fn () => static::canEdit(null)),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => static::canDelete(null))
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Contributions')
                        ->modalDescription('Are you sure you want to delete selected contributions? This action will be logged to Tier-2 audit trail.')
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                \Log::channel('audit')->warning('Tier 2 Audit Log', [
                                    'tier' => 2,
                                    'action' => 'contribution_bulk_deleted',
                                    'contribution_id' => $record->id,
                                    'member_id' => $record->member_id,
                                    'amount' => $record->amount,
                                    'performed_by' => Auth::id(),
                                    'timestamp' => now()->toDateTimeString(),
                                ]);
                            }
                        }),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContributions::class,
            'create' => Pages\CreateContribution::class,
            'edit' => Pages\EditContribution::class,
            'view' => Pages\ViewContribution::class,
        ];
    }

    public static function beforeSave(array $data): array
    {
        // Handle multi-month recording
        if (isset($data['contributions'])) {
            $contributions = $data['contributions'];
            unset($data['contributions']);
            
            // Store contributions for later processing
            $data['_contributions_to_save'] = $contributions;
        }

        // Set recorded_by to current user
        $data['recorded_by'] = Auth::id();

        return $data;
    }

    public static function afterCreate($record, array $data): void
    {
        // Handle multi-month recording
        if (isset($data['_contributions_to_save'])) {
            DB::transaction(function () use ($record, $data) {
                foreach ($data['_contributions_to_save'] as $contributionData) {
                    Contribution::create([
                        'member_id' => $record->member_id,
                        'academic_year_id' => $record->academic_year_id,
                        'amount' => $contributionData['amount'],
                        'month_name' => $contributionData['month_name'],
                        'payment_method' => $contributionData['payment_method'],
                        'custom_payment_method' => $contributionData['custom_payment_method'] ?? null,
                        'payment_date' => $record->payment_date,
                        'notes' => $record->notes,
                        'recorded_by' => Auth::id(),
                    ]);
                }
                
                // Delete the main record if it was just a placeholder
                $record->delete();
            });
        }
    }
}

