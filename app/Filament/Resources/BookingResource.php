<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Services\Facilities\BookingService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BookingResource extends BaseResource
{
    protected static ?string $model = Booking::class;

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 31;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-calendar';
    }

    public static function getNavigationLabel(): string
    {
        return 'Bookings';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('facility_id')->relationship('facility', 'name')->required(),
            Forms\Components\TextInput::make('purpose')->required()->maxLength(255),
            Forms\Components\DateTimePicker::make('start_at')->required(),
            Forms\Components\DateTimePicker::make('end_at')->required(),
            Forms\Components\Select::make('recurrence_rule')
                ->options(['weekly' => 'Weekly'])
                ->nullable(),
            Forms\Components\TextInput::make('weeks')->numeric()->default(4)->dehydrated(false),
            Forms\Components\Select::make('class_id')->relationship('class', 'name')->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('facility.name'),
                Tables\Columns\TextColumn::make('purpose'),
                Tables\Columns\TextColumn::make('start_at')->dateTime(),
                Tables\Columns\TextColumn::make('end_at')->dateTime(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('bookedBy.name'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('confirm')
                    ->visible(fn (Booking $record) => $record->status === BookingStatus::Pending
                        && (Auth::user()?->can('facilities.manage') || Auth::user()?->hasRole(['admin', 'superadmin'])))
                    ->requiresConfirmation()
                    ->action(function (Booking $record) {
                        try {
                            app(BookingService::class)->confirm($record, Auth::user());
                            Notification::make()->title('Booking confirmed')->success()->send();
                        } catch (HttpException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Actions\Action::make('cancel')
                    ->visible(fn (Booking $record) => $record->status !== BookingStatus::Cancelled)
                    ->requiresConfirmation()
                    ->action(fn (Booking $record) => app(BookingService::class)->cancel($record, Auth::user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
