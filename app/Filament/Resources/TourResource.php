<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use App\Services\UploadSanitizer;
use Filament\Schemas\Schema;
use App\Filament\Resources\TourResource\Pages;
use App\Filament\Resources\TourResource\Pages\GenerateAttendanceAction;
use App\Models\Tour;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use App\Enums\Roles;

class TourResource extends BaseResource
{
    protected static ?string $model = Tour::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-map';
    }

    public static function getNavigationLabel(): string
    {
        return 'Tours';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Tour Management';
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
                            ->label('Tour Start Date')
                            ->required()
                            ->live()
                            ->extraAttributes(['min' => now()->format('Y-m-d')])
                            ->rules(['date', 'after_or_equal:today'])
                            ->disabled(fn ($record) => $record && ! $record->canEditDate()),

                        EthiopianDatePicker::make('end_date')
                            ->label('Tour End Date')
                            ->nullable()
                            ->live()
                            ->extraAttributes(fn (callable $get) => [
                                'min' => $get('tour_date') ?: now()->format('Y-m-d'),
                            ])
                            ->rules(['date', 'nullable', 'after_or_equal:tour_date'])
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
                            ->rules(['date'])
                            ->afterOrEqual('today')
                            ->before('tour_date'),

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

                Tables\Columns\TextColumn::make('end_date')
                    ->label('End Date')
                    ->date()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->end_date ? $record->ethiopian_end_date : '—'),

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
                            ->label('End Date')
                            ->afterOrEqual('start_date'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        if ($data['start_date'] && $data['end_date']) {
                            return $query->where(function ($q) use ($data) {
                                $q->whereBetween('tour_date', [$data['start_date'], $data['end_date']])
                                  ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']]);
                            });
                        }
                        return $query;
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
                        $record->autoCreateAttendanceSession();
                    }),

                Actions\Action::make('mark_completed')
                    ->label('Mark Completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Tour $record): bool => $record && in_array($record->status, ['In Progress', 'Published']) && static::canEdit($record))
                    ->action(function (Tour $record) {
                        $record->autoCreateAttendanceSession();
                        $record->update(['status' => 'Completed']);
                    }),

                Actions\Action::make('register_passenger')
                    ->label('Register Passenger')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->visible(fn (Tour $record): bool => $record && static::canEdit($record))
                    ->modalHeading(fn (Tour $record): string => "Register Passenger — {$record->place}")
                    ->form([
                        Forms\Components\Select::make('member_id')
                            ->label('Select Member')
                            ->relationship('member', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                            ->searchable(['first_name', 'father_name', 'grandfather_name', 'phone'])
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state) {
                                    $member = \App\Models\Member::find($state);
                                    if ($member) {
                                        $set('full_name', $member->full_name);
                                        $set('phone', preg_replace('/^' . preg_quote(config('finot.phone_prefix', '+251'), '/') . '/', '', $member->phone ?? ''));
                                    }
                                }
                            })
                            ->helperText('Select an existing member to auto-fill details')
                            ->nullable(),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->prefix(config('finot.phone_prefix', '+251'))
                            ->regex('/^[0-9]{9}$/')
                            ->maxLength(9)
                            ->placeholder('912345678')
                            ->helperText('Enter 9 digits after '.config('finot.phone_prefix', '+251'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state && strlen($state) === 9) {
                                    $fullPhone = config('finot.phone_prefix', '+251').$state;
                                    $member = \App\Models\Member::where('phone', $fullPhone)->first();
                                    if ($member) {
                                        $set('member_id', $member->id);
                                        $set('full_name', $member->full_name);
                                    } else {
                                        $previous = \App\Models\TourPassenger::where('phone', $fullPhone)
                                            ->orderBy('created_at', 'desc')
                                            ->first();
                                        if ($previous) {
                                            $set('full_name', $previous->full_name);
                                        }
                                    }
                                }
                            })
                            ->required(),

                        Forms\Components\TextInput::make('full_name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('passenger_count')
                            ->label('Number of Passengers')
                            ->required()
                            ->integer()
                            ->default(1)
                            ->minValue(1),
                    ])
                    ->action(function (Tour $record, array $data) {
                        $phonePrefix = config('finot.phone_prefix', '+251');
                        $phone = $phonePrefix . preg_replace('/^' . preg_quote($phonePrefix, '/') . '/', '', $data['phone']);

                        $exists = \App\Models\TourPassenger::where('tour_id', $record->id)
                            ->where('phone', $phone)
                            ->exists();

                        if ($exists) {
                            \Filament\Notifications\Notification::make()
                                ->title('Duplicate Phone Number')
                                ->body('This phone number is already registered for this tour.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $tourPrefix = config('finot.tour_passenger_code_prefix', 'TP-');
                        $lastPassenger = \App\Models\TourPassenger::orderBy('id', 'desc')->first();
                        $lastCode = $lastPassenger ? intval(substr($lastPassenger->passenger_code, strlen($tourPrefix))) : 0;

                        \App\Models\TourPassenger::create([
                            'tour_id' => $record->id,
                            'passenger_code' => $tourPrefix . str_pad($lastCode + 1, 6, '0', STR_PAD_LEFT),
                            'full_name' => $data['full_name'],
                            'phone' => $phone,
                            'passenger_count' => $data['passenger_count'],
                            'member_id' => $data['member_id'] ?? null,
                            'registration_type' => 'Internal',
                            'status' => 'Confirmed',
                            'registration_date' => now(),
                            'registered_by' => Auth::id(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Passenger registered successfully')
                            ->success()
                            ->send();
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

                Actions\Action::make('view_attendance')
                    ->label('Mark Attendance')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('gray')
                    ->visible(fn (Tour $record): bool => $record && ! in_array($record->status, ['Draft', 'Cancelled']))
                    ->url(fn (Tour $record): string => \App\Filament\Pages\Attendance\TourAttendancePage::getUrl()),


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
                    ->visible(fn () => Auth::user()?->can('tours.view') ?? false)
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'edit' => Pages\EditTour::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchResultTitle($record): string
    {
        return $record->place;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        $dateRange = $record->tour_date->format('M d, Y');
        if ($record->end_date) {
            $dateRange .= ' - ' . $record->end_date->format('M d, Y');
        }

        return [
            'Date' => $dateRange,
            'Cost' => 'Birr ' . number_format($record->cost_per_person, 2),
            'Capacity' => $record->max_capacity . ' attendees',
            'Status' => $record->status,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['place', 'description', 'tour_date', 'end_date', 'status'];
    }
}
