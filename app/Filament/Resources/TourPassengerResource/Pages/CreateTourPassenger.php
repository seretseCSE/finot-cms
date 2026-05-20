<?php

namespace App\Filament\Resources\TourPassengerResource\Pages;

use App\Filament\Resources\TourPassengerResource;
use App\Models\TourPassenger;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateTourPassenger extends CreateRecord
{
    protected static string $resource = TourPassengerResource::class;

    protected function handleRecordCreation(array $data): TourPassenger
    {
        $additionalPassengers = $data['additional_passengers'] ?? [];
        unset($data['additional_passengers']);

        $data['passenger_count'] = 1;
        $data['registered_by'] = Auth::id();

        // Belt-and-suspenders: check duplicate before creating
        if ($data['phone']) {
            $exists = TourPassenger::where('tour_id', $data['tour_id'])
                ->where('phone', $data['phone'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'phone' => 'This phone number is already registered for this tour.',
                ]);
            }
        }

        $primaryPassenger = TourPassenger::create($data);

        $createdCount = 1;
        foreach ($additionalPassengers as $additional) {
            $phone = $additional['phone'] ?? null;

            if ($phone) {
                $exists = TourPassenger::where('tour_id', $data['tour_id'])
                    ->where('phone', $phone)
                    ->exists();

                if ($exists) {
                    Notification::make()
                        ->title('Duplicate Skipped')
                        ->body("Phone {$phone} already registered for this tour. Skipping.")
                        ->warning()
                        ->send();
                    continue;
                }
            }

            TourPassenger::create([
                'tour_id' => $data['tour_id'],
                'registration_type' => $data['registration_type'],
                'member_id' => $data['member_id'] ?? null,
                'full_name' => $additional['name'],
                'phone' => $phone,
                'passenger_count' => 1,
                'status' => $data['status'] ?? 'Pending',
                'registration_date' => $data['registration_date'] ?? now()->toDateString(),
                'receipt_image' => $data['receipt_image'] ?? null,
                'registered_by' => $data['registered_by'],
            ]);
            $createdCount++;
        }

        Notification::make()
            ->title('Passengers Registered')
            ->body("{$createdCount} passenger(s) registered successfully.")
            ->success()
            ->send();

        return $primaryPassenger;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
