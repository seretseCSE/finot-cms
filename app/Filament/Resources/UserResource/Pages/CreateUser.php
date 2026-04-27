<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure temp_password_changed is false so user is forced to change password
        $data['temp_password_changed'] = false;

        // Handle roles assignment via Spatie
        $this->roles = $data['roles'] ?? [];
        unset($data['roles']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        // Sync roles
        if (! empty($this->roles)) {
            $user->syncRoles($this->roles);
        }

        // Log the creation
        \Log::channel('audit')->info('User Created', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'created_by' => auth()->id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
