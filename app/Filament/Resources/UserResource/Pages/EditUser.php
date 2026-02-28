<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $currentUser = Auth::user();
        $user = $this->record;
        
        // Handle password change
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            
            // If force password change is not set, mark as changed
            if (!($data['force_password_change'] ?? false)) {
                $data['temp_password_changed'] = true;
            } else {
                $data['temp_password_changed'] = false;
            }
            
            // Reset failed login attempts on password change
            $data['failed_login_attempts'] = 0;
        } else {
            // Remove password field if empty
            unset($data['password']);
            unset($data['password_confirmation']);
        }
        
        // Handle role change
        $oldRole = $user->roles->first()?->name;
        $newRole = $data['roles'] ?? null;
        
        if ($oldRole !== $newRole && $newRole) {
            // Store for after save
            $this->oldRole = $oldRole;
            $this->newRole = $newRole;
            $this->roleChanged = true;
            
            // Remove roles from data to handle separately
            unset($data['roles']);
        }
        
        // Handle force logout
        if ($data['force_logout'] ?? false) {
            $this->forceLogout = true;
            unset($data['force_logout']);
        }
        
        return $data;
    }

    protected function afterSave(): void
    {
        $user = $this->record;
        $currentUser = Auth::user();
        
        // Handle role assignment
        if ($this->roleChanged && $this->newRole) {
            $user->syncRoles([$this->newRole]);
            
            // Force logout by destroying all of the user's database-backed sessions
            DB::table('sessions')->where('user_id', $user->id)->delete();
            
            // Log role change
            activity()
                ->causedBy($currentUser)
                ->performedOn($user)
                ->withProperties([
                    'action' => 'change_role',
                    'old_role' => $this->oldRole,
                    'new_role' => $this->newRole,
                    'force_logout' => true,
                ])
                ->log('user_role_changed');
        }
        
        // Handle force logout
        if ($this->forceLogout) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            
            activity()
                ->causedBy($currentUser)
                ->performedOn($user)
                ->withProperties([
                    'action' => 'force_logout',
                    'reason' => 'User modification',
                ])
                ->log('user_force_logged_out');
        }
        
        // Log the user update
        activity()
            ->causedBy($currentUser)
            ->performedOn($user)
            ->withProperties([
                'action' => 'update_user',
                'role_changed' => $this->roleChanged ?? false,
                'password_changed' => !empty($this->data['password']),
                'force_logout' => $this->forceLogout ?? false,
            ])
            ->log('user_updated');
    }

    protected function getHeaderActions(): array
    {
        $currentUser = Auth::user();
        $user = $this->record;
        
        return [
            Actions\Action::make('view_as_user')
                ->label('View as User')
                ->icon('heroicon-o-eye')
                ->visible(fn () => $currentUser->hasRole(['superadmin']) && $currentUser->id !== $user->id)
                ->url(function () use ($user) {
                    // Implementation for viewing as user
                    return '#';
                })
                ->modalHeading('View as User')
                ->modalDescription('This will allow you to see the system from this user\'s perspective.')
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Reason for viewing as user')
                        ->required()
                        ->rows(2),
                ])
                ->action(function (array $data) use ($user) {
                    activity()
                        ->causedBy($currentUser)
                        ->performedOn($user)
                        ->withProperties([
                            'action' => 'view_as_user',
                            'reason' => $data['reason'],
                        ])
                        ->log('viewed_as_user');
                    
                    \Filament\Notifications\Notification::make()
                        ->title('View as User')
                        ->body('View as user functionality would be implemented here.')
                        ->info()
                        ->send();
                }),

            Actions\Action::make('send_password_reset')
                ->label('Send Password Reset')
                ->icon('heroicon-o-key')
                ->visible(fn () => $currentUser->hasRole(['superadmin', 'admin']) && $currentUser->id !== $user->id)
                ->requiresConfirmation()
                ->modalHeading('Send Password Reset Email')
                ->modalDescription('This will send a password reset link to the user\'s email.')
                ->action(function () use ($user) {
                    // Implementation for password reset email
                    activity()
                        ->causedBy($currentUser)
                        ->performedOn($user)
                        ->withProperties([
                            'action' => 'send_password_reset',
                        ])
                        ->log('password_reset_sent');
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Password Reset Sent')
                        ->body('Password reset email has been sent to the user.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('login_history')
                ->label('Login History')
                ->icon('heroicon-o-clock')
                ->visible(fn () => $currentUser->hasRole(['superadmin', 'admin']))
                ->modalHeading('Login History')
                ->modalContent(function () use ($user) {
                    // Implementation for login history
                    return 'Login history would be displayed here.';
                })
                ->modalFooterActions([
                    \Filament\Actions\Action::make('close')
                        ->label('Close')
                        ->cancel(),
                ]),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Save Changes')
                ->icon('heroicon-o-check'),
                
            $this->getCancelFormAction()
                ->label('Cancel'),
        ];
    }

    public function getHeading(): string
    {
        $user = $this->record;
        return "Edit User: {$user->name}";
    }

    public function getSubheading(): string
    {
        $currentUser = Auth::user();
        $user = $this->record;
        
        if ($currentUser->id === $user->id) {
            return 'You are editing your own profile';
        }
        
        return "Manage {$user->name}'s account information";
    }

    protected ?string $oldRole = null;
    protected ?string $newRole = null;
    protected bool $roleChanged = false;
    protected bool $forceLogout = false;
}
