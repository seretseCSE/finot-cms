<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Forms\Components\Traits\HasPhoneFormatting;
use Filament\Schemas\Schema;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    use HasPhoneFormatting;
    protected static ?string $model = User::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationLabel(): string
    {
        return 'User Management';
    }

    public static function getModelLabel(): string
    {
        return 'User';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Users';
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(Roles::ADMINISTRATORS);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(Roles::ADMINISTRATORS);
    }

    public static function canEdit($record): bool
    {
        $user = Auth::user();

        if (! $user?->hasRole(Roles::ADMINISTRATORS)) {
            return false;
        }

        // Prevent editing own account through this resource to avoid self-lockout
        if ($record?->id === $user->id) {
            return false;
        }

        // Admin cannot edit superadmin
        if ($record?->hasRole('superadmin') && ! $user->hasRole('superadmin')) {
            return false;
        }

        return true;
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();

        // Only superadmin can delete users
        if (! $user?->hasRole('superadmin')) {
            return false;
        }

        // Cannot delete yourself
        if ($record?->id === $user->id) {
            return false;
        }

        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('User Information')
                    ->schema([
                        TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        HasPhoneFormatting::uniquePhoneInput('phone', 'Phone Number', true),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->nullable()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->relationship('roles', 'label', modifyQueryUsing: function ($query) {
                                if (! Auth::user()?->hasRole('superadmin')) {
                                    $query->where('name', '!=', 'superadmin');
                                }
                            })
                            ->saveRelationshipsUsing(fn () => null)
                            ->required()
                            ->preload()
                            ->searchable()
                            ->live(),

                        Select::make('department_id')
                            ->label('Department')
                            ->relationship('department', 'name_en')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Select::make('language_preference')
                            ->label('Language Preference')
                            ->options([
                                'en' => 'English',
                                'am' => 'Amharic',
                            ])
                            ->default('en')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Account Status')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive users cannot log in.'),

                        Toggle::make('is_locked')
                            ->label('Account Locked')
                            ->default(false)
                            ->helperText('Locked users cannot log in.'),

                        Forms\Components\DateTimePicker::make('locked_until')
                            ->label('Auto-Unlock At')
                            ->nullable()
                            ->helperText('Leave empty for permanent lock.'),

                        TextInput::make('lock_reason')
                            ->label('Lock Reason')
                            ->nullable()
                            ->maxLength(255)
                            ->visible(fn (callable $get) => $get('is_locked')),
                    ])
                    ->columns(2),

                Section::make('Security')
                    ->schema([
                        TextInput::make('password')
                            ->label(fn ($record) => $record ? 'New Password' : 'Password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->confirmed()
                            ->required(fn ($record) => ! $record)
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText(fn ($record) => $record ? 'Leave empty to keep current password' : 'Minimum 8 characters'),

                        TextInput::make('password_confirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->revealable()
                            ->dehydrated(false)
                            ->requiredWith('password'),

                        Toggle::make('force_password_change')
                            ->label('Force Password Change on Next Login')
                            ->default(false)
                            ->helperText('User must change password on next login.')
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->columns(1),
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

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.name_en')
                    ->label('Department')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('No department'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('lock_status')
                    ->label('Lock Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Locked' => 'danger',
                        'Permanently Locked' => 'danger',
                        default => 'gray',
                    })
                    ->getStateUsing(function (User $record): string {
                        $badge = $record->getLockStatusBadge();

                        return $badge['status'];
                    }),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->relationship('roles', 'label', modifyQueryUsing: function ($query) {
                        if (! Auth::user()?->hasRole('superadmin')) {
                            $query->where('name', '!=', 'superadmin');
                        }
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('department')
                    ->label('Department')
                    ->relationship('department', 'name_en')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),

                Tables\Filters\Filter::make('is_locked')
                    ->label('Locked')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $q) {
                        $q->where('is_locked', true)
                            ->orWhere(function (Builder $sq) {
                                $sq->whereNotNull('locked_until')
                                    ->where('locked_until', '>', now());
                            });
                    }))
                    ->toggle(),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (User $record) => static::canEdit($record)),

                Actions\Action::make('lock')
                    ->label('Lock')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(function (User $record): bool {
                        $currentUser = Auth::user();

                        return $currentUser?->hasRole(Roles::ADMINISTRATORS)
                            && $record->id !== $currentUser->id
                            && ! $record->isCurrentlyLocked();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Lock User Account')
                    ->modalDescription('This will prevent the user from logging in.')
                    ->modalSubmitActionLabel('Lock Account')
                    ->form([
                        Forms\Components\Textarea::make('lock_reason')
                            ->label('Reason')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->lockAccount(
                            $data['lock_reason'],
                            'permanent',
                            Auth::id()
                        );

                        // Clear user sessions
                        DB::table('sessions')->where('user_id', $record->id)->delete();

                        Notification::make()
                            ->title('Account Locked')
                            ->body("{$record->name}'s account has been locked.")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('unlock')
                    ->label('Unlock')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(function (User $record): bool {
                        $currentUser = Auth::user();

                        return $currentUser?->hasRole(Roles::ADMINISTRATORS)
                            && $record->isCurrentlyLocked();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Unlock User Account')
                    ->modalDescription('This will allow the user to log in again.')
                    ->modalSubmitActionLabel('Unlock Account')
                    ->action(function (User $record): void {
                        $record->unlockAccount(Auth::id());

                        Notification::make()
                            ->title('Account Unlocked')
                            ->body("{$record->name}'s account has been unlocked.")
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(function (User $record): bool {
                        return Auth::user()?->hasRole(Roles::ADMINISTRATORS)
                            && $record->id !== Auth::id();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Reset User Password')
                    ->modalDescription('A temporary password will be generated and displayed. This password will NOT be shown again.')
                    ->modalSubmitActionLabel('Reset Password')
                    ->action(function (User $record): void {
                        $tempPassword = Str::password(12);
                        $record->update([
                            'password' => $tempPassword,
                            'temp_password_changed' => false,
                        ]);

                        Notification::make()
                            ->title('Password Reset — Copy Temporary Password NOW')
                            ->body(
                                "**User:** {$record->name}\n".
                                "**Temporary Password:**\n".
                                "```\n{$tempPassword}\n```\n".
                                '⚠️ **This password will NOT be shown again.**'
                            )
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                Actions\Action::make('force_logout')
                    ->label('Force Logout')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('danger')
                    ->visible(function (User $record): bool {
                        return Auth::user()?->hasRole(Roles::ADMINISTRATORS)
                            && $record->id !== Auth::id();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Force Logout')
                    ->modalDescription('This will terminate all active sessions for this user.')
                    ->modalSubmitActionLabel('Force Logout')
                    ->action(function (User $record): void {
                        DB::table('sessions')->where('user_id', $record->id)->delete();

                        Notification::make()
                            ->title('User Logged Out')
                            ->body("All sessions for {$record->name} have been terminated.")
                            ->success()
                            ->send();
                    }),

                Actions\DeleteAction::make()
                    ->visible(fn (User $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                Actions\BulkAction::make('lock')
                    ->label('Lock Selected')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Lock Selected Accounts')
                    ->modalDescription('This will prevent selected users from logging in.')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                        foreach ($records as $record) {
                            if ($record->id === Auth::id()) {
                                continue;
                            }

                            $record->lockAccount('Bulk lock action', 'permanent', Auth::id());
                            DB::table('sessions')->where('user_id', $record->id)->delete();
                        }

                        Notification::make()
                            ->title('Accounts Locked')
                            ->body("{$records->count()} accounts have been locked.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => Auth::user()?->hasRole(Roles::ADMINISTRATORS) ?? false),

                Actions\BulkAction::make('unlock')
                    ->label('Unlock Selected')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Unlock Selected Accounts')
                    ->modalDescription('This will allow selected users to log in again.')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                        foreach ($records as $record) {
                            $record->unlockAccount(Auth::id());
                        }

                        Notification::make()
                            ->title('Accounts Unlocked')
                            ->body("{$records->count()} accounts have been unlocked.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => Auth::user()?->hasRole(Roles::ADMINISTRATORS) ?? false),

                Actions\DeleteBulkAction::make()
                    ->visible(fn (): bool => Auth::user()?->hasRole(Roles::ADMINISTRATORS) ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No users found')
            ->emptyStateDescription('Create your first user to get started.')
            ->emptyStateIcon('heroicon-o-user-group');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
