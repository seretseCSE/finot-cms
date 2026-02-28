<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $currentUser = Auth::user();
        
        // Set default values
        $data['temp_password_changed'] = false;
        $data['failed_login_attempts'] = 0;
        
        // Generate temporary password if not provided
        if (empty($data['password'])) {
            $data['password'] = Str::random(12);
            $data['temp_password_changed'] = false;
        }
        
        // Hash the password
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        
        // Handle role assignment
        if (isset($data['roles'])) {
            $role = $data['roles'];
            unset($data['roles']);
            
            // Store role for after creation
            $this->roleToAssign = $role;
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->record;
        $currentUser = Auth::user();
        
        // Assign role if specified
        if (isset($this->roleToAssign)) {
            $user->syncRoles([$this->roleToAssign]);
        }
        
        // Log the user creation
        activity()
            ->causedBy($currentUser)
            ->performedOn($user)
            ->withProperties([
                'action' => 'create_user',
                'role' => $this->roleToAssign ?? 'none',
                'department_id' => $user->department_id,
                'is_active' => $user->is_active,
            ])
            ->log('user_created');
        
        // Show notification with temporary password
        if (!$user->temp_password_changed) {
            \Filament\Notifications\Notification::make()
                ->title('User Created Successfully')
                ->body("User '{$user->name}' has been created with temporary password. They will be required to change it on first login.")
                ->success()
                ->duration(8000)
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index');
    }

    public function getHeading(): string
    {
        return 'Create New User';
    }

    public function getSubheading(): string
    {
        $currentUser = Auth::user();
        
        if ($currentUser->hasRole('superadmin')) {
            return 'Create a new user account with any role';
        }
        
        return 'Create a new user account (excluding Superadmin role)';
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Create User')
                ->icon('heroicon-o-user-plus'),
                
            Actions\Action::make('create_and_another')
                ->label('Create & Create Another')
                ->icon('heroicon-o-plus')
                ->action(function () {
                    $this->create();
                    $this->form->fill();
                    $this->notify('success', 'User created successfully. You can create another user.');
                })
                ->color('success'),
                
            $this->getCancelFormAction()
                ->label('Cancel'),
        ];
    }

    protected ?string $roleToAssign = null;
}
