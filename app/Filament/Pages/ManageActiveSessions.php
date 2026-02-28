<?php

namespace App\Filament\Pages;

use App\Helpers\EthiopianDateHelper;
use App\Models\UserSession;
use Filament\Actions;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class ManageActiveSessions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Manage Sessions';
    
    public static function getNavigationIcon(): ?string { return 'heroicon-o-computer-desktop'; }

    public static function getNavigationGroup(): ?string { return 'System'; }

    public static function getNavigationLabel(): string { return 'Manage Sessions'; }

    public function getTitle(): string
    {
        return 'Manage Sessions';
    }

    public function getHeading(): string
    {
        return 'Active Sessions';
    }

    public function getSubheading(): string
    {
        $activeCount = Auth::user()->activeSessionsCount();
        $maxSessions = 3;
        
        return "You have {$activeCount} of {$maxSessions} allowed active sessions";
    }

    public function getView(): string
    {
        return 'filament.pages.manage-active-sessions';
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected function getTableActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshTable')
                ->color('secondary'),
        ];
    }

    public function refreshTable(): void
    {
        // Filament tables with polling or Livewire actions refresh automatically.
    }

    public function table(Table $table): Table
    {
        $currentSessionToken = session('session_token');
        
        return $table
            ->query(
                fn () => Auth::user()->activeSessions()->orderBy('last_activity', 'desc')
            )
            ->columns([
                Tables\Columns\IconColumn::make('is_current')
                    ->label('Current')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('')
                    ->getStateUsing(fn ($record) => $record->session_token === $currentSessionToken)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('device_info')
                    ->label('Device')
                    ->formatStateUsing(fn ($state) => $this->formatDeviceInfo($state))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('IP address copied')
                    ->copyMessageDuration(1500),

                Tables\Columns\TextColumn::make('last_activity')
                    ->label('Last Active')
                    ->dateTime()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $this->formatEthiopianDate($state)),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->getStateUsing(fn ($record) => $record->session_token === $currentSessionToken ? 'Current Session' : 'Other Device')
                    ->badge(fn ($state) => $state === 'Current Session' ? 'success' : 'secondary')
                    ->alignCenter(),
            ])
            ->actions([
                Actions\Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke Session')
                    ->modalDescription('This will terminate the selected session and user will be logged out. Continue?')
                    ->modalSubmitActionLabel('Revoke Session')
                    ->action(fn ($record) => $this->revokeSession($record))
                    ->hidden(fn ($record) => $record->session_token === $currentSessionToken),
            ])
            ->emptyStateHeading('No Active Sessions')
            ->emptyStateDescription('You currently have no active sessions on other devices.')
            ->emptyStateActions([
                Actions\Action::make('refresh')
                    ->label('Refresh')
                    ->icon('heroicon-o-arrow-path')
                    ->action('refreshTable'),
            ])
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    protected function revokeSession(UserSession $session): void
    {
        // Delete the session from database
        $session->delete();
        
        // Show success message
        Notification::make()
            ->title('Session Revoked')
            ->body('The session has been terminated successfully.')
            ->success()
            ->send();
        
        // Refresh the table
        // Filament auto-refreshes the table after an action.
    }

    protected function formatDeviceInfo(?string $deviceInfo): string
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

    protected function formatEthiopianDate($dateTime): string
    {
        if (!$dateTime) {
            return '';
        }

        try {
            $helper = new EthiopianDateHelper();
            return $helper->toString($dateTime);
        } catch (\Exception $e) {
            return $dateTime->format('M d, Y H:i');
        }
    }
}

