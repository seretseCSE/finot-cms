<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use App\Filament\Resources\AcademicYearResource\Pages;
use App\Jobs\GenerateEndOfYearReport;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AcademicYearResource extends Resource
{
    protected static ?string $model = AcademicYear::class;

    public static function getNavigationGroup(): ?string { return 'Education'; }

    public static function getNavigationIcon(): ?string { return 'heroicon-o-academic-cap'; }

    public static function getNavigationLabel(): string { return 'Academic Years'; }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return (bool) $user?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();

        return (bool) $user?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canEdit($record): bool
    {
        $user = Auth::user();

        return (bool) $user?->hasRole(['education_head', 'admin', 'superadmin']);
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();

        if (! $user?->hasRole(['admin', 'superadmin'])) {
            return false;
        }

        $enrollments = StudentEnrollment::query()->where('academic_year_id', $record->getKey())->exists();

        return ! $enrollments;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(200),

                EthiopianDatePicker::make('start_date')
                    ->label('Start Date')
                    ->required(),

                EthiopianDatePicker::make('end_date')
                    ->label('End Date')
                    ->required(),

                Forms\Components\Select::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Active' => 'Active',
                        'Deactivated' => 'Deactivated',
                    ])
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(AcademicYear::query()->withCount('enrollments'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'Draft',
                        'success' => 'Active',
                        'danger' => 'Deactivated',
                    ]),
                Tables\Columns\TextColumn::make('start_date')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : ''),
                Tables\Columns\TextColumn::make('end_date')
                    ->formatStateUsing(fn ($state) => $state ? app(EthiopianDateHelper::class)->toString($state) : ''),
                Tables\Columns\TextColumn::make('enrollments_count')
                    ->label('Students Count')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->visible(fn (AcademicYear $record): bool => in_array($record->status, ['Draft', 'Deactivated'], true))
                    ->requiresConfirmation()
                    ->action(function (AcademicYear $record): void {
                        $thisYear = $record;

                        $active = AcademicYear::query()->where('is_active', true)->first();

                        if ($active && self::rangesOverlap($active->start_date, $active->end_date, $thisYear->start_date, $thisYear->end_date)) {
                            Notification::make()->title('Cannot activate due to overlapping dates')->danger()->send();
                            return;
                        }

                        DB::transaction(function () use ($thisYear, $active): void {
                            if ($active) {
                                $active->update([
                                    'is_active' => false,
                                    'status' => 'Deactivated',
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
                                'is_active' => true,
                                'status' => 'Active',
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

                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-pause')
                    ->color('danger')
                    ->visible(fn (AcademicYear $record): bool => $record->status === 'Active')
                    ->requiresConfirmation()
                    ->action(function (AcademicYear $record): void {
                        DB::transaction(function () use ($record): void {
                            $record->update([
                                'is_active' => false,
                                'status' => 'Deactivated',
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

                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (AcademicYear $record): bool => $record->status === 'Deactivated' && Auth::user()?->hasRole(['admin', 'superadmin']))
                    ->requiresConfirmation()
                    ->action(fn (AcademicYear $record) => $record->update(['status' => 'Draft', 'is_active' => false])),
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
            'index' => Pages\ListAcademicYears::class,
            'create' => Pages\CreateAcademicYear::class,
            'edit' => Pages\EditAcademicYear::class,
        ];
    }
}

