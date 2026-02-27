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
     * Ensure only one academic year can be active at a time
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

