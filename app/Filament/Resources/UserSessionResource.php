<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserSessionResource\Pages;
use App\Models\UserSession;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserSessionResource extends Resource
{
    protected static ?string $model = UserSession::class;

    public static function getNavigationIcon(): ?string { return null; }

    public static function getNavigationGroup(): ?string { return 'System'; }

    public static function getNavigationSort(): ?int { return 2; }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(),

                Forms\Components\TextInput::make('session_token')
                    ->label('Session Token')
                    ->disabled()
                    ->formatStateUsing(fn ($state) => substr($state, 0, 20) . '...'),

                Forms\Components\Textarea::make('device_info')
                    ->label('Device Info')
                    ->disabled()
                    ->rows(2),

                Forms\Components\TextInput::make('ip_address')
                    ->label('IP Address')
                    ->disabled(),

                Forms\Components\DateTimePicker::make('last_activity')
                    ->label('Last Activity')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('device_info')
                    ->label('Device')
                    ->formatStateUsing(fn ($state) => self::formatDeviceInfo($state))
                    ->limit(30),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable(),

                Tables\Columns\TextColumn::make('last_activity')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable()
                    ->description(fn ($record) => $record->isActive() ? 'Active' : 'Expired'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn ($record) => $record->isActive()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('active_only')
                    ->label('Active Sessions Only')
                    ->query(fn ($query) => $query->active())
                    ->default(true),
            ])
            ->actions([
                Actions\DeleteAction::make()
                    ->label('Terminate Session')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Terminate Session')
                    ->modalDescription('Are you sure you want to terminate this session? The user will be logged out.')
                    ->modalSubmitActionLabel('Terminate'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->label('Terminate Sessions')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Terminate Sessions')
                        ->modalDescription('Are you sure you want to terminate these sessions? The users will be logged out.')
                        ->modalSubmitActionLabel('Terminate'),
                ]),
            ])
            ->defaultSort('last_activity', 'desc');
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
            'index' => Pages\ListUserSessions::route('/'),
            'view' => Pages\ViewUserSession::route('/{record}'),
        ];
    }

    protected static function formatDeviceInfo(?string $deviceInfo): string
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

