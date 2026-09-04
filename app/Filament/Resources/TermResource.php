<?php

namespace App\Filament\Resources;

use App\Filament\Support\HidesFromNavigation;
use App\Filament\Resources\TermResource\Pages;
use App\Models\Term;
use App\Services\Academics\DeactivateTermService;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TermResource extends BaseResource
{
    use HidesFromNavigation;

    protected static ?string $model = Term::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Education Management';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-calendar-days';
    }

    public static function getNavigationLabel(): string
    {
        return 'Semesters';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('academic_year_id')
                ->relationship('academicYear', 'name')
                ->required(),
            Forms\Components\Select::make('batch_year_id')
                ->label('Batch year')
                ->relationship('batchYear', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            Forms\Components\TextInput::make('name')->required()->maxLength(100),
            Forms\Components\Select::make('semester_number')
                ->label('Semester in year')
                ->options([
                    1 => 'Semester 1',
                    2 => 'Semester 2',
                ])
                ->helperText('Each academic year has two semesters (8–10 across a 4–5 year program).')
                ->nullable(),
            Forms\Components\DatePicker::make('starts_on')->required(),
            Forms\Components\DatePicker::make('ends_on')->required(),
            Forms\Components\Select::make('status')
                ->options([
                    'planned' => 'Planned',
                    'active' => 'Active',
                    'closed' => 'Closed',
                ])
                ->default('planned')
                ->required(),
            Forms\Components\Toggle::make('is_active')->default(false)
                ->helperText('Prefer Activate action so sibling semesters in the same batch year close cleanly.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('academicYear.name'),
                Tables\Columns\TextColumn::make('batchYear.name')->label('Batch year'),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('semester_number')->label('Sem')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('starts_on')->date(),
                Tables\Columns\TextColumn::make('ends_on')->date(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('activate')
                    ->visible(fn (Term $record) => $record->status !== 'active')
                    ->requiresConfirmation()
                    ->action(fn (Term $record) => app(DeactivateTermService::class)->activate($record)),
                Actions\Action::make('deactivate')
                    ->label('Close')
                    ->visible(fn (Term $record) => $record->status === 'active' || $record->is_active)
                    ->requiresConfirmation()
                    ->action(fn (Term $record) => app(DeactivateTermService::class)->deactivate($record)),
                Actions\Action::make('compute_results')
                    ->label('Compute results')
                    ->icon('heroicon-o-calculator')
                    ->visible(fn () => auth()->user()?->can('results.manage') || auth()->user()?->hasRole(['education_head', 'education_monitor', 'admin', 'superadmin']))
                    ->action(function (Term $record) {
                        $result = app(\App\Services\Academics\ComputeTermResultsService::class)
                            ->compute($record, auth()->user());
                        \Filament\Notifications\Notification::make()
                            ->title('Results computed')
                            ->body(($result['students'] ?? 0).' students updated.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTerms::route('/'),
            'create' => Pages\CreateTerm::route('/create'),
            'edit' => Pages\EditTerm::route('/{record}/edit'),
        ];
    }
}
