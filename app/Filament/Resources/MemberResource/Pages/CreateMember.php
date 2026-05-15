<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Services\MemberCreationService;
use App\Filament\Resources\MemberResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    private MemberCreationService $memberService;

    public function boot(): void
    {
        $this->memberService = app(MemberCreationService::class);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->memberService->processBeforeCreate($data);
    }


    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $this->memberService->processAfterCreate($this->record, $this->data);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Create Member')
                ->icon('heroicon-o-check'),

            $this->getCreateAnotherFormAction()
                ->label('Create & Create Another'),

            $this->getCancelFormAction(),

            Action::make('previous_tab')
                ->label('Previous')
                ->icon('heroicon-o-chevron-left')
                ->color('gray')
                ->extraAttributes(['class' => 'member-tab-previous ms-auto'])
                ->action(function () {
                }),

            Action::make('next_tab')
                ->label('Next')
                ->icon('heroicon-o-chevron-right')
                ->color('primary')
                ->extraAttributes(['class' => 'member-tab-next'])
                ->action(function () {
                }),
        ];
    }
}
