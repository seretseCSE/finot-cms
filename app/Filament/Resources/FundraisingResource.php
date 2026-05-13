<?php

namespace App\Filament\Resources;

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
        $user = auth()->user();

        // Superadmin can see everything
        if ($user?->hasRole('superadmin')) {
            return true;
        }

        // Admin can see everything
        if ($user?->hasRole('admin')) {
            return true;
        }

        // Finance Head and Nibret Hisab Head can view and update
        if ($user?->hasRole('finance_head') || $user?->hasRole('nibret_hisab_head')) {
            return true;
        }

        return false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        // Only Admin and Superadmin can create campaigns
        return $user?->hasRole('admin') || $user?->hasRole('superadmin');
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        // Admin and Superadmin can edit everything
        if ($user?->hasRole('admin') || $user?->hasRole('superadmin')) {
            return true;
        }

        // Finance Head and Nibret Hisab Head can only edit total_raised field
        if ($user?->hasRole('finance_head') || $user?->hasRole('nibret_hisab_head')) {
            return true;
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        // Only Admin and Superadmin can delete
        return $user?->hasRole('admin') || $user?->hasRole('superadmin');
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        // Only Admin and Superadmin can delete
        return $user?->hasRole('admin') || $user?->hasRole('superadmin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
