<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use Filament\Schemas\Schema;
use App\Filament\Resources\ContributionResource\Pages;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\Contribution;
use App\Models\ContributionAmount;
use App\Models\Member;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ContributionResource extends Resource
{
    protected static ?string $model = Contribution::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-banknotes';
    }

    public static function getNavigationLabel(): string
    {
        return 'Contributions';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financial Management';
    }

    public static function getModelLabel(): string
    {
        return 'Contribution';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Contributions';
    }

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['finance_head', 'nibret_hisab_head', 'admin', 'superadmin'])
            && ! ($record?->is_archived ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                \Filament\Schemas\Components\Section::make('Contribution Details')
                    ->schema([
                        Forms\Components\Select::make('member_id')
                            ->label('Member')
                            ->relationship('member', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn (Member $record): string => "{$record->first_name} {$record->father_name} ({$record->member_code})")
                            ->searchable(['first_name', 'father_name', 'grandfather_name', 'member_code'])
                            ->preload()
                            ->live()
                            ->required(),

                        Forms\Components\Select::make('academic_year_id')
                            ->label('Academic Year')
                            ->relationship('academicYear', 'name')
                            ->default(fn () => AcademicYear::where('status', 'Active')->first()?->id)
                            ->live()
                            ->required(),

                        Forms\Components\Select::make('month_name')
                            ->label('Month')
                            ->options(EthiopianDateHelper::getMonthsForContribution('ethiopian'))
                            ->live()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                $memberId = $get('member_id');
                                $academicYearId = $get('academic_year_id');
                                $monthName = $get('month_name');

                                if (! $memberId || ! $academicYearId || ! $monthName) {
                                    return;
                                }

                                $member = Member::find($memberId);
                                $currentGroupId = $member?->currentGroupAssignment?->group_id;

                                if (! $currentGroupId) {
                                    return;
                                }

                                $expectedAmount = ContributionAmount::where('group_id', $currentGroupId)
                                    ->where('academic_year_id', $academicYearId)
                                    ->where('month_name', $monthName)
                                    ->active()
                                    ->first()?->amount;

                                if ($expectedAmount && ! $get('amount')) {
                                    $set('amount', $expectedAmount);
                                }
                            })
                            ->required(),

                        EthiopianDatePicker::make('payment_date')
                            ->label('Payment Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (Birr)')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->required()
                            ->prefix('Birr')
                            ->rules([
                                function (Forms\Get $get) {
                                    return function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                                        $memberId = $get('member_id');
                                        $academicYearId = $get('academic_year_id');
                                        $monthName = $get('month_name');

                                        if (! $memberId || ! $academicYearId || ! $monthName) {
                                            return;
                                        }

                                        $member = Member::find($memberId);
                                        $currentGroupId = $member?->currentGroupAssignment?->group_id;

                                        if (! $currentGroupId) {
                                            return;
                                        }

                                        $expectedAmount = ContributionAmount::where('group_id', $currentGroupId)
                                            ->where('academic_year_id', $academicYearId)
                                            ->where('month_name', $monthName)
                                            ->active()
                                            ->first()?->amount;

                                        if ($expectedAmount && (float) $value !== (float) $expectedAmount) {
                                            $fail("The amount must be exactly Birr {$expectedAmount} for this member's group ({$member->currentGroup?->name}) and month ({$monthName}).");
                                        }
                                    };
                                },
                            ]),

                        Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'Cash' => 'Cash',
                                'Check' => 'Check',
                                'Mobile Money' => 'Mobile Money',
                                'Bank Transfer' => 'Bank Transfer',
                                'Other' => 'Other',
                            ])
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Paid' => 'Paid',
                                'Not Paid' => 'Not Paid',
                            ])
                            ->default('Paid')
                            ->required(),

                        Forms\Components\Toggle::make('is_paid')
                            ->label('Is Paid')
                            ->default(true)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                $set('status', $state ? 'Paid' : 'Not Paid');
                            }),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.first_name')
                    ->label('Member')
                    ->formatStateUsing(fn ($record) => "{$record->member->first_name} {$record->member->father_name}")
                    ->searchable(['member.first_name', 'member.father_name', 'member.member_code'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('academicYear.name')
                    ->label('Academic Year')
                    ->searchable()
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
                    ->formatStateUsing(fn ($state) => 'Birr '.number_format($state, 2)),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Payment Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Cash' => 'success',
                        'Check' => 'warning',
                        'Mobile Money' => 'info',
                        'Bank Transfer' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Paid' => 'success',
                        'Not Paid' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_paid')
                    ->label('Paid')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['member', 'academicYear', 'recordedBy']))
            ->filters([
                Tables\Filters\SelectFilter::make('academic_year_id')
                    ->label('Academic Year')
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('month_name')
                    ->label('Month')
                    ->options(EthiopianDateHelper::getMonthsForContribution('ethiopian')),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Paid' => 'Paid',
                        'Not Paid' => 'Not Paid',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'Cash' => 'Cash',
                        'Check' => 'Check',
                        'Mobile Money' => 'Mobile Money',
                        'Bank Transfer' => 'Bank Transfer',
                        'Other' => 'Other',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date'),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $data['start_date'] && $data['end_date']
                            ? $query->whereBetween('payment_date', [$data['start_date'], $data['end_date']])
                            : $query;
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['start_date'] || ! $data['end_date']) {
                            return null;
                        }

                        return 'Date: '.$data['start_date'].' to '.$data['end_date'];
                    }),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (Contribution $record) => static::canEdit($record)),

                Actions\DeleteAction::make()
                    ->visible(fn (Contribution $record) => static::canDelete($record))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Contribution')
                    ->modalDescription('Are you sure you want to delete this contribution? This action is permanent.'),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make()
                    ->visible(fn () => static::canDelete(null))
                    ->requiresConfirmation()
                    ->modalHeading('Delete Selected Contributions')
                    ->modalDescription('Are you sure you want to delete the selected contributions?'),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No contributions recorded')
            ->emptyStateDescription('Start recording individual member contributions.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContributions::route('/'),
            'create' => Pages\CreateContribution::route('/create'),
            'edit' => Pages\EditContribution::route('/{record}/edit'),
        ];
    }

    public static function afterCreate($record, array $data): void
    {
        Notification::make()
            ->title('Contribution Recorded')
            ->body("Successfully recorded contribution of {$record->amount} from {$record->member->first_name}")
            ->success()
            ->send();
    }

    public static function afterUpdate($record, array $data): void
    {
        Notification::make()
            ->title('Contribution Updated')
            ->body('Contribution information has been updated successfully')
            ->success()
            ->send();
    }
}
