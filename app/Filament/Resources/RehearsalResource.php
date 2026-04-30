<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RehearsalResource\Pages;
use Filament\Schemas\Schema;
use App\Models\Rehearsal;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RehearsalResource extends Resource
{
    protected static ?string $model = Rehearsal::class;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-calendar-days';
    }

    public static function getNavigationLabel(): string
    {
        return 'Rehearsals';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Content Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole(['mezmur_head', 'worship_monitor', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->hasRole(['mezmur_head', 'worship_monitor', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->hasRole(['mezmur_head', 'worship_monitor', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        if ($record === null) {
            return Auth::user()?->hasRole(['mezmur_head', 'worship_monitor', 'admin', 'superadmin']);
        }

        return Auth::user()?->hasRole(['mezmur_head', 'worship_monitor', 'admin', 'superadmin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make('Rehearsal Details')
                    ->schema([
                        Forms\Components\DateTimePicker::make('date_time')
                            ->label('Date & Time')
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('location')
                            ->label('Location')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Church Hall, Room 3'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Scheduled' => 'Scheduled',
                                'Completed' => 'Completed',
                                'Cancelled' => 'Cancelled',
                            ])
                            ->required()
                            ->default('Scheduled'),
                    ])
                    ->columns(2),

                Section::make('Recurrence')
                    ->schema([
                        Forms\Components\Select::make('recurrence_type')
                            ->label('Recurrence')
                            ->options([
                                'None' => 'One-time',
                                'Weekly' => 'Weekly',
                                'Biweekly' => 'Biweekly',
                                'Monthly' => 'Monthly',
                            ])
                            ->required()
                            ->default('None')
                            ->reactive(),

                        Forms\Components\DatePicker::make('recurrence_end_date')
                            ->label('Recurrence End Date')
                            ->native(false)
                            ->visible(fn (callable $get) => $get('recurrence_type') !== 'None')
                            ->required(fn (callable $get) => $get('recurrence_type') !== 'None'),
                    ])
                    ->columns(2),

                Section::make('Songs & Notes')
                    ->schema([
                        Forms\Components\Select::make('songs')
                            ->label('Songs to Practice')
                            ->multiple()
                            ->options(function () {
                                return \App\Models\Song::where('is_active', true)->pluck('title', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('Select songs that will be practiced during this rehearsal'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(4)
                            ->placeholder('Any special instructions or notes for the rehearsal...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date_time')
                    ->label('Date & Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ethiopian_date')
                    ->label('Ethiopian Date')
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_time')
                    ->label('Time')
                    ->sortable(),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                Tables\Columns\TextColumn::make('attendance_summary.total')
                    ->label('Attendance')
                    ->formatStateUsing(function ($record) {
                        $summary = $record->attendance_summary;

                        return "{$summary['present']}/{$summary['total']}";
                    }),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable()
                    ->searchable(),

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
                        'Scheduled' => 'Scheduled',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->native(false),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->native(false)
                            ->afterOrEqual('start_date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $data['start_date'] && $data['end_date']
                            ? $query->whereBetween('date_time', [$data['start_date'], $data['end_date']])
                            : $query;
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn ($record) => static::canEdit($record)),
                Actions\DeleteAction::make()
                    ->visible(fn ($record) => static::canDelete($record)),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('mark_completed')
                        ->label('Mark Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['status' => 'Completed']);
                            }
                        }),

                    Actions\BulkAction::make('mark_cancelled')
                        ->label('Mark Cancelled')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['status' => 'Cancelled']);
                            }
                        }),

                    Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->hasRole(['mezmur_head', 'worship_monitor', 'admin', 'superadmin'])),
                ]),
            ])
            ->emptyStateActions([
                Actions\CreateAction::make()
                    ->visible(fn () => static::canCreate()),
            ])
            ->emptyStateHeading('No rehearsals found')
            ->emptyStateDescription('Schedule your first rehearsal to get started.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRehearsals::route('/'),
            'create' => Pages\CreateRehearsal::route('/create'),
            'edit' => Pages\EditRehearsal::route('/{record}/edit'),
        ];
    }
}
