<?php

namespace App\Filament\Resources;

use App\Filament\Pages\Attendance\TourAttendancePage;
use App\Filament\Resources\TourAttendanceResource\Pages;
use App\Models\Tour;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TourAttendanceResource extends BaseResource
{
    protected static ?string $model = Tour::class;

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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['In Progress', 'Completed']))
            ->columns([
                Tables\Columns\TextColumn::make('place')
                    ->label('Tour')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tour_date')
                    ->label('Tour Date')
                    ->date()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->ethiopian_date),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->end_date ? $record->ethiopian_end_date : '—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('confirmed_passengers_count')
                    ->label('Confirmed')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->confirmedPassengers->sum('passenger_count')),

                Tables\Columns\TextColumn::make('attendance_sessions_summary')
                    ->label('Attendance')
                    ->formatStateUsing(function ($record) {
                        $session = $record->attendanceSessions()->first();
                        if (! $session) {
                            return 'No session';
                        }
                        $summary = $session->attendance_summary;
                        return "{$summary['present']}/{$summary['total']} Present";
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed',
                    ]),
            ])
            ->actions([
                Actions\Action::make('take_attendance')
                    ->label('Take Attendance')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->url(fn (Tour $record): string => TourAttendancePage::getUrl() . '?tourId=' . $record->id),
            ])
            ->bulkActions([])
            ->defaultSort('tour_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTourAttendances::route('/'),
        ];
    }
}
