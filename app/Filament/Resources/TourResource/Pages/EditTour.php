<?php

namespace App\Filament\Resources\TourResource\Pages;

use App\Filament\Resources\TourResource;
use App\Models\TourPassenger;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Builder;

class EditTour extends EditRecord
{
    protected static string $resource = TourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn ($record) => TourResource::canDelete($record))
                ->before(function ($record) {
                    if (!$record->canBeDeleted()) {
                        throw new \Exception('Cannot delete tour with passengers. Use Cancel action instead.');
                    }
                }),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Tour Information')
                ->schema([
                    Forms\Components\TextInput::make('place')
                        ->label('Tour Place')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(3),

                    \App\Filament\Forms\Components\EthiopianDatePicker::make('tour_date')
                        ->label('Tour Date')
                        ->required()
                        ->disabled(fn ($record) => !$record->canEditDate()),

                    Forms\Components\TimePicker::make('start_time')
                        ->label('Start Time')
                        ->required()
                        ->withoutSeconds(),

                    Forms\Components\TextInput::make('cost_per_person')
                        ->label('Cost Per Person (Birr)')
                        ->numeric()
                        ->step(0.01)
                        ->nullable(),

                    \App\Filament\Forms\Components\EthiopianDatePicker::make('registration_deadline')
                        ->label('Registration Deadline')
                        ->nullable(),

                    Forms\Components\TextInput::make('max_capacity')
                        ->label('Maximum Capacity')
                        ->numeric()
                        ->integer()
                        ->nullable(),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'Draft' => 'Draft',
                            'Published' => 'Published',
                            'In Progress' => 'In Progress',
                            'Completed' => 'Completed',
                            'Cancelled' => 'Cancelled',
                        ])
                        ->required()
                        ->disabled(fn ($record) => $record && in_array($record->status, ['In Progress', 'Completed'])),
                ])
                ->columns(2),

            // Add Passenger Management Section
            Forms\Components\Section::make('Passenger Management')
                ->schema([
                    // This will be handled by the relation manager
                ])
                ->visible(fn ($record) => $record->passengers->isNotEmpty()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Set created_by if not set
        if (!isset($data['created_by'])) {
            $data['created_by'] = auth()->id();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Log status changes to audit trail
        $original = $this->getRecord()->getOriginal();
        $current = $this->getRecord()->toArray();

        if (isset($original['status']) && isset($current['status']) && $original['status'] !== $current['status']) {
            \Log::channel('audit')->warning('Tier 2 Audit Log', [
                'tier' => 2,
                'action' => 'tour_status_changed',
                'entity_id' => $this->getRecord()->id,
                'entity_type' => 'tour',
                'old_value' => json_encode(['status' => $original['status']]),
                'new_value' => json_encode(['status' => $current['status']]),
                'user_id' => auth()->id(),
                'timestamp' => now()->toDateTimeString(),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

