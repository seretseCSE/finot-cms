<?php

namespace App\Filament\Resources;

use App\Filament\Forms\Components\EthiopianDatePicker;
use App\Filament\Resources\AcademicYearResource\Pages;
use App\Jobs\GenerateEndOfYearReport;
use App\Helpers\EthiopianDateHelper;
use App\Models\AcademicYear;
use App\Models\StudentEnrollment;
use Illuminate\Support\Carbon;
use Filament\Actions;
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

                Forms\Components\DatePicker::make('start_date')
                    ->label('Start Date')
                    ->required()
                    ->default(Carbon::now()->format('Y-m-d')),

                Forms\Components\DatePicker::make('end_date')
                    ->label('End Date')
                    ->required()
                    ->default(Carbon::now()->addYear()->format('Y-m-d'))
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('end_date', $state))
                    ->rules(function (callable $get) {
                        $startDate = $get('start_date');
                        return $startDate ? ['after:' . $startDate] : [];
                    }),

                Forms\Components\Select::make('status')
                    ->options([
                        'Draft' => 'Draft',
                        'Active' => 'Active',
                        'Deactivated' => 'Deactivated',
                    ])
                    ->default('Draft')
                    ->disabled(fn ($record) => ! static::canEdit($record)),
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
                Actions\EditAction::make()
                    ->visible(fn (AcademicYear $record): bool => static::canEdit($record)),
                
                Actions\DeleteAction::make()
                    ->visible(fn (AcademicYear $record): bool => static::canDelete($record)),

                // Reactivate Academic Year (Admin Override)
                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->visible(fn (AcademicYear $record): bool => 
                        $record->status === 'Deactivated' && Auth::user()->hasRole(['admin', 'superadmin'])
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Reactivate Academic Year')
                    ->modalDescription('This will reactivate the deactivated academic year. This action should only be used in emergency situations.')
                    ->modalSubmitActionLabel('Yes, Reactivate')
                    ->action(function (AcademicYear $record) {
                        try {
                            // Check if there's already an active academic year
                            $activeYear = AcademicYear::where('status', 'Active')->first();
                            
                            if ($activeYear) {
                                // Deactivate the currently active year first
                                $activeYear->update([
                                    'status' => 'Deactivated',
                                    'deactivated_at' => now(),
                                    'deactivated_by' => Auth::id(),
                                ]);

                                // Log the automatic deactivation
                                activity()
                                    ->causedBy(Auth::user())
                                    ->performedOn($activeYear)
                                    ->withProperties([
                                        'action' => 'auto_deactivated_for_reactivation',
                                        'reactivated_year' => $record->name,
                                        'reason' => 'Admin override to reactivate ' . $record->name,
                                    ])
                                    ->log('Academic year automatically deactivated for admin override');
                            }

                            // Reactivate the selected academic year
                            $record->update([
                                'status' => 'Active',
                                'reactivated_at' => now(),
                                'reactivated_by' => Auth::id(),
                                'deactivated_at' => null,
                                'deactivated_by' => null,
                            ]);

                            // Log the reactivation
                            activity()
                                ->causedBy(Auth::user())
                                ->performedOn($record)
                                ->withProperties([
                                    'action' => 'admin_override_reactivate',
                                    'previous_status' => 'Deactivated',
                                    'new_status' => 'Active',
                                    'auto_deactivated_year' => $activeYear ? $activeYear->name : null,
                                ])
                                ->log('Academic year reactivated via admin override');

                            Notification::make()
                                ->title('Academic Year Reactivated')
                                ->body("Academic year '{$record->name}' has been successfully reactivated.")
                                ->success()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Reactivation Failed')
                                ->body('Failed to reactivate academic year: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Emergency Override Action (Superadmin only)
                Tables\Actions\Action::make('emergency_override')
                    ->label('Emergency Override')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn (AcademicYear $record): bool => 
                        $record->status === 'Deactivated' && Auth::user()->hasRole('superadmin')
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Emergency Override')
                    ->modalDescription('This will force reactivate the academic year and deactivate all others. Use only in critical situations.')
                    ->modalSubmitActionLabel('Yes, Force Reactivate')
                    ->action(function (AcademicYear $record) {
                        try {
                            // Deactivate ALL other academic years
                            AcademicYear::where('id', '!=', $record->id)
                                ->where('status', 'Active')
                                ->update([
                                    'status' => 'Deactivated',
                                    'deactivated_at' => now(),
                                    'deactivated_by' => Auth::id(),
                                ]);

                            // Force reactivate the selected academic year
                            $record->update([
                                'status' => 'Active',
                                'reactivated_at' => now(),
                                'reactivated_by' => Auth::id(),
                                'deactivated_at' => null,
                                'deactivated_by' => null,
                            ]);

                            // Log the emergency override
                            activity()
                                ->causedBy(Auth::user())
                                ->performedOn($record)
                                ->withProperties([
                                    'action' => 'emergency_override_reactivate',
                                    'previous_status' => 'Deactivated',
                                    'new_status' => 'Active',
                                    'emergency' => true,
                                ])
                                ->log('Emergency override: Academic year force reactivated');

                            Notification::make()
                                ->title('Emergency Override Completed')
                                ->body("Academic year '{$record->name}' has been force reactivated. All other years deactivated.")
                                ->warning()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Emergency Override Failed')
                                ->body('Failed to force reactivate academic year: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // View Details
                Tables\Actions\Action::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn (AcademicYear $record): string => static::getUrl('view', ['record' => $record])),
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
            'view' => Pages\ViewAcademicYear::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest('start_date');
    }

    /**
     * Ensure only one academic year can be active at a time.
     * When deactivating a year, automatically archive its contributions (Section 19.1).
     */
    public static function ensureSingleActiveYear(AcademicYear $newYear): void
    {
        \Log::info('ensureSingleActiveYear called for academic year: ' . $newYear->id . ' (' . $newYear->name . ')');
        
        DB::transaction(function () use ($newYear): void {
            // Deactivate all other active academic years
            $deactivatedCount = AcademicYear::query()
                ->where('status', 'Active')
                ->where('id', '!=', $newYear->id)
                ->update([
                    'status' => 'Deactivated',
                    'deactivated_at' => now(),
                    'deactivated_by' => Auth::user()->id,
                ]);

            \Log::info('Deactivated ' . $deactivatedCount . ' other active academic years');

            // Activate the new academic year
            $newYear->update([
                'status' => 'Active',
                'activated_at' => now(),
                'activated_by' => Auth::user()->id,
            ]);

            \Log::info('Activated academic year: ' . $newYear->id . ' (' . $newYear->name . ')');

            // Complete enrollments from previous active year
            $previousActive = AcademicYear::query()
                ->where('status', 'Deactivated')
                ->where('deactivated_at', '>=', now()->subMinutes(5))
                ->first();

            if ($previousActive) {
                StudentEnrollment::query()
                    ->where('academic_year_id', $previousActive->id)
                    ->where('status', 'Enrolled')
                    ->update([
                        'status' => 'Completed',
                        'completion_date' => now()->toDateString(),
                        'completed_by' => Auth::user()->id,
                    ]);

                \Log::info('Completed enrollments for previous active year: ' . $previousActive->id);

                // SECTION 19.1: Automatically archive contributions when academic year is deactivated.
                // This marks all non-archived contributions for the deactivated year as archived,
                // making them read-only and excluded from active reports.
                $archivedCount = \App\Models\Contribution::query()
                    ->where('academic_year_id', $previousActive->id)
                    ->where(function ($q) {
                        $q->where('is_archived', false)
                          ->orWhereNull('is_archived');
                    })
                    ->update([
                        'is_archived' => true,
                        'archived_at' => now(),
                        'archived_by' => Auth::user()->id,
                    ]);

                \Log::info("Archived {$archivedCount} contributions for deactivated year: {$previousActive->id}");

                // Audit trail for contribution archival
                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($previousActive)
                    ->withProperties([
                        'action' => 'archive_contributions',
                        'academic_year_id' => $previousActive->id,
                        'academic_year_name' => $previousActive->name,
                        'contributions_archived' => $archivedCount,
                    ])
                    ->log('Contributions automatically archived on academic year deactivation');
            }

            // Log the activation
            \Log::channel('audit')->info('Academic Year Activated', [
                'action' => 'academic_year_activated',
                'academic_year_id' => $newYear->id,
                'academic_year_name' => $newYear->name,
                'previous_academic_year_id' => $previousActive?->id,
                'activated_by' => Auth::user()->id,
                'timestamp' => now()->toDateTimeString(),
            ]);
        });
    }
}

