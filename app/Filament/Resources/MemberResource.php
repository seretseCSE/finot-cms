<?php

namespace App\Filament\Resources;

use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Filament\Forms\Components\MemberForms\PersonalInformationTab;
use App\Filament\Forms\Components\MemberForms\AddressContactTab;
use App\Filament\Forms\Components\MemberForms\EmergencySpiritualTab;
use App\Filament\Forms\Components\MemberForms\ParentGuardianTab;
use App\Filament\Forms\Components\MemberForms\FamilyOccupationTab;
use App\Filament\Forms\Components\MemberForms\StatusHistoryTab;
use App\Filament\Actions\MemberBulkActions;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use App\Filament\Resources\MemberResource\Pages;
use App\Models\Member;
use App\Models\MemberGroup;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Helpers\EthiopianDateHelper;
use App\Models\Scopes\DepartmentScope;

class MemberResource extends BaseResource
{
    protected static ?string $model = Member::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Membership Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationLabel(): string
    {
        return 'Members';
    }

    public static function getModelLabel(): string
    {
        return 'Member';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Members';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('MemberTabs')
                ->contained(false)
                ->tabs([
                    PersonalInformationTab::make(),
                    AddressContactTab::make(),
                    EmergencySpiritualTab::make(),
                    ParentGuardianTab::make(),
                    FamilyOccupationTab::make(),
                    StatusHistoryTab::make(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function handleParentData($record, array $data): void
    {
        if (! isset($data['parentGuardians']) || ! is_array($data['parentGuardians'])) {
            return;
        }

        app(\App\Actions\Members\SyncParentGuardiansAction::class)
            ->execute($record, $data['parentGuardians']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $parentId = request()->query('parent_id');
                if ($parentId) {
                    $query->whereHas('parentGuardians', function (Builder $q) use ($parentId) {
                        $q->where('parent_id', $parentId)->where('is_external', false);
                    });
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('member_code')
                    ->label('Member ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full Name')
                    ->searchable(['first_name', 'father_name', 'grandfather_name']),

                Tables\Columns\TextColumn::make('member_type')
                    ->badge()
                    ->color(fn (MemberType $state): string => match ($state) {
                        MemberType::KIDS => 'info',
                        MemberType::YOUTH => 'warning',
                        MemberType::ADULT => 'success',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (MemberStatus $state): string => match ($state) {
                        MemberStatus::DRAFT => 'gray',
                        MemberStatus::MEMBER => 'info',
                        MemberStatus::ACTIVE => 'success',
                        MemberStatus::FORMER => 'danger',
                    }),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('currentGroupAssignment.group.name')
                    ->label('Current Group')
                    ->getStateUsing(fn (Member $record): string => $record->currentGroup?->name ?? 'Unassigned')
                    ->badge()
                    ->color(fn (Member $record): string => $record->currentGroup ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : '')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Member' => 'Member',
                        'Active' => 'Active',
                        'Former' => 'Former',
                    ]),

                Tables\Filters\SelectFilter::make('member_type')
                    ->options([
                        'Kids' => 'Kids',
                        'Youth' => 'Youth',
                        'Adult' => 'Adult',
                    ]),

                Tables\Filters\SelectFilter::make('department')
                    ->label('Department')
                    ->options(
                        fn () => \App\Models\Department::query()
                        ->withoutGlobalScope(\App\Models\Scopes\DepartmentScope::class)
                        ->orderBy('name_en')
                        ->pluck('name_en', 'id')
                        ->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        $departmentId = $data['value'] ?? null;
                        if (blank($departmentId)) {
                            return $query;
                        }

                        return $query->where('department_id', $departmentId);
                    }),

                Tables\Filters\SelectFilter::make('group_id')
                    ->label('Filter by Group')
                    ->options(fn () => MemberGroup::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $groupId = $data['value'] ?? null;
                        if (blank($groupId)) {
                            return $query;
                        }

                        return $query->whereHas('currentGroupAssignment', function (Builder $q) use ($groupId): void {
                            $q->active()->where('group_id', $groupId);
                        });
                    }),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),

                Action::make('timeline')
                    ->label('Timeline')
                    ->icon('heroicon-o-clock')
                    ->url(fn ($record): string => static::getUrl('timeline', ['record' => $record]))
                    ->color('primary'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make(MemberBulkActions::getActions()),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('New Member')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No members found')
            ->emptyStateDescription('Get started by creating a new member.')
            ->emptyStateIcon('heroicon-o-users')
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
            'view' => Pages\ViewMember::route('/{record}'),
            'timeline' => Pages\Timeline::route('/{record}/timeline'),
        ];
    }

    protected static function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery();
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // When viewing linked children from a parent, bypass department scoping
        // so hr_head and internal_relations_head can see all linked children
        if (request()->query('parent_id')) {
            return static::getModel()::query()
                ->withoutGlobalScope(DepartmentScope::class)
                ->with(['currentGroupAssignment.group']);
        }

        return parent::getEloquentQuery()
            ->with(['currentGroupAssignment.group']);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['department']);
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->full_name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Member Code' => $record->member_code,
            'Phone' => $record->phone,
            'Type' => $record->member_type,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'father_name', 'phone', 'member_code'];
    }
}
