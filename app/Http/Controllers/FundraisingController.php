<?php

namespace App\Http\Controllers;

use App\Services\FundraisingDataService;

class FundraisingController extends Controller
{
    private FundraisingDataService $dataService;

    public function __construct(FundraisingDataService $dataService)
    {
        $this->dataService = $dataService;
    }
    /**
     * Display fundraising progress page
     */
    public function index()
    {
        $data = $this->dataService->getPublicData();

        return view('public.fundraising', $data);
    }

    /**
     * API endpoint for fundraising data (for AJAX calls)
     */
    public function api()
    {
        return response()->json($this->dataService->getApiData());
    }
}
