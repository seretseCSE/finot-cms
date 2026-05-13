<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Services\MemberCreationService;
use App\Filament\Resources\MemberResource;
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
}
