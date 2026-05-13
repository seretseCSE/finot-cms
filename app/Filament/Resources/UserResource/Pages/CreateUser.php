<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\UserCreationService;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected array $roles = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $service = app(UserCreationService::class);
        $data = $service->processBeforeCreate($data);

        // Handle roles assignment via Spatie
        $this->roles = $data['roles'] ?? [];
        unset($data['roles']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;
        $service = app(UserCreationService::class);

        // Sync roles
        $service->syncRoles($user, $this->roles);

        // Log the creation
        $service->logUserCreation($user);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
