<?php

namespace App\Filament\Resources;

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
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Services\EthiopianDateHelper;

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
                        $q->where('parent_id', $parentId);
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
                    ->color(fn (string $state): string => match ($state) {
                        'Kids' => 'info',
                        'Youth' => 'warning',
                        'Adult' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'Member' => 'info',
                        'Active' => 'success',
                        'Former' => 'danger',
                        default => 'gray',
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
                // Use Tables\Actions for individual row actions
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),

                Action::make('timeline')
                    ->label('Timeline')
                    ->icon('heroicon-o-clock')
                    ->url(fn ($record): string => static::getUrl('timeline', ['record' => $record]))
                    ->color('primary'),

                Action::make('remove_from_group')
                    ->label('Remove from Group')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn ($record) => $record->currentGroup)
                    ->requiresConfirmation()
                    ->modalHeading('Remove Member from Group')
                    ->modalDescription(
                        fn ($record) => "Are you sure you want to remove {$record->full_name} from {$record->currentGroup->name}?"
                    )
                    ->action(function ($record) {
                        try {
                            if ($record->currentGroup) {
                                $record->currentGroup->removeMember($record->id);
                                Notification::make()
                                    ->title('Member Removed')
                                    ->body("{$record->full_name} has been removed from {$record->currentGroup->name}")
                                    ->success()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Removal Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions(MemberBulkActions::getActions())
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
        $query = parent::getTableQuery();

        // Handle parent_id filter from ParentResource
        if (request()->has('parent_id')) {
            $parentId = request()->get('parent_id');
            $query->whereHas('parentGuardians', function ($q) use ($parentId) {
                $q->where('parent_id', $parentId);
            });
        }

        return $query;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
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
