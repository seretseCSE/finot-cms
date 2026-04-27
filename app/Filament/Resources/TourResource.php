<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use App\Services\UploadSanitizer;
use Filament\Schemas\Schema;
use App\Filament\Resources\TourResource\Pages;
use App\Filament\Resources\TourResource\Pages\GenerateAttendanceAction;
use App\Filament\Resources\TourResource\RelationManagers\AttendanceRelationManager;
use App\Filament\Resources\TourResource\RelationManagers\InternalRegistrationRelationManager;
use App\Filament\Resources\TourResource\RelationManagers\PassengersRelationManager;
use App\Filament\Resources\TourResource\RelationManagers\TourAttendanceRelationManager;
use App\Models\Tour;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class TourResource extends BaseResource
{
    protected static ?string $model = Tour::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-map';
    }

    public static function getNavigationLabel(): string
    {
        return __('resources.tour.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources.tour.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Tour Information')
                    ->schema([
                        Forms\Components\TextInput::make('place')
                            ->label('Tour Place')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3),

                        Forms\Components\FileUpload::make('image')
                            ->label('Tour Image')
                            ->image()
                            ->disk('public')
                            ->directory('tour-images')
                            ->maxSize(5120)
                            ->nullable()
                            ->saveUploadedFileUsing(UploadSanitizer::saveCallback('tour-images', 'public', ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])),

                        EthiopianDatePicker::make('tour_date')
                            ->label('Tour Date')
                            ->required()
                            ->live()
                            ->extraAttributes(['min' => now()->format('Y-m-d')])
                            ->rules(['date', 'after_or_equal:today'])
                            ->disabled(fn ($record) => $record && ! $record->canEditDate()),

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
                            ->nullable()
                            ->extraAttributes(fn (callable $get) => [
                                'min' => now()->format('Y-m-d'),
                                ...($get('tour_date') ? ['max' => $get('tour_date')] : []),
                            ])
                            ->rules(['date', 'after_or_equal:today', 'before:tour_date']),

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
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $data['start_date'] && $data['end_date']
                            ? $query->whereBetween('tour_date', [$data['start_date'], $data['end_date']])
                            : $query;
                    }),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (Tour $record): bool => $record && static::canEdit($record)),

                Actions\Action::make('publish')
                    ->label('Publish Tour')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (Tour $record): bool => $record && $record->status === 'Draft' && static::canEdit($record))
                    ->action(function (Tour $record) {
                        $record->update(['status' => 'Published']);
                    }),

                Actions\Action::make('mark_in_progress')
                    ->label('Mark In Progress')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (Tour $record): bool => $record && $record->status === 'Published' && static::canEdit($record))
                    ->action(function (Tour $record) {
                        $record->update(['status' => 'In Progress']);
                    }),

                Actions\Action::make('mark_completed')
                    ->label('Mark Completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Tour $record): bool => $record && in_array($record->status, ['In Progress', 'Published']) && static::canEdit($record))
                    ->action(function (Tour $record) {
                        $record->update(['status' => 'Completed']);
                    }),

                Actions\Action::make('cancel')
                    ->label('Cancel Tour')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Tour $record): bool => $record && ! in_array($record->status, ['Cancelled', 'Completed']) && static::canEdit($record))
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Tour $record, array $data) {
                        $record->cancel($data['cancellation_reason'], Auth::id());
                    }),

                GenerateAttendanceAction::make('generate_attendance')
                    ->visible(fn (Tour $record): bool => $record && $record->status === 'In Progress' && static::canEdit($record)),

                Actions\DeleteAction::make()
                    ->before(function (?Tour $record, Actions\DeleteAction $action) {
                        if (! $record || ! $record->canBeDeleted()) {
                            $action->halt();

                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Delete')
                                ->body('Cannot delete tour with passengers. Use Cancel action instead.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ])
            ->headerActions([
                Actions\Action::make('update_all_statuses')
                    ->label('Update All Tour Statuses')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn () => Auth::user()?->hasRole(['tour_head', 'tour_manager', 'admin', 'superadmin']) ?? false)
                    ->action(function () {
                        $tours = Tour::whereNotIn('status', ['Cancelled', 'Completed'])->get();
                        $updatedCount = 0;

                        foreach ($tours as $tour) {
                            $oldStatus = $tour->status;
                            $tour->updateStatusIfNeeded();

                            if ($tour->status !== $oldStatus) {
                                $updatedCount++;
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Tour Statuses Updated')
                            ->body("Successfully updated {$updatedCount} tour(s).")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No tours found')
            ->emptyStateDescription('Create your first tour to get started.')
            ->emptyStateIcon('heroicon-o-map');
    }

    public static function getRelations(): array
    {
        return [
            PassengersRelationManager::class,
            InternalRegistrationRelationManager::class,
            AttendanceRelationManager::class,
            TourAttendanceRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'edit' => Pages\EditTour::route('/{record}/edit'),
        ];
    }
}
