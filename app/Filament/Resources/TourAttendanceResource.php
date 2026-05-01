<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TourAttendanceResource\Pages;
use Filament\Schemas\Schema;
use App\Models\TourAttendance;
use App\Models\TourAttendanceSession;
use App\Models\TourPassenger;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TourAttendanceResource extends Resource
{
    protected static ?string $model = TourAttendance::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function getNavigationLabel(): string
    {
        return 'Tour Attendance';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tour Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'tour_manager', 'revenue_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'tour_manager', 'revenue_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'tour_manager', 'revenue_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Attendance Details')
                    ->schema([
                        Select::make('session_id')
                            ->label('Session')
                            ->options(TourAttendanceSession::query()->with('tour')->get()->mapWithKeys(fn ($s) => [$s->id => $s->tour->place.' - '.$s->session_date]))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('passenger_id')
                            ->label('Passenger')
                            ->options(TourPassenger::query()->where('status', 'Confirmed')->pluck('full_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'Present' => 'Present',
                                'Not Present' => 'Not Present',
                            ])
                            ->required()
                            ->default('Present'),

                        DateTimePicker::make('marked_at')
                            ->label('Marked At')
                            ->required()
                            ->default(now())
                            ->native(false),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('session.tour.place')
                    ->label('Tour')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('session.session_date')
                    ->label('Session Date')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('passenger.full_name')
                    ->label('Passenger')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match($state) {
                        'Present' => 'success',
                        'Not Present' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('markedBy.name')
                    ->label('Marked By')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('marked_at')
                    ->label('Marked At')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('session')
                    ->relationship('session', 'session_date')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('passenger')
                    ->relationship('passenger', 'full_name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Present' => 'Present',
                        'Not Present' => 'Not Present',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until')
                            ->afterOrEqual('from'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereHas('session', fn ($sq) => $sq->whereDate('session_date', '>=', $data['from'])))
                            ->when($data['until'], fn ($q) => $q->whereHas('session', fn ($sq) => $sq->whereDate('session_date', '<=', $data['until'])));
                    }),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('marked_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTourAttendances::route('/'),
            'create' => Pages\CreateTourAttendance::route('/create'),
            'edit' => Pages\EditTourAttendance::route('/{record}/edit'),
        ];
    }
}
