<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\TourPassenger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TourController extends Controller
{
    /**
     * Display public tours listing page
     */
    public function index()
    {
        $tours = Tour::with(['confirmedPassengers'])
            ->where('status', 'Published')
            ->orderBy('tour_date', 'asc')
            ->get()
            ->map(function ($tour) {
                return [
                    'id' => $tour->id,
                    'place' => $tour->place,
                    'description' => Str::limit($tour->description, 150),
                    'tour_date' => $tour->tour_date,
                    'ethiopian_date' => $tour->ethiopian_date,
                    'start_time' => $tour->start_time,
                    'formatted_cost' => $tour->formatted_cost,
                    'registration_deadline' => $tour->registration_deadline,
                    'max_capacity' => $tour->max_capacity,
                    'remaining_capacity' => $tour->remaining_capacity,
                    'is_full' => $tour->is_full,
                    'is_registration_open' => $tour->is_registration_open,
                    'confirmed_passengers_count' => $tour->confirmedPassengers->sum('passenger_count'),
                ];
            });

        return view('public.tours', compact('tours'));
    }

    /**
     * Show tour registration form
     */
    public function showRegister($id)
    {
        $tour = Tour::findOrFail($id);

        if ($tour->status !== 'Published') {
            abort(404, 'Tour not found');
        }

        if (!$tour->is_registration_open) {
            return redirect()->route('tours.index')
                ->with('error', 'Registration is closed for this tour');
        }

        return view('public.tour-register', compact('tour'));
    }

    /**
     * Process tour registration
     */
    public function register(Request $request, $id)
    {
        $tour = Tour::findOrFail($id);

        if ($tour->status !== 'Published') {
            abort(404, 'Tour not found');
        }

        if (!$tour->is_registration_open) {
            return redirect()->route('tours.index')
                ->with('error', 'Registration is closed for this tour');
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^\+251[0-9]{9}$/',
            'passenger_count' => 'required|integer|min:1',
            'receipt_image' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'honeypot' => 'nullable|string|max:0', // Bot prevention
        ]);

        // Check honeypot field (bot prevention)
        if (!empty($validated['honeypot'])) {
            return redirect()->back()
                ->with('error', 'Invalid submission');
        }

        // Check if phone already registered for this tour
        if (TourPassenger::where('tour_id', $tour->id)
            ->where('phone', $validated['phone'])
            ->exists()) {
            return redirect()->back()
                ->withErrors(['phone' => 'This phone number is already registered for this tour'])
                ->withInput();
        }

        // Check capacity
        if ($tour->max_capacity) {
            $currentConfirmed = $tour->confirmedPassengers->sum('passenger_count');
            if ($currentConfirmed + $validated['passenger_count'] > $tour->max_capacity) {
                return redirect()->back()
                    ->withErrors(['passenger_count' => 'Not enough capacity available'])
                    ->withInput();
            }
        }

        // Generate passenger code
        $lastPassenger = TourPassenger::orderBy('id', 'desc')->first();
        $lastCode = $lastPassenger ? intval(substr($lastPassenger->passenger_code, 3)) : 0;
        $passengerCode = 'TP-' . str_pad($lastCode + 1, 6, '0', STR_PAD_LEFT);

        // Handle receipt upload
        $receiptImage = null;
        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            
            // Create directory if it doesn't exist
            $directory = 'receipts/tours/' . $tour->id;
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
            
            $file->storeAs($directory, $filename, 'public');
            $receiptImage = $filename;
        }

        // Create passenger record
        $passenger = TourPassenger::create([
            'passenger_code' => $passengerCode,
            'tour_id' => $tour->id,
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'],
            'passenger_count' => $validated['passenger_count'],
            'receipt_image' => $receiptImage,
            'registration_type' => 'Public',
            'status' => 'Pending',
            'registration_date' => now()->toDateString(),
        ]);

        // Create in-app notification for tour head
        // This would depend on your notification system
        // Notification::send(User::role('tour_head')->get(), new NewTourRegistrationNotification($passenger));

        return redirect()->route('tours.index')
            ->with('success', "Registration submitted! Your registration is pending confirmation. Reference: {$passengerCode}");
    }

    /**
     * API endpoint for phone lookup
     */
    public function lookupPhone(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'tour_id' => 'required|integer|exists:tours,id',
        ]);

        $phone = $request->input('phone');
        $tourId = $request->input('tour_id');

        // Check members table first
        $member = \App\Models\Member::where('phone', $phone)->first();
        if ($member) {
            return response()->json([
                'found' => true,
                'source' => 'member',
                'full_name' => $member->full_name,
                'member_id' => $member->id,
                'message' => 'Member found',
            ]);
        }

        // Check previous tour registrations
        $previousPassenger = TourPassenger::where('phone', $phone)
            ->whereHas('tour', function ($query) {
                $query->where('status', 'Completed');
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if ($previousPassenger) {
            return response()->json([
                'found' => true,
                'source' => 'previous',
                'full_name' => $previousPassenger->full_name,
                'member_id' => null,
                'message' => 'Previous passenger found',
            ]);
        }

        return response()->json([
            'found' => false,
            'source' => 'new',
            'full_name' => null,
            'member_id' => null,
            'message' => 'New passenger – enter details manually',
        ]);
    }
}
