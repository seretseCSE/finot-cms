<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Services\Facilities\BookingService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(BookingService::class)->request(Auth::user(), $data + [
                'weeks' => (int) ($this->data['weeks'] ?? 4),
            ]);
        } catch (HttpException $e) {
            throw ValidationException::withMessages(['start_at' => $e->getMessage()]);
        }
    }
}
