<?php

namespace App\Filament\Resources;

use App\Enums\FacilityType;
use App\Filament\Resources\FacilityResource\Pages;
use App\Filament\Support\HidesFromNavigation;
use App\Models\Facility;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class FacilityResource extends BaseResource
{
    use HidesFromNavigation;

    protected static ?string $model = Facility::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Operations';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-office';
    }

    public static function getNavigationLabel(): string
    {
        return 'Facilities';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('name')->required()->maxLength(150),
            Forms\Components\Select::make('type')->options(
                collect(FacilityType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->name])->all()
            )->required(),
            Forms\Components\TextInput::make('capacity')->numeric()->minValue(1),
            Forms\Components\Textarea::make('location_notes'),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('capacity'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacilities::route('/'),
            'create' => Pages\CreateFacility::route('/create'),
            'edit' => Pages\EditFacility::route('/{record}/edit'),
        ];
    }
}
