<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Models\Department;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getNavigationIcon(): ?string 
    { 
        return 'heroicon-o-users'; 
    }

    public static function getNavigationGroup(): ?string 
    { 
        return 'System'; 
    }

    protected static ?int $navigationSort = 1;

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

    public static function form(Schema $schema): Schema
    {
        $currentUser = Auth::user();
        $isSuperadmin = $currentUser->hasRole('superadmin');
        
        return $schema
            ->components([
                Forms\Components\Tabs::make('user_tabs')
                    ->tabs([
                        // Personal Information Tab
                        Forms\Components\Tabs\Tab::make('Personal Information')
                            ->icon('heroicon-o-user')
                            ->schema([
                                \Filament\Schemas\Components\Section::make('Basic Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Full Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->autofocus()
                                            ->placeholder('Enter user\'s full name'),

                                        Forms\Components\TextInput::make('phone')
                                            ->label('Phone Number')
                                            ->required()
                                            ->tel()
                                            ->unique(ignoreRecord: true)
                                            ->placeholder('+251 xxx xxx xxx')
                                            ->regex('/^\+?[0-9]{10,15}$/'),

                                        Forms\Components\TextInput::make('email')
                                            ->label('Email Address')
                                            ->email()
                                            ->unique(ignoreRecord: true)
                                            ->nullable()
                                            ->placeholder('user@example.com'),

                                        Forms\Components\Select::make('language_preference')
                                            ->label('Preferred Language')
                                            ->options([
                                                'am' => 'አማርኛ (Amharic)',
                                                'en' => 'English',
                                            ])
                                            ->default('am')
                                            ->required(),

                                        Forms\Components\Select::make('department_id')
                                            ->label('Department')
                                            ->relationship('department', 'name_en')
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText('Assign user to a department (optional)'),
                                    ])
                                    ->columns(2),

                                \Filament\Schemas\Components\Section::make('Account Status')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Active Account')
                                            ->default(true)
                                            ->helperText('Inactive users cannot log in to the system'),

                                        Forms\Components\Toggle::make('temp_password_changed')
                                            ->label('Password Changed')
                                            ->default(false)
                                            ->helperText('First-time users must change their temporary password')
                                            ->visible(fn ($record) => !$record?->exists),
                                    ])
                                    ->columns(2),
                            ]),

                        // Role & Department Tab
                        Forms\Components\Tabs\Tab::make('Role & Department')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                \Filament\Schemas\Components\Section::make('Role Assignment')
                                    ->schema([
                                        Forms\Components\Select::make('roles')
                                            ->label('User Role')
                                            ->relationship('roles', 'name')
                                            ->options(function () use ($currentUser) {
                                                $query = Role::query();
                                                
                                                // Only Superadmin can assign Superadmin role
                                                if (!$currentUser->hasRole('superadmin')) {
                                                    $query->whereNotIn('name', ['superadmin']);
                                                }
                                                
                                                return $query->pluck('name', 'name')->toArray();
                                            })
                                            ->required()
                                            ->multiple(false) // Only one role per user
                                            ->searchable()
                                            ->preload()
                                            ->helperText(function () use ($currentUser, $isSuperadmin) {
                                                if ($isSuperadmin) {
                                                    return 'Superadmin can assign any role. Users can only have one role.';
                                                }
                                                return 'Admin can assign roles except Superadmin. Users can only have one role.';
                                            })
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, $record) {
                                                // Auto-logout user if role is changed
                                                if ($record && $record->exists && $record->roles->first()?->name !== $state) {
                                                    $set('force_logout', true);
                                                }
                                            }),

                                        Forms\Components\Hidden::make('force_logout')
                                            ->default(false),
                                    ])
                                    ->columns(1),

                                \Filament\Schemas\Components\Section::make('Department Assignment')
                                    ->schema([
                                        Forms\Components\Select::make('department_id')
                                            ->label('Department')
                                            ->relationship('department', 'name_en')
                                            ->searchable()
                                            ->preload()
                                            ->required(fn ($get) => !in_array($get('roles'), ['superadmin', 'admin']))
                                            ->nullable(fn ($get) => in_array($get('roles'), ['superadmin', 'admin']))
                                            ->helperText(function ($get) {
                                                $role = $get('roles');
                                                if (in_array($role, ['superadmin', 'admin'])) {
                                                    return 'Department is not required for Superadmin and Admin roles.';
                                                }
                                                return 'Department is required for all other roles.';
                                            })
                                            ->reactive(),
                                    ])
                                    ->columns(1),

                                Forms\Components\Placeholder::make('role_department_info')
                                    ->content(function ($get) {
                                        $role = $get('roles');
                                        $deptId = $get('department_id');
                                        
                                        if (in_array($role, ['superadmin', 'admin'])) {
                                            return '⚠️ Superadmin and Admin roles have access to all departments.';
                                        }
                                        
                                        if (!$deptId && $role) {
                                            return '⚠️ Department assignment is required for this role.';
                                        }
                                        
                                        return '✅ Role and department properly configured.';
                                    })
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => $get('roles')),
                            ])
                            ->visible(fn ($record) => $currentUser->hasRole(['superadmin', 'admin'])),

                        // Security Tab
                        Forms\Components\Tabs\Tab::make('Security')
                            ->icon('heroicon-o-lock-closed')
                            ->schema([
                                \Filament\Schemas\Components\Section::make('Password Settings')
                                    ->schema([
                                        Forms\Components\TextInput::make('password')
                                            ->label(fn ($record) => $record?->exists ? 'New Password' : 'Password')
                                            ->password()
                                            ->required(fn ($record) => !$record?->exists)
                                            ->rule(Password::default())
                                            ->dehydrateStateUsing(fn ($state) => $state ? Hash::make($state) : null)
                                            ->dehydrated(fn ($state) => filled($state))
                                            ->helperText(function ($record) {
                                                if ($record?->exists) {
                                                    return 'Leave empty to keep current password';
                                                }
                                                return 'Password must be at least 8 characters with mixed case, numbers, and symbols';
                                            }),

                                        Forms\Components\TextInput::make('password_confirmation')
                                            ->label('Confirm Password')
                                            ->password()
                                            ->requiredWith('password')
                                            ->same('password')
                                            ->dehydrated(false),

                                        Forms\Components\Toggle::make('force_password_change')
                                            ->label('Force Password Change on Next Login')
                                            ->default(false)
                                            ->helperText('User will be required to change password on next login')
                                            ->visible(fn ($record) => $record?->exists),
                                    ])
                                    ->columns(2),

                                \Filament\Schemas\Components\Section::make('Security Status')
                                    ->schema([
                                        Forms\Components\Placeholder::make('current_status')
                                            ->label('Current Status')
                                            ->content(function ($record) {
                                                if (!$record?->exists) {
                                                    return 'New user - not yet created';
                                                }

                                                $status = [];
                                                
                                                if ($record->is_active) {
                                                    $status[] = '✅ Active';
                                                } else {
                                                    $status[] = '❌ Inactive';
                                                }

                                                if ($record->isCurrentlyLocked()) {
                                                    $status[] = '🔒 Locked';
                                                } else {
                                                    $status[] = '🔓 Unlocked';
                                                }

                                                if (!$record->temp_password_changed) {
                                                    $status[] = '⚠️ Temporary password';
                                                }

                                                return implode(' | ', $status);
                                            }),

                                        Forms\Components\Placeholder::make('failed_attempts')
                                            ->label('Failed Login Attempts')
                                            ->content(fn ($record) => $record?->failed_login_attempts ?? 0)
                                            ->visible(fn ($record) => $record?->exists),

                                        Forms\Components\Placeholder::make('last_login')
                                            ->label('Last Login')
                                            ->content(fn ($record) => $record?->last_login_at?->format('M j, Y H:i') ?? 'Never')
                                            ->visible(fn ($record) => $record?->exists),
                                    ])
                                    ->columns(3),
                            ])
                            ->visible(fn ($record) => $currentUser->hasRole(['superadmin', 'admin']) || (!$record?->exists)),
                    ])
                    ->columnSpanFull()
                    ->persistTab()
                    ->defaultTab('personal-information'),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currentUser = Auth::user();
        $isSuperadmin = $currentUser->hasRole('superadmin');

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Phone number copied'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\BadgeColumn::make('roles.name')
                    ->label('Role')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        'superadmin' => 'danger',
                        'admin' => 'warning',
                        'teacher' => 'success',
                        'student' => 'primary',
                        'parent' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.name_en')
                    ->label('Department')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('No department'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\BadgeColumn::make('lock_status')
                    ->label('Status')
                    ->getStateUsing(fn (User $record): array => $record->getLockStatusBadge())
                    ->sortable(),

                Tables\Columns\IconColumn::make('temp_password_changed')
                    ->label('Password')
                    ->boolean()
                    ->sortable()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (User $record): string => 
                        $record->temp_password_changed ? 'Password changed' : 'Temporary password'
                    ),

                Tables\Columns\TextColumn::make('language_preference')
                    ->label('Lang')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'am' => 'warning',
                        'en' => 'primary',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),

                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload()
                    ->options(function () use ($currentUser) {
                        $query = Role::query();
                        
                        if (!$currentUser->hasRole('superadmin')) {
                            $query->whereNotIn('name', ['superadmin']);
                        }
                        
                        return $query->pluck('name', 'name')->toArray();
                    }),

                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name_en')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('is_locked')
                    ->label('Lock Status')
                    ->options([
                        '1' => 'Locked',
                        '0' => 'Not Locked',
                    ]),

                Tables\Filters\Filter::make('temp_password')
                    ->label('Temporary Password')
                    ->query(fn (Builder $query): Builder => $query->where('temp_password_changed', false)),

                Tables\Filters\Filter::make('created_this_month')
                    ->label('Created This Month')
                    ->query(fn (Builder $query): Builder => $query->whereMonth('created_at', now()->month)),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (User $record): bool => 
                        $currentUser->hasRole(['superadmin', 'admin']) || $currentUser->id === $record->id
                    ),

                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn (User $record): bool => 
                        !$record->is_active && $currentUser->hasRole(['superadmin', 'admin'])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Activate User Account')
                    ->modalDescription('This will activate the user account and allow them to log in.')
                    ->action(function (User $record) {
                        $record->update(['is_active' => true]);
                        
                        activity()
                            ->causedBy($currentUser)
                            ->performedOn($record)
                            ->withProperties([
                                'action' => 'activate_user',
                                'old_status' => false,
                                'new_status' => true,
                            ])
                            ->log('user_activated');

                        \Filament\Notifications\Notification::make()
                            ->title('User Activated')
                            ->body("User '{$record->name}' has been activated successfully.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-user-minus')
                    ->color('warning')
                    ->visible(fn (User $record): bool => 
                        $record->is_active && 
                        $currentUser->hasRole(['superadmin', 'admin']) && 
                        $currentUser->id !== $record->id
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Deactivate User Account')
                    ->modalDescription('This will deactivate the user account and prevent them from logging in.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for deactivation')
                            ->required()
                            ->rows(3)
                            ->placeholder('Please provide a reason for deactivating this account...'),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update(['is_active' => false]);
                        
                        // Force logout user
                        Session::where('user_id', $record->id)->delete();
                        
                        activity()
                            ->causedBy($currentUser)
                            ->performedOn($record)
                            ->withProperties([
                                'action' => 'deactivate_user',
                                'old_status' => true,
                                'new_status' => false,
                                'reason' => $data['reason'],
                            ])
                            ->log('user_deactivated');

                        \Filament\Notifications\Notification::make()
                            ->title('User Deactivated')
                            ->body("User '{$record->name}' has been deactivated and logged out.")
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('lock')
                    ->label('Lock Account')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (User $record): bool => 
                        !$record->isCurrentlyLocked() && 
                        $currentUser->hasRole(['superadmin', 'admin']) && 
                        $currentUser->id !== $record->id
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Lock User Account')
                    ->modalDescription('This will lock the user account and prevent them from logging in.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for locking')
                            ->required()
                            ->rows(3)
                            ->placeholder('Please provide a reason for locking this account...'),
                        
                        Forms\Components\Select::make('duration')
                            ->label('Lock Duration')
                            ->options([
                                '1h' => '1 Hour',
                                '24h' => '24 Hours',
                                '7d' => '7 Days',
                                '30d' => '30 Days',
                                'permanent' => 'Permanent',
                            ])
                            ->required()
                            ->default('24h'),
                    ])
                    ->action(function (User $record, array $data) use ($currentUser) {
                        $record->lockAccount($data['reason'], $data['duration'], $currentUser->id);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Account Locked')
                            ->body("User '{$record->name}' has been locked for {$data['duration']}.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('unlock')
                    ->label('Unlock Account')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn (User $record): bool => 
                        $record->isCurrentlyLocked() && $currentUser->hasRole(['superadmin', 'admin'])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Unlock User Account')
                    ->modalDescription('This will unlock the user account and allow them to log in again.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for unlocking')
                            ->required()
                            ->rows(3)
                            ->placeholder('Please provide a reason for unlocking this account...'),
                    ])
                    ->action(function (User $record, array $data) use ($currentUser) {
                        $record->unlockAccount($currentUser->id);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Account Unlocked')
                            ->body("User '{$record->name}' has been unlocked successfully.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn (User $record): bool => 
                        $currentUser->hasRole(['superadmin', 'admin']) && $currentUser->id !== $record->id
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Reset User Password')
                    ->modalDescription('This will generate a temporary password for the user.')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('New Temporary Password')
                            ->password()
                            ->default(fn () => \Illuminate\Support\Str::random(12))
                            ->required()
                            ->helperText('User will be required to change this password on next login'),
                    ])
                    ->action(function (User $record, array $data) use ($currentUser) {
                        $record->update([
                            'password' => Hash::make($data['new_password']),
                            'temp_password_changed' => false,
                            'failed_login_attempts' => 0,
                        ]);
                        
                        // Force logout user
                        Session::where('user_id', $record->id)->delete();
                        
                        activity()
                            ->causedBy($currentUser)
                            ->performedOn($record)
                            ->withProperties([
                                'action' => 'password_reset',
                                'temp_password' => true,
                            ])
                            ->log('password_reset');

                        \Filament\Notifications\Notification::make()
                            ->title('Password Reset')
                            ->body("Temporary password for '{$record->name}': {$data['new_password']}")
                            ->warning()
                            ->duration(10000) // Show for 10 seconds
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('copy')
                                    ->label('Copy Password')
                                    ->action(function () use ($data) {
                                        // This would need clipboard JS in a real implementation
                                    }),
                            ])
                            ->send();
                    }),

                Actions\DeleteAction::make()
                    ->visible(fn (User $record): bool => 
                        $isSuperadmin && $currentUser->id !== $record->id
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->visible(fn (): bool => $currentUser->hasRole(['superadmin', 'admin']))
                        ->requiresConfirmation()
                        ->action(function (array $records) use ($currentUser) {
                            $activatedCount = 0;
                            foreach ($records as $record) {
                                if (!$record->is_active) {
                                    $record->update(['is_active' => true]);
                                    $activatedCount++;
                                    
                                    activity()
                                        ->causedBy($currentUser)
                                        ->performedOn($record)
                                        ->withProperties([
                                            'action' => 'bulk_activate_user',
                                            'old_status' => false,
                                            'new_status' => true,
                                        ])
                                        ->log('user_bulk_activated');
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Bulk Activation Completed')
                                ->body("Successfully activated {$activatedCount} user(s).")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-user-minus')
                        ->color('warning')
                        ->visible(fn (): bool => $currentUser->hasRole(['superadmin', 'admin']))
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for deactivation')
                                ->required()
                                ->rows(3)
                                ->placeholder('Please provide a reason for deactivating these accounts...'),
                        ])
                        ->action(function (array $records, array $data) use ($currentUser) {
                            $deactivatedCount = 0;
                            foreach ($records as $record) {
                                if ($record->is_active && $currentUser->id !== $record->id) {
                                    $record->update(['is_active' => false]);
                                    Session::where('user_id', $record->id)->delete();
                                    $deactivatedCount++;
                                    
                                    activity()
                                        ->causedBy($currentUser)
                                        ->performedOn($record)
                                        ->withProperties([
                                            'action' => 'bulk_deactivate_user',
                                            'old_status' => true,
                                            'new_status' => false,
                                            'reason' => $data['reason'],
                                        ])
                                        ->log('user_bulk_deactivated');
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Bulk Deactivation Completed')
                                ->body("Successfully deactivated {$deactivatedCount} user(s).")
                                ->warning()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('lock')
                        ->label('Lock Selected')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->visible(fn (): bool => $currentUser->hasRole(['superadmin', 'admin']))
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for locking')
                                ->required()
                                ->rows(3)
                                ->placeholder('Please provide a reason for locking these accounts...'),
                            
                            Forms\Components\Select::make('duration')
                                ->label('Lock Duration')
                                ->options([
                                    '1h' => '1 Hour',
                                    '24h' => '24 Hours',
                                    '7d' => '7 Days',
                                    '30d' => '30 Days',
                                    'permanent' => 'Permanent',
                                ])
                                ->required()
                                ->default('24h'),
                        ])
                        ->action(function (array $records, array $data) use ($currentUser) {
                            $lockedCount = 0;
                            foreach ($records as $record) {
                                if (!$record->isCurrentlyLocked() && $currentUser->id !== $record->id) {
                                    $record->lockAccount($data['reason'], $data['duration'], $currentUser->id);
                                    $lockedCount++;
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Bulk Lock Completed')
                                ->body("Successfully locked {$lockedCount} user(s).")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('unlock')
                        ->label('Unlock Selected')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->visible(fn (): bool => $currentUser->hasRole(['superadmin', 'admin']))
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for unlocking')
                                ->required()
                                ->rows(3)
                                ->placeholder('Please provide a reason for unlocking these accounts...'),
                        ])
                        ->action(function (array $records, array $data) use ($currentUser) {
                            $unlockedCount = 0;
                            foreach ($records as $record) {
                                if ($record->isCurrentlyLocked()) {
                                    $record->unlockAccount($currentUser->id);
                                    $unlockedCount++;
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Bulk Unlock Completed')
                                ->body("Successfully unlocked {$unlockedCount} user(s).")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn (): bool => $currentUser->hasRole(['superadmin', 'admin'])),
            ])
            ->recordUrl(function (User $record) use ($currentUser) {
                // Only allow editing if user has permission
                if ($currentUser->hasRole(['superadmin', 'admin']) || $currentUser->id === $record->id) {
                    return Pages\EditUser::getUrl(['record' => $record]);
                }
                return null;
            });
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $currentUser = Auth::user();
        
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                //
            ])
            ->when(!$currentUser->hasRole('superadmin'), function (Builder $query) use ($currentUser) {
                // Non-superadmin users cannot see superadmin users
                $query->whereDoesntHave('roles', function (Builder $roleQuery) {
                    $roleQuery->where('name', 'superadmin');
                });
            });
    }

    // Access Control Methods
    public static function canViewAny(): bool
    {
        return Auth::user()->hasRole(['superadmin', 'admin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()->hasRole(['superadmin', 'admin']);
    }

    public static function canEdit($record): bool
    {
        $currentUser = Auth::user();
        return $currentUser->hasRole(['superadmin', 'admin']) || $currentUser->id === $record->id;
    }

    public static function canDelete($record): bool
    {
        $currentUser = Auth::user();
        return $currentUser->hasRole('superadmin') && $currentUser->id !== $record->id;
    }

    public static function canDeleteAny(): bool
    {
        return Auth::user()->hasRole('superadmin');
    }

    public static function canRestore($record): bool
    {
        return Auth::user()->hasRole('superadmin');
    }

    public static function canForceDelete($record): bool
    {
        return Auth::user()->hasRole('superadmin') && Auth::id() !== $record->id;
    }

    public static function canForceDeleteAny(): bool
    {
        return Auth::user()->hasRole('superadmin');
    }
}


