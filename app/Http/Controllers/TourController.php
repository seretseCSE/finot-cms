<?php

namespace App\Http\Controllers;

use App\Models\Tour;
use App\Models\Product;
use App\Services\TourRegistrationService;
use App\Services\TourPhoneLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TourController extends Controller
{
    private TourRegistrationService $registrationService;
    private TourPhoneLookupService $phoneLookupService;

    public function __construct(
        TourRegistrationService $registrationService,
        TourPhoneLookupService $phoneLookupService
    ) {
        $this->registrationService = $registrationService;
        $this->phoneLookupService = $phoneLookupService;
    }
    /**
     * Display public tours listing page (with Shop tab)
     */
    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'tours');

        $tours = Tour::with(['confirmedPassengers'])
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->get()
            ->sortBy('days_left'); // Sort by days left (ascending - least days first)

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
                    'ethiopian_registration_deadline' => $tour->ethiopian_registration_deadline,
                    'days_left' => $tour->days_left,
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

        try {
            $result = $this->registrationService->register($request, $tour);
            return redirect()->route('tours.index')
                ->with('success', $result['message']);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            return $e->getResponse();
        }
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

        $result = $this->phoneLookupService->lookup($phone, $tourId);

        return response()->json($result);
    }
}
