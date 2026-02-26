<?php

namespace App\Filament\Resources\UserSessionResource\Pages;

use App\Filament\Resources\UserSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUserSession extends ViewRecord
{
    protected static string $resource = UserSessionResource::class;

    protected ?string $heading = 'Session Details';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Terminate Session')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Terminate Session')
                ->modalDescription('Are you sure you want to terminate this session? The user will be logged out.')
                ->modalSubmitActionLabel('Terminate'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['device_info'] = $this->formatDeviceInfo($data['device_info'] ?? null);
        
        return $data;
    }

    private function formatDeviceInfo(?string $deviceInfo): string
    {
        if (!$deviceInfo) {
            return 'Unknown Device';
        }

        // Extract browser and OS from user agent
        $browser = 'Unknown';
        $os = 'Unknown';

        if (preg_match('/Chrome\/[\d.]+/', $deviceInfo)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\/[\d.]+/', $deviceInfo)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\/[\d.]+/', $deviceInfo)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge\/[\d.]+/', $deviceInfo)) {
            $browser = 'Edge';
        }

        if (preg_match('/Windows/i', $deviceInfo)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac/i', $deviceInfo)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $deviceInfo)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $deviceInfo)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $deviceInfo)) {
            $os = 'iOS';
        }

        return "{$browser} on {$os}";
    }
}

