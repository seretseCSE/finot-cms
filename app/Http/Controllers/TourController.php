<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\TourPassenger;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TourController extends Controller
{
    /**
     * Display public tours listing page (with Shop tab)
     */
    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'tours');

        $tours = Tour::with(['confirmedPassengers'])
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->orderBy('tour_date', 'asc')
            ->get();

        // Auto-update status for tours that have passed or are full
        foreach ($tours as $tour) {
            $tour->updateStatusIfNeeded();
        }

        $tours = $tours
            ->filter(fn ($tour) => ! in_array($tour->status, ['Draft', 'Cancelled']))
            ->map(function ($tour) {
                return (object) [
                    'id' => $tour->id,
                    'place' => $tour->place,
                    'description' => Str::limit($tour->description, 150),
                    'tour_date' => $tour->tour_date,
                    'ethiopian_date' => $tour->ethiopian_date,
                    'start_time' => $tour->start_time,
                    'formatted_cost' => $tour->formatted_cost,
                    'registration_deadline' => $tour->registration_deadline,
                    'image' => $tour->image,
                    'max_capacity' => $tour->max_capacity,
                    'remaining_capacity' => $tour->remaining_capacity,
                    'is_full' => $tour->is_full,
                    'is_registration_open' => $tour->is_registration_open,
                ];
            });

        $productQuery = Product::active()->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $productQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $productQuery->where('category', $request->input('category'));
        }

        $products = $productQuery->paginate(12, ['*'], 'products_page')->withQueryString();

        $categories = Product::active()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        $totalProducts = Product::active()->count();
        $inStockProducts = Product::active()->where('stock_quantity', '>', 0)->count();

        $currentPage = 'tours';

        return view('public.tours', compact(
            'tours',
            'activeTab',
            'products',
            'categories',
            'totalProducts',
            'inStockProducts',
            'currentPage'
        ));
    }

    /**
     * Show tour registration form
     */
    public function showRegister($id)
    {
        $tour = Tour::findOrFail($id);
        $tour->updateStatusIfNeeded();

        if (in_array($tour->status, ['Draft', 'Cancelled'])) {
            abort(404, 'Tour not found');
        }

        if (! $tour->is_registration_open) {
            return redirect()->route('tours.index')
                ->with('error', 'Registration is closed for this tour');
        }

        if ($tour->is_full) {
            return redirect()->route('tours.index')
                ->with('error', 'This tour is already full');
        }

        return view('public.tour-register', compact('tour'));
    }

    /**
     * Process tour registration
     */
    public function register(Request $request, $id)
    {
        $tour = Tour::findOrFail($id);
        $tour->updateStatusIfNeeded();

        if (in_array($tour->status, ['Draft', 'Cancelled'])) {
            abort(404, 'Tour not found');
        }

        if (! $tour->is_registration_open) {
            return redirect()->route('tours.index')
                ->with('error', 'Registration is closed for this tour');
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^[0-9]{9}$/',
            'passenger_count' => 'required|integer|min:1|max:20',
            'passenger_names' => 'nullable|array',
            'passenger_names.*' => 'required_with:passenger_names|string|max:255',
            'receipt_image' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'honeypot' => 'nullable|string|max:0', // Bot prevention
        ]);

        // Check honeypot field (bot prevention)
        if (! empty($validated['honeypot'])) {
            return redirect()->back()
                ->with('error', 'Invalid submission');
        }

        // Prepend phone prefix
        $fullPhone = config('finot.phone_prefix', '+251').$validated['phone'];

        // Check if phone already registered for this tour
        if (TourPassenger::where('tour_id', $tour->id)
            ->where('phone', $fullPhone)
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

        // Handle receipt upload
        $receiptImage = null;
        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $filename = time().'_'.Str::random(10).'.'.$file->getClientOriginalExtension();

            $directory = 'receipts/tours/'.$tour->id;
            if (! Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $receiptImage = $file->storeAs($directory, $filename, 'public');
        }

        // Generate passenger codes
        $lastPassenger = TourPassenger::orderBy('id', 'desc')->first();
        $lastCode = $lastPassenger ? intval(substr($lastPassenger->passenger_code, 3)) : 0;

        $passengerCount = (int) $validated['passenger_count'];
        $passengerNames = $validated['passenger_names'] ?? [];
        $createdPassengers = [];

        // Create primary passenger (the one with the phone)
        $primaryCode = config('finot.tour_passenger_code_prefix', 'TP-').str_pad($lastCode + 1, 6, '0', STR_PAD_LEFT);
        $primaryPassenger = TourPassenger::create([
            'passenger_code' => $primaryCode,
            'tour_id' => $tour->id,
            'full_name' => $validated['full_name'],
            'phone' => $fullPhone,
            'passenger_count' => 1,
            'receipt_image' => $receiptImage,
            'registration_type' => 'Public',
            'status' => 'Pending',
            'registration_date' => now()->toDateString(),
        ]);
        $createdPassengers[] = $primaryPassenger;
        $lastCode++;

        // Create additional passengers as separate rows
        for ($i = 0; $i < $passengerCount - 1; $i++) {
            $additionalName = $passengerNames[$i] ?? ($validated['full_name'].' ('.__('Guest').' '.($i + 2).')');
            $additionalCode = config('finot.tour_passenger_code_prefix', 'TP-').str_pad($lastCode + 1, 6, '0', STR_PAD_LEFT);

            $additionalPassenger = TourPassenger::create([
                'passenger_code' => $additionalCode,
                'tour_id' => $tour->id,
                'full_name' => $additionalName,
                'phone' => null, // Additional passengers don't need a phone
                'passenger_count' => 1,
                'receipt_image' => null,
                'registration_type' => 'Public',
                'status' => 'Pending',
                'registration_date' => now()->toDateString(),
            ]);
            $createdPassengers[] = $additionalPassenger;
            $lastCode++;
        }

        // Check if tour is now full and update status
        $tour->refresh();
        $tour->updateStatusIfNeeded();

        $primaryCode = $createdPassengers[0]->passenger_code;

        return redirect()->route('tours.index')
            ->with('success', "Registration submitted! Your registration is pending confirmation. Reference: {$primaryCode}");
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

        // Normalize phone number
        $phonePrefix = config('finot.phone_prefix', '+251');
        if (! str_starts_with($phone, $phonePrefix)) {
            $phone = $phonePrefix.preg_replace('/^0?/', '', $phone);
        }

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
