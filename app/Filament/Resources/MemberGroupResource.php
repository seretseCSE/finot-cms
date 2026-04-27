<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberGroupResource\Pages;
use Filament\Schemas\Schema;
use App\Models\MemberGroup;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class MemberGroupResource extends Resource
{
    protected static ?string $model = MemberGroup::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Membership Management';
    }

    public static function getNavigationLabel(): string
    {
        return 'Member Groups';
    }

    public static function getModelLabel(): string
    {
        return 'Member Group';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Member Groups';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Grid::make(3)
                    ->schema([
                        Section::make('Group Registry')
                            ->description('Basic identification for this group.')
                            ->icon('heroicon-m-identification')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Group Name')
                                    ->placeholder('e.g. Saint Yared Choir')
                                    ->required()
                                    ->maxLength(200)
                                    ->unique(ignoreRecord: true)
                                    ->columnSpanFull(),

                                Select::make('group_type')
                                    ->label('Group Type')
                                    ->prefixIcon('heroicon-m-tag')
                                    ->options([
                                        'Kids' => 'Kids',
                                        'Elder Kids' => 'Elder Kids',
                                        'Youngsters' => 'Youngsters',
                                        'Youth' => 'Youth',
                                        'Finot Family' => 'Finot Family',
                                    ])
                                    ->native(false)
                                    ->searchable()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpan(['lg' => 2]),

                        Section::make('Group Purpose')
                            ->description('Additional details about the group’s focus and requirements.')
                            ->icon('heroicon-m-chat-bubble-bottom-center-text')
                            ->schema([
                                Textarea::make('description')
                                    ->label('Description')
                                    ->placeholder('Write a brief summary of what this group does...')
                                    ->rows(4)
                                    ->maxLength(1000)
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(['lg' => 2]),

                        Section::make('Visibility & Status')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Active Status')
                                    ->helperText('Inactive groups will not be available for new member assignments.')
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('danger'),
                            ])
                            ->columnSpan(['lg' => 1]),

                        Section::make('Audit Information')
                            ->icon('heroicon-m-clock')
                            ->schema([
                                Placeholder::make('created_at')
                                    ->label('Created On')
                                    ->content(fn (?MemberGroup $record): string => $record?->created_at?->toFormattedDateString() ?? 'New Record'),

                                Placeholder::make('updated_at')
                                    ->label('Last Updated')
                                    ->content(fn (?MemberGroup $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
                            ])
                            ->collapsible()
                            ->collapsed(fn (?MemberGroup $record): bool => $record === null)
                            ->columnSpan(['lg' => 1]),
                    ]),
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
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make(),

                Actions\Action::make('assign_member')
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
                                    ->with(['currentGroupAssignment.group'])
                                    ->orderBy('first_name')
                                    ->get()
                                    ->mapWithKeys(function ($member) {
                                        $groupName = $member->currentGroup?->name ?? 'Unassigned';
                                        return [
                                            (string) $member->id => "{$member->full_name} ({$groupName})",
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
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
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
