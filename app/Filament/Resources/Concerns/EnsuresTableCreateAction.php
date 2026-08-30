<?php

namespace App\Filament\Resources\Concerns;

use Filament\Actions\CreateAction;
use Filament\Tables\Table;

trait EnsuresTableCreateAction
{
    public static function configureTable(Table $table): void
    {
        parent::configureTable($table);

        static::ensureTableCreateAction($table);
    }

    protected static function ensureTableCreateAction(Table $table): void
    {
        if (! static::hasPage('create') || ! static::canCreate()) {
            return;
        }

        $alreadyHasCreate = collect($table->getHeaderActions())
            ->contains(fn ($action): bool => $action instanceof CreateAction);

        if ($alreadyHasCreate) {
            return;
        }

        $table->headerActions([
            ...$table->getHeaderActions(),
            CreateAction::make()
                ->label('New '.static::getModelLabel())
                ->icon('heroicon-o-plus')
                ->url(static::getUrl('create')),
        ]);
    }
}
