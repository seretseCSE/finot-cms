<?php

namespace App\Filament\Resources;


use App\Filament\Support\HidesFromNavigation;
use App\Filament\Forms\Components\EthiopianDatePicker;
use Filament\Schemas\Schema;
use App\Filament\Resources\ContributionAmountResource\Pages;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\ContributionAmount;
use App\Models\MemberGroup;
// Filament v5: Action classes live under Filament\Actions, not Filament\Tables\Actions
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ContributionAmountResource extends BaseResource
{
    use HidesFromNavigation;

    protected static ?string $model = ContributionAmount::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-currency-dollar';
    }

    public static function getNavigationLabel(): string
    {
        return 'Contribution Settings';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Contributions';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getModelLabel(): string
    {
        return 'Contribution Amount';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Contribution Amounts';
    }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->can('contribution_amounts.view');
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->can('contribution_amounts.create');
    }

    public static function canEdit($record): bool
    {
        if ($record === null) {
            return false;
        }
        return parent::canEdit($record);
    }

    public static function canDelete($record): bool
    {
        if ($record === null) {
            return false;
        }
        return parent::canDelete($record) && $record->canBeDeleted();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\CheckboxList::make('group_id')
                    ->label('Member Groups')
                    ->options(fn () => MemberGroup::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->columns(3)
                    ->required()
                    ->formatStateUsing(fn ($state) => is_array($state) ? $state : [(string) $state])
                    ->bulkToggleable()
                    ->live(),

                Forms\Components\Select::make('academic_year_id')
                    ->label('Academic Year')
                    ->options(fn () => AcademicYear::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->required()
                    ->default(fn () => AcademicYear::where('status', 'Active')->first()?->id)
                    ->live(),

                Forms\Components\CheckboxList::make('month_name')
                    ->label('Contribution Months')
                    ->options(EthiopianDateHelper::getMonthsForContribution('ethiopian'))
                    ->columns(3)
                    ->required()
                    ->formatStateUsing(fn ($state) => is_array($state) ? $state : [$state])
                    ->bulkToggleable()
                    ->live()
                    ->rules([
                        fn (Get $get, $record = null) => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                            $groupIds = $get('group_id') ?? [];
                            $academicYearId = $get('academic_year_id');
                            if (! $academicYearId || empty($groupIds)) {
                                return;
                            }

                            foreach ($groupIds as $groupId) {
                                foreach ($value as $mName) {
                                    $query = ContributionAmount::where('group_id', $groupId)
                                        ->where('month_name', $mName)
                                        ->where('academic_year_id', $academicYearId);

                                    if ($record && $record instanceof ContributionAmount) {
                                        $query->where('id', '!=', $record->id);
                                    }

                                    if ($query->exists()) {
                                        $groupName = MemberGroup::find($groupId)?->name ?? "Group #{$groupId}";
                                        $fail("A contribution amount already exists for group '{$groupName}' in '{$mName}' for this academic year.");
                                        break 2;
                                    }
                                }
                            }
                        },
                    ]),

                Forms\Components\TextInput::make('amount')
                    ->label('Amount (Birr)')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0.01)
                    ->required()
                    ->prefix('Birr'),

                EthiopianDatePicker::make('effective_from')
                    ->label('Effective From')
                    ->required()
                    ->live(),

                EthiopianDatePicker::make('effective_to')
                    ->label('Effective To')
                    ->nullable(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('academicYear.name')
                    ->label('Academic Year')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('group.name')
                    ->label('Member Group')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('month_name')
                    ->label('Month')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('ETB')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => 'Birr '.number_format($state, 2)),

                Tables\Columns\TextColumn::make('effective_from')
                    ->label('Effective From')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_to')
                    ->label('Effective To')
                    ->date()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? $state->format('M d, Y') : 'Ongoing'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->isCurrentlyActive() ? 'success' : 'gray')
                    ->formatStateUsing(fn ($record) => $record->status),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('academic_year_id')
                    ->label('Academic Year')
                    ->relationship('academicYear', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('group_id')
                    ->label('Member Group')
                    ->relationship('group', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('month_name')
                    ->label('Month')
                    ->options(EthiopianDateHelper::getMonthsForContribution('ethiopian')),

                Tables\Filters\TernaryFilter::make('active_only')
                    ->label('Active Only')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Historical')
                    ->queries(
                        true: fn (Builder $query) => $query->active(),
                        false: fn (Builder $query) => $query->whereNotActive(),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            // Filament v5: Use \Filament\Actions\EditAction and DeleteAction for row actions
            ->actions([
                EditAction::make(),

                DeleteAction::make()
                    ->modalHeading('Delete Contribution Amount')
                    ->modalDescription('Are you sure you want to delete this contribution amount? This action cannot be undone.'),
            ])
            // Filament v5: Bulk actions require Filament\Actions\BulkAction or built-in bulk actions
            ->bulkActions([
                DeleteBulkAction::make()
                    ->modalHeading('Delete Selected Contribution Amounts')
                    ->modalDescription('Are you sure you want to delete the selected contribution amounts? This action cannot be undone.'),
            ])
            // Filament v5: Use Filament\Actions\Action for custom empty-state actions
            ->emptyStateActions([
                Action::make('create')
                    ->label('Create Contribution Amount')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => route('filament.admin.resources.contribution-amounts.create')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContributionAmounts::route('/'),
            'create' => Pages\CreateContributionAmount::route('/create'),
            'edit' => Pages\EditContributionAmount::route('/{record}/edit'),
        ];
    }

    public static function getRecordRoute(): string
    {
        return 'filament.admin.resources.contribution-amounts.index';
    }
}
