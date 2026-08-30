<?php

namespace App\Filament\Resources\FundraisingCampaigns;

use App\Filament\Resources\FundraisingCampaigns\Pages\CreateFundraisingCampaign;
use Filament\Schemas\Schema;
use App\Filament\Resources\FundraisingCampaigns\Pages\EditFundraisingCampaign;
use App\Filament\Resources\FundraisingCampaigns\Pages\ListFundraisingCampaigns;
use App\Filament\Resources\FundraisingCampaigns\Schemas\FundraisingCampaignForm;
use App\Filament\Resources\FundraisingCampaigns\Tables\FundraisingCampaignsTable;
use App\Models\FundraisingCampaign;
use App\Filament\Resources\BaseResource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class FundraisingCampaignResource extends BaseResource
{
    protected static ?string $model = FundraisingCampaign::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Donations';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        // Debug: Log that resource is being accessed
        Log::info('FundraisingCampaignResource form method called');

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
            RelationManagers\DonationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFundraisingCampaigns::route('/'),
            'create' => CreateFundraisingCampaign::route('/create'),
            'edit' => EditFundraisingCampaign::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

}
