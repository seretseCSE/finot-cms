<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberGroupResource\Pages;
use App\Models\MemberGroup;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MemberGroupResource extends Resource
{
    protected static ?string $model = MemberGroup::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-user-group'; }

    public static function getNavigationGroup(): ?string { return 'Membership'; }

    public static function getNavigationLabel(): string { return 'Member Groups / የአባላት ቡድሮች'; }

    public static function getModelLabel(): string { return 'Member Group'; }

    public static function getPluralModelLabel(): string { return 'Member Groups'; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Group Name')
                            ->required()
                            ->maxLength(200)
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('group_type')
                            ->label('Group Type')
                            ->options([
                                'Kids' => 'Kids',
                                'Youth' => 'Youth',
                                'Adult' => 'Adult',
                                'Ministry' => 'Ministry',
                                'Other' => 'Other',
                            ])
                            ->nullable(),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('group_type')
                    ->label('Type')
                    ->colors([
                        'info' => 'Kids',
                        'warning' => 'Youth',
                        'success' => 'Adult',
                        'primary' => 'Ministry',
                        'secondary' => 'Other',
                    ]),

                Tables\Columns\TextColumn::make('active_member_count')
                    ->label('Active Members')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state . ' members')
                    ->counts('activeAssignments'),

                Tables\Columns\BadgeColumn::make('is_active')
                    ->label('Status')
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ])
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group_type')
                    ->label('Group Type')
                    ->options([
                        'Kids' => 'Kids',
                        'Youth' => 'Youth',
                        'Adult' => 'Adult',
                        'Ministry' => 'Ministry',
                        'Other' => 'Other',
                    ]),

                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),

                Tables\Actions\Action::make('assign_member')
                    ->label('Assign Member')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\Select::make('member_id')
                            ->label('Select Member')
                            ->options(function () {
                                return \App\Models\Member::query()
                                    ->where(function ($query) {
                                        // Show unassigned members or those in other groups
                                        $query->whereNotIn('id', function ($subQuery) {
                                            $subQuery->select('member_id')
                                                ->from('member_group_assignments')
                                                ->whereNull('effective_to');
                                        })
                                        ->orWhereHas('groupAssignments', function ($query) {
                                            $query->whereNotNull('effective_to');
                                        });
                                    })
                                    ->with(['currentGroup'])
                                    ->orderBy('first_name')
                                    ->get()
                                    ->mapWithKeys(function ($member) {
                                        $groupName = $member->currentGroup?->name ?? 'Unassigned';
                                        return [
                                            'label' => "{$member->full_name} ({$groupName})",
                                            'value' => $member->id,
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->searchable()
                            ->required(),

                        Forms\Components\DatePicker::make('effective_from')
                            ->label('Effective From Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (array $data, MemberGroup $record) {
                        try {
                            $record->assignMember($data['member_id'], $data['effective_from']);

                            \Filament\Notifications\Notification::make()
                                ->title('Member Assigned')
                                ->body("Member successfully assigned to {$record->name}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Assignment Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalHeading('Assign Member to Group')
                    ->modalWidth('2xl'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListMemberGroups::route('/'),
            'create' => Pages\CreateMemberGroup::route('/create'),
            'edit' => Pages\EditMemberGroup::route('/{record}/edit'),
            'view' => Pages\ViewMemberGroup::route('/{record}'),
            'assignment-history' => Pages\GroupAssignmentHistory::route('/{record}/assignment-history'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'admin',
            'superadmin',
            'hr_head',
            'internal_relations_head'
        ]);
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'admin',
            'superadmin',
            'hr_head',
            'internal_relations_head'
        ]);
    }

    public static function canEdit($record): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'admin',
            'superadmin',
            'hr_head',
            'internal_relations_head'
        ]);
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();

        if (!$user->hasRole(['admin', 'superadmin'])) {
            return false;
        }

        // Check if group has active assignments
        return $record->canBeDeleted();
    }

    public static function canRestore($record): bool
    {
        $user = Auth::user();

        return $user->hasRole([
            'admin',
            'superadmin'
        ]);
    }

    protected static function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getTableQuery()->withCount(['activeAssignments']);
    }
}

