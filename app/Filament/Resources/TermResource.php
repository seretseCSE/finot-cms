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
        return 'Terms';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('academic_year_id')
                ->relationship('academicYear', 'name')
                ->required(),
            Forms\Components\TextInput::make('name')->required()->maxLength(100),
            Forms\Components\DatePicker::make('starts_on')->required(),
            Forms\Components\DatePicker::make('ends_on')->required(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('academicYear.name'),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('starts_on')->date(),
                Tables\Columns\TextColumn::make('ends_on')->date(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('deactivate')
                    ->visible(fn (Term $record) => $record->is_active)
                    ->requiresConfirmation()
                    ->action(fn (Term $record) => app(DeactivateTermService::class)->deactivate($record)),
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
