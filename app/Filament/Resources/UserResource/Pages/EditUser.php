<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => auth()->user()?->can('users.delete')),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->record;

        // Load current role IDs into the form
        $data['roles'] = $user->roles->pluck('id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle roles assignment via Spatie
        $this->roles = $data['roles'] ?? [];
        unset($data['roles']);

        // Map force password change to the database column
        if (! empty($data['force_password_change'])) {
            $data['temp_password_changed'] = false;
        }
        unset($data['force_password_change']);

        // If password is empty, remove it so it doesn't overwrite the existing password
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->record;

        // Sync roles
        if (isset($this->roles)) {
            $user->syncRoles($this->roles);
        }

        // Log the update
        \Log::channel('audit')->info('User Updated', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'updated_by' => auth()->id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
