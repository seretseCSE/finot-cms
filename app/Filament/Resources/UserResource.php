<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;
use BackedEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | null | BackedEnum $navigationIcon = 'heroicon-o-users';

    protected static string | null | UnitEnum $navigationGroup = 'Administration';

    public static function getNavigationSort(): ?int { return 1; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                    
                Forms\Components\TextInput::make('phone')
                    ->required()
                    ->tel()
                    ->unique(ignoreRecord: true),
                    
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->nullable(),
                    
                Forms\Components\Select::make('language_preference')
                    ->options([
                        'am' => 'Amharic (አማርኛ)',
                        'en' => 'English',
                    ])
                    ->default('am'),
                    
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                    
                Forms\Components\Toggle::make('temp_password_changed')
                    ->label('Password Changed')
                    ->default(true),
                    
                Forms\Components\Select::make('department_id')
                    ->relationship('department')
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_locked')
                    ->label('Locked')
                    ->boolean()
                    ->sortable()
                    ->color(fn (User $record): string => match ($record->is_locked) {
                        true => 'danger',
                        false => 'success',
                    }),
                    
                Tables\Columns\TextColumn::make('language_preference')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'am' => 'warning',
                        'en' => 'primary',
                    })
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('failed_login_attempts')
                    ->label('Failed Attempts')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('locked_until')
                    ->label('Locked Until')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (User $record): string => 
                        $record->locked_until?->format('M j, Y H:i') ?? 'Not locked'
                    ),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                    
                Tables\Filters\SelectFilter::make('is_locked')
                    ->options([
                        '1' => 'Locked',
                        '0' => 'Not Locked',
                    ]),
                    
                Tables\Filters\SelectFilter::make('language_preference')
                    ->options([
                        'am' => 'Amharic',
                        'en' => 'English',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('lock')
                    ->label('Lock Account')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (User $record): bool => !$record->is_locked && Auth::user()->can('lock', $record))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for locking')
                            ->required()
                            ->rows(3)
                            ->placeholder('Please provide a reason for locking this account...'),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->manuallyLock($data['reason'], Auth::id());
                    })
                    ->successNotificationTitle('Account locked')
                    ->failureNotificationTitle('Failed to lock account'),
                    
                Tables\Actions\Action::make('unlock')
                    ->label('Unlock Account')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->is_locked && Auth::user()->can('unlock', $record))
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for unlocking')
                            ->required()
                            ->rows(3)
                            ->placeholder('Please provide a reason for unlocking this account...'),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->manuallyUnlock($data['reason'], Auth::id());
                    })
                    ->successNotificationTitle('Account unlocked')
                    ->failureNotificationTitle('Failed to unlock account'),
                    
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record): bool => Auth::user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('lock')
                        ->label('Lock Selected')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for locking')
                                ->required()
                                ->rows(3)
                                ->placeholder('Please provide a reason for locking these accounts...'),
                        ])
                        ->action(function (array $records, array $data) {
                            foreach ($records as $record) {
                                if (!$record->is_locked) {
                                    $record->manuallyLock($data['reason'], Auth::id());
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                        
                    Tables\Actions\BulkAction::make('unlock')
                        ->label('Unlock Selected')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for unlocking')
                                ->required()
                                ->rows(3)
                                ->placeholder('Please provide a reason for unlocking these accounts...'),
                        ])
                        ->action(function (array $records, array $data) {
                            foreach ($records as $record) {
                                if ($record->is_locked) {
                                    $record->manuallyUnlock($data['reason'], Auth::id());
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ])
                    ->deselectRecordsAfterCompletion()
                    ->visible(fn (): bool => Auth::user()->can('lock', User::class)),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
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
            'index' => Pages\ListUsers::class,
            'create' => Pages\CreateUser::class,
            'edit' => Pages\EditUser::class,
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                // Remove any soft deletes if you're using them
            ]);
    }

    public static function canViewAny(): bool
    {
        return Auth::user()->hasRole(['admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()->hasRole(['admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()->hasRole(['admin', 'superadmin']) || Auth::id() === $record->id;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()->hasRole(['superadmin']) && Auth::id() !== $record->id;
    }

    public static function canLock($record): bool
    {
        return Auth::user()->hasRole(['admin', 'superadmin']) && Auth::id() !== $record->id;
    }

    public static function canUnlock($record): bool
    {
        return Auth::user()->hasRole(['admin', 'superadmin']);
    }
}

