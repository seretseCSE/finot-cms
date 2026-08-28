<?php

namespace App\Filament\Resources;

use App\Enums\WithdrawalRequestStatus;
use App\Filament\Resources\WithdrawalRequestResource\Pages;
use App\Models\WithdrawalRequest;
use App\Services\Movement\WithdrawalService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class WithdrawalRequestResource extends BaseResource
{
    protected static ?string $model = WithdrawalRequest::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 21;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrow-right-start-on-rectangle';
    }

    public static function getNavigationLabel(): string
    {
        return 'Withdrawals';
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return (bool) ($user?->hasRole('superadmin')
            || $user?->can('withdrawal.approve')
            || $user?->can('withdrawal.finalize'));
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Textarea::make('reason')->disabled(),
            Forms\Components\TextInput::make('destination')->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.first_name')->label('Student'),
                Tables\Columns\TextColumn::make('class.name'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('requested_at')->dateTime(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\Action::make('approve')
                    ->visible(fn (WithdrawalRequest $record) => $record->status === WithdrawalRequestStatus::Pending
                        && Auth::user()?->can('withdrawal.approve'))
                    ->requiresConfirmation()
                    ->action(function (WithdrawalRequest $record) {
                        app(WithdrawalService::class)->approve($record, Auth::user());
                        Notification::make()->title('Approved')->success()->send();
                    }),
                Actions\Action::make('finalize')
                    ->visible(fn (WithdrawalRequest $record) => $record->status === WithdrawalRequestStatus::EducationApproved
                        && Auth::user()?->can('withdrawal.finalize'))
                    ->requiresConfirmation()
                    ->action(function (WithdrawalRequest $record) {
                        app(WithdrawalService::class)->finalize($record, Auth::user());
                        Notification::make()->title('Finalized')->success()->send();
                    }),
                Actions\Action::make('print')
                    ->url(fn (WithdrawalRequest $record) => route('withdrawals.print', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawalRequests::route('/'),
            'view' => Pages\ViewWithdrawalRequest::route('/{record}'),
        ];
    }
}
