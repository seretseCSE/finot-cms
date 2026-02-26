<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use App\Filament\Resources\TourResource\Pages;
use App\Filament\Resources\TourResource\Pages\GenerateAttendanceAction;
use App\Models\Tour;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TourResource extends Resource
{
    protected static ?string $model = Tour::class;

    public static function getNavigationIcon(): ?string { return 'heroicon-o-map'; }

    public static function getNavigationLabel(): string { return 'Tours'; }

    public static function getNavigationGroup(): ?string { return 'Tours'; }

    public static function getNavigationSort(): ?int { return 1; }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->hasRole(['tour_head', 'admin', 'superadmin']) && $record->canBeDeleted();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Tour Information')
                    ->schema([
                        Forms\Components\TextInput::make('place')
                            ->label('Tour Place')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3),

                        EthiopianDatePicker::make('tour_date')
                            ->label('Tour Date')
                            ->required()
                            ->disabled(fn ($record) => !$record->canEditDate()),

                        Forms\Components\TimePicker::make('start_time')
                            ->label('Start Time')
                            ->required()
                            ->withoutSeconds(),

                        Forms\Components\TextInput::make('cost_per_person')
                            ->label('Cost Per Person (Birr)')
                            ->numeric()
                            ->step(0.01)
                            ->nullable(),

                        EthiopianDatePicker::make('registration_deadline')
                            ->label('Registration Deadline')
                            ->nullable(),

                        Forms\Components\TextInput::make('max_capacity')
                            ->label('Maximum Capacity')
                            ->numeric()
                            ->integer()
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Draft' => 'Draft',
                                'Published' => 'Published',
                                'In Progress' => 'In Progress',
                                'Completed' => 'Completed',
                                'Cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->disabled(fn ($record) => $record && in_array($record->status, ['In Progress', 'Completed'])),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('place')
                    ->label('Place')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tour_date')
                    ->label('Tour Date')
                    ->date()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->ethiopian_date),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start Time')
                    ->time()
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_cost')
                    ->label('Cost')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->formatted_cost),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('confirmed_passengers_count')
                    ->label('Confirmed')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->confirmedPassengers->sum('passenger_count')),

                Tables\Columns\TextColumn::make('max_capacity')
                    ->label('Capacity')
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->max_capacity ?: 'Unlimited'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Draft' => 'Draft',
                        'Published' => 'Published',
                        'In Progress' => 'In Progress',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date'),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $data['start_date'] && $data['end_date']
                            ? $query->whereBetween('tour_date', [$data['start_date'], $data['end_date']])
                            : $query;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Tour $record) => static::canEdit($record)),
                
                Tables\Actions\Action::make('publish')
                    ->label('Publish Tour')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (Tour $record) => $record->status === 'Draft' && static::canEdit($record))
                    ->action(function (Tour $record) {
                        $record->update(['status' => 'Published']);
                    }),
                
                Tables\Actions\Action::make('mark_in_progress')
                    ->label('Mark In Progress')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (Tour $record) => $record->status === 'Published' && static::canEdit($record))
                    ->action(function (Tour $record) {
                        $record->update(['status' => 'In Progress']);
                    }),
                
                Tables\Actions\Action::make('mark_completed')
                    ->label('Mark Completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Tour $record) => in_array($record->status, ['In Progress', 'Published']) && static::canEdit($record))
                    ->action(function (Tour $record) {
                        $record->update(['status' => 'Completed']);
                    }),
                
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel Tour')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Tour $record) => !in_array($record->status, ['Cancelled', 'Completed']) && static::canEdit($record))
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Tour $record, array $data) {
                        $record->cancel($data['cancellation_reason'], Auth::id());
                    }),
                
                GenerateAttendanceAction::make()
                    ->visible(fn (Tour $record) => $record->status === 'In Progress' && static::canEdit($record)),
                
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Tour $record) => static::canDelete($record))
                    ->before(function (Tour $record) {
                        if (!$record->canBeDeleted()) {
                            throw new \Exception('Cannot delete tour with passengers. Use Cancel action instead.');
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => static::canDelete(null)),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No tours found')
            ->emptyStateDescription('Create your first tour to get started.')
            ->emptyStateIcon('heroicon-o-map');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PassengersRelationManager::class,
            RelationManagers\InternalRegistrationRelationManager::class,
            RelationManagers\AttendanceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::class,
            'create' => Pages\CreateTour::class,
            'edit' => Pages\EditTour::class,
        ];
    }
}

