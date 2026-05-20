<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\TourPassenger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TourRegistrationService
{
    /**
     * Process tour registration
     */
    public function register(Request $request, Tour $tour): array
    {
        $tour->updateStatusIfNeeded();

        $this->validateTourAvailability($tour);

        $validated = $this->validateRegistrationData($request);

        $this->validatePhoneUniqueness($validated['phone'], $tour->id);

        $this->validateCapacity($tour, $validated['passenger_count']);

        $receiptImage = $this->handleReceiptUpload($request, $tour);

        $passengers = $this->createPassengers($tour, $validated, $receiptImage);

        $tour->refresh();
        $tour->updateStatusIfNeeded();

        return [
            'success' => true,
            'primary_code' => $passengers[0]->passenger_code,
            'message' => "Registration submitted! Your registration is pending confirmation. Reference: {$passengers[0]->passenger_code}"
        ];
    }

    /**
     * Validate tour availability
     */
    private function validateTourAvailability(Tour $tour): void
    {
        if (in_array($tour->status, ['Draft', 'Cancelled'])) {
            abort(404, 'Tour not found');
        }

        if (! $tour->is_registration_open) {
            redirect()->route('tours.index')
                ->with('error', 'Registration is closed for this tour')
                ->throwResponse();
        }

        if ($tour->is_full) {
            redirect()->route('tours.index')
                ->with('error', 'This tour is already full')
                ->throwResponse();
        }
    }

    /**
     * Validate registration data
     */
    private function validateRegistrationData(Request $request): array
    {
        return $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^[0-9]{9}$/',
            'passenger_count' => 'required|integer|min:1|max:20',
            'passenger_names' => 'nullable|array',
            'passenger_names.*' => 'required_with:passenger_names|string|max:255',
            'passenger_phones' => 'nullable|array',
            'passenger_phones.*' => 'nullable|string|regex:/^[0-9]{9}$/',
            'receipt_image' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'honeypot' => 'nullable|string|max:0', // Bot prevention
        ]);
    }

    /**
     * Validate phone uniqueness for tour
     */
    private function validatePhoneUniqueness(string $phone, int $tourId): void
    {
        $fullPhone = config('finot.phone_prefix', '+251') . $phone;

        if (TourPassenger::where('tour_id', $tourId)
            ->where('phone', $fullPhone)
            ->exists()) {

            redirect()->back()
                ->withErrors(['phone' => 'This phone number is already registered for this tour'])
                ->withInput()
                ->throwResponse();
        }
    }

    /**
     * Validate tour capacity
     */
    private function validateCapacity(Tour $tour, int $passengerCount): void
    {
        if (! $tour->max_capacity) {
            return;
        }

        $currentConfirmed = $tour->confirmedPassengers->sum('passenger_count');

        if ($currentConfirmed + $passengerCount > $tour->max_capacity) {
            redirect()->back()
                ->withErrors(['passenger_count' => 'Not enough capacity available'])
                ->withInput()
                ->throwResponse();
        }
    }

    /**
     * Handle receipt file upload
     */
    private function handleReceiptUpload(Request $request, Tour $tour): ?string
    {
        if (! $request->hasFile('receipt_image')) {
            return null;
        }

        $file = $request->file('receipt_image');
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        $directory = 'receipts/tours/' . $tour->id;

        if (! Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        return $file->storeAs($directory, $filename, 'public');
    }

    /**
     * Create passengers for registration
     */
    private function createPassengers(Tour $tour, array $validated, ?string $receiptImage): array
    {
        $lastPassenger = TourPassenger::orderBy('id', 'desc')->first();
        $lastCode = $lastPassenger ? intval(substr($lastPassenger->passenger_code, 3)) : 0;

        $passengerCount = (int) $validated['passenger_count'];
        $passengerNames = $validated['passenger_names'] ?? [];
        $passengerPhones = $validated['passenger_phones'] ?? [];
        $createdPassengers = [];

        $fullPhone = config('finot.phone_prefix', '+251') . $validated['phone'];

        // Lookup returning passenger — use their previous name silently
        $previous = TourPassenger::where('phone', $fullPhone)
            ->where('tour_id', '!=', $tour->id)
            ->latest('id')
            ->first();

        $nameToUse = $previous ? $previous->full_name : $validated['full_name'];

        // Create primary passenger
        $primaryCode = $this->generatePassengerCode(++$lastCode);
        $primaryPassenger = TourPassenger::create([
            'passenger_code' => $primaryCode,
            'tour_id' => $tour->id,
            'full_name' => $nameToUse,
            'phone' => $fullPhone,
            'passenger_count' => 1,
            'receipt_image' => $receiptImage,
            'registration_type' => 'Public',
            'status' => 'Pending',
            'registration_date' => now()->toDateString(),
        ]);
        $createdPassengers[] = $primaryPassenger;

        // Create additional passengers
        for ($i = 0; $i < $passengerCount - 1; $i++) {
            $additionalName = $passengerNames[$i] ?? ($validated['full_name'] . ' (' . __('Guest') . ' ' . ($i + 2) . ')');
            $additionalCode = $this->generatePassengerCode(++$lastCode);

            $additionalPhone = null;
            if (isset($passengerPhones[$i]) && !empty($passengerPhones[$i])) {
                $additionalPhone = config('finot.phone_prefix', '+251') . $passengerPhones[$i];
            }

            $additionalPassenger = TourPassenger::create([
                'passenger_code' => $additionalCode,
                'tour_id' => $tour->id,
                'full_name' => $additionalName,
                'phone' => $additionalPhone,
                'passenger_count' => 1,
                'receipt_image' => null,
                'registration_type' => 'Public',
                'status' => 'Pending',
                'registration_date' => now()->toDateString(),
            ]);
            $createdPassengers[] = $additionalPassenger;
        }

        return $createdPassengers;
    }

    /**
     * Generate passenger code
     */
    private function generatePassengerCode(int $number): string
    {
        return config('finot.tour_passenger_code_prefix', 'TP-') . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
