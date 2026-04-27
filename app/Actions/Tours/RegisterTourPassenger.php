<?php

namespace App\Actions\Tours;

use App\Models\Tour;
use App\Models\TourPassenger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegisterTourPassenger
{
    /**
     * Register passengers for a tour.
     *
     * @param  Tour  $tour
     * @param  array  $data  Validated registration data
     * @param  UploadedFile|null  $receiptFile
     * @return array{primaryCode: string, passengers: array<int, TourPassenger>}
     */
    public function execute(Tour $tour, array $data, ?UploadedFile $receiptFile = null): array
    {
        $receiptImage = $this->storeReceipt($tour, $receiptFile);

        $lastCode = $this->getLastPassengerCode();
        $passengerCount = (int) ($data['passenger_count'] ?? 1);
        $passengerNames = $data['passenger_names'] ?? [];
        $createdPassengers = [];

        // Create primary passenger (the one with the phone)
        $primaryCode = $this->generatePassengerCode(++$lastCode);
        $primaryPassenger = TourPassenger::create([
            'passenger_code' => $primaryCode,
            'tour_id' => $tour->id,
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'passenger_count' => 1,
            'receipt_image' => $receiptImage,
            'registration_type' => 'Public',
            'status' => 'Pending',
            'registration_date' => now()->toDateString(),
        ]);
        $createdPassengers[] = $primaryPassenger;

        // Create additional passengers as separate rows
        for ($i = 0; $i < $passengerCount - 1; $i++) {
            $additionalName = $passengerNames[$i] ?? ($data['full_name'].' ('.__('Guest').' '.($i + 2).')');
            $additionalCode = $this->generatePassengerCode(++$lastCode);

            $additionalPassenger = TourPassenger::create([
                'passenger_code' => $additionalCode,
                'tour_id' => $tour->id,
                'full_name' => $additionalName,
                'phone' => null,
                'passenger_count' => 1,
                'receipt_image' => null,
                'registration_type' => 'Public',
                'status' => 'Pending',
                'registration_date' => now()->toDateString(),
            ]);
            $createdPassengers[] = $additionalPassenger;
        }

        // Refresh tour status in case it's now full
        $tour->refresh();
        $tour->updateStatusIfNeeded();

        return [
            'primaryCode' => $createdPassengers[0]->passenger_code,
            'passengers' => $createdPassengers,
        ];
    }

    /**
     * Store the receipt image if provided.
     */
    protected function storeReceipt(Tour $tour, ?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        $filename = time().'_'.Str::random(10).'.'.$file->getClientOriginalExtension();
        $directory = 'receipts/tours/'.$tour->id;

        if (! Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        $file->storeAs($directory, $filename, 'public');

        return $filename;
    }

    /**
     * Get the last numeric passenger code.
     */
    protected function getLastPassengerCode(): int
    {
        $lastPassenger = TourPassenger::orderBy('id', 'desc')->first();

        if (! $lastPassenger) {
            return 0;
        }

        $prefix = config('finot.tour_passenger_code_prefix', 'TP-');
        $numericPart = substr($lastPassenger->passenger_code, strlen($prefix));

        return (int) $numericPart;
    }

    /**
     * Generate a passenger code.
     */
    protected function generatePassengerCode(int $number): string
    {
        $prefix = config('finot.tour_passenger_code_prefix', 'TP-');

        return $prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
