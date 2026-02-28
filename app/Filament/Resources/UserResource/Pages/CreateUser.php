<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * The plain-text temporary password (stored before hashing so we can display it once).
     */
    protected ?string $generatedPassword = null;

    /**
     * Role to assign after the user record is created.
     */
    protected ?string $roleToAssign = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default values for new accounts
        $data['temp_password_changed'] = false;
        $data['failed_login_attempts'] = 0;

        // Generate a secure temporary password if not provided
        if (empty($data['password'])) {
            // Generate a 12-character password with mixed case, numbers, and symbols
            $this->generatedPassword = Str::password(12);
        } else {
            // Admin manually typed a password — store plain text for display
            $this->generatedPassword = $data['password'];
        }

        // Hash the password for storage
        $data['password'] = Hash::make($this->generatedPassword);

        // Extract role to assign after creation (roles are not a column on users table)
        if (isset($data['roles'])) {
            $this->roleToAssign = $data['roles'];
            unset($data['roles']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->record;
        $currentUser = Auth::user();

        // Assign role if specified
        if ($this->roleToAssign) {
            $user->syncRoles([$this->roleToAssign]);
        }

        // Log the user creation in audit trail
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

        // Display the temporary password ONCE with a copyable notification.
        // This password will NEVER be shown again — the admin must copy it now.
        Notification::make()
            ->title('✅ User Created — Copy Temporary Password NOW')
            ->body(
                "**User:** {$user->name}\n" .
                "**Phone:** {$user->phone}\n" .
                "**Temporary Password:**\n" .
                "```\n{$this->generatedPassword}\n```\n" .
                "⚠️ **This password will NOT be shown again.** " .
                "The user must change it on first login."
            )
            ->success()
            ->persistent() // Keep notification until manually dismissed
            ->send();
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
                })
                ->color('success'),

            $this->getCancelFormAction()
                ->label('Cancel'),
        ];
    }
}
