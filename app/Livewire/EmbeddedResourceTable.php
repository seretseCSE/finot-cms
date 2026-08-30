<?php

namespace App\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EmbeddedResourceTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** @var class-string<Resource> */
    #[Locked]
    public string $resource;

    public function mount(): void
    {
        abort_unless(is_subclass_of($this->resource, Resource::class), 404);
        abort_unless($this->resource::canViewAny(), 403);
    }

    public function table(Table $table): Table
    {
        $resource = $this->resource;

        $table->query($resource::getEloquentQuery());

        $resource::configureTable($table);

        if (! $table->hasCustomRecordUrl()) {
            $table->recordUrl(function (Model $record) use ($resource): ?string {
                if ($resource::hasPage('edit') && $resource::canEdit($record)) {
                    return $resource::getUrl('edit', ['record' => $record]);
                }

                if ($resource::hasPage('view') && $resource::canView($record)) {
                    return $resource::getUrl('view', ['record' => $record]);
                }

                return null;
            });
        }

        foreach ($table->getRecordActions() as $action) {
            if ($action instanceof EditAction && $resource::hasPage('edit')) {
                $action->url(fn (Model $record): string => $resource::getUrl('edit', ['record' => $record]));
            }

            if ($action instanceof ViewAction && $resource::hasPage('view')) {
                $action->url(fn (Model $record): string => $resource::getUrl('view', ['record' => $record]));
            }
        }

        $hasCreate = collect($table->getHeaderActions())
            ->contains(fn ($action) => $action instanceof CreateAction);

        if (! $hasCreate && $resource::hasPage('create') && $resource::canCreate()) {
            $table->headerActions([
                ...$table->getHeaderActions(),
                CreateAction::make()
                    ->label('New '.$resource::getModelLabel())
                    ->icon('heroicon-o-plus')
                    ->url($resource::getUrl('create')),
            ]);
        }

        return $table;
    }

    public function render()
    {
        return view('livewire.embedded-resource-table');
    }
}
