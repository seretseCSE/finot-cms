<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $currentUser = Auth::user();
        
        return [
            Actions\Action::make('create_user')
                ->label('Create User')
                ->url(UserResource::getUrl('create'))
                ->icon('heroicon-o-user-plus')
                ->visible(fn () => $currentUser->hasRole(['superadmin', 'admin'])),

            // Force Logout All Users — emergency action for Superadmin/Admin
            Actions\Action::make('force_logout_all')
                ->label('Force Logout All Users')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('danger')
                ->visible(fn () => $currentUser->hasRole(['superadmin', 'admin']))
                ->requiresConfirmation()
                ->modalHeading('⚠️ Force Logout All Users')
                ->modalDescription(
                    'This will immediately terminate ALL active user sessions across the entire system. ' .
                    'Every user (except you) will be logged out and must log in again. ' .
                    'Use this only in emergencies (e.g., security breach, critical role changes).'
                )
                ->modalSubmitActionLabel('Yes, Logout Everyone')
                ->action(function () use ($currentUser) {
                    // Delete all sessions except the current user's
                    $currentSessionId = session()->getId();
                    DB::table('sessions')
                        ->where('id', '!=', $currentSessionId)
                        ->delete();

                    // Audit trail
                    activity()
                        ->causedBy($currentUser)
                        ->withProperties([
                            'action' => 'force_logout_all_users',
                            'reason' => 'Admin-initiated global force logout',
                            'sessions_cleared' => true,
                        ])
                        ->log('force_logout_all_users');

                    Notification::make()
                        ->title('All Users Logged Out')
                        ->body('All active sessions have been terminated. Users must log in again.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('bulk_import')
                ->label('Bulk Import')
                ->icon('heroicon-o-document-arrow-up')
                ->visible(fn () => $currentUser->hasRole(['superadmin', 'admin']))
                ->modalHeading('Bulk User Import')
                ->modalDescription('Import multiple users from a CSV file.')
                ->form([
                    \Filament\Forms\Components\FileUpload::make('csv_file')
                        ->label('CSV File')
                        ->required()
                        ->helperText('Upload a CSV file with columns: name, phone, email, role, department_id')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel']),
                ])
                ->action(function (array $data) {
                    // Implementation for bulk import
                    \Filament\Notifications\Notification::make()
                        ->title('Bulk Import')
                        ->body('Bulk import functionality would be implemented here.')
                        ->info()
                        ->send();
                }),

            Actions\Action::make('export_users')
                ->label('Export Users')
                ->icon('heroicon-o-document-arrow-down')
                ->visible(fn () => $currentUser->hasRole(['superadmin', 'admin']))
                ->modalHeading('Export Users')
                ->modalDescription('Export users to CSV file.')
                ->form([
                    \Filament\Forms\Components\CheckboxList::make('columns')
                        ->label('Select Columns')
                        ->options([
                            'name' => 'Name',
                            'phone' => 'Phone',
                            'email' => 'Email',
                            'role' => 'Role',
                            'department' => 'Department',
                            'is_active' => 'Active',
                            'created_at' => 'Created At',
                            'last_login_at' => 'Last Login',
                        ])
                        ->selectAll()
                        ->default(['name', 'phone', 'email', 'role', 'department', 'is_active']),
                ])
                ->action(function (array $data) {
                    // Implementation for export
                    \Filament\Notifications\Notification::make()
                        ->title('Export Started')
                        ->body('User export will be downloaded shortly.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        $currentUser = Auth::user();
        
        $tabs = [
            'all' => \Filament\Resources\Components\Tab::make('All Users')
                ->icon('heroicon-o-users')
                ->badge(fn () => static::getModel()::count()),
        ];

        if ($currentUser->hasRole(['superadmin', 'admin'])) {
            $tabs = array_merge($tabs, [
                'active' => \Filament\Resources\Components\Tab::make('Active')
                    ->icon('heroicon-o-check-circle')
                    ->modifyQueryUsing(fn ($query) => $query->where('is_active', true))
                    ->badge(fn () => static::getModel()::where('is_active', true)->count()),

                'inactive' => \Filament\Resources\Components\Tab::make('Inactive')
                    ->icon('heroicon-o-x-circle')
                    ->modifyQueryUsing(fn ($query) => $query->where('is_active', false))
                    ->badge(fn () => static::getModel()::where('is_active', false)->count()),

                'locked' => \Filament\Resources\Components\Tab::make('Locked')
                    ->icon('heroicon-o-lock-closed')
                    ->modifyQueryUsing(fn ($query) => $query->whereNotNull('locked_until')->where('locked_until', '>', now()))
                    ->badge(fn () => static::getModel()::whereNotNull('locked_until')->where('locked_until', '>', now())->count()),

                'temp_password' => \Filament\Resources\Components\Tab::make('Temp Password')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->modifyQueryUsing(fn ($query) => $query->where('temp_password_changed', false))
                    ->badge(fn () => static::getModel()::where('temp_password_changed', false)->count()),
            ]);
        }

        return $tabs;
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50, 100];
    }

    public function getHeading(): string
    {
        return 'User Management';
    }

    public function getSubheading(): string
    {
        $currentUser = Auth::user();
        
        if ($currentUser->hasRole('superadmin')) {
            return 'Manage all system users including Superadmin accounts';
        }
        
        return 'Manage system users (excluding Superadmin accounts)';
    }
}
