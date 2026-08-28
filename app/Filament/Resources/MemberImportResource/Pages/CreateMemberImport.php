<?php

namespace App\Filament\Resources\MemberImportResource\Pages;

use App\Filament\Resources\MemberImportResource;
use App\Services\Imports\MemberImportValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMemberImport extends CreateRecord
{
    protected static string $resource = MemberImportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['department_id'] = Auth::user()?->department_id;
        $data['status'] = 'draft';

        return $data;
    }

    protected function afterCreate(): void
    {
        $csv = $this->data['csv'] ?? '';
        $lines = preg_split('/\r\n|\r|\n/', trim((string) $csv));
        if (! $lines || count($lines) < 2) {
            return;
        }

        $headers = str_getcsv(array_shift($lines));
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = array_combine($headers, array_pad(str_getcsv($line), count($headers), null));
        }

        $map = [];
        foreach ($headers as $header) {
            $map[$header] = $header;
        }

        app(MemberImportValidator::class)->ingest($this->record, $rows, $map);
    }
}
