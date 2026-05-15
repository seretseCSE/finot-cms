<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use Filament\Schemas\Schema;
use App\Filament\Resources\AcademicYearResource\Pages;
use App\Helpers\EthiopianDateHelper;
use App\Jobs\GenerateEndOfYearReport;
use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use App\Enums\Roles;
use Illuminate\Support\Facades\DB;

class AcademicYearResource extends BaseResource
{
    protected static ?string $model = AcademicYear::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-academic-cap';
    }

    public static function getNavigationLabel(): string
    {
        return 'Academic Years';
    }

    public static function canViewAny(): bool
    {
        return (bool) Auth::user()?->can('academic_years.view');
    }

    public static function canCreate(): bool
    {
        return (bool) Auth::user()?->can('academic_years.create');
    }

    public static function canEdit($record): bool
    {
        return (bool) Auth::user()?->can('academic_years.update');
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();

        if (! $user?->can('academic_years.delete')) {
            return false;
        }

        $enrollments = StudentEnrollment::query()->where('academic_year_id', $record->getKey())->exists();

        return ! $enrollments;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(200),

                EthiopianDatePicker::make('start_date')
                    ->label('Start Date')
                    ->required(),

                EthiopianDatePicker::make('end_date')
                    ->label('End Date')
                    ->required()
                    ->after('start_date'),

                Select::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Active' => 'Active',
                        'Deactivated' => 'Deactivated',
                    ]),

                Select::make('phase')
                    ->options([
                        'upcoming' => 'Upcoming',
                        'current' => 'Current',
                        'completed' => 'Completed',
                    ])
                    ->helperText('Only one year can be Current and one Upcoming at a time.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(AcademicYear::query()->withCount('enrollments'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'Draft' => 'gray',
                        'Active' => 'success',
                        'Deactivated' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('phase')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'upcoming' => 'warning',
                        'current' => 'success',
                        'completed' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('start_date')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : ''),
                Tables\Columns\TextColumn::make('end_date')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : ''),
                Tables\Columns\TextColumn::make('enrollments_count')
                    ->label('Students Count')
                    ->sortable(),
            ])
            ->actions([
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->visible(fn (AcademicYear $record): bool => in_array($record->status, ['Draft', 'Deactivated'], true))
                    ->requiresConfirmation()
                    ->action(function (AcademicYear $record): void {
                        $thisYear = $record;

                        $active = AcademicYear::query()->where('status', 'Active')->first();

                        if ($active && self::rangesOverlap($active->start_date, $active->end_date, $thisYear->start_date, $thisYear->end_date)) {
                            Notification::make()->title('Cannot activate due to overlapping dates')->danger()->send();

                            return;
                        }

                        DB::transaction(function () use ($thisYear, $active): void {
                            if ($active) {
                                $active->update([
                                    'status' => 'Deactivated',
                                    'phase' => 'completed',
                                    'deactivated_at' => now(),
                                    'deactivated_by' => Auth::id(),
                                ]);

                                StudentEnrollment::query()
                                    ->where('academic_year_id', $active->getKey())
                                    ->where('status', 'Enrolled')
                                    ->update(['status' => 'Completed', 'completion_date' => now()->toDateString(), 'completed_by' => Auth::id()]);

                                GenerateEndOfYearReport::dispatch($active->getKey());
                            }

                            $thisYear->update([
                                'status' => 'Active',
                                'phase' => 'current',
                                'activated_at' => now(),
                                'activated_by' => Auth::id(),
                            ]);

                            \Log::channel('audit')->warning('Tier 2 Audit Log', [
                                'tier' => 2,
                                'action' => 'academic_year_activated',
                                'academic_year_id' => $thisYear->getKey(),
                                'academic_year_name' => $thisYear->name,
                                'previous_academic_year_id' => $active?->getKey(),
                                'activated_by' => Auth::id(),
                                'timestamp' => now()->toDateTimeString(),
                            ]);
                        });

                        Notification::make()->title('Academic year activated')->success()->send();
                    }),

                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-pause')
                    ->color('danger')
                    ->visible(fn (AcademicYear $record): bool => $record->status === 'Active')
                    ->requiresConfirmation()
                    ->action(function (AcademicYear $record): void {
                        DB::transaction(function () use ($record): void {
                            $record->update([
                                'status' => 'Deactivated',
                                'phase' => 'completed',
                                'deactivated_at' => now(),
                                'deactivated_by' => Auth::id(),
                            ]);

                            StudentEnrollment::query()
                                ->where('academic_year_id', $record->getKey())
                                ->where('status', 'Enrolled')
                                ->update(['status' => 'Completed', 'completion_date' => now()->toDateString(), 'completed_by' => Auth::id()]);

                            \Log::channel('audit')->warning('Tier 2 Audit Log', [
                                'tier' => 2,
                                'action' => 'academic_year_deactivated',
                                'academic_year_id' => $record->getKey(),
                                'academic_year_name' => $record->name,
                                'deactivated_by' => Auth::id(),
                                'timestamp' => now()->toDateTimeString(),
                            ]);
                        });

                        Notification::make()->title('Academic year deactivated')->success()->send();
                    }),

                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (AcademicYear $record): bool => $record->status === 'Deactivated' && Auth::user()?->can('academic_years.update'))
                    ->requiresConfirmation()
                    ->action(fn (AcademicYear $record) => $record->update(['status' => 'Draft', 'phase' => null])),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function rangesOverlap($aStart, $aEnd, $bStart, $bEnd): bool
    {
        return ! ($bEnd < $aStart || $bStart > $aEnd);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAcademicYears::route('/'),
            'create' => Pages\CreateAcademicYear::route('/create'),
            'edit' => Pages\EditAcademicYear::route('/{record}/edit'),
        ];
    }
}
