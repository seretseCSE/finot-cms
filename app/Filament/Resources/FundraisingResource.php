<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\EnsuresTableCreateAction;
use App\Filament\Resources\FundraisingCampaigns\Schemas\FundraisingCampaignForm;
use Filament\Schemas\Schema;
use App\Filament\Resources\FundraisingCampaigns\Tables\FundraisingCampaignsTable;
use App\Models\FundraisingCampaign;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class FundraisingResource extends Resource
{
    use EnsuresTableCreateAction;

    protected static ?string $model = FundraisingCampaign::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';

    protected static UnitEnum|string|null $navigationGroup = 'Tour Management';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema(FundraisingCampaignForm::getSchema());
    }

    public static function table(Table $table): Table
    {
        return FundraisingCampaignsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\FundraisingCampaigns\Pages\ListFundraisingCampaigns::route('/'),
            'create' => \App\Filament\Resources\FundraisingCampaigns\Pages\CreateFundraisingCampaign::route('/create'),
            'edit' => \App\Filament\Resources\FundraisingCampaigns\Pages\EditFundraisingCampaign::route('/{record}/edit'),
        ];
    }

    public static function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => static::canCreate()),
        ];
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('fundraising.view');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('fundraising.create');
    }

    public static function canEdit($record): bool
    {
        return (bool) auth()->user()?->can('fundraising.update');
    }

    public static function canDelete($record): bool
    {
        return (bool) auth()->user()?->can('fundraising.delete');
    }

    public static function canDeleteAny(): bool
    {
        return (bool) auth()->user()?->can('fundraising.delete');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
